<?php
/*
Gibbon: the flexible, open school platform
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

use Exception;
use PDO;
use Gibbon\Data\Validator;

/**
 * Import Processor
 *
 * @version v29
 * @since   v29
 */
class ImportProcessor
{
    protected $pdo;
    protected $options = [];
    protected $validator;

    /**
     * Constructor
     *
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->validator = new Validator();
    }

    /**
     * Set import options
     *
     * @param array $options
     */
    public function setOptions(array $options)
    {
        $this->options = array_merge([
            'dryRun' => true,
            'delimiter' => ',',
            'encoding' => 'UTF-8',
            'skipFirstRow' => true,
            'continueOnError' => true
        ], $options);
    }

    /**
     * Validate the import data
     *
     * @param string $filePath
     * @param array $fieldMappings
     * @return array
     */
    public function validate(string $filePath, array $fieldMappings): array
    {
        $results = [
            'total' => 0,
            'success' => 0,
            'errors' => 0,
            'messages' => []
        ];

        try {
            // Open and read the CSV file
            $handle = fopen($filePath, 'r');
            if ($handle === false) {
                throw new Exception('Could not open file for reading');
            }

            // Skip first row if needed
            if ($this->options['skipFirstRow']) {
                fgetcsv($handle, 0, $this->options['delimiter']);
            }

            // Process each row
            $rowNumber = $this->options['skipFirstRow'] ? 2 : 1;
            while (($row = fgetcsv($handle, 0, $this->options['delimiter'])) !== false) {
                $results['total']++;

                // Validate required fields
                foreach ($fieldMappings as $field => $mapping) {
                    if (!empty($mapping['required']) && empty($row[$mapping['sourceField']])) {
                        $results['errors']++;
                        $results['messages'][] = "Row {$rowNumber}: Required field '{$field}' is empty";
                        continue 2;
                    }
                }

                // Validate field formats
                foreach ($fieldMappings as $field => $mapping) {
                    if (!empty($mapping['validate'])) {
                        $value = $row[$mapping['sourceField']] ?? '';
                        if (!$this->validator->validate($mapping['validate'], $value)) {
                            $results['errors']++;
                            $results['messages'][] = "Row {$rowNumber}: Invalid format for field '{$field}'";
                            continue 2;
                        }
                    }
                }

                $results['success']++;
                $rowNumber++;
            }

            fclose($handle);

        } catch (Exception $e) {
            $results['errors']++;
            $results['messages'][] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Process the import
     *
     * @param string $filePath
     * @param array $fieldMappings
     * @return array
     */
    public function import(string $filePath, array $fieldMappings): array
    {
        $results = [
            'total' => 0,
            'success' => 0,
            'errors' => 0,
            'messages' => []
        ];

        try {
            // Open and read the CSV file
            $handle = fopen($filePath, 'r');
            if ($handle === false) {
                throw new Exception('Could not open file for reading');
            }

            // Skip first row if needed
            if ($this->options['skipFirstRow']) {
                fgetcsv($handle, 0, $this->options['delimiter']);
            }

            // Process each row
            $rowNumber = $this->options['skipFirstRow'] ? 2 : 1;
            while (($row = fgetcsv($handle, 0, $this->options['delimiter'])) !== false) {
                $results['total']++;

                try {
                    // Map fields according to configuration
                    $data = [];
                    foreach ($fieldMappings as $field => $mapping) {
                        if (isset($mapping['value'])) {
                            $data[$field] = $mapping['value'];
                        } elseif (isset($mapping['sourceField'])) {
                            $value = $row[$mapping['sourceField']] ?? '';
                            
                            // Apply transformations if specified
                            if (!empty($mapping['transform'])) {
                                $value = $this->transformValue($value, $mapping['transform']);
                            }
                            
                            $data[$field] = $value;
                        }
                    }

                    // Skip actual database operations in dry run mode
                    if (!$this->options['dryRun']) {
                        // Database operations would go here
                        // This is a placeholder for actual implementation
                    }

                    $results['success']++;

                } catch (Exception $e) {
                    $results['errors']++;
                    $results['messages'][] = "Row {$rowNumber}: " . $e->getMessage();
                    
                    if (!$this->options['continueOnError']) {
                        throw $e;
                    }
                }

                $rowNumber++;
            }

            fclose($handle);

        } catch (Exception $e) {
            $results['errors']++;
            $results['messages'][] = $e->getMessage();
            throw $e;
        }

        return $results;
    }

    /**
     * Transform a value based on the specified transformation
     *
     * @param mixed $value
     * @param mixed $transform
     * @return mixed
     */
    protected function transformValue($value, $transform)
    {
        if (is_array($transform)) {
            // Mapping transformation
            return $transform[$value] ?? $value;
        } elseif (is_string($transform)) {
            // Function transformation
            switch ($transform) {
                case 'date':
                    return date('Y-m-d', strtotime($value));
                case 'upper':
                    return strtoupper($value);
                case 'lower':
                    return strtolower($value);
                default:
                    return $value;
            }
        }

        return $value;
    }
}
