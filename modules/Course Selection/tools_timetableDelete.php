<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

use Gibbon\Forms\Form;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\School\SchoolYearGateway;

// Module Bootstrap
require 'module.php';

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/tools_timetableDelete.php') == false) {
    //Acess denied
    echo "<div class='error'>" ;
        echo __('You do not have access to this action.');
    echo "</div>" ;
} else {

    $page->breadcrumbs->add(__m('Clear Timetable'));

	$settingGateway = $container->get(SettingGateway::class);
    $gibbonSchoolYearID = $settingGateway->getSettingByScope('Course Selection', 'activeSchoolYear');

    $toolsGateway = $container->get('CourseSelection\Domain\ToolsGateway');
    $timetableResults = $toolsGateway->selectTimetablesBySchoolYear($gibbonSchoolYearID);

    $schoolYearGateway = $container->get(SchoolYearGateway::class);
    $targetSchoolYear = $schoolYearGateway->getSchoolYearByID($gibbonSchoolYearID);

    $form = Form::create('timetableClear', $session->get('absoluteURL').'/modules/Course Selection/tools_timetableDeleteProcess.php');

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);

    $row = $form->addRow()->addHeading(__('Danger Zone'));

    $row = $form->addRow();
        $row->addAlert(__('Clearing the timetable will delete ALL timetabling results, delete ALL classes which have no enrolments, and delete ALL timetable data for the selected timetable. Proceed with caution.'), 'error');

    $row = $form->addRow();
        $row->addLabel('schoolYear', __('School Year'));
        $row->addTextField('schoolYear')->setValue($targetSchoolYear['name'])->readonly();

    $row = $form->addRow();
        $row->addLabel('gibbonTTID', __('Timetable'));
        $row->addSelect('gibbonTTID')->fromResults($timetableResults)->required()->placeholder();

    $row = $form->addRow();
        $row->addLabel('confirm', sprintf(__('Type %1$s to confirm'), __('DELETE')) );
        $row->addTextField('confirm')
            ->addValidation('Validate.Presence')
            ->addValidation('Validate.Inclusion',
                'within: [\''.__('DELETE').'\'], failureMessage: "'.__(' Please enter the text exactly as it is displayed to confirm this action.').'"')
            ->addValidationOption('onlyOnSubmit: true');

    $row = $form->addRow();
        $row->addContent('<input type="submit" value="'.__('Clear Timetable').'" class="shortWidth" style="background: #B10D0D;color:#ffffff;">')->setClass('right');

    echo $form->getOutput();
}
