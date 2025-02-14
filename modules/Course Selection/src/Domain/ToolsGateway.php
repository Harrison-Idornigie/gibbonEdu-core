<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

namespace CourseSelection\Domain;

use Gibbon\Contracts\Database\Connection;

/**
 * Course Selection: Tools Gateway
 *
 * @version v14
 * @since   17th April 2017
 * @author  Sandra Kuipers
 *
 * @uses  courseSelectionOffering
 * @uses  courseSelectionOfferingBlock
 * @uses  courseSelectionBlockCourse
 * @uses  gibbonSchoolYear
 * @uses  gibbonYearGroup
 * @uses  gibbonStudentEnrolment
 * @uses  gibbonPerson
 * @uses  gibbonCourse
 * @uses  gibbonCourseClass
 * @uses  gibbonCourseClassPerson
 */
class ToolsGateway
{
    protected $pdo;

    public function __construct(Connection $pdo)
    {
        $this->pdo = $pdo;
    }

    public function selectSchoolYear($gibbonSchoolYearID)
    {
        $data = array('gibbonSchoolYearID' => $gibbonSchoolYearID);
        $sql = "SELECT gibbonSchoolYearID, name FROM gibbonSchoolYear WHERE gibbonSchoolYearID=:gibbonSchoolYearID";

        return $this->pdo->executeQuery($data, $sql);
    }

    public function selectTimetablesBySchoolYear($gibbonSchoolYearID)
    {
        $data = array('gibbonSchoolYearID' => $gibbonSchoolYearID);
        $sql = "SELECT gibbonTTID as value, name
                FROM gibbonTT
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID
                ORDER BY name";

        return $this->pdo->executeQuery($data, $sql);
    }

    public function selectAllCoursesBySchoolYear($gibbonSchoolYearID, $grouped = true)
    {
        $data = array('gibbonSchoolYearID' => $gibbonSchoolYearID);
        if ($grouped) {
            $sql = "SELECT gibbonYearGroup.name as grouping, gibbonCourse.gibbonCourseID as value, CONCAT(gibbonCourse.nameShort, ' - ', gibbonCourse.name) as name
                FROM gibbonCourse
                JOIN gibbonYearGroup ON (FIND_IN_SET(gibbonYearGroup.gibbonYearGroupID, gibbonCourse.gibbonYearGroupIDList))
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID
                ORDER BY gibbonYearGroup.sequenceNumber DESC, gibbonCourse.nameShort, gibbonCourse.name";
        } else {
            $sql = "SELECT gibbonCourse.gibbonCourseID as value, CONCAT(gibbonCourse.nameShort, ' - ', gibbonCourse.name) as name
                FROM gibbonCourse
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID
                ORDER BY gibbonCourse.nameShort, gibbonCourse.name";
        }

        return $this->pdo->executeQuery($data, $sql);
    }

    public function selectAllCourseClassesBySchoolYear($gibbonSchoolYearID)
    {
        $data = array('gibbonSchoolYearID' => $gibbonSchoolYearID);
        $sql = "SELECT gibbonCourse.gibbonCourseID as gibbonCourseID, gibbonCourseClass.gibbonCourseClassID as value, CONCAT(gibbonCourse.nameShort, '.', gibbonCourseClass.nameShort) as name, (SELECT gibbonTTDay.gibbonTTID FROM gibbonTTDayRowClass JOIN gibbonTTDay ON (gibbonTTDay.gibbonTTDayID=gibbonTTDayRowClass.gibbonTTDayID) WHERE gibbonTTDayRowClass.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID GROUP BY gibbonTTDayRowClass.gibbonCourseClassID LIMIT 1) as gibbonTTID
                FROM gibbonCourse
                JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseID=gibbonCourse.gibbonCourseID)
                WHERE gibbonSchoolYearID=:gibbonSchoolYearID
                ORDER BY gibbonCourse.nameShort, gibbonCourseClass.nameShort";

        return $this->pdo->executeQuery($data, $sql);
    }

    public function selectCoursesOfferedBySchoolYear($gibbonSchoolYearID)
    {
        $data = array('gibbonSchoolYearID' => $gibbonSchoolYearID);
        $sql = "SELECT gibbonCourse.gibbonCourseID as value, CONCAT(gibbonCourse.nameShort, ' - ', gibbonCourse.name) as name
                FROM courseSelectionOffering
                JOIN courseSelectionOfferingBlock ON (courseSelectionOfferingBlock.courseSelectionOfferingID=courseSelectionOffering.courseSelectionOfferingID)
                JOIN courseSelectionBlockCourse ON (courseSelectionBlockCourse.courseSelectionBlockID=courseSelectionOfferingBlock.courseSelectionBlockID)
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=courseSelectionBlockCourse.gibbonCourseID)
                WHERE courseSelectionOffering.gibbonSchoolYearID=:gibbonSchoolYearID
                GROUP BY gibbonCourse.gibbonCourseID
                ORDER BY gibbonCourse.nameShort";

        return $this->pdo->executeQuery($data, $sql);
    }

    public function selectClassesByCourse($gibbonCourseID)
    {
        $data = array('gibbonCourseID' => $gibbonCourseID);
        $sql = "SELECT gibbonCourseClass.gibbonCourseClassID as value, CONCAT(gibbonCourse.nameShort, '.', gibbonCourseClass.nameShort) as name
                FROM gibbonCourseClass
                JOIN gibbonCourse ON (gibbonCourseClass.gibbonCourseID=gibbonCourse.gibbonCourseID)
                WHERE gibbonCourseClass.gibbonCourseID=:gibbonCourseID
                ORDER BY gibbonCourse.nameShort, gibbonCourseClass.nameShort";

        return $this->pdo->executeQuery($data, $sql);
    }

    public function selectStudentsByCourse($gibbonCourseID)
    {
        $data = array('gibbonCourseID' => $gibbonCourseID);
        $sql = "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.surname, gibbonPerson.preferredName, gibbonYearGroup.name as yearGroupName, CONCAT(gibbonCourse.nameShort, '.',gibbonCourseClass.nameShort) as courseClassName
                FROM gibbonPerson
                JOIN gibbonCourseClassPerson ON (gibbonCourseClassPerson.gibbonPersonID=gibbonPerson.gibbonPersonID)
                JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseClassID=gibbonCourseClassPerson.gibbonCourseClassID)
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID)
                JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID AND gibbonStudentEnrolment.gibbonSchoolYearID=gibbonCourse.gibbonSchoolYearID)
                JOIN gibbonYearGroup ON (gibbonYearGroup.gibbonYearGroupID=gibbonStudentEnrolment.gibbonYearGroupID)
                WHERE (gibbonPerson.gibbonPersonID=gibbonCourseClassPerson.gibbonPersonID)
                AND gibbonCourse.gibbonCourseID=:gibbonCourseID
                AND (gibbonPerson.status='Full' OR gibbonPerson.status='Expected')
                AND gibbonCourseClassPerson.role='Student'
                GROUP BY gibbonPerson.gibbonPersonID
                ORDER BY gibbonCourseClassPerson.role DESC, gibbonPerson.surname, gibbonPerson.preferredName";

        return $this->pdo->executeQuery($data, $sql);
    }

    public function selectStudentsByCourseSelection($gibbonCourseID)
    {
        $data = array('gibbonCourseID' => $gibbonCourseID);
        $sql = "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.surname, gibbonPerson.preferredName, gibbonYearGroup.name as yearGroupName, (CASE WHEN courseSelectionApproval.courseSelectionChoiceID > 0 THEN 'Approved' ELSE courseSelectionChoice.status END) as courseClassName
                FROM gibbonPerson
                JOIN courseSelectionChoice ON (courseSelectionChoice.gibbonPersonIDStudent=gibbonPerson.gibbonPersonID)
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=courseSelectionChoice.gibbonCourseID)
                JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID)
                JOIN gibbonYearGroup ON (gibbonYearGroup.gibbonYearGroupID=gibbonStudentEnrolment.gibbonYearGroupID)
                LEFT JOIN courseSelectionApproval ON (courseSelectionApproval.courseSelectionChoiceID=courseSelectionChoice.courseSelectionChoiceID)
                WHERE courseSelectionChoice.gibbonCourseID=:gibbonCourseID
                AND gibbonStudentEnrolment.gibbonSchoolYearID=(SELECT gibbonSchoolYearID FROM gibbonSchoolYear WHERE status='Current')
                AND (gibbonPerson.status='Full' OR gibbonPerson.status='Expected')
                AND courseSelectionChoice.status != 'Removed'
                GROUP BY gibbonPerson.gibbonPersonID
                ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

        return $this->pdo->executeQuery($data, $sql);
    }

    public function selectCourseClass($gibbonCourseClassID)
    {
        $data = array('gibbonCourseClassID' => $gibbonCourseClassID);
        $sql = "SELECT gibbonCourse.name as courseName, gibbonCourse.nameShort as courseNameShort, gibbonCourseClass.name as className, gibbonCourseClass.nameShort as classNameShort, gibbonCourseClass.*
                FROM gibbonCourseClass
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID)
                WHERE gibbonCourseClass.gibbonCourseClassID=:gibbonCourseClassID";

        return $this->pdo->executeQuery($data, $sql);
    }

    public function selectTimetableDaysByClass($gibbonCourseClassID, $gibbonTTID)
    {
        $data = array('gibbonCourseClassID' => $gibbonCourseClassID, 'gibbonTTID' => $gibbonTTID);
        $sql = "SELECT gibbonTTDayRowClass.gibbonTTDayRowClassID, gibbonCourse.name as courseName, gibbonCourse.nameShort as courseNameShort, gibbonCourseClass.name as className, gibbonTTColumnRow.name as columnName, gibbonTTDay.name as dayName, gibbonTT.name as ttName, gibbonSpace.name as spaceName
                FROM gibbonCourseClass
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID)
                JOIN gibbonTTDayRowClass ON (gibbonTTDayRowClass.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID)
                JOIN gibbonTTColumnRow ON (gibbonTTColumnRow.gibbonTTColumnRowID=gibbonTTDayRowClass.gibbonTTColumnRowID)
                JOIN gibbonTTDay ON (gibbonTTDay.gibbonTTDayID=gibbonTTDayRowClass.gibbonTTDayID)
                JOIN gibbonTT ON (gibbonTT.gibbonTTID=gibbonTTDay.gibbonTTID)
                LEFT JOIN gibbonSpace ON (gibbonSpace.gibbonSpaceID=gibbonTTDayRowClass.gibbonSpaceID)
                WHERE gibbonTT.gibbonTTID=:gibbonTTID
                AND gibbonCourseClass.gibbonCourseClassID=:gibbonCourseClassID
                ORDER BY gibbonTTDay.name, gibbonTTColumnRow.name";

        return $this->pdo->executeQuery($data, $sql);
    }


    public function selectTimetableDaysByTimetable($gibbonTTID)
    {
        $data = array('gibbonTTID' => $gibbonTTID);
        $sql = "SELECT gibbonTTDayID as value, name
                FROM gibbonTTDay
                WHERE gibbonTTDay.gibbonTTID=:gibbonTTID
                ORDER BY gibbonTTDay.name
        ";

        return $this->pdo->executeQuery($data, $sql);
    }

    public function selectTimetableColumnsByTimetable($gibbonTTID)
    {
        $data = array('gibbonTTID' => $gibbonTTID);
        $sql = "SELECT CONCAT(gibbonTTColumnRow.gibbonTTColumnRowID, '-', gibbonTTDay.gibbonTTDayID) as value, gibbonTTColumnRow.name, gibbonTTDay.gibbonTTDayID
                FROM gibbonTTDay
                JOIN gibbonTTColumnRow ON (gibbonTTDay.gibbonTTColumnID=gibbonTTColumnRow.gibbonTTColumnID)
                WHERE gibbonTTDay.gibbonTTID=:gibbonTTID
                GROUP BY value
                ORDER BY gibbonTTColumnRow.timeStart
        ";

        return $this->pdo->executeQuery($data, $sql);
    }

    public function selectTTDayRowClasses($gibbonCourseClassID, $gibbonTTID) {
        $data = array('gibbonCourseClassID' => $gibbonCourseClassID, 'gibbonTTID' => $gibbonTTID);
        $sql = "SELECT gibbonTTDayRowClassID, gibbonTTDayRowClass.gibbonTTDayID, gibbonTTDayRowClass.gibbonTTColumnRowID, gibbonTTDayRowClass.gibbonSpaceID
                FROM gibbonTTDayRowClass
                JOIN gibbonTTColumnRow ON (gibbonTTColumnRow.gibbonTTColumnRowID=gibbonTTDayRowClass.gibbonTTColumnRowID)
                JOIN gibbonTTDay ON (gibbonTTDay.gibbonTTDayID=gibbonTTDayRowClass.gibbonTTDayID)
                JOIN gibbonTT ON (gibbonTT.gibbonTTID=gibbonTTDay.gibbonTTID)
                LEFT JOIN gibbonSpace ON (gibbonSpace.gibbonSpaceID=gibbonTTDayRowClass.gibbonSpaceID)
                WHERE gibbonTT.gibbonTTID=:gibbonTTID
                AND gibbonTTDayRowClass.gibbonCourseClassID=:gibbonCourseClassID
                ORDER BY gibbonTTDay.name, gibbonTTColumnRow.name";
        return $this->pdo->executeQuery($data, $sql);
    }

    public function insertTTDayRowClass(array $data)
    {
        $sql = "INSERT INTO gibbonTTDayRowClass SET gibbonTTColumnRowID=:gibbonTTColumnRowID, gibbonTTDayID=:gibbonTTDayID, gibbonCourseClassID=:gibbonCourseClassID, gibbonSpaceID=:gibbonSpaceID";
        $result = $this->pdo->executeQuery($data, $sql);

        return $this->pdo->getConnection()->lastInsertID();
    }

    public function deleteTTDayRowClasses($gibbonCourseClassID, $gibbonTTID)
    {
        $data = array('gibbonCourseClassID' => $gibbonCourseClassID, 'gibbonTTID' => $gibbonTTID);
        $sql = "DELETE gibbonTTDayRowClass
                FROM gibbonTTDayRowClass
                INNER JOIN gibbonTTDay ON (gibbonTTDay.gibbonTTDayID=gibbonTTDayRowClass.gibbonTTDayID)
                INNER JOIN gibbonTT ON (gibbonTT.gibbonTTID=gibbonTTDay.gibbonTTID)
                WHERE gibbonTT.gibbonTTID=:gibbonTTID
                AND gibbonTTDayRowClass.gibbonCourseClassID=:gibbonCourseClassID";
        $this->pdo->executeQuery($data, $sql);

        return $this->pdo->getQuerySuccess();
    }

    public function renameCourseClass(array $data)
    {
        $sql = "UPDATE gibbonCourseClass SET name=:name, nameShort=:nameShort WHERE gibbonCourseClassID=:gibbonCourseClassID";
        $result = $this->pdo->executeQuery($data, $sql);

        return $this->pdo->getQuerySuccess();
    }
}
