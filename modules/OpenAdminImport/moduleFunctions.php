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

use Gibbon\Services\Format;
use Symfony\Component\Yaml\Yaml;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\OpenAdminImport\Domain\ImportGateway;

 



/**
 * Get list of available import modes for OAFS data
 *
 * @param \PDO $connection2
 * @param string $guid
 * @return array
 */
function getImportModes()
{
    return [
        'staff' => [
            'name' => 'Staff Data',
            'table' => 'gibbonPerson',
            'fields' => [
                'title' => 'sal',
                'surname' => 'lastname', 
                'firstName' => 'firstname',
                'username' => 'userid',
                'email' => 'emailwork',
                'phone1' => 'home_phone',
                'phone2' => 'cell_phone',
                'address1' => 'street',
                'address1District' => 'city',
                'address1Country' => 'prov',
                'address1PostalCode' => 'pcode',
                'gender' => 'gender',
                'dob' => 'birthdate'
            ]
        ],
        'student' => [
            'name' => 'Student Data',
            'table' => 'gibbonPerson',
            'fields' => [
                'surname' => 'lastname',
                'firstName' => 'firstname',
                'username' => 'studnum',
                'gender' => 'sex', 
                'dob' => 'birthdate',
                'email' => 'email',
                'address1' => 'address1',
                'address1District' => 'city1',
                'address1Country' => 'prov1',
                'address1PostalCode' => 'pcode1'
            ]
        ]
    ];
}

/**
 * Process CSV data import for staff or students
 * 
 * @param \PDO $connection2
 * @param string $mode Import mode (staff/student)
 * @param string $csvPath Path to CSV file
 * @param array $options Import options
 * @return array Results with success/error counts
 */
function processCSVImport($connection2, $mode, $csvPath, $options = [])
{
    $modes = getImportModes();
    if (!isset($modes[$mode])) {
        throw new \Exception('Invalid import mode');
    }

    $results = [
        'success' => 0,
        'errors' => 0,
        'messages' => []
    ];

    // Read CSV with UTF-8 encoding
    $handle = fopen($csvPath, 'r');
    if ($handle === false) {
        throw new \Exception('Could not open CSV file');
    }

    // Get headers and validate required fields
    $headers = fgetcsv($handle);
    if ($headers === false) {
        throw new \Exception('Could not read CSV headers');
    }

    // Map CSV columns to database fields
    $fieldMap = $modes[$mode]['fields'];
    $columnMap = [];
    foreach ($headers as $index => $header) {
        if (in_array($header, array_values($fieldMap))) {
            $columnMap[$index] = array_search($header, $fieldMap);
        }
    }

    // Begin transaction
    $connection2->beginTransaction();

    try {
        $row = 2; // Start at row 2 after headers
        while (($data = fgetcsv($handle)) !== false) {
            $personData = [];
            foreach ($columnMap as $index => $field) {
                if (isset($data[$index])) {
                    // Special handling for dates
                    if ($field == 'dob') {
                        $personData[$field] = !empty($data[$index]) ? date('Y-m-d', strtotime($data[$index])) : null;
                    } else {
                        $personData[$field] = $data[$index];
                    }
                }
            }

            // Add required fields
            $personData['status'] = 'Full';
            $personData['gibbonRoleIDPrimary'] = $mode == 'staff' ? '002' : '003';
            
            // Generate username if not provided
            if (empty($personData['username'])) {
                $personData['username'] = strtolower(substr($personData['firstName'], 0, 1) . $personData['surname']);
            }

            // Insert or update person
            $sql = "INSERT INTO gibbonPerson (" . implode(',', array_keys($personData)) . ") 
                    VALUES (" . str_repeat('?,', count($personData)-1) . "?)
                    ON DUPLICATE KEY UPDATE " . 
                    implode(',', array_map(function($field) { 
                        return "$field=VALUES($field)"; 
                    }, array_keys($personData)));

            $stmt = $connection2->prepare($sql);
            if ($stmt->execute(array_values($personData))) {
                $results['success']++;
            } else {
                $results['errors']++;
                $results['messages'][] = "Error on row $row: " . implode(', ', $stmt->errorInfo());
            }
            $row++;
        }

        $connection2->commit();
    } catch (\Exception $e) {
        $connection2->rollBack();
        throw $e;
    }

    fclose($handle);
    return $results;
}

/**
 * Validate CSV file format and required fields
 *
 * @param string $csvPath Path to CSV file
 * @param string $mode Import mode
 * @return array Validation results
 */
function validateCSVFormat($csvPath, $mode) 
{
    $results = [
        'valid' => true,
        'messages' => []
    ];

    $modes = getImportModes();
    if (!isset($modes[$mode])) {
        $results['valid'] = false;
        $results['messages'][] = 'Invalid import mode';
        return $results;
    }

    // Check file exists and is readable
    if (!file_exists($csvPath) || !is_readable($csvPath)) {
        $results['valid'] = false;
        $results['messages'][] = 'CSV file not found or not readable';
        return $results;
    }

    // Check headers match required fields
    $handle = fopen($csvPath, 'r');
    if ($handle === false) {
        $results['valid'] = false;
        $results['messages'][] = 'Could not open CSV file';
        return $results;
    }

    $headers = fgetcsv($handle);
    if ($headers === false) {
        $results['valid'] = false;
        $results['messages'][] = 'Could not read CSV headers';
        return $results;
    }

    // Check required fields are present
    $requiredFields = array_values($modes[$mode]['fields']);
    $missingFields = array_diff($requiredFields, $headers);
    if (!empty($missingFields)) {
        $results['valid'] = false;
        $results['messages'][] = 'Missing required fields: ' . implode(', ', $missingFields);
    }

    fclose($handle);
    return $results;
}

/**
 * Format import status for display
 *
 * @param array $results
 * @return string
 */
function formatImportStatus($results)
{
    if (empty($results)) {
        return Format::tag(__('Failed'), 'error');
    }

    if ($results['success'] > 0 && $results['failed'] == 0) {
        return Format::tag(__('Success'), 'success');
    } elseif ($results['success'] > 0 && $results['failed'] > 0) {
        return Format::tag(__('Partial'), 'warning');
    } else {
        return Format::tag(__('Failed'), 'error');
    }
}

/**
 * Get default field mappings for a given import type
 * 
 * @param string $importType Type of import (staff/student)
 * @return array Associative array of field mappings
 */
function getDefaultFieldMappings($importType)
{
    $mappings = [
        'student' => [
            // Personal info
            'surname' => ['target' => 'surname', 'required' => 'Y'],
            'firstName' => ['target' => 'firstName', 'required' => 'Y'],
            'preferredName' => ['target' => 'preferredName', 'required' => 'N'],
            'officialName' => ['target' => 'officialName', 'required' => 'N'],
            'gender' => ['target' => 'gender', 'required' => 'Y'],
            'dob' => ['target' => 'dateStart', 'required' => 'Y'],
            'email' => ['target' => 'email', 'required' => 'N'],
            
            // Contact info
            'phone1' => ['target' => 'phone1', 'required' => 'N'],
            'phone2' => ['target' => 'phone2', 'required' => 'N'],
            'address1' => ['target' => 'address1', 'required' => 'N'],
            'address1District' => ['target' => 'address1District', 'required' => 'N'],
            'address1Country' => ['target' => 'address1Country', 'required' => 'N'],
            'address1PostalCode' => ['target' => 'address1PostalCode', 'required' => 'N'],
            
            // School info
            'yearGroup' => ['target' => 'gibbonYearGroupID', 'required' => 'Y'],
            'rollGroup' => ['target' => 'gibbonRollGroupID', 'required' => 'Y'],
            'house' => ['target' => 'gibbonHouseID', 'required' => 'N'],
            'studentID' => ['target' => 'studentID', 'required' => 'N']
        ],
        'staff' => [
            // Personal info
            'title' => ['target' => 'title', 'required' => 'N'],
            'surname' => ['target' => 'surname', 'required' => 'Y'],
            'firstName' => ['target' => 'firstName', 'required' => 'Y'],
            'preferredName' => ['target' => 'preferredName', 'required' => 'N'],
            'officialName' => ['target' => 'officialName', 'required' => 'N'],
            'gender' => ['target' => 'gender', 'required' => 'Y'],
            'dob' => ['target' => 'dateStart', 'required' => 'Y'],
            'email' => ['target' => 'email', 'required' => 'Y'],
            
            // Contact info
            'phone1' => ['target' => 'phone1', 'required' => 'N'],
            'phone2' => ['target' => 'phone2', 'required' => 'N'],
            'address1' => ['target' => 'address1', 'required' => 'N'],
            'address1District' => ['target' => 'address1District', 'required' => 'N'],
            'address1Country' => ['target' => 'address1Country', 'required' => 'N'],
            'address1PostalCode' => ['target' => 'address1PostalCode', 'required' => 'N'],
            
            // Staff info
            'jobTitle' => ['target' => 'jobTitle', 'required' => 'N'],
            'department' => ['target' => 'gibbonDepartmentID', 'required' => 'N'],
            'staffID' => ['target' => 'staffID', 'required' => 'N']
        ]
    ];

    return $mappings[$importType] ?? [];
}

/**
 * Save field mapping to database
 * 
 * @param PDO $connection2
 * @param string $importType
 * @param array $mapping
 * @param int $gibbonPersonID
 * @return bool
 */
function saveFieldMapping($connection2, $importType, $mapping, $gibbonPersonID)
{
    try {
        // Delete existing mappings for this import type
        $data = ['importType' => $importType];
        $sql = "DELETE FROM oafsFieldMapping WHERE importType=:importType";
        $connection2->prepare($sql)->execute($data);

        // Insert new mappings
        $sql = "INSERT INTO oafsFieldMapping 
                (importType, sourceField, targetField, isRequired, defaultValue, gibbonPersonIDCreated) 
                VALUES 
                (:importType, :sourceField, :targetField, :isRequired, :defaultValue, :gibbonPersonIDCreated)";
        $insert = $connection2->prepare($sql);

        foreach ($mapping as $source => $details) {
            $data = [
                'importType' => $importType,
                'sourceField' => $source,
                'targetField' => $details['target'],
                'isRequired' => $details['required'] ?? 'N',
                'defaultValue' => $details['default'] ?? null,
                'gibbonPersonIDCreated' => $gibbonPersonID
            ];
            $insert->execute($data);
        }

        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get saved field mappings from database
 * 
 * @param PDO $connection2
 * @param string $importType
 * @return array
 */
function getFieldMappings($connection2, $importType)
{
    try {
        $data = ['importType' => $importType];
        $sql = "SELECT sourceField, targetField, isRequired, defaultValue 
                FROM oafsFieldMapping 
                WHERE importType=:importType";
        $result = $connection2->prepare($sql);
        $result->execute($data);

        $mappings = [];
        while ($row = $result->fetch()) {
            $mappings[$row['sourceField']] = [
                'target' => $row['targetField'],
                'required' => $row['isRequired'],
                'default' => $row['defaultValue']
            ];
        }

        return $mappings;
    } catch (Exception $e) {
        return [];
    }
}
