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
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_GET['address'])."/atl_write_data.php&atlColumnID=$atlColumnID&gibbonCourseClassID=$gibbonCourseClassID";

if (isActionAccessible($guid, $connection2, '/modules/ATL/atl_write_data.php') == false) {
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
                $name = $row['name' ];
                $count = $_POST['count'];
                $partialFail = false;

                for ($i = 1;$i <= $count;++$i) {
                    $gibbonPersonIDStudent = $_POST["$i-gibbonPersonID"];
                    //Complete
                    $completeValue = isset($_POST["complete$i"])? $_POST["complete$i"] : 'N';
                    $gibbonPersonIDLastEdit = $session->get('gibbonPersonID');

                    $selectFail = false;
                    try {
                        $data = array('atlColumnID' => $atlColumnID, 'gibbonPersonIDStudent' => $gibbonPersonIDStudent);
                        $sql = 'SELECT * FROM atlEntry WHERE atlColumnID=:atlColumnID AND gibbonPersonIDStudent=:gibbonPersonIDStudent';
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $partialFail = true;
                        $selectFail = true;
                    }
                    if (!($selectFail)) {
                        if ($result->rowCount() < 1) {
                            try {
                                $data = array('atlColumnID' => $atlColumnID, 'gibbonPersonIDStudent' => $gibbonPersonIDStudent, 'complete' => $completeValue, 'gibbonPersonIDLastEdit' => $gibbonPersonIDLastEdit);
                                $sql = 'INSERT INTO atlEntry SET atlColumnID=:atlColumnID, gibbonPersonIDStudent=:gibbonPersonIDStudent, complete=:complete, gibbonPersonIDLastEdit=:gibbonPersonIDLastEdit';
                                $result = $connection2->prepare($sql);
                                $result->execute($data);
                            } catch (PDOException $e) {
                                $partialFail = true;
                            }
                        } else {
                            $row = $result->fetch();
                            //Update
                            try {
                                $data = array('atlColumnID' => $atlColumnID, 'gibbonPersonIDStudent' => $gibbonPersonIDStudent, 'complete' => $completeValue, 'gibbonPersonIDLastEdit' => $gibbonPersonIDLastEdit, 'atlEntryID' => $row['atlEntryID']);
                                $sql = 'UPDATE atlEntry SET atlColumnID=:atlColumnID, gibbonPersonIDStudent=:gibbonPersonIDStudent, complete=:complete, gibbonPersonIDLastEdit=:gibbonPersonIDLastEdit WHERE atlEntryID=:atlEntryID';
                                $result = $connection2->prepare($sql);
                                $result->execute($data);
                            } catch (PDOException $e) {
                                $partialFail = true;
                            }
                        }
                    }
                }

                //Update column
                $completeDate = $_POST['completeDate'];
                if ($completeDate == '') {
                    $completeDate = null;
                    $complete = 'N';
                } else {
                    $completeDate = Format::dateConvert($completeDate);
                    $complete = 'Y';
                }
                try {
                    $data = array('completeDate' => $completeDate, 'complete' => $complete, 'atlColumnID' => $atlColumnID);
                    $sql = 'UPDATE atlColumn SET completeDate=:completeDate, complete=:complete WHERE atlColumnID=:atlColumnID';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $partialFail = true;
                }

                //Return!
                if ($partialFail == true) {
                    //Fail 3
                    $URL .= '&return=error3';
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
