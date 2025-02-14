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

/**
 * OpenAdmin Importer
 *
 * @version v29
 * @since   v29
 */
class OpenAdminImporter
{
    protected $pdo;
    protected $data;
    protected $config;

    /**
     * Create a new importer instance.
     *
     * @param Connection $pdo
     * @param array $data
     */
    public function __construct(Connection $pdo, array $data = [])
    {
        $this->pdo = $pdo;
        $this->data = $data;
    }

    /**
     * Set the import configuration.
     *
     * @param array $config
     * @return self
     */
    public function setConfig(array $config): self
    {
        $this->config = $config;
        return $this;
    }

    /**
     * Process the import data.
     *
     * @return array
     */
    public function process(): array
    {
        if (empty($this->config)) {
            return ['errors' => [__('Import configuration not set')]];
        }

        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
            'warnings' => []
        ];

        // Process each row
        foreach ($this->data as $row) {
            try {
                $processed = $this->processRow($row);
                if ($processed) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = $e->getMessage();
            }
        }

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
        // Validate required fields
        foreach ($this->config['required'] ?? [] as $field) {
            if (empty($row[$field])) {
                throw new \Exception(sprintf(__('Required field "%1$s" is missing or empty.'), $field));
            }
        }

        // Map fields according to configuration
        $mappedData = [];
        foreach ($this->config['fields'] ?? [] as $field => $mapping) {
            if (isset($row[$field])) {
                $mappedData[$mapping] = $row[$field];
            }
        }

        if (empty($mappedData)) {
            throw new \Exception(__('No valid fields to import.'));
        }

        // Insert or update based on unique identifier
        $table = $this->config['table'];
        $identifier = $this->config['identifier'];

        if (!empty($mappedData[$identifier])) {
            $existing = $this->pdo->selectOne("SELECT * FROM {$table} WHERE {$identifier}=?", [$mappedData[$identifier]]);
            
            if ($existing) {
                return $this->pdo->update($table, $mappedData, [$identifier => $mappedData[$identifier]]);
            }
        }

        return $this->pdo->insert($table, $mappedData);
    }
}
