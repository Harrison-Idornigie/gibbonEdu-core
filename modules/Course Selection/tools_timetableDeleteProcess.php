<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

use Gibbon\Domain\System\SettingGateway;
use CourseSelection\Domain\TimetableGateway;

include '../../gibbon.php';

// Module Bootstrap
require 'module.php';

$URL = $session->get('absoluteURL')."/index.php?q=/modules/Course Selection/tools_timetableDelete.php";

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/tools_timetableDelete.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    //Proceed!
    $settingGateway = $container->get(SettingGateway::class);
    $gibbonSchoolYearID = $settingGateway->getSettingByScope('Course Selection', 'activeSchoolYear');
    $gibbonTTID = $_POST['gibbonTTID'] ?? '';

    if (empty($gibbonSchoolYearID) || empty($gibbonTTID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    } else {
        $timetableGateway = $container->get('CourseSelection\Domain\TimetableGateway');

        // First delete any timetabling results
        $deleted = $timetableGateway->deleteAllResultsBySchoolYear($gibbonSchoolYearID);

        $data = array(
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'gibbonTTID'         => $gibbonTTID,
        );

        // Delete all the classes which don't have enrolments for year groups involved in this timetable
        $sql = "DELETE gibbonCourseClass
                FROM gibbonCourseClass 
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID)
                JOIN gibbonYearGroup ON (FIND_IN_SET(gibbonYearGroup.gibbonYearGroupID, gibbonCourse.gibbonYearGroupIDList))
                JOIN gibbonTT ON (FIND_IN_SET(gibbonYearGroup.gibbonYearGroupID, gibbonTT.gibbonYearGroupIDList))
                LEFT JOIN gibbonCourseClassPerson ON (gibbonCourseClass.gibbonCourseClassID=gibbonCourseClassPerson.gibbonCourseClassID)
                WHERE gibbonCourse.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonTT.gibbonTTID=:gibbonTTID
                AND gibbonCourseClassPerson.gibbonCourseClassPersonID IS NULL";

        $pdo->delete($sql, $data);
        $deleted &= $pdo->getQuerySuccess();

        // Delete all the timetable data for this timetable
        $sql = "DELETE gibbonTTDayRowClass 
                FROM gibbonTTDayRowClass JOIN gibbonCourseClass ON (gibbonTTDayRowClass.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID) 
                JOIN gibbonCourse ON (gibbonCourseClass.gibbonCourseID=gibbonCourse.gibbonCourseID) 
                JOIN gibbonTTDay ON (gibbonTTDay.gibbonTTDayID=gibbonTTDayRowClass.gibbonTTDayID)
                JOIN gibbonTT ON (gibbonTT.gibbonTTID=gibbonTTDay.gibbonTTID)
                WHERE gibbonCourse.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonTT.gibbonTTID=:gibbonTTID";

        $pdo->delete($sql, $data);
        $deleted &= $pdo->getQuerySuccess();

        if ($deleted == false) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit;
        } else {
            $URL .= "&return=success0";
            header("Location: {$URL}");
            exit;
        }
    }
}
