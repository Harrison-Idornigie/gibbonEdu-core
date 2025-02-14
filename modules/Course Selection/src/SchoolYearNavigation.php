<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

namespace CourseSelection;

use Gibbon\Contracts\Services\Session;
use Gibbon\Contracts\Database\Connection;

/**
 * School Year Navigation
 *
 * @version v14
 * @since   26th April 2017
 */
class SchoolYearNavigation
{
    protected $pdo;
    protected $session;

    protected $schoolYear;
    protected $previousYear;
    protected $nextYear;

    public function __construct(Connection $pdo, Session $session)
    {
        $this->pdo = $pdo;
        $this->session = $session;
    }

    public function getNextYear($gibbonSchoolYearID)
    {
        $this->nextYear = $this->selectNextSchoolYearByID($gibbonSchoolYearID);
        return $this->nextYear;
    }

    public function selectNextSchoolYearByID($gibbonSchoolYearID)
    {
        $data = array('gibbonSchoolYearID' => $gibbonSchoolYearID);
        $sql = "SELECT gibbonSchoolYearID, name FROM gibbonSchoolYear WHERE sequenceNumber=(SELECT MIN(sequenceNumber) FROM gibbonSchoolYear WHERE sequenceNumber > (SELECT sequenceNumber FROM gibbonSchoolYear WHERE gibbonSchoolYearID=:gibbonSchoolYearID))";

        $result = $this->pdo->executeQuery($data, $sql);

        return ($result && $result->rowCount() > 0)? $result->fetch() : null;
    }
}
