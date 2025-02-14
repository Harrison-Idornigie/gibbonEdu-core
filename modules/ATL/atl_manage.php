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

use Gibbon\Services\Format;
use Gibbon\Module\ATL\Domain\ATLColumnGateway;
use Gibbon\Domain\DataSet;
use Gibbon\Tables\DataTable;

//Module includes
include './modules/'.$session->get('module').'/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/ATL/atl_manage.php') == false) {
    //Acess denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Get action with highest precendence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        //Get class variable
        $gibbonCourseClassID = null;
        if (isset($_GET['gibbonCourseClassID'])) {
            $gibbonCourseClassID = $_GET['gibbonCourseClassID'];
        } else {
            try {
                $data = array('gibbonPersonID' => $session->get('gibbonPersonID'));
                $sql = "SELECT gibbonCourse.nameShort AS course, gibbonCourseClass.nameShort AS class, gibbonCourseClass.gibbonCourseClassID FROM gibbonCourse, gibbonCourseClass, gibbonCourseClassPerson WHERE gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID AND gibbonCourseClass.gibbonCourseClassID=gibbonCourseClassPerson.gibbonCourseClassID AND gibbonCourseClassPerson.gibbonPersonID=:gibbonPersonID AND gibbonCourse.gibbonSchoolYearID=(SELECT gibbonSchoolYearID FROM gibbonSchoolYear WHERE status='Current') AND gibbonCourseClass.reportable='Y' ORDER BY course, class";
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                echo "<div class='error'>".$e->getMessage().'</div>';
            }
            if ($result->rowCount() > 0) {
                $row = $result->fetch();
                $gibbonCourseClassID = $row['gibbonCourseClassID'];
            }
        }
        if ($gibbonCourseClassID == '') {
            echo '<h1>';
            echo 'Manage ATL';
            echo '</h1>';
            echo "<div class='warning'>";
            echo __('Use the class listing on the right to choose a ATL to edit.');
            echo '</div>';
        } else {
            //Check existence of and access to this class.
            try {
                $data = array('gibbonCourseClassID' => $gibbonCourseClassID);
                $sql = 'SELECT gibbonCourse.nameShort AS course, gibbonCourseClass.nameShort AS class, gibbonCourseClass.gibbonCourseClassID FROM gibbonCourse, gibbonCourseClass WHERE gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID AND gibbonCourseClass.gibbonCourseClassID=:gibbonCourseClassID ORDER BY course, class';
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                echo "<div class='error'>".$e->getMessage().'</div>';
            }

            if ($result->rowCount() != 1) {
                echo '<h1>';
                echo __('Manage ATL');
                echo '</h1>';
                echo "<div class='error'>";
                echo __('The selected record does not exist, or you do not have access to it.');
                echo '</div>';
            } else {
                $row = $result->fetch();
                $page->breadcrumbs->add(__('Manage {courseClass} ATLs', ['courseClass' => $row['course'].'.'.$row['class']]));

                if (isset($_GET['deleteReturn'])) {
                    $deleteReturn = $_GET['deleteReturn'];
                } else {
                    $deleteReturn = '';
                }
                $deleteReturnMessage = '';
                $class = 'error';
                if (!($deleteReturn == '')) {
                    if ($deleteReturn == 'success0') {
                        $deleteReturnMessage = __('Your request was completed successfully.');
                        $class = 'success';
                    }
                    echo "<div class='$class'>";
                    echo $deleteReturnMessage;
                    echo '</div>';
                }

                //Get teacher list
                $teaching = false;
                try {
                    $data = array('gibbonCourseClassID' => $gibbonCourseClassID);
                    $sql = "SELECT gibbonPerson.gibbonPersonID, title, surname, preferredName, gibbonCourseClassPerson.reportable FROM gibbonCourseClassPerson JOIN gibbonPerson ON (gibbonCourseClassPerson.gibbonPersonID=gibbonPerson.gibbonPersonID) WHERE (role='Teacher' OR role='Assistant') AND gibbonCourseClassID=:gibbonCourseClassID ORDER BY surname, preferredName";
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    echo "<div class='error'>".$e->getMessage().'</div>';
                }
                if ($result->rowCount() > 0) {
                    echo '<h3>';
                    echo __('Teachers');
                    echo '</h3>';
                    echo '<ul>';
                    while ($row = $result->fetch()) {
                        if ($row['reportable'] != 'Y') continue;

                        echo '<li>'.Format::name($row['title'], $row['preferredName'], $row['surname'], 'Staff').'</li>';
                        if ($row['gibbonPersonID'] == $session->get('gibbonPersonID')) {
                            $teaching = true;
                        }
                    }
                    echo '</ul>';
                }
                //TABLE
                $atlColumnGateway = $container->get(ATLColumnGateway::class);
                $atlColumnData = $atlColumnGateway->selectBy(['gibbonCourseClassID' => $gibbonCourseClassID])->fetchAll();

                $table = DataTable::create('atlColumns');
                $table->setTitle('ATL Columns');

                $table->addHeaderAction('addMultiple', __('Add Multiple Columns'))
                    ->setURL('/modules/ATL/atl_manage_add.php')
                    ->addParam('gibbonCourseClassID', $gibbonCourseClassID)
                    ->displayLabel();

                $table->addColumn('name', __('Name'));
                $table->addColumn('completeDate', __('Date Complete'));
                $table->addActionColumn()
                    ->addParam('gibbonCourseClassID', $gibbonCourseClassID)
                    ->addParam('atlColumnID')
                    ->format(function ($row, $actions) use ($session) {
                        $actions->addAction('edit', __('Edit'))
                                ->setURL('/modules/' . $session->get('module') . '/atl_manage_edit.php');

                        $actions->addAction('delete', __('Delete'))
                                ->setURL('/modules/' . $session->get('module') . '/atl_manage_delete.php');

                        $actions->addAction('enterData', __('Enter Data'))
                                ->setURL('/modules/' . $session->get('module') . '/atl_write_data.php')
                                ->setIcon('markbook');

                    });

                echo $table->render($atlColumnData);
            }
        }
    }

    //Print sidebar
    $session->set('sidebarExtra', sidebarExtra($guid, $connection2, $gibbonCourseClassID, 'manage', $highestAction));
}
