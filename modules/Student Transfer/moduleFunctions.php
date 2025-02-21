<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
Gibbon, Gibbon Education Ltd. (Hong Kong)
*/

use Gibbon\Contracts\Database\Connection;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Services\Format;
use Gibbon\Domain\Students\StudentGateway;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Domain\School\FacilityGateway;
use Gibbon\Domain\Students\MedicalGateway;
use Gibbon\Domain\Students\FirstAidGateway;
use Gibbon\Domain\System\CustomFieldGateway;
use Gibbon\Module\StudentTransfer\Domain\TransferGateway;
use Gibbon\Module\StudentTransfer\Domain\TransferImportGateway;
use Gibbon\Module\StudentTransfer\Domain\SecurityService;
use Gibbon\Module\StudentTransfer\Domain\StudentExporter;
use Gibbon\Session\Session;

/**
 * Register module services with the container
 */
global $container;

// Register the TransferGateway
$container->add(TransferGateway::class)
    ->addArgument(Connection::class);

// Register the TransferImportGateway
$container->add(TransferImportGateway::class)
    ->addArgument(Connection::class);

// Register the SecurityService
$container->add(SecurityService::class)
    ->addArgument(Connection::class)
    ->addArgument(SettingGateway::class);

/**
 * Register StudentExporter dependencies - only call this when needed
 * This function is used to delay loading of heavy dependencies until they are required
 * 
 * @return void
 */
function registerStudentExporter()
{
    global $container;
    
    // Only register if not already registered
    if (!$container->has(StudentExporter::class)) {
        $container->add(StudentExporter::class)
            ->addArgument(Connection::class)
            ->addArgument(SettingGateway::class)
            ->addArgument(StudentGateway::class)
            ->addArgument(FacilityGateway::class)
            ->addArgument(UserGateway::class)
            ->addArgument(CustomFieldGateway::class)
            ->addArgument(MedicalGateway::class)
            ->addArgument(FirstAidGateway::class)
            ->addArgument(Session::class)
            ->addArgument(SecurityService::class);
    }
}

/**
 * Student Transfer Module Helper Functions
 */

/**
 * Get list of students eligible for transfer
 *
 * @param Connection $pdo
 * @param int $gibbonSchoolYearID
 * @return array
 */
function getEligibleStudentsForTransfer(Connection $pdo, $gibbonSchoolYearID)
{
    $sql = "SELECT DISTINCT gibbonPerson.gibbonPersonID, 
                   gibbonPerson.surname, 
                   gibbonPerson.preferredName,
                   gibbonYearGroup.nameShort as yearGroup,
                   gibbonFormGroup.nameShort as rollGroup 
            FROM gibbonPerson 
            JOIN gibbonStudentEnrolment ON (
                gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID
                AND gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID
            )
            JOIN gibbonYearGroup ON (
                gibbonStudentEnrolment.gibbonYearGroupID=gibbonYearGroup.gibbonYearGroupID
            )
            JOIN gibbonFormGroup ON (
                gibbonStudentEnrolment.gibbonFormGroupID=gibbonFormGroup.gibbonFormGroupID
            )
            WHERE gibbonPerson.status='Full'
            ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

    return $pdo->select($sql, ['gibbonSchoolYearID' => $gibbonSchoolYearID])->fetchAll();
}

/**
 * Check if required tables exist
 *
 * @param \PDO $connection2
 * @return bool
 */
function checkTablesExist($connection2)
{
    $tables = [
        'gibbonStudentTransferLog',
        'gibbonStudentTransferData',
        'gibbonStudentTransferAttachment'
    ];

    foreach ($tables as $table) {
        try {
            $sql = "SHOW TABLES LIKE :table";
            $result = $connection2->prepare($sql);
            $result->execute(['table' => $table]);
            
            if ($result->rowCount() == 0) {
                return false;
            }
        } catch (\PDOException $e) {
            return false;
        }
    }

    return true;
}

/**
 * Check if required columns exist in a table
 *
 * @param \PDO $connection2
 * @param string $table
 * @param array $columns
 * @return bool
 */
function checkColumnsExist($connection2, $table, $columns)
{
    try {
        $sql = "SHOW COLUMNS FROM `$table`";
        $result = $connection2->prepare($sql);
        $result->execute();
        $existingColumns = array_column($result->fetchAll(), 'Field');

        foreach ($columns as $column) {
            if (!in_array($column, $existingColumns)) {
                return false;
            }
        }

        return true;
    } catch (\PDOException $e) {
        return false;
    }
}

/**
 * Format a transfer status with appropriate color coding
 *
 * @param string $status
 * @return string
 */
function formatTransferStatus($status)
{
    $colors = [
        'Pending' => '#FFB500',
        'Exported' => '#00B0F0',
        'Imported' => '#85BA4E',
        'Complete' => '#00A746',
        'Cancelled' => '#D60000'
    ];

    $color = isset($colors[$status]) ? $colors[$status] : '#999999';
    return "<span style='color: $color'>$status</span>";
}

/**
 * Check if a student has an active transfer
 *
 * @param Connection $pdo
 * @param int $gibbonPersonID
 * @return bool
 */
function hasActiveTransfer(Connection $pdo, $gibbonPersonID)
{
    $sql = "SELECT COUNT(*) FROM gibbonStudentTransferLog 
            WHERE gibbonPersonID=:gibbonPersonID 
            AND status IN ('Pending', 'Exported')";
    
    $result = $pdo->select($sql, ['gibbonPersonID' => $gibbonPersonID]);
    return ($result->fetchColumn() > 0);
}

/**
 * Validate a transfer package
 *
 * @param string $packagePath
 * @return array
 */
function validateTransferPackage($packagePath)
{
    $errors = [];
    
    // Check if file exists
    if (!file_exists($packagePath)) {
        $errors[] = 'Package file not found';
        return $errors;
    }

    // Check file extension
    if (pathinfo($packagePath, PATHINFO_EXTENSION) !== 'zip') {
        $errors[] = 'Invalid package format. Must be a ZIP file';
        return $errors;
    }

    // Additional validation can be added here

    return $errors;
}
