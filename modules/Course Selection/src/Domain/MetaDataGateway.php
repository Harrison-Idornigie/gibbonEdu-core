<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

namespace CourseSelection\Domain;

use Gibbon\Contracts\Database\Connection;

/**
 * Course Selection: Meta Data Gateway
 *
 * @version v14
 * @since   10th May 2017
 * @author  Sandra Kuipers
 *
 * @uses  courseSelectionMetaData
 */
class MetaDataGateway
{
    protected $pdo;

    public function __construct(Connection $pdo)
    {
        $this->pdo = $pdo;
    }

    // TRANSACTIONS
    public function insert(array $data)
    {
        $sql = "INSERT INTO courseSelectionMetaData SET gibbonCourseID=:gibbonCourseID, enrolmentGroup=:enrolmentGroup, timetablePriority=:timetablePriority, tags=:tags ON DUPLICATE KEY UPDATE enrolmentGroup=:enrolmentGroup, timetablePriority=:timetablePriority, tags=:tags, excludeClasses=:excludeClasses";
        $result = $this->pdo->executeQuery($data, $sql);

        return $this->pdo->getConnection()->lastInsertID();
    }

    public function update(array $data)
    {
        $sql = "UPDATE courseSelectionMetaData SET gibbonCourseID=:gibbonCourseID, enrolmentGroup=:enrolmentGroup, timetablePriority=:timetablePriority, tags=:tags, excludeClasses=:excludeClasses WHERE courseSelectionMetaDataID=:courseSelectionMetaDataID";
        $result = $this->pdo->executeQuery($data, $sql);

        return $this->pdo->getQuerySuccess();
    }

    public function delete($courseSelectionMetaDataID)
    {
        $data = array('courseSelectionMetaDataID' => $courseSelectionMetaDataID);
        $sql = "DELETE FROM courseSelectionMetaData WHERE courseSelectionMetaDataID=:courseSelectionMetaDataID";
        $result = $this->pdo->executeQuery($data, $sql);

        return $this->pdo->getQuerySuccess();
    }

    public function copyAllBySchoolYear($gibbonSchoolYearID, $gibbonSchoolYearIDNext)
    {
        $data = array('gibbonSchoolYearID' => $gibbonSchoolYearID, 'gibbonSchoolYearIDNext' => $gibbonSchoolYearIDNext );
        $sql = "INSERT INTO courseSelectionMetaData (gibbonCourseID, enrolmentGroup, timetablePriority, tags, excludeClasses) 
                SELECT nextCourse.gibbonCourseID, courseSelectionMetaData.enrolmentGroup, courseSelectionMetaData.timetablePriority, courseSelectionMetaData.tags, courseSelectionMetaData.excludeClasses
                FROM courseSelectionMetaData
                JOIN gibbonCourse as prevCourse ON (prevCourse.gibbonCourseID=courseSelectionMetaData.gibbonCourseID)
                JOIN gibbonCourse as nextCourse ON (nextCourse.nameShort=prevCourse.nameShort)
                WHERE prevCourse.gibbonSchoolYearID=:gibbonSchoolYearID 
                AND nextCourse.gibbonSchoolYearID=:gibbonSchoolYearIDNext";
        $result = $this->pdo->executeQuery($data, $sql);

        return $this->pdo->getQuerySuccess();
    }

    // QUERIES
    public function selectOne($courseSelectionMetaDataID)
    {
        $data = array('courseSelectionMetaDataID' => $courseSelectionMetaDataID);
        $sql = "SELECT * FROM courseSelectionMetaData WHERE courseSelectionMetaDataID=:courseSelectionMetaDataID ";
        $result = $this->pdo->executeQuery($data, $sql);

        return $result;
    }

    public function selectAllBySchoolYear($gibbonSchoolYearID)
    {
        $data = array('gibbonSchoolYearID' => $gibbonSchoolYearID);
        $sql = "SELECT *
                FROM courseSelectionMetaData
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=courseSelectionMetaData.gibbonCourseID)
                WHERE gibbonCourse.gibbonSchoolYearID=:gibbonSchoolYearID";
        $result = $this->pdo->executeQuery($data, $sql);

        return $result;
    }
}
