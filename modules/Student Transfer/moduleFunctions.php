<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)
*/

use Gibbon\Services\Format;
use Gibbon\Domain\System\SettingGateway;

/**
 * Gets a list of students eligible for transfer
 *
 * @param \PDO $pdo
 * @param string $gibbonSchoolYearID
 * @return array
 */
function getEligibleStudentsForTransfer($pdo, $gibbonSchoolYearID)
{
    $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
    $sql = "SELECT gibbonPerson.gibbonPersonID, surname, preferredName, gibbonYearGroup.nameShort as yearGroup, gibbonRollGroup.nameShort as rollGroup 
            FROM gibbonPerson 
            JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID) 
            JOIN gibbonYearGroup ON (gibbonStudentEnrolment.gibbonYearGroupID=gibbonYearGroup.gibbonYearGroupID)
            JOIN gibbonRollGroup ON (gibbonStudentEnrolment.gibbonRollGroupID=gibbonRollGroup.gibbonRollGroupID)
            WHERE gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID 
            AND gibbonPerson.status='Full'
            ORDER BY surname, preferredName";

    return $pdo->select($sql, $data);
}

/**
 * Validates a student transfer package
 *
 * @param string $packagePath
 * @return array
 */
function validateTransferPackage($packagePath)
{
    $errors = [];
    
    // Check if file exists
    if (!file_exists($packagePath)) {
        $errors[] = __('Transfer package file not found.');
        return $errors;
    }

    // Check file structure
    $zip = new ZipArchive();
    if ($zip->open($packagePath) !== true) {
        $errors[] = __('Invalid transfer package format.');
        return $errors;
    }

    // Required files
    $requiredFiles = ['student_data.json', 'metadata.json', 'manifest.json'];
    foreach ($requiredFiles as $file) {
        if ($zip->locateName($file) === false) {
            $errors[] = sprintf(__('Required file %s is missing.'), $file);
        }
    }

    // Check metadata
    $metadata = json_decode($zip->getFromName('metadata.json'), true);
    if (empty($metadata)) {
        $errors[] = __('Invalid metadata format.');
    } else {
        // Validate timestamps
        if (empty($metadata['exportTimestamp'])) {
            $errors[] = __('Export timestamp is missing.');
        }
        
        // Validate source school
        if (empty($metadata['sourceSchool'])) {
            $errors[] = __('Source school information is missing.');
        }
    }

    $zip->close();
    return $errors;
}

/**
 * Gets transfer status label and class
 *
 * @param string $status
 * @return array
 */
function getTransferStatusDetails($status)
{
    $statuses = [
        'Pending' => ['label' => __('Pending'), 'class' => 'message'],
        'Exported' => ['label' => __('Exported'), 'class' => 'success'],
        'Imported' => ['label' => __('Imported'), 'class' => 'success'],
        'Cancelled' => ['label' => __('Cancelled'), 'class' => 'error']
    ];

    return $statuses[$status] ?? ['label' => $status, 'class' => 'default'];
}

/**
 * Checks if a student is already in a transfer process
 *
 * @param \PDO $pdo
 * @param string $gibbonPersonID
 * @return bool
 */
function isStudentInTransfer($pdo, $gibbonPersonID)
{
    $data = ['gibbonPersonID' => $gibbonPersonID];
    $sql = "SELECT COUNT(*) FROM gibbonStudentTransferLog 
            WHERE gibbonPersonID=:gibbonPersonID 
            AND status IN ('Pending', 'Exported')";

    return $pdo->selectOne($sql, $data) > 0;
}
