<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

use Gibbon\Forms\Form;

// Module Bootstrap
require 'module.php';

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/tt_engine.php') == false) {
	//Acess denied
	echo "<div class='error'>" ;
		echo __('You do not have access to this action.');
	echo "</div>" ;
} else {
    $gibbonSchoolYearID = $_GET['gibbonSchoolYearID'] ?? '';

    if ($gibbonSchoolYearID == '') {
        echo "<div class='error'>";
        echo __($guid, 'You have not specified one or more required parameters.');
        echo '</div>';
    } else {
        $form = Form::create('accessRecord', $session->get('absoluteURL').'/modules/'.$session->get('module').'/tt_engine_goLiveProcess.php');

        $form->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);
        $form->addHiddenValue('address', $session->get('address'));

        $row = $form->addRow()->addHeading(__('Are you sure you want to continue?'));

        $row = $form->addRow();
            $row->addContent(__('This operation cannot be undone.').' '.__('Taking the timetable live will turn all results into student enrolments for the selected school year. After going live the new student enrolments can be managed as usual from the Timetable Admin module.'));

        $row = $form->addRow();
            $row->addLabel('confirm', sprintf(__('Type %1$s to confirm'), __('I LOVE GIBBON')) )->addClass('mediumWidth');
            $row->addTextField('confirm')
                ->addValidation('Validate.Presence')
                ->addValidation('Validate.Inclusion',
                    'within: [\''.__('I LOVE GIBBON').'\'], failureMessage: "'.__(' Please enter the text exactly as it is displayed to confirm this action.').'", partialMatch: false, caseSensitive: false')
                ->addValidationOption('onlyOnSubmit: true');

        $form->addRow()->addSubmit();

        echo $form->getOutput();
    }
}
