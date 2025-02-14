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

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/upcomingTimetable.php') == false) {
    //Acess denied
    echo "<div class='error'>" ;
        echo __('You do not have access to this action.');
    echo "</div>" ;
} else {
    $page->breadcrumbs
    	->add(__m('Upcoming Timetable'));

    $highestGroupedAction = getHighestGroupedAction($guid, '/modules/Course Selection/upcomingTimetable.php', $connection2);

    if ($highestGroupedAction == 'Upcoming Timetable_all') {
        $gibbonPersonIDStudent = $_POST['gibbonPersonIDStudent'] ?? 0;

        $form = Form::create('selectStudent', $session->get('absoluteURL').'/index.php?q=/modules/Course Selection/upcomingTimetable.php');
        $form->setFactory(DatabaseFormFactory::create($pdo));

        $form->addHiddenValue('address', $session->get('address'));

        $row = $form->addRow();
            $row->addLabel('gibbonPersonIDStudent', __('Student'));
            $row->addSelectStudent('gibbonPersonIDStudent', $session->get('gibbonSchoolYearID'))->selected($gibbonPersonIDStudent);

        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit();

        echo $form->getOutput();
    } else if ($highestGroupedAction == 'Upcoming Timetable_my') {
        $gibbonPersonIDStudent = $session->get('gibbonPersonID');
    }

	$settingGateway = $container->get(SettingGateway::class);
    $gibbonSchoolYearID = $settingGateway->getSettingByScope('Course Selection', 'activeSchoolYear');

    // Cancel out early if there's no valid student selected
    if (empty($gibbonSchoolYearID) || empty($gibbonPersonIDStudent)) return;

    $timetableGateway = $container->get('CourseSelection\Domain\TimetableGateway');
    $timetables = $timetableGateway->selectRelevantTimetablesByPerson($gibbonSchoolYearID, $gibbonPersonIDStudent)->fetchAll();

    $gibbonTTID = $timetables[0]['gibbonTTID'] ?? null;

    if (empty($gibbonTTID)) {
        $page->addError(__m('A relevant timetable could not be located for this student in the target school year.'));
        return;
    }

    $timetableDays = $timetableGateway->selectTimetableDaysAndColumns($gibbonTTID)->fetchGrouped();
    $courses = $timetableGateway->selectEnroledCoursesBySchoolYearAndStudent($gibbonSchoolYearID, $gibbonPersonIDStudent)->fetchGrouped();

    $table = DataTable::create('timetable');

    $timetableData = new DataSet($timetableDays);

    $timetableData->transform(function (&$ttDay) use (&$courses) {
        foreach ($ttDay as $index => $values) {
            $ttDayRow = $values['gibbonTTDayID'].'-'.$values['gibbonTTColumnRowID'];
            if (isset($courses[$ttDayRow])) {
                $ttDay[$index]['courseClass'] = $courses[$ttDayRow];
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

    $canAccessTT = isActionAccessible($guid, $connection2, '/modules/Timetable Admin/courseEnrolment_manage_class_edit.php');
    foreach (current($timetableDays) as $index => $ttDay) {
        $table->addColumn($ttDay['nameShort'], str_replace('MF', '', $ttDay['nameShort']))
            ->context('primary')
            ->width('23%')
            ->format(function ($values) use ($index, $canAccessTT) {
                $name = $url = $title = '';
                $class = ' block px-4 border border-gray-500 border-solid flex flex-col justify-center';

                $output = '';
                $courseClassList = $values[$index]['courseClass'] ?? [];
                if (!empty($courseClassList)) {
                    foreach ($courseClassList as $courseClass) {
                        $height = count($courseClassList) == 1 ? 'h-20' : (count($courseClassList) == 2 ? 'h-10 -mt-px' : 'h-6 -mt-px');
                        $name = '<span class="font-bold text-sm">'.$courseClass['courseNameShort'].'.'.$courseClass['className'].'</span><br>';
                        $name .= count($courseClassList) == 1 ? '<span class="text-xs text-gray-700">'.$courseClass['courseName'].'</span>' : '';
                        $title = $courseClass['courseName'];
                        $url = $canAccessTT
                        ? './index.php?q=/modules/Timetable Admin/courseEnrolment_manage_class_edit.php&gibbonSchoolYearID='.$courseClass['gibbonSchoolYearID'].'&gibbonCourseID='.$courseClass['gibbonCourseID'].'&gibbonCourseClassID='.$courseClass['gibbonCourseClassID']
                        : "#";
                        $output .= Format::link($url, $name, ['title' => $title, 'class' => $class.' '.$height.' bg-blue-200', 'style' => 'text-decoration: none;']);
                    }
                } else {
                    $output .= Format::link($url, $name, ['title' => $title, 'class' => $class.' h-20', 'style' => 'text-decoration: none;']);
                }



                return $output;
            })
            ->modifyCells(function ($values, $cell) use ($index) {
                $cell->addClass('p-1 text-center leading-tight');
                return $cell;
            });
    }

    echo $table->render(new DataSet(array_values($timetableData->toArray())));
}
