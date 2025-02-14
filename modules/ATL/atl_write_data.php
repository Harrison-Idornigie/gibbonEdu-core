<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

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

use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Forms\Form;
use Gibbon\Services\Format;

//Module includes
include './modules/'.$session->get('module').'/moduleFunctions.php';

echo "<script type='text/javascript'>";
    echo '$(document).ready(function(){';
        echo "autosize($('textarea'));";
    echo '});';
echo '</script>';

if (isActionAccessible($guid, $connection2, '/modules/ATL/atl_write_data.php') == false) {
    //Acess denied
    echo "<div class='error'>";
    echo __('You do not have access to this action.');
    echo '</div>';
} else {
    // Register scripts available to the core, but not included by default
    $page->scripts->add('chart');

    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        echo "<div class='error'>";
        echo __('The highest grouped action cannot be determined.');
        echo '</div>';
    } else {
        //Check if school year specified
        $gibbonCourseClassID = $_GET['gibbonCourseClassID'] ?? '';
        $atlColumnID = $_GET['atlColumnID'];
        if ($gibbonCourseClassID == '' or $atlColumnID == '') {
            echo "<div class='error'>";
            echo __('You have not specified one or more required parameters.');
            echo '</div>';
        } else {
            try {
                if ($highestAction == 'Write ATLs_all') {
                    $data = array('gibbonCourseClassID' => $gibbonCourseClassID);
                    $sql = "SELECT gibbonCourse.nameShort AS course, gibbonCourse.name AS courseName, gibbonCourseClass.nameShort AS class, gibbonYearGroupIDList FROM gibbonCourse JOIN gibbonCourseClass ON (gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID) WHERE gibbonCourseClassID=:gibbonCourseClassID AND gibbonCourseClass.reportable='Y' ";
                } else {
                    $data = array('gibbonCourseClassID' => $gibbonCourseClassID, 'gibbonPersonID' => $session->get('gibbonPersonID'), 'gibbonCourseClassID2' => $gibbonCourseClassID, 'gibbonPersonID2' => $session->get('gibbonPersonID'), 'gibbonSchoolYearID' => $session->get('gibbonSchoolYearID'));
                    $sql = "(SELECT gibbonCourse.nameShort AS course, gibbonCourse.name AS courseName, gibbonCourseClass.nameShort AS class, gibbonYearGroupIDList FROM gibbonCourse JOIN gibbonCourseClass ON (gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID) JOIN gibbonCourseClassPerson ON (gibbonCourseClassPerson.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID) WHERE gibbonCourseClass.gibbonCourseClassID=:gibbonCourseClassID AND gibbonPersonID=:gibbonPersonID AND (role='Teacher' OR role='Assistant') AND gibbonCourseClass.reportable='Y')
                        UNION
                        (SELECT gibbonCourse.nameShort AS course, gibbonCourse.name AS courseName, gibbonCourseClass.nameShort AS class, gibbonYearGroupIDList FROM gibbonCourseClass JOIN gibbonCourse ON (gibbonCourseClass.gibbonCourseID=gibbonCourse.gibbonCourseID) JOIN gibbonDepartment ON (gibbonCourse.gibbonDepartmentID=gibbonDepartment.gibbonDepartmentID) JOIN gibbonDepartmentStaff ON (gibbonDepartmentStaff.gibbonDepartmentID=gibbonDepartment.gibbonDepartmentID) WHERE gibbonCourseClass.gibbonCourseClassID=:gibbonCourseClassID2 AND gibbonDepartmentStaff.gibbonPersonID=:gibbonPersonID2 AND gibbonDepartmentStaff.role='Coordinator' AND gibbonCourse.gibbonSchoolYearID=:gibbonSchoolYearID AND gibbonCourseClass.reportable='Y')";
                }
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                echo "<div class='error'>".$e->getMessage().'</div>';
            }

            if ($result->rowCount() != 1) {
                echo "<div class='error'>";
                echo __('The selected record does not exist, or you do not have access to it.');
                echo '</div>';
            } else {
                try {
                    $data2 = array('atlColumnID' => $atlColumnID);
                    $sql2 = 'SELECT * FROM atlColumn WHERE atlColumnID=:atlColumnID';
                    $result2 = $connection2->prepare($sql2);
                    $result2->execute($data2);
                } catch (PDOException $e) {
                    echo "<div class='error'>".$e->getMessage().'</div>';
                }

                if ($result2->rowCount() != 1) {
                    echo "<div class='error'>";
                    echo 'The selected column does not exist, or you do not have access to it.';
                    echo '</div>';
                } else {
                    //Let's go!
                    $class = $result->fetch();
                    $values = $result2->fetch();

                    $page->breadcrumbs
                      ->add(__('Write {courseClass} ATLs', ['courseClass' => $class['course'].'.'.$class['class']]), 'atl_write.php', ['gibbonCourseClassID' => $gibbonCourseClassID])
                      ->add(__('Enter ATL Results'));

                    $data = array('gibbonCourseClassID' => $gibbonCourseClassID, 'atlColumnID' => $atlColumnID, 'today' => date('Y-m-d'));
                    $sql = "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.title, gibbonPerson.surname, gibbonPerson.preferredName, gibbonPerson.dateStart, atlEntry.*
                        FROM gibbonCourseClassPerson
                        JOIN gibbonPerson ON (gibbonCourseClassPerson.gibbonPersonID=gibbonPerson.gibbonPersonID)
                        LEFT JOIN atlEntry ON (atlEntry.gibbonPersonIDStudent=gibbonPerson.gibbonPersonID AND atlEntry.atlColumnID=:atlColumnID)
                        WHERE gibbonCourseClassPerson.gibbonCourseClassID=:gibbonCourseClassID
                        AND gibbonCourseClassPerson.reportable='Y' AND gibbonCourseClassPerson.role='Student'
                        AND gibbonPerson.status='Full' AND (dateStart IS NULL OR dateStart<=:today) AND (dateEnd IS NULL  OR dateEnd>=:today)
                        ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";
                    $result = $pdo->executeQuery($data, $sql);
                    $students = ($result->rowCount() > 0)? $result->fetchAll() : array();

                    $form = Form::create('internalAssessment', $session->get('absoluteURL').'/modules/'.$session->get('module').'/atl_write_dataProcess.php?gibbonCourseClassID='.$gibbonCourseClassID.'&atlColumnID='.$atlColumnID.'&address='.$session->get('address'));
                    $form->setFactory(DatabaseFormFactory::create($pdo));
                    $form->addHiddenValue('address', $session->get('address'));

                    $form->addRow()->addHeading(__('Assessment Details'));

                    if (count($students) == 0) {
                        $form->addRow()->addHeading(__('Students'));
                        $form->addRow()->addAlert(__('There are no records to display.'), 'error');
                    } else {
                        $table = $form->addRow()->setClass('p-0')->addTable()->setClass('smallIntBorder w-full colorOddEven noMargin noPadding noBorder');

                        $completeText = !empty($values['completeDate'])? __('Marked on').' '.Format::date($values['completeDate']) : __('Unmarked');

                        $header = $table->addHeaderRow();
                            $header->addTableCell(__('Student'))->rowSpan(2);
                            $header->addTableCell($values['name'])
                                ->setTitle($values['description'])
                                ->append('<br><span class="small emphasis" style="font-weight:normal;">'.$completeText.'</span>')
                                ->setClass('textCenter')
                                ->colSpan(3);

                        $header = $table->addHeaderRow();
                            $header->addContent(__('Complete'))->setClass('textCenter');
                            $header->addContent(__('Rubric'))->setClass('textCenter');
                    }

                    foreach ($students as $index => $student) {
                        $count = $index+1;
                        $row = $table->addRow();

                        $row->addWebLink(Format::name('', $student['preferredName'], $student['surname'], 'Student', true))
                            ->setURL($session->get('absoluteURL').'/index.php?q=/modules/Students/student_view_details.php')
                            ->addParam('gibbonPersonID', $student['gibbonPersonID'])
                            ->addParam('subpage', 'Markbook')
                            ->wrap('<strong>', '</strong>')
                            ->prepend($count.') ');

                        $row->addCheckbox('complete'.$count)->setValue('Y')->checked($student['complete'])->setClass('textCenter');

                        $row->addWebLink('<img title="'.__('Mark Rubric').'" src="./themes/'.$session->get('gibbonThemeName').'/img/rubric.png" style="margin-left:4px;"/>')
                        ->setURL($session->get('absoluteURL').'/fullscreen.php?q=/modules/'.$session->get('module').'/atl_write_rubric.php')
                        ->setClass('thickbox textCenter')
                        ->addParam('gibbonRubricID', $values['gibbonRubricID'])
                        ->addParam('gibbonCourseClassID', $gibbonCourseClassID)
                        ->addParam('gibbonPersonID', $student['gibbonPersonID'])
                        ->addParam('atlColumnID', $atlColumnID)
                        ->addParam('type', 'effort')
                        ->addParam('width', '1100')
                        ->addParam('height', '550');

                        $form->addHiddenValue($count.'-gibbonPersonID', $student['gibbonPersonID']);
                    }

                    $form->addHiddenValue('count', $count);

                    $form->addRow()->addHeading(__('Assessment Complete?'));

                    $row = $form->addRow();
                        $row->addLabel('completeDate', __('Go Live Date'))->prepend('1. ')->append('<br/>'.__('2. Column is hidden until date is reached.'));
                        $row->addDate('completeDate');

                    if (!empty($values['completeDate']) && $values['completeDate'] > date('Y-m-d', strtotime('+1 day'))) {
                        $row = $form->addRow()->addAlert(__m('Your Go Live date is more than 24 hours in the future. If you have completed this ATL, be sure to update your Go Live date to set it to the next school day.'), 'message');
                    }

                    $row = $form->addRow();
                        $row->addSubmit();

                    $form->loadAllValuesFrom($values);

                    echo $form->getOutput();
                }
            }
        }

        //Print sidebar
        $session->set('sidebarExtra', sidebarExtra($guid, $connection2, $gibbonCourseClassID, 'write', $highestAction));
    }
}
