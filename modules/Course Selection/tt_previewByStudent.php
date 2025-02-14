<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\DataSet;
use Gibbon\Services\Format;

// Module Bootstrap
require 'module.php';

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/tt_resultsByStudent.php') == false) {
    //Acess denied
    echo "<div class='error'>" ;
        echo __('You do not have access to this action.');
    echo "</div>" ;
} else {
	$page->breadcrumbs
    	->add(__m('Preview Timetable'));

	$settingGateway = $container->get(SettingGateway::class);
    $gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $settingGateway->getSettingByScope('Course Selection', 'activeSchoolYear');
    $gibbonPersonIDStudent = $_POST['gibbonPersonIDStudent'] ?? $_GET['gibbonPersonID'] ?? 0;

    $form = Form::create('selectStudent', $session->get('absoluteURL').'/index.php?q=/modules/Course Selection/tt_previewByStudent.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('address', $session->get('address'));

    $row = $form->addRow();
        $row->addLabel('gibbonPersonIDStudent', __('Student'));
        $row->addSelectStudent('gibbonPersonIDStudent', $session->get('gibbonSchoolYearID'))->selected($gibbonPersonIDStudent);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();

    // Cancel out early if there's no valid student selected
    if (empty($gibbonPersonIDStudent)) return;

    $timetableGateway = $container->get('CourseSelection\Domain\TimetableGateway');
    $timetables = $timetableGateway->selectRelevantTimetablesByPerson($gibbonSchoolYearID, $gibbonPersonIDStudent)->fetchAll();

    $gibbonTTID = $timetables[0]['gibbonTTID'] ?? null;

    if (empty($gibbonTTID)) {
        $page->addError(__m('A relevant timetable could not be located for this student in the target school year.'));
        return;
    }

    $timetableDays = $timetableGateway->selectTimetableDaysAndColumns($gibbonTTID)->fetchGrouped();
    $timetablePreview = $timetableGateway->selectTimetablePreviewByStudent($gibbonSchoolYearID, $gibbonPersonIDStudent)->fetchGrouped();

    $table = DataTable::create('timetable');

    $timetableData = new DataSet($timetableDays);

    $timetableData->transform(function (&$ttDay) use (&$timetablePreview) {
        foreach ($ttDay as $index => $values) {
            $ttDayRow = $values['gibbonTTDayID'].'-'.$values['gibbonTTColumnRowID'];
            if (isset($timetablePreview[$ttDayRow])) {
                $preview = array_reduce($timetablePreview[$ttDayRow], function ($group, $item) {
                    $group[$item['status']][] = $item;
                    return $group;
                }, []);

                $ttDay[$index]['preview'] = $preview;
            }
        }
    });

    foreach (array_keys($timetableDays) as $index => $ttRow) {
        $table->addColumn('period', '')
            ->width('8%')
            ->format(function ($values) use ($index) {
                return $values[$index]['rowName'];
            });
    }

    foreach (current($timetableDays) as $index => $ttDay) {
        $table->addColumn($ttDay['nameShort'], str_replace('MF', '', $ttDay['nameShort']))
            ->context('primary')
            ->width('23%')
            ->format(function ($values) use ($index) {
                $name = $url = $title = $class = '';

                $preview = $values[$index]['preview'] ?? [];
                if (!empty($preview['Complete'])) {
                    $class = 'bg-blue-200';
                    $complete = current($preview['Complete']);
                    $name = '<span class="font-bold text-sm">'.$complete['courseNameShort'].'.'.$complete['className'].'</span><br>';
                    $name .= '<span class="text-xs text-gray-700">'.$complete['courseName'].'</span>';

                    if ($complete['reason'] == 'Locked') {
                        
                        $name .= '<span class="text-xs text-gray-700 font-bold">';
                        $name .= '<img class="mr-1" src="./themes/Default/img/key.png" width="15" height="15">';
                        $name .= __('Pre-enrolled').'</span>';
                    }
                    $url = 'index.php?q=/modules/Course Selection/tt_resultsByStudent.php&gibbonSchoolYearID='.$complete['gibbonSchoolYearID'].'&gibbonCourseClassID='.$complete['gibbonCourseClassID'];

                    // $name = $complete['courseSelectionTTResultID'];
                }

                if (!empty($preview['Flagged'])) {
                    // $class = 'bg-transparent';
                    
                    $title = 'Conflicts with '.implode(', ', array_reduce($preview['Flagged'], function ($group, $item) {
                        $group[] = $item['courseNameShort'].'.'.$item['className'];
                        return $group;
                    }, []));

                    // $flagged = current($preview['Flagged']);
                    // $name = $flagged['courseSelectionTTResultID'];
                }

                $class .= ' block px-4 h-20 border border-gray-500 border-solid flex flex-col justify-center';
                
                return Format::link($url, $name, ['title' => $title, 'class' => $class, 'style' => 'text-decoration: none;']);
            })
            ->modifyCells(function ($values, $cell) use ($index) {
                $cell->addClass('p-1 text-center leading-tight');

                $preview = $values[$index]['preview'] ?? [];
                if (!empty($preview['Flagged'])) {
                    $cell->addClass('warning');

                    $title = 'Couldn\'t Schedule '.implode(', ', array_reduce($preview['Flagged'], function ($group, $item) {
                        $group[] = $item['courseNameShort'].'.'.$item['className'];
                        return $group;
                    }, []));
                    $cell->setTitle($title);
                }
                return $cell;
            });
    }

    echo $table->render(new DataSet(array_values($timetableData->toArray())));
}
