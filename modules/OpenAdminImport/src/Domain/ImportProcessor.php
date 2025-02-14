<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
Gibbon, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

namespace Gibbon\Module\OpenAdminImport\Domain;

use Gibbon\Domain\DataSet;
use Gibbon\Services\Format;
use Gibbon\Contracts\Database\Connection;
use ParseCsv\Csv;

/**
 * Import Processor
 *
 * @version v29
 * @since   v29
 */
class ImportProcessor
{
    protected $pdo;
    protected $importGateway;
    protected $importLogGateway;
    protected $config;
    protected $fieldMap;
    protected $csv;
    protected $totalRows;
    protected $currentRow;
    protected $processedRows;
    protected $errors;
    protected $warnings;

    /**
     * Create a new import processor.
     *
     * @param Connection $pdo
     * @param ImportGateway $importGateway
     * @param ImportLogGateway $importLogGateway
     */
    public function __construct(Connection $pdo, ImportGateway $importGateway, ImportLogGateway $importLogGateway)
    {
        $this->pdo = $pdo;
        $this->importGateway = $importGateway;
        $this->importLogGateway = $importLogGateway;
        $this->processedRows = 0;
        $this->errors = [];
        $this->warnings = [];
    }

    /**
     * Initialize the import process.
     *
     * @param string $filePath
     * @param array $config
     * @param array $fieldMap
     * @param array $options
     * @return bool
     */
    public function init(string $filePath, array $config, array $fieldMap, array $options = []): bool
    {
        $this->config = $config;
        $this->fieldMap = $fieldMap;

        // Initialize CSV parser
        $this->csv = new Csv();
        $this->csv->delimiter = $options['delimiter'] ?? ',';
        $this->csv->enclosure = $options['enclosure'] ?? '"';
        $this->csv->heading = true;
        $this->csv->parseFile($filePath);

        if (empty($this->csv->data)) {
            $this->addError('No data found in CSV file.');
            return false;
        }

        $this->totalRows = count($this->csv->data);
        $this->currentRow = 0;

        // Create import record
        $importData = [
            'type' => $config['type'] ?? '',
            'name' => basename($filePath),
            'status' => 'Pending',
            'totalRows' => $this->totalRows,
            'processedRows' => 0,
            'successRows' => 0,
            'errorRows' => 0,
            'gibbonPersonID' => $options['gibbonPersonID'] ?? null,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        $inserted = $this->importGateway->insert($importData);
        if (!$inserted) {
            $this->addError('Failed to create import record.');
            return false;
        }

        return true;
    }

    /**
     * Process the next batch of rows.
     *
     * @param int $batchSize
     * @return array
     */
    public function processBatch(int $batchSize = 50): array
    {
        $batch = array_slice($this->csv->data, $this->currentRow, $batchSize);
        $results = [
            'success' => 0,
            'error' => 0,
            'processed' => 0,
            'total' => $this->totalRows,
            'current' => $this->currentRow,
            'complete' => false,
            'errors' => [],
        ];

        foreach ($batch as $row) {
            try {
                $processed = $this->processRow($row);
                $results[$processed ? 'success' : 'error']++;
            } catch (\Exception $e) {
                $this->addError("Row {$this->currentRow}: " . $e->getMessage());
                $results['error']++;
            }

            $this->currentRow++;
            $this->processedRows++;
            $results['processed'] = $this->processedRows;
        }

        $results['complete'] = ($this->currentRow >= $this->totalRows);
        $results['errors'] = $this->errors;

        // Update import status
        $this->updateImportStatus($results);

        return $results;
    }

    /**
     * Process a single row of data.
     *
     * @param array $row
     * @return bool
     */
    protected function processRow(array $row): bool
    {
        // Map CSV fields to database fields
        $data = [];
        foreach ($this->fieldMap as $csvField => $dbField) {
            if (empty($dbField)) continue;
            $data[$dbField] = $this->formatFieldValue($row[$csvField] ?? '', $dbField);
        }

        // Validate required fields
        foreach ($this->config['required'] ?? [] as $field) {
            if (empty($data[$field])) {
                throw new \Exception("Required field '{$field}' is missing or empty.");
            }
        }

        // Insert or update record
        $table = $this->config['table'];
        $identifier = $this->config['identifier'];
        
        if (!empty($data[$identifier])) {
            $existing = $this->pdo->selectOne("SELECT * FROM {$table} WHERE {$identifier}=?", [$data[$identifier]]);
            if ($existing) {
                return $this->pdo->update($table, $data, [$identifier => $data[$identifier]]);
            }
        }

        return $this->pdo->insert($table, $data);
    }

    /**
     * Format a field value based on its type.
     *
     * @param mixed $value
     * @param string $field
     * @return mixed
     */
    protected function formatFieldValue($value, string $field)
    {
        if (empty($value)) return null;

        $fieldType = $this->config['fields'][$field]['type'] ?? 'string';

        switch ($fieldType) {
            case 'date':
                return Format::dateConvert($value);
            case 'timestamp':
                return date('Y-m-d H:i:s', strtotime($value));
            case 'bool':
            case 'boolean':
                return in_array(strtolower($value), ['y', 'yes', 'true', '1']);
            case 'int':
            case 'integer':
                return (int) $value;
            case 'float':
            case 'decimal':
                return (float) $value;
            default:
                return $value;
        }
    }

    /**
     * Update the import status and log progress.
     *
     * @param array $results
     */
    protected function updateImportStatus(array $results)
    {
        $status = $results['complete'] ? 'Complete' : 'In Progress';
        if ($results['error'] > 0) {
            $status = $results['complete'] ? 'Failed' : 'Warning';
        }

        // Update import record
        $this->importGateway->update($this->importID, [
            'status' => $status,
            'processedRows' => $this->processedRows,
            'successRows' => $results['success'],
            'errorRows' => $results['error'],
        ]);

        // Log any errors
        if (!empty($results['errors'])) {
            foreach ($results['errors'] as $error) {
                $this->importLogGateway->insert([
                    'openAdminImportID' => $this->importID,
                    'title' => 'Import Error',
                    'description' => $error,
                    'type' => 'Error',
                    'gibbonPersonID' => $this->config['gibbonPersonID'] ?? null,
                    'timestamp' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    /**
     * Add an error message.
     *
     * @param string $message
     */
    protected function addError(string $message)
    {
        $this->errors[] = $message;
    }

    /**
     * Add a warning message.
     *
     * @param string $message
     */
    protected function addWarning(string $message)
    {
        $this->warnings[] = $message;
    }
}
