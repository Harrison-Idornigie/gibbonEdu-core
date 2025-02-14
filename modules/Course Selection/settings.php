<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\System\SettingGateway;

// Module Bootstrap
require 'module.php';

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/settings.php') == false) {
    //Acess denied
    echo "<div class='error'>" ;
        echo __('You do not have access to this action.');
    echo "</div>" ;
} else {
    $page->breadcrumbs
    ->add(__m('Course Selection Settings'));
    
    $form = Form::create('settings', $session->get('absoluteURL').'/modules/Course Selection/settingsProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('address', $session->get('address'));

	$settingGateway = $container->get(SettingGateway::class);
    $setting = $settingGateway->getSettingByScope('Course Selection', 'activeSchoolYear', true);
    $row = $form->addRow();
        $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addSelectSchoolYear($setting['name'], 'Active')->selected($setting['value'])->required();

    $setting = $settingGateway->getSettingByScope('Course Selection', 'requireApproval', true);
    $row = $form->addRow();
        $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addYesNo($setting['name'])->selected($setting['value'])->required();

    $form->addRow()->addHeading(__('Information'));

    $setting = $settingGateway->getSettingByScope('Course Selection', 'infoTextOfferings', true);
    $row = $form->addRow();
        $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addTextArea($setting['name'])->setRows(4)->setValue($setting['value']);

    $setting = $settingGateway->getSettingByScope('Course Selection', 'infoTextSelectionBefore', true);
    $row = $form->addRow();
        $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addTextArea($setting['name'])->setRows(4)->setValue($setting['value']);

    $setting = $settingGateway->getSettingByScope('Course Selection', 'infoTextSelectionAfter', true);
    $row = $form->addRow();
        $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addTextArea($setting['name'])->setRows(4)->setValue($setting['value']);

    $form->addRow()->addHeading(__('Course Selection Messages'));

    $setting = $settingGateway->getSettingByScope('Course Selection', 'selectionComplete', true);
    $row = $form->addRow();
        $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addTextArea($setting['name'])->setRows(4)->setValue($setting['value']);

    $setting = $settingGateway->getSettingByScope('Course Selection', 'selectionInvalid', true);
    $row = $form->addRow();
        $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addTextArea($setting['name'])->setRows(4)->setValue($setting['value']);

    $setting = $settingGateway->getSettingByScope('Course Selection', 'selectionContinue', true);
    $row = $form->addRow();
        $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addTextArea($setting['name'])->setRows(4)->setValue($setting['value']);

    $form->addRow()->addHeading(__('Timetabling'));

    $setting = $settingGateway->getSettingByScope('Course Selection', 'classEnrolmentMinimum', true);
    $row = $form->addRow();
        $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addNumber($setting['name'])->setValue($setting['value']);

    $setting = $settingGateway->getSettingByScope('Course Selection', 'classEnrolmentTarget', true);
    $row = $form->addRow();
        $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addNumber($setting['name'])->setValue($setting['value']);

    $setting = $settingGateway->getSettingByScope('Course Selection', 'classEnrolmentMaximum', true);
    $row = $form->addRow();
        $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addNumber($setting['name'])->setValue($setting['value']);

    $form->addRow()->addHeading(__('Reporting Integration'));

    $setting = $settingGateway->getSettingByScope('Course Selection', 'enableCourseGrades', true);
    $row = $form->addRow();
        $row->addLabel($setting['name'], __($setting['nameDisplay']))->description(__($setting['description']));
        $row->addYesNo($setting['name'])->selected($setting['value']);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
