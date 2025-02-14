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

include '../../gibbon.php';

$gibbonCourseClassID = $_GET['gibbonCourseClassID'] ?? '';
$atlColumnID = $_GET['atlColumnID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_GET['address'])."/atl_manage_edit.php&atlColumnID=$atlColumnID&gibbonCourseClassID=$gibbonCourseClassID";

if (isActionAccessible($guid, $connection2, '/modules/ATL/atl_manage_edit.php') == false) {
    //Fail 0
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    $highestAction = getHighestGroupedAction($guid, $_GET['address'], $connection2);
    if ($highestAction == false) {
        //Fail 0
        $URL .= '&return=error0';
        header("Location: {$URL}");
    } else {
        if (empty($_POST)) {
            $URL .= '&return=error5';
            header("Location: {$URL}");
        } else {
            //Proceed!
            //Check if school year specified
            if ($atlColumnID == '' or $gibbonCourseClassID == '') {
                //Fail1
                $URL .= '&return=error1';
                header("Location: {$URL}");
            } else {
                try {
                    $data = array('atlColumnID' => $atlColumnID, 'gibbonCourseClassID' => $gibbonCourseClassID);
                    $sql = 'SELECT * FROM atlColumn WHERE atlColumnID=:atlColumnID AND gibbonCourseClassID=:gibbonCourseClassID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    //Fail2
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                if ($result->rowCount() != 1) {
                    //Fail 2
                    $URL .= '&return=error2';
                    header("Location: {$URL}");
                } else {
                    $row = $result->fetch();

                    //Validate Inputs
                    $name = $_POST['name'] ?? '';
                    $description = $_POST['description'] ?? '';
                    $gibbonRubricID = $_POST['gibbonRubricID'] ?? '';
                    $completeDate = $_POST['completeDate'];
                    if ($completeDate == '') {
                        $completeDate = null;
                        $complete = 'N';
                    } else {
                        $completeDate = Format::dateConvert($completeDate);
                        $complete = 'Y';
                    }
                    $gibbonPersonIDLastEdit = $session->get('gibbonPersonID');
                    $groupingID = $row['groupingID'];

                    if ($name == '' or $description == '') {
                        //Fail 3
                        $URL .= '&return=error3';
                        header("Location: {$URL}");
                    } else {
                        //ATTEMPT TO UPDATE LINKED COLUMNS
                        $partialFail = false;
                        if (isset($_POST['gibbonCourseClassID'])) {
                            if (is_array($_POST['gibbonCourseClassID'])) {
                                $gibbonCourseClassIDs = $_POST['gibbonCourseClassID'];
                                foreach ($gibbonCourseClassIDs as $gibbonCourseClassID2) {
                                    //Write to database
                                    try {
                                        $data = array('name' => $name, 'description' => $description, 'gibbonRubricID' => $gibbonRubricID, 'completeDate' => $completeDate, 'complete' => $complete, 'gibbonPersonIDLastEdit' => $session->get('gibbonPersonID'), 'groupingID' => $groupingID, 'gibbonCourseClassID' => $gibbonCourseClassID2);
                                        $sql = 'UPDATE atlColumn SET name=:name, description=:description, gibbonRubricID=:gibbonRubricID, completeDate=:completeDate, complete=:complete, gibbonPersonIDLastEdit=:gibbonPersonIDLastEdit WHERE groupingID=:groupingID AND gibbonCourseClassID=:gibbonCourseClassID';
                                        $result = $connection2->prepare($sql);
                                        $result->execute($data);
                                    } catch (PDOException $e) {
                                        $partialFail = true;
                                        echo $e->getMessage();
                                    }

                                }
                            }
                        }

                        //Write to database
                        try {
                            $data = array('gibbonCourseClassID' => $gibbonCourseClassID, 'name' => $name, 'description' => $description, 'gibbonRubricID' => $gibbonRubricID, 'completeDate' => $completeDate, 'complete' => $complete, 'gibbonPersonIDLastEdit' => $gibbonPersonIDLastEdit, 'atlColumnID' => $atlColumnID);
                            $sql = 'UPDATE atlColumn SET gibbonCourseClassID=:gibbonCourseClassID, name=:name, description=:description, gibbonRubricID=:gibbonRubricID, completeDate=:completeDate, complete=:complete, gibbonPersonIDLastEdit=:gibbonPersonIDLastEdit WHERE atlColumnID=:atlColumnID';
                            $result = $connection2->prepare($sql);
                            $result->execute($data);
                        } catch (PDOException $e) {
                            //Fail 2
                            $URL .= '&return=error2';
                            header("Location: {$URL}");
                            exit();
                        }

                        if ($partialFail == true) {
                            //Fail 6
                            $URL .= '&return=warning1';
                            header("Location: {$URL}");
                        } else {
                            //Success 0
                            $URL .= '&return=success0';
                            header("Location: {$URL}");
                        }
                    }
                }
            }
        }
    }
}
