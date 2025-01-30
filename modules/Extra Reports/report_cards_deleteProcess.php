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

use Gibbon\Domain\System\LogGateway;

/**
 * Delete Report Card Assessment Process
 *
 * This script handles the deletion of a report card assessment.
 * It includes security checks, validation, and logging of the deletion.
 */

// Gibbon system-wide includes
include '../../gibbon.php';

// Module includes
include './moduleFunctions.php';

// Set up return URL
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/report_cards_view.php';

// Check access rights
if (isActionAccessible($guid, $connection2, '/modules/Extra Reports/report_cards_enter.php') == false) {
    // Access denied
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

// Proceed!
$extraReportAssessmentID = $_POST['extraReportAssessmentID'] ?? '';

// Validate the required parameters
if (empty($extraReportAssessmentID)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

try {
    // Get assessment details before deletion for logging
    $data = ['extraReportAssessmentID' => $extraReportAssessmentID];
    $sql = "SELECT assessment.*, gibbonPerson.surname, gibbonPerson.preferredName 
            FROM extraReportAssessment as assessment
            JOIN gibbonPerson ON (assessment.gibbonPersonIDStudent=gibbonPerson.gibbonPersonID)
            WHERE extraReportAssessmentID=:extraReportAssessmentID";
    
    $result = $pdo->select($sql, $data);
    
    if ($result->rowCount() != 1) {
        // Record not found
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }
    
    $assessment = $result->fetch();

    // Delete the assessment
    $data = ['extraReportAssessmentID' => $extraReportAssessmentID];
    $sql = "DELETE FROM extraReportAssessment WHERE extraReportAssessmentID=:extraReportAssessmentID";
    $result = $pdo->delete($sql, $data);

    if ($result) {
        // Log the deletion
        $logGateway = $container->get(LogGateway::class);
        $logGateway->addLog(
            gibbonSchoolYearID: $session->get('gibbonSchoolYearID'),
            gibbonModuleID: getModuleIDFromName($connection2, 'Extra Reports'),
            gibbonPersonID: $session->get('gibbonPersonID'),
            title: 'Report Card Assessment Deleted',
            array: [
                'extraReportAssessmentID' => $extraReportAssessmentID,
                'studentName' => Format::name('', $assessment['preferredName'], $assessment['surname'], 'Student'),
            ]
        );

        $URL .= '&return=success0';
    } else {
        $URL .= '&return=error2';
    }
} catch (Exception $e) {
    // Log the error for debugging
    error_log("Extra Reports: Error deleting assessment - " . $e->getMessage());
    $URL .= '&return=error2';
}

// Redirect back to the view page
header("Location: {$URL}");
exit;
