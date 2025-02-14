<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

namespace CourseSelection\Domain;

use Gibbon\Contracts\Database\Connection;

/**
 * Course Selection: Timetable Gateway
 *
 * @version v14
 * @since   4th May 2017
 * @author  Sandra Kuipers
 *
 * @uses
 */
class TimetableGateway
{
    protected $pdo;

    public function __construct(Connection $pdo)
    {
        $this->pdo = $pdo;
    }

    public function selectTimetabledClassesBySchoolYear($gibbonSchoolYearID)
    {
        $data = array('gibbonSchoolYearID' => $gibbonSchoolYearID);
        $sql = "SELECT gibbonCourseClass.gibbonCourseClassID as groupBy, COUNT(DISTINCT gibbonCourseClassPerson.gibbonPersonID) as students, COUNT(DISTINCT CASE WHEN gibbonPerson.gender = 'M' THEN gibbonCourseClassPerson.gibbonPersonID END) as studentsMale, COUNT(DISTINCT CASE WHEN gibbonPerson.gender = 'F' THEN gibbonCourseClassPerson.gibbonPersonID END) as studentsFemale, gibbonCourse.name as courseName, gibbonCourse.nameShort as courseNameShort, CONCAT(gibbonCourse.nameShort,'.',gibbonCourseClass.nameShort) as className, gibbonCourseClass.nameShort as classNameShort, gibbonCourseClass.gibbonCourseClassID, courseSelectionMetaData.enrolmentGroup, courseSelectionMetaData.timetablePriority as priority, FIND_IN_SET(gibbonCourseClass.gibbonCourseClassID, courseSelectionMetaData.excludeClasses) as excluded
                FROM gibbonCourse
                JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseID=gibbonCourse.gibbonCourseID)
                JOIN courseSelectionOffering ON (courseSelectionOffering.gibbonSchoolYearID=gibbonCourse.gibbonSchoolYearID)
                JOIN courseSelectionOfferingRestriction ON (courseSelectionOfferingRestriction.courseSelectionOfferingID=courseSelectionOffering.courseSelectionOfferingID AND FIND_IN_SET(courseSelectionOfferingRestriction.gibbonYearGroupID, gibbonCourse.gibbonYearGroupIDList))
                LEFT JOIN courseSelectionMetaData ON (courseSelectionMetaData.gibbonCourseID=gibbonCourse.gibbonCourseID)
                LEFT JOIN gibbonCourseClassPerson ON (gibbonCourseClassPerson.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID AND gibbonCourseClassPerson.role='Student')
                LEFT JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=gibbonCourseClassPerson.gibbonPersonID)
                WHERE gibbonCourse.gibbonSchoolYearID=:gibbonSchoolYearID
                GROUP BY gibbonCourseClass.gibbonCourseClassID";

        return $this->pdo->executeQuery($data, $sql);
    }

    public function selectApprovedStudentsBySchoolYear($gibbonSchoolYearID, $orderBy = 'yearGroupDesc')
    {
        $data = array('gibbonSchoolYearID' => $gibbonSchoolYearID);
        $sql = "SELECT gibbonPerson.gibbonPersonID as groupBy, gibbonPerson.gibbonPersonID, gibbonPerson.gender
                FROM gibbonPerson
                JOIN courseSelectionChoice ON (courseSelectionChoice.gibbonPersonIDStudent=gibbonPerson.gibbonPersonID)
                JOIN courseSelectionApproval ON (courseSelectionApproval.courseSelectionChoiceID=courseSelectionChoice.courseSelectionChoiceID)
                JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonPersonID=courseSelectionChoice.gibbonPersonIDStudent)
                JOIN gibbonYearGroup ON (gibbonYearGroup.gibbonYearGroupID=gibbonStudentEnrolment.gibbonYearGroupID)
                WHERE courseSelectionChoice.gibbonSchoolYearID=:gibbonSchoolYearID
                AND (gibbonStudentEnrolment.gibbonSchoolYearID=(SELECT gibbonSchoolYearID FROM gibbonSchoolYear WHERE status='Current') 
                    OR gibbonStudentEnrolment.gibbonSchoolYearID=courseSelectionChoice.gibbonSchoolYearID)
                GROUP BY gibbonPerson.gibbonPersonID";

        switch ($orderBy) {
            case 'yearGroupDesc':   $sql .= " ORDER BY gibbonYearGroup.sequenceNumber DESC, MD5(gibbonPerson.gibbonPersonID)"; break;
            case 'yearGroupAsc':    $sql .= " ORDER BY gibbonYearGroup.sequenceNumber ASC, MD5(gibbonPerson.gibbonPersonID)"; break;
            case 'random':
            default:                $sql .= " ORDER BY MD5(gibbonPerson.gibbonPersonID)"; break;
        }

        return $this->pdo->executeQuery($data, $sql);
    }

    public function selectCourseEnrolmentsBySchoolYear($gibbonSchoolYearID)
    {
        $data = array('gibbonSchoolYearID' => $gibbonSchoolYearID);
        $sql = "SELECT gibbonCourseClassPerson.gibbonPersonID as groupBy, gibbonCourse.gibbonCourseID, gibbonCourseClass.gibbonCourseClassID, gibbonCourseClass.nameShort as classNameShort, GROUP_CONCAT(CONCAT(gibbonTTColumnRow.nameShort,'-',gibbonTTDay.nameShort) ORDER BY gibbonTTDay.nameShort SEPARATOR ',') as ttDays, GROUP_CONCAT(DISTINCT gibbonTTColumnRow.gibbonTTColumnRowID ORDER BY gibbonTTColumnRow.gibbonTTColumnRowID SEPARATOR ',') as ttColumnRow
                FROM gibbonCourseClassPerson
                JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseClassID=gibbonCourseClassPerson.gibbonCourseClassID)
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID)
                JOIN gibbonTTDayRowClass ON (gibbonTTDayRowClass.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID)
                JOIN gibbonTTColumnRow ON (gibbonTTColumnRow.gibbonTTColumnRowID=gibbonTTDayRowClass.gibbonTTColumnRowID)
                JOIN gibbonTTDay ON (gibbonTTDay.gibbonTTDayID=gibbonTTDayRowClass.gibbonTTDayID)
                WHERE gibbonCourse.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonCourseClassPerson.role = 'Student'
                GROUP BY gibbonCourseClassPerson.gibbonPersonID, gibbonCourseClass.gibbonCourseClassID";

        return $this->pdo->executeQuery($data, $sql);
    }

    public function selectApprovedCourseSelectionsBySchoolYear($gibbonSchoolYearID)
    {
        $data = array('gibbonSchoolYearID' => $gibbonSchoolYearID);
        $sql = "SELECT courseSelectionChoice.gibbonPersonIDStudent as groupBy, gibbonCourse.gibbonCourseID, gibbonCourse.nameShort as courseNameShort, gibbonCourseClass.gibbonCourseClassID, gibbonCourseClass.nameShort as classNameShort, (CASE WHEN gibbonCourseClassPerson.gibbonCourseClassID IS NOT NULL THEN 1 ELSE 0 END) as currentEnrolment, GROUP_CONCAT(CONCAT(gibbonTTColumnRow.nameShort,'-',gibbonTTDay.nameShort) ORDER BY gibbonTTDay.nameShort SEPARATOR ',') as ttDays, GROUP_CONCAT(DISTINCT gibbonTTColumnRow.gibbonTTColumnRowID ORDER BY gibbonTTColumnRow.gibbonTTColumnRowID SEPARATOR ',') as ttColumnRow, courseSelectionChoice.gibbonPersonIDStudent as gibbonPersonID
                FROM courseSelectionChoice
                JOIN courseSelectionApproval ON (courseSelectionApproval.courseSelectionChoiceID=courseSelectionChoice.courseSelectionChoiceID)
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=courseSelectionChoice.gibbonCourseID)
                LEFT JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseID=gibbonCourse.gibbonCourseID)
                LEFT JOIN gibbonCourseClassPerson ON (gibbonCourseClassPerson.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID AND gibbonCourseClassPerson.gibbonPersonID=courseSelectionChoice.gibbonPersonIDStudent)
                LEFT JOIN gibbonTTDayRowClass ON (gibbonTTDayRowClass.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID)
                LEFT JOIN gibbonTTColumnRow ON (gibbonTTColumnRow.gibbonTTColumnRowID=gibbonTTDayRowClass.gibbonTTColumnRowID)
                LEFT JOIN gibbonTTDay ON (gibbonTTDay.gibbonTTDayID=gibbonTTDayRowClass.gibbonTTDayID)
                WHERE courseSelectionChoice.gibbonSchoolYearID=:gibbonSchoolYearID
                AND courseSelectionChoice.status <> 'Removed'
                AND courseSelectionChoice.status <> 'Recommended'
                GROUP BY courseSelectionChoice.courseSelectionChoiceID, gibbonCourseClass.gibbonCourseClassID
                ORDER BY gibbonCourse.orderBy, gibbonCourse.nameShort";

        return $this->pdo->executeQuery($data, $sql);
    }

    public function selectCourseResultsBySchoolYear($gibbonSchoolYearID, $orderBy = 'nameShort', $allCourses = null)
    {
        $data = array('gibbonSchoolYearID' => $gibbonSchoolYearID);
        $sql = "SELECT GROUP_CONCAT(DISTINCT gibbonCourse.name ORDER BY gibbonCourse.name SEPARATOR '<br>') as courseName, gibbonCourseClass.gibbonCourseClassID, GROUP_CONCAT(DISTINCT CONCAT(gibbonCourse.nameShort,'.',gibbonCourseClass.nameShort) ORDER BY gibbonCourse.nameShort, gibbonCourseClass.nameShort SEPARATOR '<br>') as className, COUNT(DISTINCT CASE WHEN courseSelectionTTResult.status = 'Complete' THEN courseSelectionTTResult.gibbonPersonIDStudent END) as students, COUNT(DISTINCT CASE WHEN gibbonPerson.gender = 'M' AND courseSelectionTTResult.status = 'Complete' THEN courseSelectionTTResult.gibbonPersonIDStudent END) as studentsMale, COUNT(DISTINCT CASE WHEN gibbonPerson.gender = 'F' AND courseSelectionTTResult.status = 'Complete' THEN courseSelectionTTResult.gibbonPersonIDStudent END) as studentsFemale, COUNT(DISTINCT CASE WHEN courseSelectionTTResult.status = 'Flagged' THEN courseSelectionTTResult.gibbonPersonIDStudent END) as issues, courseSelectionMetaData.enrolmentGroup, (CASE WHEN courseSelectionMetaData.enrolmentGroup IS NOT NULL AND courseSelectionMetaData.enrolmentGroup <> '' THEN CONCAT(courseSelectionMetaData.enrolmentGroup,'.',gibbonCourseClass.nameShort) ELSE CONCAT(gibbonCourse.nameShort,'.',gibbonCourseClass.nameShort) END) as enrolmentGroupName, FIND_IN_SET(gibbonCourseClass.gibbonCourseClassID, courseSelectionMetaData.excludeClasses) as excluded
                FROM gibbonCourse
                LEFT JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseID=gibbonCourse.gibbonCourseID)
                LEFT JOIN courseSelectionMetaData ON (courseSelectionMetaData.gibbonCourseID=gibbonCourse.gibbonCourseID)
                LEFT JOIN courseSelectionTTResult ON (gibbonCourseClass.gibbonCourseClassID=courseSelectionTTResult.gibbonCourseClassID)
                LEFT JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=courseSelectionTTResult.gibbonPersonIDStudent)
                WHERE gibbonCourse.gibbonSchoolYearID=:gibbonSchoolYearID
                AND (SELECT COUNT(courseSelectionChoiceID) FROM courseSelectionChoice WHERE courseSelectionChoice.gibbonCourseID=gibbonCourse.gibbonCourseID AND courseSelectionChoice.gibbonSchoolYearID=gibbonCourse.gibbonSchoolYearID)
                GROUP BY enrolmentGroupName";

        if ($allCourses != 'Y') {
            $sql .= " HAVING students > 0";
        }

        if ($orderBy == 'students') {
            $sql .= " ORDER BY students DESC, gibbonCourse.nameShort, gibbonCourse.name";
        } else if ($orderBy == 'issues') {
            $sql .= " ORDER BY issues DESC, gibbonCourse.nameShort, gibbonCourse.name";
        } else if ($orderBy == 'period') {
            $sql .= " ORDER BY gibbonCourseClass.nameShort, gibbonCourse.nameShort";
        } else if ($orderBy == 'name') {
            $sql .= " ORDER BY gibbonCourse.name, gibbonCourse.nameShort";
        } else {
            $sql .= " ORDER BY gibbonCourse.nameShort, gibbonCourse.name";
        }

        return $this->pdo->executeQuery($data, $sql);
    }

    public function selectStudentResultsBySchoolYear($gibbonSchoolYearID, $orderBy = 'surname', $gibbonCourseClassID = null)
    {
        $data = array('gibbonSchoolYearID' => $gibbonSchoolYearID);
        $sql = "SELECT courseSelectionTTResult.gibbonPersonIDStudent, gibbonPerson.gibbonPersonID, gibbonPerson.surname, gibbonPerson.preferredName, gibbonFormGroup.nameShort as formGroupName, courseSelectionTTResult.weight, gibbonCourse.name as courseName, gibbonCourse.nameShort as courseNameShort, gibbonCourseClass.nameShort as classNameShort, gibbonCourse.gibbonCourseID, gibbonCourseClass.gibbonCourseClassID, courseSelectionTTResult.status, courseSelectionTTResult.flag, courseSelectionTTResult.reason, (CASE WHEN gibbonCourseClassPerson.gibbonCourseClassID IS NOT NULL THEN 1 ELSE 0 END) as currentEnrolment, courseSelectionChoiceOffering.courseSelectionOfferingID
                FROM courseSelectionTTResult
                JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=courseSelectionTTResult.gibbonPersonIDStudent)
                JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID)
                JOIN gibbonFormGroup ON (gibbonStudentEnrolment.gibbonFormGroupID=gibbonFormGroup.gibbonFormGroupID)
                LEFT JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=courseSelectionTTResult.gibbonCourseID)
                LEFT JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseClassID=courseSelectionTTResult.gibbonCourseClassID)
                LEFT JOIN gibbonCourseClassPerson ON (gibbonCourseClassPerson.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID AND gibbonCourseClassPerson.gibbonPersonID=courseSelectionTTResult.gibbonPersonIDStudent)
                LEFT JOIN gibbonTTDayRowClass ON (gibbonTTDayRowClass.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID)
                LEFT JOIN courseSelectionMetaData ON (courseSelectionMetaData.gibbonCourseID=gibbonCourse.gibbonCourseID)
                LEFT JOIN courseSelectionChoiceOffering ON (courseSelectionChoiceOffering.gibbonSchoolYearID=courseSelectionTTResult.gibbonSchoolYearID AND courseSelectionChoiceOffering.gibbonPersonIDStudent=courseSelectionTTResult.gibbonPersonIDStudent)
                WHERE courseSelectionTTResult.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonStudentEnrolment.gibbonSchoolYearID=(SELECT gibbonSchoolYearID FROM gibbonSchoolYear WHERE status='Current')
                AND (gibbonPerson.status = 'Full' OR gibbonPerson.status = 'Expected')
        ";

        if (!empty($gibbonCourseClassID)) {
            $data['gibbonCourseClassID'] = $gibbonCourseClassID;
            $sql .= " AND (courseSelectionTTResult.gibbonCourseClassID=:gibbonCourseClassID OR courseSelectionMetaData.enrolmentGroup=:gibbonCourseClassID)";
        }

        $sql .= " GROUP BY gibbonPerson.gibbonPersonID, courseSelectionTTResult.courseSelectionTTResultID";

        if ($orderBy == 'count') {
            $sql .= " ORDER BY flag DESC, LENGTH(gibbonFormGroup.nameShort), gibbonFormGroup.nameShort, gibbonPerson.surname, gibbonPerson.preferredName";
        } else if ($orderBy == 'formGroup') {
            $sql .= " ORDER BY LENGTH(gibbonFormGroup.nameShort), gibbonFormGroup.nameShort, gibbonPerson.surname, gibbonPerson.preferredName";
        } else if ($orderBy == 'weight') {
            $sql .= " ORDER BY courseSelectionTTResult.weight, gibbonPerson.surname, gibbonPerson.preferredName";
        } else {
            $sql .= " ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";
        }

        return $this->pdo->executeQuery($data, $sql);
    }

    public function selectIncompleteResultsBySchoolYearAndStudent($gibbonSchoolYearID, $gibbonPersonIDStudent, $gibbonCourseID = null)
    {
        $data = array('gibbonPersonIDStudent' => $gibbonPersonIDStudent, 'gibbonSchoolYearID' => $gibbonSchoolYearID);
        $sql = "SELECT gibbonCourse.name as courseName, gibbonCourse.nameShort as courseNameShort, gibbonCourse.gibbonCourseID, COUNT(DISTINCT gibbonCourseClass.gibbonCourseClassID) as classCount, COUNT(DISTINCT gibbonTTDayRowClass.gibbonTTDayRowClassID) as ttCount, courseSelectionMetaData.excludeClasses
                FROM courseSelectionChoice
                JOIN courseSelectionApproval ON (courseSelectionApproval.courseSelectionChoiceID=courseSelectionChoice.courseSelectionChoiceID)
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=courseSelectionChoice.gibbonCourseID)
                LEFT JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseID=gibbonCourse.gibbonCourseID)
                LEFT JOIN courseSelectionMetaData ON (courseSelectionMetaData.gibbonCourseID=gibbonCourse.gibbonCourseID)
                LEFT JOIN gibbonTTDayRowClass ON (gibbonTTDayRowClass.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID)
                LEFT JOIN courseSelectionTTResult ON (courseSelectionTTResult.gibbonCourseID=courseSelectionChoice.gibbonCourseID AND courseSelectionTTResult.gibbonPersonIDStudent=courseSelectionChoice.gibbonPersonIDStudent)
                WHERE courseSelectionChoice.gibbonPersonIDStudent=:gibbonPersonIDStudent
                AND courseSelectionChoice.gibbonSchoolYearID=:gibbonSchoolYearID
                AND courseSelectionTTResult.gibbonCourseClassID IS NULL
        ";

        if (!empty($gibbonCourseID)) {
            $data['gibbonCourseID'] = $gibbonCourseID;
            $sql .= " AND courseSelectionChoice.gibbonCourseID=:gibbonCourseID";
        }

        $sql .= " GROUP BY gibbonCourse.gibbonCourseID";

        return $this->pdo->executeQuery($data, $sql);
    }

    public function selectEnroledCoursesBySchoolYearAndStudent($gibbonSchoolYearID, $gibbonPersonIDStudent)
    {
        $data = array('gibbonPersonIDStudent' => $gibbonPersonIDStudent, 'gibbonSchoolYearID' => $gibbonSchoolYearID);
        $sql = "SELECT CONCAT(gibbonTTDayRowClass.gibbonTTDayID, '-', gibbonTTDayRowClass.gibbonTTColumnRowID) as groupBy, gibbonCourse.gibbonCourseID, gibbonCourse.name as courseName, gibbonCourse.nameShort as courseNameShort, gibbonCourseClass.gibbonCourseClassID, gibbonCourseClass.nameShort as className, gibbonTTColumnRow.nameShort as period, gibbonCourse.gibbonSchoolYearID
                FROM gibbonCourseClassPerson
                JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseClassID=gibbonCourseClassPerson.gibbonCourseClassID)
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID)
                JOIN gibbonTTDayRowClass ON (gibbonTTDayRowClass.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID)
                LEFT JOIN gibbonTTColumnRow ON (gibbonTTColumnRow.gibbonTTColumnRowID=gibbonTTDayRowClass.gibbonTTColumnRowID)
                LEFT JOIN gibbonTTDay ON (gibbonTTDay.gibbonTTDayID=gibbonTTDayRowClass.gibbonTTDayID)
                WHERE gibbonCourse.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonCourseClassPerson.gibbonPersonID=:gibbonPersonIDStudent
                AND gibbonCourseClassPerson.role = 'Student'
                AND gibbonCourse.nameShort NOT LIKE '%Advis%'
                AND gibbonCourse.nameShort NOT LIKE '%TAP'
                AND gibbonCourse.nameShort NOT LIKE '%HOME%'
                AND gibbonCourse.nameShort NOT LIKE '%ECA%'
                AND (FIND_IN_SET('014', gibbonCourse.gibbonYearGroupIDList)
                    OR FIND_IN_SET('015', gibbonCourse.gibbonYearGroupIDList)
                    OR FIND_IN_SET('016', gibbonCourse.gibbonYearGroupIDList) )
                GROUP BY gibbonCourseClass.gibbonCourseClassID, gibbonTTDay.gibbonTTDayID
                ORDER BY gibbonCourse.nameShort, gibbonCourseClass.nameShort
        ";

        return $this->pdo->executeQuery($data, $sql);
    }


    public function selectTimetableDaysAndColumns($gibbonTTID)
    {
        $data = array('gibbonTTID' => $gibbonTTID);
        $sql = "SELECT gibbonTTColumnRow.nameShort as groupBy, gibbonTTColumnRow.gibbonTTColumnRowID, gibbonTTDay.*, gibbonTTColumn.name AS columnName, gibbonTTColumnRow.nameShort as rowName
                FROM gibbonTTDay 
                JOIN gibbonTTColumn ON (gibbonTTDay.gibbonTTColumnID=gibbonTTColumn.gibbonTTColumnID) 
                JOIN gibbonTTColumnRow ON (gibbonTTColumnRow.gibbonTTColumnID=gibbonTTDay.gibbonTTColumnID)
                WHERE gibbonTTDay.gibbonTTID=:gibbonTTID
                AND gibbonTTColumnRow.type='Lesson'
                AND gibbonTTDay.nameShort LIKE '%MF'
                ORDER BY gibbonTTDay.gibbonTTDayID, gibbonTTColumnRow.timeStart, gibbonTTColumnRow.name";

        return $this->pdo->select($sql, $data);
    }

    public function selectTimetablePreviewByStudent($gibbonSchoolYearID, $gibbonPersonIDStudent)
    {
        $data = array('gibbonPersonIDStudent' => $gibbonPersonIDStudent, 'gibbonSchoolYearID' => $gibbonSchoolYearID);
        $sql = "SELECT CONCAT(gibbonTTDayRowClass.gibbonTTDayID, '-', gibbonTTDayRowClass.gibbonTTColumnRowID) as groupBy, courseSelectionTTResult.*, gibbonTTDayRowClass.*, gibbonCourse.name as courseName, gibbonCourse.nameShort as courseNameShort, gibbonCourseClass.nameShort as className, gibbonTTColumnRow.nameShort as period
                FROM courseSelectionTTResult
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=courseSelectionTTResult.gibbonCourseID)
                JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseClassID=courseSelectionTTResult.gibbonCourseClassID)
                LEFT JOIN gibbonTTDayRowClass ON (gibbonTTDayRowClass.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID)
                LEFT JOIN gibbonTTColumnRow ON (gibbonTTColumnRow.gibbonTTColumnRowID=gibbonTTDayRowClass.gibbonTTColumnRowID)
                WHERE courseSelectionTTResult.gibbonSchoolYearID=:gibbonSchoolYearID 
                AND courseSelectionTTResult.gibbonPersonIDStudent=:gibbonPersonIDStudent";

        return $this->pdo->select($sql, $data);
    }

    public function selectRelevantTimetablesByPerson($gibbonSchoolYearID, $gibbonPersonID)
    {
        $data = array('gibbonSchoolYearID' => $gibbonSchoolYearID, 'gibbonPersonID' => $gibbonPersonID);
        $sql = "SELECT DISTINCT gibbonTT.gibbonTTID, gibbonTT.name, gibbonTT.nameShortDisplay 
                FROM gibbonTT 
                JOIN gibbonTTDay ON (gibbonTT.gibbonTTID=gibbonTTDay.gibbonTTID) 
                JOIN gibbonTTDayRowClass ON (gibbonTTDayRowClass.gibbonTTDayID=gibbonTTDay.gibbonTTDayID) 
                JOIN gibbonCourseClass ON (gibbonTTDayRowClass.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID) 
                JOIN gibbonCourseClassPerson ON (gibbonCourseClassPerson.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID)
                 WHERE gibbonPersonID=:gibbonPersonID 
                 AND gibbonSchoolYearID=:gibbonSchoolYearID";

        return $this->pdo->select($sql, $data);
    }

    public function insertResult(array $data)
    {
        $sql = "INSERT INTO courseSelectionTTResult SET gibbonSchoolYearID=:gibbonSchoolYearID, gibbonPersonIDStudent=:gibbonPersonIDStudent, gibbonCourseID=:gibbonCourseID, gibbonCourseClassID=:gibbonCourseClassID, weight=:weight, status=:status, flag=:flag, reason=:reason";
        $result = $this->pdo->executeQuery($data, $sql);

        return $this->pdo->getConnection()->lastInsertID();
    }

    public function deleteAllResultsBySchoolYear($gibbonSchoolYearID)
    {
        $data = array('gibbonSchoolYearID' => $gibbonSchoolYearID);
        $sql = "DELETE FROM courseSelectionTTResult WHERE gibbonSchoolYearID=:gibbonSchoolYearID";
        $result = $this->pdo->executeQuery($data, $sql);

        return $this->pdo->getQuerySuccess();
    }

    public function transformResultsIntoClassEnrolments($gibbonSchoolYearID)
    {
        $data = array('gibbonSchoolYearID' => $gibbonSchoolYearID);
        $sql = "INSERT IGNORE INTO gibbonCourseClassPerson (gibbonCourseClassID, gibbonPersonID, role)
                SELECT courseSelectionTTResult.gibbonCourseClassID, courseSelectionTTResult.gibbonPersonIDStudent, 'Student'
                FROM courseSelectionTTResult
                LEFT JOIN gibbonCourseClassPerson ON (gibbonCourseClassPerson.gibbonCourseClassID=courseSelectionTTResult.gibbonCourseClassID
                    AND gibbonCourseClassPerson.gibbonPersonID=courseSelectionTTResult.gibbonPersonIDStudent)
                WHERE courseSelectionTTResult.gibbonSchoolYearID=:gibbonSchoolYearID
                AND courseSelectionTTResult.status='Complete'
                AND gibbonCourseClassPerson.gibbonCourseClassPersonID IS NULL";
        $result = $this->pdo->executeQuery($data, $sql);

        return $this->pdo->getConnection()->lastInsertID();
    }

    public function insertClassEnrolment(array $data)
    {
        $sql = "INSERT IGNORE INTO gibbonCourseClassPerson SET gibbonCourseClassID=:gibbonCourseClassID, gibbonPersonID=:gibbonPersonID, role=:role";
        $result = $this->pdo->executeQuery($data, $sql);

        return $this->pdo->getConnection()->lastInsertID();
    }
}
