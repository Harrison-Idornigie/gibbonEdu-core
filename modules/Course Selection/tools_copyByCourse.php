<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\System\SettingGateway;
use CourseSelection\Domain\ToolsGateway;
use CourseSelection\SchoolYearNavigation;

// Module Bootstrap
require 'module.php';

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/tools_copyByCourse.php') == false) {
    //Acess denied
    echo "<div class='error'>" ;
        echo __('You do not have access to this action.');
    echo "</div>" ;
} else {
	$page->breadcrumbs
    	->add(__m('Copy Selections By Course'));

    $toolsGateway = $container->get('CourseSelection\Domain\ToolsGateway');

	$settingGateway = $container->get(SettingGateway::class);
    $gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $settingGateway->getSettingByScope('Course Selection', 'activeSchoolYear');
    $gibbonSchoolYearIDCopyTo = $_GET['gibbonSchoolYearIDCopyTo'] ?? null;
    $gibbonCourseID = $_GET['gibbonCourseID'] ?? '';
    $actionCopyFrom = $_GET['actionCopyFrom'] ?? '';
    $actionCopyTo = $_GET['actionCopyTo'] ?? '';

    $page->navigator->addSchoolYearNavigation($gibbonSchoolYearID);

    if ($actionCopyFrom == 'Requests') {
        $gibbonSchoolYearIDCopyTo = $gibbonSchoolYearID;
    } else if (empty($gibbonSchoolYearIDCopyTo)) {
        $navigation = new SchoolYearNavigation($pdo, $gibbon->session);
        $nextSchoolYear = $navigation->selectNextSchoolYearByID($gibbonSchoolYearID);
        $gibbonSchoolYearIDCopyTo = $nextSchoolYear['gibbonSchoolYearID'] ?? '';
    }

    // SELECT COURSE
    $form = Form::create('copySelectionsPicker', $session->get('absoluteURL').'/index.php', 'get');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('q', '/modules/Course Selection/tools_copyByCourse.php');
    $form->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);

    $courses = $toolsGateway->selectAllCoursesBySchoolYear($gibbonSchoolYearID, false)->fetchKeyPair();

    $form->addRow()->addContent(__("This tool lets you copy student enrolments or course requests and convert them into requests for a different course. After selecting the year and course below you'll have the option to select students and the destination course to copy to."))->wrap('<br/><p>', '</p>');

    $courseCopyFromResults = $toolsGateway->selectCoursesOfferedBySchoolYear($gibbonSchoolYearID);
    $courseCopyOptions = ($courseCopyFromResults->rowCount() > 0)? array('Requests', 'Enrolments') : array('Enrolments');

    $row = $form->addRow();
        $row->addLabel('actionCopyFrom', __('Copy From'));
        $row->addSelect('actionCopyFrom')->fromArray($courseCopyOptions)->required()->placeholder()->selected($actionCopyFrom);

    $row = $form->addRow();
        $row->addLabel('actionCopyTo', __('Copy To'));
        $row->addSelect('actionCopyTo')->fromArray(array('Requests', 'Enrolments'))->required()->placeholder()->selected($actionCopyTo);

    $form->toggleVisibilityByClass('courseEnrolment')->onSelect('actionCopyFrom')->when('Enrolments');
    $form->toggleVisibilityByClass('courseRequests')->onSelect('actionCopyFrom')->when('Requests');

    $row = $form->addRow()->addClass('courseEnrolment');
        $row->addLabel('gibbonCourseID', __('Course'));
        $row->addSelect('gibbonCourseID')->fromArray($courses)->required()->selected($gibbonCourseID);

    $row = $form->addRow()->addClass('courseEnrolment');
        $row->addLabel('gibbonSchoolYearIDCopyTo', __('Destination School Year'))->setClass('mediumWidth');
        $row->addSelectSchoolYear('gibbonSchoolYearIDCopyTo', 'Active')->required()->selected($gibbonSchoolYearIDCopyTo);

    $row = $form->addRow()->addClass('courseRequests');
        $row->addLabel('gibbonCourseID', __('Course'));
        $row->addSelect('gibbonCourseID')->fromResults($courseCopyFromResults)->required()->selected($gibbonCourseID);

    $row = $form->addRow();
        $row->addSubmit('Next');

    echo $form->getOutput();

    // SELECT STUDENTS
    if (!empty($gibbonCourseID) && !empty($gibbonSchoolYearIDCopyTo)) {
        echo '<h2>';
        echo __($guid, (($actionCopyFrom == 'Requests')? 'Current Requests' : 'Current Enrolments') );
        echo '</h2>';

        if ($actionCopyFrom == 'Requests') {
            $studentsResults = $toolsGateway->selectStudentsByCourseSelection($gibbonCourseID);
        } else {
            $studentsResults = $toolsGateway->selectStudentsByCourse($gibbonCourseID);
        }

        if ($studentsResults->rowCount() == 0) {
            echo '<div class="error">';
            echo __("There are no records to display.") ;
            echo '</div>';
            return;
        }

        $form = Form::create('copySelectionsStudents', $session->get('absoluteURL').'/modules/Course Selection/tools_copyByCourseProcess.php');

        $form->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);
        $form->addHiddenValue('gibbonSchoolYearIDCopyTo', $gibbonSchoolYearIDCopyTo);
        $form->addHiddenValue('gibbonCourseID', $gibbonCourseID);
        $form->addHiddenValue('actionCopyTo', $actionCopyTo);

        $row = $form->addRow()->setClass('break');
            $row->addContent(__('Student Name'))->setClass('w-2/5');
            $row->addContent(__('Grade'))->setClass('w-1/5');
            $row->addContent(__('Current'))->setClass('w-1/5');
            $row->addContent('<input type="checkbox" class="checkall" checked>')->setClass('right w-1/5');

        while ($student = $studentsResults->fetch()) {
            $row = $form->addRow()->addClass('rowHighlight');
                $row->addLabel('studentList[]', Format::name('', $student['preferredName'], $student['surname'], 'Student', true))->setClass('w-2/5');
                $row->addContent($student['yearGroupName'])->setClass('w-1/5');
                $row->addContent($student['courseClassName'])->setClass('w-1/5');
                $row->addCheckbox('studentList[]')->addClass('w-1/5 studentList')->setValue($student['gibbonPersonID'])->checked($student['gibbonPersonID']);
        }

        $row = $form->addRow();
            $row->addContent('<span><input type="text" class="countall" readonly style="text-align: right;"></span>')->setClass('right');

        $form->addRow()->addHeading(__('Copy to Course'));

        $schoolYearCopyToResults = $toolsGateway->selectSchoolYear($gibbonSchoolYearIDCopyTo);
        $schoolYearCopyToName = ($schoolYearCopyToResults->rowCount() > 0)? $schoolYearCopyToResults->fetchColumn(1) : '';

        $row = $form->addRow();
            $row->addLabel('gibbonSchoolYearCopyToName', __('Destination School Year'));
            $row->addTextField('gibbonSchoolYearCopyToName')->readonly()->setValue($schoolYearCopyToName);

        if ($actionCopyTo == 'Requests') {
            $courseCopyToOffered = $toolsGateway->selectAllCoursesBySchoolYear($gibbonSchoolYearIDCopyTo, false);
            $row = $form->addRow()->addClass('actionCopyToRequest');
                $row->addLabel('gibbonCourseIDCopyTo', __('Course Selection'));
                $row->addSelect('gibbonCourseIDCopyTo')->fromResults($courseCopyToOffered)->required();

            $row = $form->addRow()->addClass('actionCopyToRequest');
                $row->addLabel('status', __('Selection Status'));
                $row->addSelect('status')->fromArray(array('Required', 'Approved', 'Requested', 'Selected', 'Recommended', 'Removed'))->required();

            $row = $form->addRow()->addClass('actionCopyToRequest');
                $row->addLabel('overwrite', __('Overwrite?'))->description(__('Replace the course selection status if one already exists for that student and course.'));
                $row->addYesNo('overwrite')->required()->selected('Y');

        } else if ($actionCopyTo == 'Enrolments') {
            $courseCopyToAll = $toolsGateway->selectAllCoursesBySchoolYear($gibbonSchoolYearIDCopyTo, false);
            $row = $form->addRow()->addClass('actionCopyToEnrolment');
                $row->addLabel('gibbonCourseIDCopyTo', __('Course'));
                $row->addSelect('gibbonCourseIDCopyTo')->fromResults($courseCopyToAll)->required();

            $classCopyToRequest = $toolsGateway->selectAllCourseClassesBySchoolYear($gibbonSchoolYearIDCopyTo);
            $classCopyTo = ($classCopyToRequest->rowCount() > 0)? $classCopyToRequest->fetchAll() : array();
            $classCopyToChained = array_combine(array_column($classCopyTo, 'value'), array_column($classCopyTo, 'gibbonCourseID'));
            $classCopyToOptions = array_combine(array_column($classCopyTo, 'value'), array_column($classCopyTo, 'name'));

            $row = $form->addRow()->addClass('actionCopyToEnrolment');
                $row->addLabel('gibbonCourseClassIDCopyTo', __('Class'));
                $row->addSelect('gibbonCourseClassIDCopyTo')
                    ->fromArray($classCopyToOptions)
                    ->required()
                    ->placeholder()
                    ->chainedTo('gibbonCourseIDCopyTo', $classCopyToChained);

            $row = $form->addRow()->addClass('actionCopyToEnrolment');
                $row->addAlert(__('This will copy course requests directly to class enrolments, bypassing the timetabling engine. Use with caution!'), 'warning');
        }

        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit();

        echo $form->getOutput();
        ?>

        <script type="text/javascript">
            $(function () {
                $('.checkall').click(function () {
                    $(this).parents('#copySelectionsStudents').find(':checkbox').attr('checked', this.checked);
                });

                $(':checkbox').change(function () {
                    $('.countall').val( 'Selected: '+$(this).parents('#copySelectionsStudents').find('.studentList:checked').length );
                });

                $('.checkall:checkbox').change();
            });
        </script>
        <?php
    }
}
