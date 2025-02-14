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

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\DataSet;
use Aura\SqlQuery\Common\DeleteInterface;
use Aura\SqlQuery\Common\UpdateInterface;

/**
 * Import Gateway
 *
 * Handles importing data from OpenAdmin into Gibbon
 *
 * @version v29
 * @since   v29
 */
class ImportGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'openAdminImport';
    private static $primaryKey = 'openAdminImportID';
    private static $searchableColumns = ['name'];

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryImports(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'openAdminImport.openAdminImportID',
                'openAdminImport.name',
                'openAdminImport.type',
                'openAdminImport.status',
                'openAdminImport.timestamp',
                'openAdminImport.gibbonPersonID',
                'gibbonPerson.surname',
                'gibbonPerson.preferredName'
            ])
            ->leftJoin('gibbonPerson', 'openAdminImport.gibbonPersonID=gibbonPerson.gibbonPersonID');

        return $this->runQuery($query, $criteria);
    }

    /**
     * @param array $data
     * @return bool
     */
    public function insert($data)
    {
        $query = $this
            ->newInsert()
            ->into($this->getTableName())
            ->cols($data);

        return $this->runInsert($query);
    }

    /**
     * @param string $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data)
    {
        $query = $this
            ->newUpdate()
            ->table($this->getTableName())
            ->cols($data)
            ->where('openAdminImportID = :openAdminImportID')
            ->bindValue('openAdminImportID', $id);

        return $this->runUpdate($query);
    }

    /**
     * @param string $id
     * @return bool
     */
    public function delete($id)
    {
        $query = $this
            ->newDelete()
            ->from($this->getTableName())
            ->where('openAdminImportID = :openAdminImportID')
            ->bindValue('openAdminImportID', $id);

        return $this->runDelete($query);
    }

    /**
     * Import student data from OpenAdmin
     * 
     * @param array $data CSV data to import
     * @param array $mapping Column mapping configuration
     * @return array Import results
     */
    public function importStudents(array $data, array $mapping): array
    {
        $results = [
            'inserted' => 0,
            'updated' => 0,
            'errors' => [],
        ];

        foreach ($data as $row) {
            try {
                // Map CSV data to database fields
                $studentData = [];
                foreach ($mapping as $csvField => $dbField) {
                    if (empty($dbField)) continue;
                    $studentData[$dbField] = $row[$csvField] ?? '';
                }

                // Add required system fields
                $studentData['status'] = $studentData['status'] ?? 'Full';
                $studentData['roleCategory'] = 'Student';
                
                // Format dates
                if (!empty($studentData['birthdate'])) {
                    $studentData['dob'] = Format::dateConvert($studentData['birthdate']);
                }

                // Map grade/homeroom to proper IDs
                if (!empty($studentData['grade'])) {
                    $yearGroup = $this->getYearGroupByName($studentData['grade']);
                    if ($yearGroup) {
                        $studentData['gibbonYearGroupID'] = $yearGroup['gibbonYearGroupID'];
                    }
                }

                if (!empty($studentData['homeroom'])) {
                    $formGroup = $this->getFormGroupByName($studentData['homeroom']);
                    if ($formGroup) {
                        $studentData['gibbonFormGroupID'] = $formGroup['gibbonFormGroupID'];
                    }
                }

                // Check if student exists
                $existing = $this->selectOne('SELECT gibbonPersonID FROM gibbonPerson WHERE studentID=:studentID', [
                    'studentID' => $studentData['studid']
                ]);

                if ($existing) {
                    // Update existing student
                    $success = $this->update($existing['gibbonPersonID'], $studentData);
                    if ($success) {
                        $results['updated']++;
                    }
                } else {
                    // Insert new student
                    $success = $this->insert($studentData);
                    if ($success) {
                        $results['inserted']++;
                    }
                }
            } catch (\Exception $e) {
                $results['errors'][] = sprintf('Error processing student %s: %s', 
                    $row['studid'] ?? 'unknown',
                    $e->getMessage()
                );
            }
        }

        return $results;
    }

    /**
     * Get year group by name/short name
     */
    private function getYearGroupByName(string $name)
    {
        return $this->selectOne(
            'SELECT gibbonYearGroupID FROM gibbonYearGroup WHERE name=:name OR nameShort=:nameShort',
            ['name' => $name, 'nameShort' => $name]
        );
    }

    /**
     * Get form group by name/short name
     */
    private function getFormGroupByName(string $name)
    {
        return $this->selectOne(
            'SELECT gibbonFormGroupID FROM gibbonFormGroup WHERE name=:name OR nameShort=:nameShort',
            ['name' => $name, 'nameShort' => $name]
        );
    }

    /**
     * Import staff data from OpenAdmin
     *
     * @param array $data CSV data to import
     * @param array $mapping Column mapping configuration
     * @return array Import results
     */
    public function importStaff(array $data, array $mapping)
    {
        $results = [
            'inserted' => 0,
            'updated' => 0,
            'errors' => [],
        ];

        foreach ($data as $row) {
            try {
                // Map OpenAdmin columns to Gibbon columns
                $gibbonData = [];
                foreach ($mapping as $gibbonField => $oaField) {
                    $gibbonData[$gibbonField] = $row[$oaField] ?? '';
                }

                // Add required defaults for staff
                $gibbonData['status'] = 'Full';
                $gibbonData['canLogin'] = 'Y';
                $gibbonData['passwordForceReset'] = 'Y';
                $gibbonData['type'] = 'Staff';

                // Check if staff exists
                $existing = $this->db()->selectOne(
                    'SELECT gibbonPersonID FROM gibbonPerson WHERE username=:username',
                    ['username' => $gibbonData['username']]
                );

                if ($existing) {
                    // Update existing staff
                    $this->db()->update('gibbonPerson', $gibbonData, ['gibbonPersonID' => $existing['gibbonPersonID']]);
                    $results['updated']++;
                } else {
                    // Insert new staff
                    $this->db()->insert('gibbonPerson', $gibbonData);
                    $results['inserted']++;
                }
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'row' => $row,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Log an import operation
     *
     * @param string $type Import type (students, staff, etc)
     * @param string $importType Specific import type details
     * @param array $results Import results
     * @param int $gibbonPersonID Person who performed the import
     * @return bool
     */
    public function logImport($type, $importType, $results, $gibbonPersonID)
    {
        $data = [
            'type' => $type,
            'importType' => $importType,
            'status' => empty($results['errors']) ? 'Complete' : 'Failed',
            'recordCount' => $results['inserted'] + $results['updated'],
            'importTime' => date('Y-m-d H:i:s'),
            'gibbonPersonID' => $gibbonPersonID,
        ];

        return $this->insert($data);
    }

    /**
     * @param UpdateInterface $query
     * @return bool
     */
    public function runUpdate(UpdateInterface $query): bool
    {
        return parent::runUpdate($query);
    }

    /**
     * @param DeleteInterface $query
     * @return bool
     */
    public function runDelete(DeleteInterface $query): bool
    {
        return parent::runDelete($query);
    }
}
