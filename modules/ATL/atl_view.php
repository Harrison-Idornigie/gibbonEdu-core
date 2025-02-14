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


use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\System\SettingGateway;

//Module includes
include './modules/'.$session->get('module').'/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/ATL/atl_view.php') == false) {
    //Acess denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Register scripts available to the core, but not included by default
    $page->scripts->add('chart');

    //Get action with highest precendence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        if ($highestAction == 'View ATLs_all') { //ALL STUDENTS
            $page->breadcrumbs->add(__('View All ATLs'));

            $gibbonPersonID = null;
            if (isset($_GET['gibbonPersonID'])) {
                $gibbonPersonID = $_GET['gibbonPersonID'];
            }

            echo '<h3>';
            echo __('Choose A Student');
            echo '</h3>';

            $form = Form::create("filter", $session->get('absoluteURL').'/index.php', 'get', 'noIntBorder w-full');
            $form->setFactory(DatabaseFormFactory::create($pdo));

            $form->addHiddenValue('q', '/modules/ATL/atl_view.php');
            $form->addHiddenValue('address', $session->get('address'));

            $row = $form->addRow();
                $row->addLabel('gibbonPersonID', __('Student'));
                $row->addSelectStudent('gibbonPersonID', $session->get("gibbonSchoolYearID"), array())->selected($gibbonPersonID)->placeholder();

            $row = $form->addRow();
                $row->addSearchSubmit($gibbon->session);
              

            echo $form->getOutput();

            if (!empty($gibbonPersonID)) {
                echo '<h3>';
                echo __('ATLs');
                echo '</h3>';

                //Check for access
                try {
                    $dataCheck = array('gibbonPersonID' => $gibbonPersonID);
                    $sqlCheck = "SELECT DISTINCT gibbonPerson.* FROM gibbonPerson LEFT JOIN gibbonStudentEnrolment ON (gibbonPerson.gibbonPersonID=gibbonStudentEnrolment.gibbonPersonID) WHERE gibbonPerson.gibbonPersonID=:gibbonPersonID AND status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."')";
                    $resultCheck = $connection2->prepare($sqlCheck);
                    $resultCheck->execute($dataCheck);
                } catch (PDOException $e) {
                    echo "<div class='error'>".$e->getMessage().'</div>';
                }

                if ($resultCheck->rowCount() != 1) {
                    echo "<div class='error'>";
                    echo __('The selected record does not exist, or you do not have access to it.');
                    echo '</div>';
                } else {

                    $roleCategory = $session->get('gibbonRoleIDCurrentCategory');
                    if ($roleCategory == 'Staff') {
                        echo Format::alert(__m('As a staff member, your view of this ATL diagram accounts for all current ATL records, including those before their complete date. Parents and students will only see the ATL diagram based on completed data.'), 'message');
                    }

                    echo '<br/>';
                    echo visualiseATL($container, $gibbonPersonID);

                    echo getATLRecord($guid, $connection2, $gibbonPersonID);
                }
            }
        } elseif ($highestAction == 'View ATLs_myChildrens') { //MY CHILDREN
            $page->breadcrumbs->add(__('View My Childrens\'s ATLs'));

            //Test data access field for permission
            try {
                $data = array('gibbonPersonID' => $session->get('gibbonPersonID'));
                $sql = "SELECT * FROM gibbonFamilyAdult WHERE gibbonPersonID=:gibbonPersonID AND childDataAccess='Y'";
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
                echo "<div class='error'>".$e->getMessage().'</div>';
            }

            if ($result->rowCount() < 1) {
                $page->addMessage(__('There are no records to display.'));
            } else {
                //Get child list
                $gibbonPersonID = null;
                $options = array();
                while ($row = $result->fetch()) {
                    try {
                        $dataChild = array('gibbonFamilyID' => $row['gibbonFamilyID'], 'gibbonSchoolYearID' => $session->get('gibbonSchoolYearID'), 'today' => date('Y-m-d'));
                        $sqlChild = "SELECT * FROM gibbonFamilyChild JOIN gibbonPerson ON (gibbonFamilyChild.gibbonPersonID=gibbonPerson.gibbonPersonID) JOIN gibbonStudentEnrolment ON (gibbonPerson.gibbonPersonID=gibbonStudentEnrolment.gibbonPersonID) JOIN gibbonFormGroup ON (gibbonStudentEnrolment.gibbonFormGroupID=gibbonFormGroup.gibbonFormGroupID) WHERE gibbonFamilyID=:gibbonFamilyID AND gibbonPerson.status='Full' AND (dateStart IS NULL OR dateStart<=:today) AND (dateEnd IS NULL  OR dateEnd>=:today) AND gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID ORDER BY surname, preferredName ";
                        $resultChild = $connection2->prepare($sqlChild);
                        $resultChild->execute($dataChild);
                    } catch (PDOException $e) {
                        echo "<div class='error'>".$e->getMessage().'</div>';
                    }
                    while ($rowChild = $resultChild->fetch()) {
                        $options[$rowChild['gibbonPersonID']]=Format::name('', $rowChild['preferredName'], $rowChild['surname'], 'Student', true);
                    }
                }

                if (count($options) == 0) {
                    $page->addMessage(__('There are no records to display.'));
                } elseif (count($options) == 1) {
                    $gibbonPersonID = key($options);
                } else {
                    echo '<h2>';
                    echo __('Choose Student');
                    echo '</h2>';

                    $gibbonPersonID = (isset($_GET['gibbonPersonID']))? $_GET['gibbonPersonID'] : null;

                    $form = Form::create("filter", $session->get('absoluteURL')."/index.php", "get", "noIntBorder w-full standardForm");
                    $form->setFactory(DatabaseFormFactory::create($pdo));

                    $form->addHiddenValue('q', '/modules/ATL/atl_view.php');

                    $row = $form->addRow();
                        $row->addLabel('gibbonPersonID', __('Child'))->description('Choose the child you are registering for.');
                        $row->addSelect('gibbonPersonID')->fromArray($options)->selected($gibbonPersonID);

                    $row = $form->addRow();
                        $row->addSearchSubmit($gibbon->session);

                    echo $form->getOutput();
                }

				$settingGateway = $container->get(SettingGateway::class);
                $showParentAttainmentWarning = $settingGateway->getSettingByScope('Markbook', 'showParentAttainmentWarning');
                $showParentEffortWarning = $settingGateway->getSettingByScope('Markbook', 'showParentEffortWarning');

                if (!empty($gibbonPersonID) and count($options) > 0) {
                    //Confirm access to this student
                    try {
                        $dataChild = array();
                        $sqlChild = "SELECT * FROM gibbonFamilyChild JOIN gibbonFamily ON (gibbonFamilyChild.gibbonFamilyID=gibbonFamily.gibbonFamilyID) JOIN gibbonFamilyAdult ON (gibbonFamilyAdult.gibbonFamilyID=gibbonFamily.gibbonFamilyID) JOIN gibbonPerson ON (gibbonFamilyChild.gibbonPersonID=gibbonPerson.gibbonPersonID) WHERE gibbonPerson.status='Full' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') AND gibbonFamilyChild.gibbonPersonID=$gibbonPersonID AND gibbonFamilyAdult.gibbonPersonID=".$session->get('gibbonPersonID')." AND childDataAccess='Y'";
                        $resultChild = $connection2->prepare($sqlChild);
                        $resultChild->execute($dataChild);
                    } catch (PDOException $e) {
                        echo "<div class='error'>".$e->getMessage().'</div>';
                    }
                    if ($resultChild->rowCount() < 1) {
                        echo "<div class='error'>";
                        echo __('The selected record does not exist, or you do not have access to it.');
                        echo '</div>';
                    } else {
                        $rowChild = $resultChild->fetch();

                        echo '<br/>';
                        echo visualiseATL($container, $gibbonPersonID);

                        echo getATLRecord($guid, $connection2, $gibbonPersonID);
                    }
                }
            }
        } else { //My ATLS
            $page->breadcrumbs->add(__('View My ATLs'));

            echo '<h3>';
            echo __('ATLs');
            echo '</h3>';

            echo visualiseATL($container, $session->get('gibbonPersonID'));

            echo getATLRecord($guid, $connection2, $session->get('gibbonPersonID'));
        }
    }
}
