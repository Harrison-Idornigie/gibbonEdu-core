<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

namespace CourseSelection\Domain;

use Gibbon\Contracts\Database\Connection;

/**
 * Course Selection: courseSelectionBlock Table Gateway
 *
 * @version v14
 * @since   13th April 2017
 * @author  Sandra Kuipers
 *
 * @uses  courseSelectionBlock
 * @uses  courseSelectionBlockCourse
 * @uses  gibbonCourse
 * @uses  gibbonSchoolYear
 * @uses  gibbonDepartment
 */
class BlocksGateway
{
    protected $pdo;

    public function __construct(Connection $pdo)
    {
        $this->pdo = $pdo;
    }

    // BLOCKS

    public function selectAllBySchoolYear($gibbonSchoolYearID)
    {
        $data = array('gibbonSchoolYearID' => $gibbonSchoolYearID);
        $sql = "SELECT courseSelectionBlock.*, gibbonSchoolYear.name as schoolYearName, GROUP_CONCAT(DISTINCT gibbonDepartment.name ORDER BY gibbonDepartment.name SEPARATOR '<br/>') as departmentName, COUNT(DISTINCT gibbonCourseID) as courseCount
                FROM courseSelectionBlock
                JOIN gibbonSchoolYear ON (courseSelectionBlock.gibbonSchoolYearID=gibbonSchoolYear.gibbonSchoolYearID)
                LEFT JOIN gibbonDepartment ON (FIND_IN_SET(gibbonDepartment.gibbonDepartmentID, courseSelectionBlock.gibbonDepartmentIDList))
                LEFT JOIN courseSelectionBlockCourse ON (courseSelectionBlockCourse.courseSelectionBlockID=courseSelectionBlock.courseSelectionBlockID)
                WHERE courseSelectionBlock.gibbonSchoolYearID=:gibbonSchoolYearID
                GROUP BY courseSelectionBlock.courseSelectionBlockID
                ORDER BY name";

        return $this->pdo->executeQuery($data, $sql);
    }

    public function selectOne($courseSelectionBlockID)
    {
        $data = array('courseSelectionBlockID' => $courseSelectionBlockID);
        $sql = "SELECT courseSelectionBlock.*, gibbonSchoolYear.name as schoolYearName, GROUP_CONCAT(gibbonDepartment.name SEPARATOR '<br/>') as departmentName
                FROM courseSelectionBlock
                JOIN gibbonSchoolYear ON (courseSelectionBlock.gibbonSchoolYearID=gibbonSchoolYear.gibbonSchoolYearID)
                LEFT JOIN gibbonDepartment ON (FIND_IN_SET(gibbonDepartment.gibbonDepartmentID, courseSelectionBlock.gibbonDepartmentIDList))
                WHERE courseSelectionBlockID=:courseSelectionBlockID
                GROUP BY courseSelectionBlock.courseSelectionBlockID";

        return $this->pdo->executeQuery($data, $sql);
    }

    public function insert(array $data)
    {
        $sql = "INSERT INTO courseSelectionBlock SET gibbonSchoolYearID=:gibbonSchoolYearID, gibbonDepartmentIDList=:gibbonDepartmentIDList, name=:name, description=:description, countable=:countable";
        $result = $this->pdo->executeQuery($data, $sql);

        return $this->pdo->getConnection()->lastInsertID();
    }

    public function update(array $data)
    {
        $sql = "UPDATE courseSelectionBlock SET gibbonSchoolYearID=:gibbonSchoolYearID, gibbonDepartmentIDList=:gibbonDepartmentIDList, name=:name, description=:description, countable=:countable WHERE courseSelectionBlockID=:courseSelectionBlockID";
        $result = $this->pdo->executeQuery($data, $sql);

        return $this->pdo->getQuerySuccess();
    }

    public function delete($courseSelectionBlockID)
    {
        $data = array('courseSelectionBlockID' => $courseSelectionBlockID);

        $sql = "DELETE FROM courseSelectionBlock WHERE courseSelectionBlockID=:courseSelectionBlockID";
        $result = $this->pdo->executeQuery($data, $sql);

        return $this->pdo->getQuerySuccess();
    }

    public function copyAllBySchoolYear($gibbonSchoolYearID, $gibbonSchoolYearIDNext)
    {
        $data = array('gibbonSchoolYearID' => $gibbonSchoolYearID, 'gibbonSchoolYearIDNext' => $gibbonSchoolYearIDNext );
        $sql = "INSERT INTO courseSelectionBlock (gibbonSchoolYearID, gibbonDepartmentIDList, name, description, countable) 
                SELECT :gibbonSchoolYearIDNext, gibbonDepartmentIDList, name, description, countable
                FROM courseSelectionBlock WHERE courseSelectionBlock.gibbonSchoolYearID=:gibbonSchoolYearID";
        $result = $this->pdo->executeQuery($data, $sql);

        $partialSuccess = $this->pdo->getQuerySuccess();
        if ($partialSuccess) {
            $data = array('gibbonSchoolYearID' => $gibbonSchoolYearID, 'gibbonSchoolYearIDNext' => $gibbonSchoolYearIDNext );
            $sql = "INSERT INTO courseSelectionBlockCourse (courseSelectionBlockID, gibbonCourseID) 
                    SELECT 
                        (SELECT courseSelectionBlockID FROM courseSelectionBlock WHERE gibbonSchoolYearID=:gibbonSchoolYearIDNext AND gibbonDepartmentIDList=prevBlock.gibbonDepartmentIDList AND name=prevBlock.name AND description=prevBlock.description) as courseSelectionBlockID, 
                        nextCourse.gibbonCourseID
                    FROM courseSelectionBlockCourse 
                    JOIN courseSelectionBlock as prevBlock ON (prevBlock.courseSelectionBlockID=courseSelectionBlockCourse.courseSelectionBlockID)
                    JOIN gibbonCourse as prevCourse ON (prevCourse.gibbonCourseID=courseSelectionBlockCourse.gibbonCourseID)
                    JOIN gibbonCourse as nextCourse ON (nextCourse.nameShort=prevCourse.nameShort)
                    WHERE prevBlock.gibbonSchoolYearID=:gibbonSchoolYearID
                    AND prevCourse.gibbonSchoolYearID=:gibbonSchoolYearID 
                    AND nextCourse.gibbonSchoolYearID=:gibbonSchoolYearIDNext";
            $result = $this->pdo->executeQuery($data, $sql);
        }

        return $partialSuccess;
    }

    // BLOCK COURSES

    public function selectAllCoursesByBlock($courseSelectionBlockID)
    {
        $data = array('courseSelectionBlockID' => $courseSelectionBlockID);
        $sql = "SELECT gibbonCourse.gibbonCourseID, courseSelectionBlockCourse.*, gibbonCourse.name as courseName, gibbonCourse.nameShort as courseNameShort
                FROM courseSelectionBlockCourse
                JOIN gibbonCourse ON (courseSelectionBlockCourse.gibbonCourseID=gibbonCourse.gibbonCourseID)
                WHERE courseSelectionBlockID=:courseSelectionBlockID
                ORDER BY gibbonCourse.name, gibbonCourse.nameShort";

        return $this->pdo->executeQuery($data, $sql);
    }

    public function insertCourse(array $data)
    {
        $sql = "INSERT INTO courseSelectionBlockCourse SET courseSelectionBlockID=:courseSelectionBlockID, gibbonCourseID=:gibbonCourseID";
        $result = $this->pdo->executeQuery($data, $sql);

        return $this->pdo->getConnection()->lastInsertID();
    }

    public function deleteCourse($courseSelectionBlockID, $gibbonCourseID)
    {
        $data = array('courseSelectionBlockID' => $courseSelectionBlockID, 'gibbonCourseID' => $gibbonCourseID);
        $sql = "DELETE FROM courseSelectionBlockCourse WHERE courseSelectionBlockID=:courseSelectionBlockID AND gibbonCourseID=:gibbonCourseID";
        $result = $this->pdo->executeQuery($data, $sql);

        return $this->pdo->getQuerySuccess();
    }

    public function deleteAllCoursesByBlock($courseSelectionBlockID)
    {
        $data = array('courseSelectionBlockID' => $courseSelectionBlockID);
        $sql = "DELETE FROM courseSelectionBlockCourse WHERE courseSelectionBlockID=:courseSelectionBlockID";
        $result = $this->pdo->executeQuery($data, $sql);

        return $this->pdo->getQuerySuccess();
    }

    // FORM QUERIES

    public function selectAvailableCourses($courseSelectionBlockID)
    {
        $data = array('courseSelectionBlockID' => $courseSelectionBlockID);
        $sql = "SELECT gibbonCourse.gibbonCourseID AS value, CONCAT(gibbonCourse.nameShort, ' - ', gibbonCourse.name) as name
                FROM gibbonCourse
                JOIN courseSelectionBlock ON (gibbonCourse.gibbonSchoolYearID=courseSelectionBlock.gibbonSchoolYearID)
                LEFT JOIN courseSelectionBlockCourse ON (
                    courseSelectionBlockCourse.gibbonCourseID=gibbonCourse.gibbonCourseID
                    AND courseSelectionBlockCourse.courseSelectionBlockID=:courseSelectionBlockID)
                WHERE courseSelectionBlock.courseSelectionBlockID=:courseSelectionBlockID
                AND courseSelectionBlockCourse.gibbonCourseID IS NULL
                ORDER BY nameShort, name";

        return $this->pdo->executeQuery($data, $sql);
    }

    public function selectAvailableCoursesByDepartments($courseSelectionBlockID, $gibbonDepartmentIDList)
    {
        $data = array('courseSelectionBlockID' => $courseSelectionBlockID, 'gibbonDepartmentIDList' => $gibbonDepartmentIDList);
        $sql = "SELECT gibbonCourse.gibbonCourseID AS value, CONCAT(gibbonCourse.nameShort, ' - ', gibbonCourse.name) as name
                FROM gibbonCourse
                JOIN courseSelectionBlock ON (gibbonCourse.gibbonSchoolYearID=courseSelectionBlock.gibbonSchoolYearID)
                LEFT JOIN courseSelectionBlockCourse ON (
                    courseSelectionBlockCourse.gibbonCourseID=gibbonCourse.gibbonCourseID
                    AND courseSelectionBlockCourse.courseSelectionBlockID=:courseSelectionBlockID)
                WHERE FIND_IN_SET(gibbonCourse.gibbonDepartmentID, :gibbonDepartmentIDList)
                AND courseSelectionBlock.courseSelectionBlockID=:courseSelectionBlockID
                AND courseSelectionBlockCourse.gibbonCourseID IS NULL
                ORDER BY nameShort, name";

        return $this->pdo->executeQuery($data, $sql);
    }
}
