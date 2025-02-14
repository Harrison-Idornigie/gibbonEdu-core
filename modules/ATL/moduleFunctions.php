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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Contracts\Database\Connection;
use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Module\ATL\VisualiseATL;
use Gibbon\Module\Rubrics\Visualise;
use Gibbon\Domain\Rubrics\RubricGateway;
use Gibbon\Module\ATL\Domain\ATLColumnGateway;
use Gibbon\Contracts\Services\Session;

function getATLRecord($guid, $connection2, $gibbonPersonID) {
    global $session, $container;

    $roleCategory = $session->get('gibbonRoleIDCurrentCategory');

    require_once $session->get('absolutePath').'/modules/ATL/src/Domain/ATLColumnGateway.php';

    $atlColumnGateway = $container->get(ATLColumnGateway::class);

    $output = '';

    //Get school years in reverse order
    try {
        $dataYears = array('gibbonPersonID' => $gibbonPersonID);
        $sqlYears = "SELECT * FROM gibbonSchoolYear JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonSchoolYearID=gibbonSchoolYear.gibbonSchoolYearID) WHERE (status='Current' OR status='Past') AND gibbonPersonID=:gibbonPersonID ORDER BY sequenceNumber DESC";
        $resultYears = $connection2->prepare($sqlYears);
        $resultYears->execute($dataYears);
    } catch (PDOException $e) {
        $output .= "<div class='error'>".$e->getMessage().'</div>';
    }

    if ($resultYears->rowCount() < 1) {
        $output .= "<div class='error'>";
        $output .= __('There are no records to display.');
        $output .= '</div>';
    } else {
        $results = false;
        while ($rowYears = $resultYears->fetch()) {
            //Get and output ATLs
            $entries = $atlColumnGateway->selectATLEntriesByStudent($rowYears['gibbonSchoolYearID'], $gibbonPersonID, $roleCategory)->fetchAll();
            $currentDate = date('Y-m-d');

            if (!empty($entries)) {
                $results = true;
                $output .= '<h4>';
                $output .= $rowYears['name'];
                $output .= '</h4>';
                $output .= "<table cellspacing='0' style='width: 100%'>";
                $output .= "<tr class='head'>";
                $output .= "<th style='width: 350px'>";
                $output .= 'Assessment';
                $output .= '</th>';
                $output .= '</th>';
                $output .= "<th style='text-align: center'>";
                $output .= __('Rubric');
                $output .= '</th>';
                $output .= '</tr>';

                $count = 0;
                foreach ($entries as $rowATL) {
                    if ($count % 2 == 0) {
                        $rowNum = 'even';
                    } else {
                        $rowNum = 'odd';
                    }
                    ++$count;

                    if (!empty($rowATL['completeDate']) && $currentDate < $rowATL['completeDate']) {
                        $rowNum .= ' dull';
                        if ($roleCategory != 'Staff') {
                            continue;
                        }
                    }

                    $output .= "<tr class='$rowNum'>";
                    $output .= '<td>';
                    $output .= "<span title='".htmlPrep($rowATL['description'])."'><b><u>".$rowATL['course'].'<br/>'.$rowATL['name'].'</u></b></span><br/>';
                    $output .= "<span style='font-size: 90%; font-style: italic; font-weight: normal'>";
                    
                    if ($rowATL['completeDate'] != '') {
                        $output .= 'Marked on '.Format::date($rowATL['completeDate']);
                    } else {
                        $output .= 'Unmarked';
                    }

                    if (!empty($rowATL['completeDate']) && $currentDate < $rowATL['completeDate']) {
                        $output .= Format::tag(__m('Unpublished'), 'text-xxs bg-gray-400 ml-2');
                    }
                    
                    $output .= '</span><br/>';
                    $output .= '</td>';
                    if ($rowATL['gibbonRubricID'] == '') {
                        $output .= "<td class='dull' style='color: #bbb; text-align: center'>";
                        $output .= __('N/A');
                        $output .= '</td>';
                    } else {
                        $output .= "<td style='text-align: center'>";
                        $output .= "<a class='thickbox' href='".$session->get('absoluteURL').'/fullscreen.php?q=/modules/ATL/atl_view_rubric.php&gibbonRubricID='.$rowATL['gibbonRubricID'].'&gibbonCourseClassID='.$rowATL['gibbonCourseClassID'].'&atlColumnID='.$rowATL['atlColumnID']."&gibbonPersonID=$gibbonPersonID&mark=FALSE&type=effort&width=1100&height=550'><img style='margin-bottom: -3px; margin-left: 3px' title='View Rubric' src='./themes/".$session->get('gibbonThemeName')."/img/rubric.png'/></a>";
                        $output .= '</td>';
                    }

                    $output .= '</tr>';
                }

                $output .= '</table>';
            }
        }
        if ($results == false) {
            $output .= "<div class='error'>";
            $output .= __('There are no records to display.');
            $output .= '</div>';
        }
    }

    return $output;
}

function sidebarExtra($guid, $connection2, $gibbonCourseClassID, $mode = 'manage', $highestAction = '') {
    global $pdo, $session;

    $output = '';

    $output .= '<div class="column-no-break">';
    $output .= '<h2>';
    $output .= __('View Classes');
    $output .= '</h2>';

    $selectCount = 0;

    $form = Form::create('classSelect', $session->get('absoluteURL').'/index.php', 'get');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('q', '/modules/'.$session->get('module').'/'.($mode == 'write'? 'atl_write.php' : 'atl_manage.php'));
    $form->setClass('smallIntBorder w-full');

    $row = $form->addRow();
        $row->addSelectClass('gibbonCourseClassID', $session->get('gibbonSchoolYearID'), $session->get('gibbonPersonID'))
            ->selected($gibbonCourseClassID)
            ->placeholder()
            ->setClass('w-full');
        $row->addSubmit(__('Go'));

    $output .= $form->getOutput();
    $output .= '</div>';

    return $output;
}

function visualiseATL($container, $gibbonPersonID) {
    $session = $container->get(Session::class);
    $pdo = $container->get(Connection::class);

    $roleCategory = $session->get('gibbonRoleIDCurrentCategory');

    require_once $session->get('absolutePath').'/modules/ATL/src/Domain/ATLColumnGateway.php';

    // Display the visualization of all ATLs
    $contextDBTable = 'atlColumn';
    $contextDBTableID = $gibbonPersonID;
    $contextDBTableIDField = 'atlColumnID';
    $contextDBTableGibbonRubricIDField = 'gibbonRubricID';
    $contextDBTableNameField = 'name';
    $contextDBTableDateField = 'completeDate';

    $rubricGateway = $container->get(RubricGateway::class);
    $studentRubricInfo = $container->get(ATLColumnGateway::class)->getATLRubricByStudent($session->get('gibbonSchoolYearID'), $gibbonPersonID, $roleCategory);
    $gibbonRubricID = $studentRubricInfo['gibbonRubricID'] ?? '';
    $rubric = $rubricGateway->getByID($gibbonRubricID);

    if ($gibbonRubricID && $rubric) {
        // Get rows, columns and cells
        $rows = $rubricGateway->selectRowsByRubric($gibbonRubricID)->fetchAll();
        $columns = $rubricGateway->selectColumnsByRubric($gibbonRubricID)->fetchAll();
        $gradeScales = $rubricGateway->selectGradeScalesByRubric($gibbonRubricID)->fetchGroupedUnique();

        if (empty($rows) or empty($columns)) {
            $col->addAlert(__('The rubric is missing data and cannot be drawn.'));
        } else {
            $cells = [];
            $resultCells = $rubricGateway->selectCellsByRubric($gibbonRubricID);
            while ($rowCells = $resultCells->fetch()) {
                $cells[$rowCells['gibbonRubricRowID']][$rowCells['gibbonRubricColumnID']] = $rowCells;
            }

            // Get other uses of this rubric in this context, and store for use in visualisation
            $contexts = [];

            $dataContext = array('gibbonPersonID' => $gibbonPersonID, 'gibbonSchoolYearID' => $session->get('gibbonSchoolYearID'));
            $sqlContext = "SELECT gibbonRubricEntry.*, $contextDBTable.*, gibbonRubricEntry.*, gibbonRubricCell.*, gibbonCourse.nameShort AS course, gibbonCourseClass.nameShort AS class
                FROM gibbonRubricEntry
                JOIN $contextDBTable ON (gibbonRubricEntry.contextDBTableID=$contextDBTable.$contextDBTableIDField
                    AND gibbonRubricEntry.gibbonRubricID=$contextDBTable.$contextDBTableGibbonRubricIDField)
                JOIN gibbonRubricCell ON (gibbonRubricEntry.gibbonRubricCellID=gibbonRubricCell.gibbonRubricCellID)
                JOIN gibbonCourseClass ON ($contextDBTable.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID)
                JOIN gibbonCourse ON (gibbonCourseClass.gibbonCourseID=gibbonCourse.gibbonCourseID)
                WHERE contextDBTable='$contextDBTable'
                AND gibbonRubricEntry.gibbonPersonID=:gibbonPersonID
                AND gibbonSchoolYearID=:gibbonSchoolYearID
                AND NOT $contextDBTableDateField IS NULL ";

            if ($roleCategory != 'Staff') {
                $sqlContext .= " AND CURRENT_DATE >= $contextDBTable.$contextDBTableDateField ";
            }

            $sqlContext .= " ORDER BY $contextDBTableDateField DESC";

            $resultContext = $pdo->select($sqlContext, $dataContext);

            if ($resultContext->rowCount() > 0) {
                while ($rowContext = $resultContext->fetch()) {
                    $context = $rowContext['course'].'.'.$rowContext['class'].' - '.$rowContext[$contextDBTableNameField].' ('.Format::date($rowContext[$contextDBTableDateField]).')';
                    $cells[$rowContext['gibbonRubricRowID']][$rowContext['gibbonRubricColumnID']]['context'][] = $context;

                    $contexts[] = [
                        'gibbonRubricEntry' => $rowContext['gibbonRubricEntry'],
                        'gibbonRubricID' => $rowContext['gibbonRubricID'],
                        'gibbonPersonID' => $rowContext['gibbonPersonID'],
                        'gibbonRubricCellID' => $rowContext['gibbonRubricCellID'],
                        'contextDBTable' => $rowContext['contextDBTable'],
                        'contextDBTableID' => $rowContext['contextDBTableID']
                    ];
                }
            }
            
        }

        if (!empty($contexts) && !empty($columns) && !empty($rows) && !empty($cells)) {
            require_once $session->get('absolutePath').'/modules/Rubrics/src/Visualise.php';
            require_once $session->get('absolutePath').'/modules/ATL/src/VisualiseATL.php';

            if (intval($gibbonRubricID) >= 318) {
                $visualise = new VisualiseATL($session->get('absoluteURL'), $container->get('page'), $gibbonPersonID.'All', $columns, $rows, $cells, $contexts);
            } else {
                $visualise = new Visualise($session->get('absoluteURL'), $container->get('page'), $gibbonPersonID.'All', $columns, $rows, $cells, $contexts);
            }

            return $visualise->renderVisualise();
        }
        
        return '';
    }
}
