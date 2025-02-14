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
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_GET['address'])."/atl_manage_add.php&gibbonCourseClassID=$gibbonCourseClassID";

if (isActionAccessible($guid, $connection2, '/modules/ATL/atl_manage_add.php') == false) {
    //Fail 0
    $URL .= '&return=error0';
    header("Location: {$URL}");
} else {
    if (empty($_POST)) {
        $URL .= '&return=error5';
        header("Location: {$URL}");
    } else {
        //Proceed!
        //Validate Inputs
        $gibbonCourseClassIDMulti = null;
        if (isset($_POST['gibbonCourseClassIDMulti'])) {
            $gibbonCourseClassIDMulti = $_POST['gibbonCourseClassIDMulti'];
            $gibbonCourseClassIDMulti = array_unique($gibbonCourseClassIDMulti);
        }
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $gibbonRubricID = $_POST['gibbonRubricID'] ?? '';
        $completeDate = $_POST['completeDate'] ?? '';
        if ($completeDate == '') {
            $completeDate = null;
            $complete = 'N';
        } else {
            $completeDate = Format::dateConvert($completeDate);
            $complete = 'Y';
        }
        $gibbonPersonIDCreator = $session->get('gibbonPersonID');
        $gibbonPersonIDLastEdit = $session->get('gibbonPersonID');

        //Lock markbook column table
        try {
            $sqlLock = 'LOCK TABLES atlColumn WRITE';
            $resultLock = $connection2->query($sqlLock);
        } catch (PDOException $e) {
            //Fail 2
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }

        //Get next groupingID
        try {
            $sqlGrouping = 'SELECT DISTINCT groupingID FROM atlColumn WHERE NOT groupingID IS NULL ORDER BY groupingID DESC';
            $resultGrouping = $connection2->query($sqlGrouping);
        } catch (PDOException $e) {
            //Fail 2
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }

        $rowGrouping = $resultGrouping->fetch();
        if (is_null($rowGrouping['groupingID'])) {
            $groupingID = 1;
        } else {
            $groupingID = ($rowGrouping['groupingID'] + 1);
        }

        if (is_array($gibbonCourseClassIDMulti) == false or is_numeric($groupingID) == false or $groupingID < 1 or $name == '' or $description == '') {
            //Fail 3
            $URL .= '&return=error3';
            header("Location: {$URL}");
        } else {
            $partialFail = false;

            foreach ($gibbonCourseClassIDMulti as $gibbonCourseClassIDSingle) {
                //Write to database
                try {
                    $data = array('groupingID' => $groupingID, 'gibbonCourseClassID' => $gibbonCourseClassIDSingle, 'name' => $name, 'description' => $description, 'gibbonRubricID' => $gibbonRubricID, 'completeDate' => $completeDate, 'complete' => $complete, 'gibbonPersonIDCreator' => $gibbonPersonIDCreator, 'gibbonPersonIDLastEdit' => $gibbonPersonIDLastEdit);
                    $sql = 'INSERT INTO atlColumn SET groupingID=:groupingID, gibbonCourseClassID=:gibbonCourseClassID, name=:name, description=:description, gibbonRubricID=:gibbonRubricID, completeDate=:completeDate, complete=:complete, gibbonPersonIDCreator=:gibbonPersonIDCreator, gibbonPersonIDLastEdit=:gibbonPersonIDLastEdit';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    //Fail 2
                    $partialFail = true;
                }
            }

            //Unlock module table
            try {
                $sql = 'UNLOCK TABLES';
                $result = $connection2->query($sql);
            } catch (PDOException $e) {
            }

            if ($partialFail != false) {
                //Success 0
                $URL .= '&return=error6';
                header("Location: {$URL}");
            } else {
                //Success 0
                $URL .= '&return=success0';
                header("Location: {$URL}");
            }
        }
    }
}
