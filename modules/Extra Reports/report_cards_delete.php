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

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Services\Format;

/**
 * Delete Report Card Assessment
 *
 * This page provides a confirmation form for deleting a report card assessment.
 * It includes security checks and displays student information for verification.
 */

// Module includes for common functions
require_once __DIR__ . '/moduleFunctions.php';

// Check access rights
if (isActionAccessible($guid, $connection2, '/modules/Extra Reports/report_cards_enter.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__('View Report Cards'), 'report_cards_view.php')
        ->add(__('Delete Assessment'));

    // Get the assessment ID from URL parameters
    $extraReportAssessmentID = $_GET['extraReportAssessmentID'] ?? '';

    // Validate the required parameters
    if (empty($extraReportAssessmentID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    // Fetch the assessment details including student information
    $data = ['extraReportAssessmentID' => $extraReportAssessmentID];
    $sql = "SELECT assessment.*, 
                   gibbonPerson.surname, 
                   gibbonPerson.preferredName, 
                   gibbonFormGroup.name as formGroup,
                   term.name as termName
            FROM extraReportAssessment as assessment
            JOIN gibbonPerson ON (assessment.gibbonPersonIDStudent=gibbonPerson.gibbonPersonID)
            JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID)
            JOIN gibbonFormGroup ON (gibbonFormGroup.gibbonFormGroupID=gibbonStudentEnrolment.gibbonFormGroupID)
            JOIN gibbonSchoolYearTerm as term ON (term.gibbonSchoolYearTermID=assessment.gibbonSchoolYearTermID)
            WHERE extraReportAssessmentID=:extraReportAssessmentID";
    
    $result = $pdo->select($sql, $data);

    // Check if the assessment exists
    if ($result->rowCount() != 1) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $values = $result->fetch();

    // Create the deletion confirmation form
    $form = Form::create('deleteAssessment', 
        $session->get('absoluteURL').'/modules/'.$session->get('module').'/report_cards_deleteProcess.php');

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('extraReportAssessmentID', $extraReportAssessmentID);

    // Display student information for verification
    $row = $form->addRow();
        $row->addContent('<strong>' . __('Student') . ':</strong> ' . 
            Format::name('', $values['preferredName'], $values['surname'], 'Student'));

    $row = $form->addRow();
        $row->addContent('<strong>' . __('Form Group') . ':</strong> ' . $values['formGroup']);

    $row = $form->addRow();
        $row->addContent('<strong>' . __('Term') . ':</strong> ' . $values['termName']);

    $row = $form->addRow();
        $row->addContent('<strong>' . __('Template') . ':</strong> ' . ucfirst($values['template']));

    // Add confirmation message
    $row = $form->addRow();
        $row->addContent(__('Are you sure you want to delete this assessment? This operation cannot be undone.'))
            ->wrap('<p class="text-red-600 font-bold">', '</p>');

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Delete'));

    echo $form->getOutput();
}
