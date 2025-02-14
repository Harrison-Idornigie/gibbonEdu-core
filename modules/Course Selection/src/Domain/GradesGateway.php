<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

namespace CourseSelection\Domain;

use Gibbon\Contracts\Database\Connection;

/**
 * Course Selection: Grades Gateway
 *
 * @version v14
 * @since   26th April 2017
 * @author  Sandra Kuipers
 *
 * @uses  gibbonSchoolYear
 * @uses  gibbonCourse
 * @uses  gibbonCourseClass
 * @uses  gibbonCourseClassPerson
 * @uses  arrCriteria
 * @uses  arrReportGrade
 * @uses  arrReport
 * @uses  arrLegacyGrade
 */
class GradesGateway
{
    protected $pdo;

    public function __construct(Connection $pdo)
    {
        $this->pdo = $pdo;
    }

    // GRADES

    public function selectStudentReportGradesByDepartments($gibbonDepartmentIDList, $gibbonPersonIDStudent)
    {
        $data = array('gibbonDepartmentIDList' => $gibbonDepartmentIDList, 'gibbonPersonIDStudent' => $gibbonPersonIDStudent);
        $sql = "(SELECT gradeID as grade, gibbonSchoolYear.name as schoolYearName, gibbonSchoolYear.status as schoolYearStatus, gibbonCourse.name as courseName, gibbonCourse.nameShort as courseNameShort, arrReport.reportName, arrReport.reportID, (CASE WHEN gibbonCourse.orderBy > 0 THEN gibbonCourse.orderBy ELSE 80 end) as courseOrder
                FROM gibbonCourse
                JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseID=gibbonCourse.gibbonCourseID)
                JOIN gibbonCourseClassPerson ON (gibbonCourseClassPerson.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID AND gibbonCourseClassPerson.role='Student')
                JOIN gibbonSchoolYear ON (gibbonSchoolYear.gibbonSchoolYearID=gibbonCourse.gibbonSchoolYearID)
                LEFT JOIN arrCriteria ON (gibbonCourse.gibbonCourseID=arrCriteria.subjectID  )
                LEFT JOIN arrReportGrade ON (arrReportGrade.criteriaID=arrCriteria.criteriaID AND arrReportGrade.studentID = gibbonCourseClassPerson.gibbonPersonID )
                LEFT JOIN arrReport ON (arrReport.reportID=arrReportGrade.reportID AND arrReport.schoolYearID=gibbonCourse.gibbonSchoolYearID )
                WHERE FIND_IN_SET(gibbonCourse.gibbonDepartmentID, :gibbonDepartmentIDList)
                AND gibbonCourseClassPerson.gibbonPersonID=:gibbonPersonIDStudent
                AND gibbonCourseClass.reportable='Y'
                AND gibbonCourse.nameShort NOT LIKE '%ECA%'
                AND gibbonCourse.nameShort NOT LIKE '%HOMEROOM%'
                AND gibbonCourse.nameShort NOT LIKE '%Advisor%'
                AND arrReportGrade.reportGradeID IS NOT NULL
                AND arrCriteria.criteriaID = (SELECT arrCriteria.criteriaID FROM arrCriteria JOIN arrReportGrade ON (arrReportGrade.criteriaID=arrCriteria.criteriaID) WHERE subjectID=gibbonCourse.gibbonCourseID AND schoolYearID=gibbonCourse.gibbonSchoolYearID AND (criteriaType=2 || criteriaType=4) AND arrReportGrade.studentID=gibbonCourseClassPerson.gibbonPersonID ORDER BY arrCriteria.criteriaType DESC, arrCriteria.reportID DESC LIMIT 1)
                GROUP BY gibbonCourse.gibbonCourseID
                ORDER BY arrReport.schoolYearID DESC, arrReport.reportNum DESC, arrCriteria.criteriaType DESC
            ) UNION ALL (
            SELECT DISTINCT grade, gibbonSchoolYear.name as schoolYearName, gibbonSchoolYear.status as schoolYearStatus, gibbonCourse.name as courseName, gibbonCourse.nameShort as courseNameShort, 'Final', 0 as reportID, (CASE WHEN gibbonCourse.orderBy > 0 THEN gibbonCourse.orderBy ELSE 80 end) as courseOrder
                FROM arrLegacyGrade
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=arrLegacyGrade.gibbonCourseID)
                JOIN gibbonSchoolYear ON (gibbonSchoolYear.gibbonSchoolYearID=gibbonCourse.gibbonSchoolYearID)
                WHERE arrLegacyGrade.gibbonPersonID=:gibbonPersonIDStudent
                AND FIND_IN_SET(gibbonCourse.gibbonDepartmentID, :gibbonDepartmentIDList)
                AND arrLegacyGrade.reportTerm='Final'
                ) ORDER BY schoolYearName, courseOrder, courseNameShort";

        return $this->pdo->executeQuery($data, $sql);
    }

    public function selectStudentReportGradesBySchoolYear($gibbonSchoolYearID, $gibbonPersonID) {
        $data = array(
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonSchoolYearID' => $gibbonSchoolYearID
        );

        $sql = "(SELECT (CASE WHEN gibbonReportingCriteriaType.name like 'Final%' THEN 'Final' ELSE gibbonReportingCycle.nameShort END) as reportTerm, gibbonReportingValue.value as grade, 'Standard' as gradeType, (CASE WHEN gibbonReportingCriteriaType.name = 'Final Percent' AND gibbonReportingValue.value >= 50.0 THEN gibbonCourse.credits WHEN gibbonReportingValue.value = '' THEN '' ELSE 0 END) as creditsAwarded, gibbonCourse.name as courseName, gibbonCourse.nameShort as courseNameShort, gibbonCourseClass.gibbonCourseClassID, (CASE WHEN gibbonCourse.orderBy > 0 THEN gibbonCourse.orderBy ELSE 80 end) as courseOrder
                FROM gibbonCourse
                JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseID=gibbonCourse.gibbonCourseID)
                JOIN gibbonCourseClassPerson ON (gibbonCourseClassPerson.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID AND gibbonCourseClassPerson.role='Student')

                LEFT JOIN gibbonReportingCriteria ON (gibbonReportingCriteria.gibbonCourseID=gibbonCourse.gibbonCourseID AND gibbonReportingCriteria.target='Per Student' AND gibbonReportingCriteria.gibbonReportingCriteriaTypeID = 12 )
                LEFT JOIN gibbonReportingCriteriaType ON (gibbonReportingCriteriaType.gibbonReportingCriteriaTypeID=gibbonReportingCriteria.gibbonReportingCriteriaTypeID  )                
                LEFT JOIN gibbonReportingValue ON (gibbonReportingValue.gibbonReportingCriteriaID=gibbonReportingCriteria.gibbonReportingCriteriaID
                    AND gibbonReportingValue.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID 
                    AND gibbonReportingValue.gibbonPersonIDStudent=gibbonCourseClassPerson.gibbonPersonID)
                LEFT JOIN gibbonReportingProgress ON (gibbonReportingProgress.gibbonReportingScopeID=gibbonReportingCriteria.gibbonReportingScopeID AND gibbonReportingProgress.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID AND gibbonReportingProgress.gibbonPersonIDStudent=gibbonReportingValue.gibbonPersonIDStudent)
                LEFT JOIN gibbonReportingCycle ON (gibbonReportingCycle.gibbonReportingCycleID=gibbonReportingCriteria.gibbonReportingCycleID)

                WHERE gibbonCourse.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonCourseClassPerson.gibbonPersonID=:gibbonPersonID
                AND gibbonCourseClass.reportable='Y'
                AND gibbonCourse.nameShort NOT LIKE '%ECA%'
                AND gibbonCourse.nameShort NOT LIKE '%HOMEROOM%'
                AND gibbonCourse.nameShort NOT LIKE '%Advisor%'
                AND gibbonCourse.nameShort NOT LIKE '%TAP'
            ) UNION ALL (
                SELECT (CASE WHEN arrCriteria.criteriaType=10 THEN 'Final' WHEN arrCriteria.criteriaType=1 THEN 'Exam' ELSE arrReport.reportName END) as reportTerm, gradeID as grade, 'Standard' as gradeType, (CASE WHEN arrCriteria.criteriaType=10 AND gradeID >= 50.0 THEN gibbonCourse.credits WHEN gradeID = '' THEN '' ELSE 0 END) as creditsAwarded, gibbonCourse.name as courseName, gibbonCourse.nameShort as courseNameShort, gibbonCourseClass.gibbonCourseClassID, (CASE WHEN gibbonCourse.orderBy > 0 THEN gibbonCourse.orderBy ELSE 80 end) as courseOrder
                FROM gibbonCourse
                JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseID=gibbonCourse.gibbonCourseID)
                JOIN gibbonCourseClassPerson ON (gibbonCourseClassPerson.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID AND gibbonCourseClassPerson.role='Student')
                LEFT JOIN arrCriteria ON (gibbonCourse.gibbonCourseID=arrCriteria.subjectID AND (arrCriteria.criteriaType=7 OR arrCriteria.criteriaType=10) )
                LEFT JOIN arrReportGrade ON (arrReportGrade.criteriaID=arrCriteria.criteriaID AND arrReportGrade.studentID = gibbonCourseClassPerson.gibbonPersonID )
                LEFT JOIN arrReport ON (arrReport.reportID=arrReportGrade.reportID AND arrReport.schoolYearID=gibbonCourse.gibbonSchoolYearID )
                WHERE gibbonCourse.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonCourse.gibbonSchoolYearID=013
                AND gibbonCourseClassPerson.gibbonPersonID=:gibbonPersonID
                AND gibbonCourseClass.reportable='Y'
                AND gibbonCourse.nameShort NOT LIKE '%ECA%'
                AND gibbonCourse.nameShort NOT LIKE '%HOMEROOM%'
                AND gibbonCourse.nameShort NOT LIKE '%Advisor%'
                AND gibbonCourse.nameShort NOT LIKE '%TAP'
            ) UNION ALL (
                SELECT (CASE WHEN arrCriteria.criteriaType=4 THEN 'Final' WHEN arrCriteria.criteriaType=1 THEN 'Exam' ELSE arrReport.reportName END) as reportTerm, gradeID as grade, 'Standard' as gradeType, (CASE WHEN arrCriteria.criteriaType=4 AND gradeID >= 50.0 THEN gibbonCourse.credits WHEN gradeID = '' THEN '' ELSE 0 END) as creditsAwarded, gibbonCourse.name as courseName, gibbonCourse.nameShort as courseNameShort, gibbonCourseClass.gibbonCourseClassID, (CASE WHEN gibbonCourse.orderBy > 0 THEN gibbonCourse.orderBy ELSE 80 end) as courseOrder
                FROM gibbonCourse
                JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseID=gibbonCourse.gibbonCourseID)
                JOIN gibbonCourseClassPerson ON (gibbonCourseClassPerson.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID AND gibbonCourseClassPerson.role='Student')
                LEFT JOIN arrCriteria ON (gibbonCourse.gibbonCourseID=arrCriteria.subjectID AND (arrCriteria.criteriaType=2 OR arrCriteria.criteriaType=4 OR arrCriteria.criteriaType=1) )
                LEFT JOIN arrReportGrade ON (arrReportGrade.criteriaID=arrCriteria.criteriaID AND arrReportGrade.studentID = gibbonCourseClassPerson.gibbonPersonID )
                LEFT JOIN arrReport ON (arrReport.reportID=arrReportGrade.reportID AND arrReport.schoolYearID=gibbonCourse.gibbonSchoolYearID )
                WHERE gibbonCourse.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonCourse.gibbonSchoolYearID<013
                AND gibbonCourseClassPerson.gibbonPersonID=:gibbonPersonID
                AND gibbonCourseClass.reportable='Y'
                AND gibbonCourse.nameShort NOT LIKE '%ECA%'
                AND gibbonCourse.nameShort NOT LIKE '%HOMEROOM%'
                AND gibbonCourse.nameShort NOT LIKE '%Advisor%'
                AND gibbonCourse.nameShort NOT LIKE '%TAP'
            )  UNION ALL (
            SELECT DISTINCT reportTerm, grade, gradeType, creditsAwarded, gibbonCourse.name as courseName, gibbonCourse.nameShort as courseNameShort, '', (CASE WHEN gibbonCourse.orderBy > 0 THEN gibbonCourse.orderBy ELSE 80 end) as courseOrder
                FROM arrLegacyGrade
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=arrLegacyGrade.gibbonCourseID)
                WHERE arrLegacyGrade.gibbonPersonID = :gibbonPersonID
                AND arrLegacyGrade.gibbonSchoolYearID= :gibbonSchoolYearID
                ) ORDER BY courseOrder, courseNameShort";

        return $this->pdo->executeQuery($data, $sql);
    }

    public function selectStudentEnrolmentByStudent($gibbonPersonID, $yearStart = 7, $yearEnd = 12 ) {
        $data = array(
            'gibbonPersonID' => $gibbonPersonID,
            'yearStart' => $yearStart,
            'yearEnd' => $yearEnd
        );

        $sql = "(SELECT gibbonYearGroup.name as yearGroupName, SUBSTRING(gibbonYearGroup.nameShort, 2) as gradeLevel, teacher.surname as teacherSurname, teacher.preferredName as teacherPreferredName, teacher.title as teacherTitle, gibbonSchoolYear.name as schoolYearName, gibbonSchoolYear.status as schoolYearStatus, gibbonSchoolYear.gibbonSchoolYearID as schoolYearID, gibbonSchoolYear.sequenceNumber
                FROM gibbonPerson
                JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonPersonID = gibbonPerson.gibbonPersonID)
                JOIN gibbonFormGroup ON (gibbonFormGroup.gibbonFormGroupID=gibbonStudentEnrolment.gibbonFormGroupID)
                JOIN gibbonYearGroup ON (gibbonYearGroup.gibbonYearGroupID=gibbonStudentEnrolment.gibbonYearGroupID)
                JOIN gibbonPerson AS teacher ON (teacher.gibbonPersonID=gibbonFormGroup.gibbonPersonIDTutor)
                JOIN gibbonSchoolYear ON (gibbonStudentEnrolment.gibbonSchoolYearID=gibbonSchoolYear.gibbonSchoolYearID)
                WHERE gibbonPerson.gibbonPersonID = :gibbonPersonID
                AND CAST(SUBSTRING(gibbonYearGroup.nameShort, 2) AS UNSIGNED) >= :yearStart
                AND CAST(SUBSTRING(gibbonYearGroup.nameShort, 2) AS UNSIGNED) <= :yearEnd) UNION
            (SELECT '' as yearGroupName, '' as gradeLevel, '' as teacherSurname, '' as teacherPreferredName, '' as teacherTitle, gibbonSchoolYear.name as schoolYearName, gibbonSchoolYear.status as schoolYearStatus, gibbonSchoolYear.gibbonSchoolYearID as schoolYearID, gibbonSchoolYear.sequenceNumber
                FROM gibbonPerson
                JOIN arrLegacyGrade ON (arrLegacyGrade.gibbonPersonID=gibbonPerson.gibbonPersonID)
                JOIN gibbonSchoolYear ON (arrLegacyGrade.gibbonSchoolYearID=gibbonSchoolYear.gibbonSchoolYearID)
                LEFT JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID && gibbonStudentEnrolment.gibbonSchoolYearID=gibbonSchoolYear.gibbonSchoolYearID)
                WHERE gibbonPerson.gibbonPersonID = :gibbonPersonID
                AND gibbonStudentEnrolment.gibbonStudentEnrolmentID IS NULL
                GROUP BY gibbonSchoolYear.gibbonSchoolYearID )
                ORDER BY sequenceNumber DESC";
        return $this->pdo->executeQuery($data, $sql);
    }

    public function processStudentGrades($enrolment, $gradesData) {

        $grades = array();

        foreach ($gradesData as $row) {
            //if ($this->isTranscriptCourse($row['courseNameShort']) == false) continue;

            $courseGrades = (isset($grades[$row['courseNameShort']]))? $grades[$row['courseNameShort']] : array('Sem1-Mid' => '', 'Sem2-Mid' => '', 'Final' => '', 'credits' => '' );

            // Remove the extra Grade N fron the start of course names
            $courseName = (!empty($enrolment['yearGroupName']))? str_replace($enrolment['yearGroupName'].' ', '', $row['courseName']) : $row['courseName'];

            // Tranfer Credits
            if ($row['gradeType'] == 'Transfer' && stristr($courseName, 'transfer') === false ) {
                $courseName .= (intval($row['creditsAwarded']) > 1)? ' Transfer Credits Awarded' : ' Transfer Credit Awarded';
            }

            // Retoactive credits
            if ($row['gradeType'] == 'Retroactive' && stristr($courseName, 'retroactive') === false) {
                $courseName .= (intval($row['creditsAwarded']) > 1)? ' Retroactive Credits Awarded' : ' Retroactive Credit Awarded';
            }

            // Change in report term names
            if ($row['reportTerm'] == 'Term 1 Mid') $row['reportTerm'] = 'Sem1-Mid';
            if ($row['reportTerm'] == 'Term 1 Interim') $row['reportTerm'] = 'Sem1-Mid';
            if ($row['reportTerm'] == 'Term 1 End') $row['reportTerm'] = 'Sem1-End';
            if ($row['reportTerm'] == 'Term 1') $row['reportTerm'] = 'Sem1-End';
            if ($row['reportTerm'] == 'Term 2 Mid') $row['reportTerm'] = 'Sem2-Mid';
            if ($row['reportTerm'] == 'Term 2 Interim') $row['reportTerm'] = 'Sem2-Mid';
            if ($row['reportTerm'] == 'Term 2 End') $row['reportTerm'] = 'Sem2-End';
            if ($row['reportTerm'] == 'Term 2') $row['reportTerm'] = 'Sem2-End';

            $courseGrades['courseName'] = $courseName;
            $courseGrades['courseNameShort'] = $row['courseNameShort'];
            $courseGrades['gibbonCourseClassID'] = $row['gibbonCourseClassID'];

            $grade = (is_numeric($row['grade']))? round($row['grade']) : $row['grade'];
            $grade = (stripos($row['grade'], '%') !== false)? intval($row['grade']) : $row['grade'];
            $courseGrades[ $row['reportTerm'] ] = $grade;

            if ($row['reportTerm'] == 'Final') {
                $defaultCredits = ($enrolment['gradeLevel'] >= 10 && !empty($grade) && intval($grade) < 50)? '0' : '';
                $credits = (is_numeric($row['creditsAwarded']) && !empty($row['creditsAwarded']) && $enrolment['gradeLevel'] >= 10)? intval($row['creditsAwarded']) : $defaultCredits;

                $courseGrades['credits'] = $credits;
            }

            $grades[$row['courseNameShort']] = $courseGrades;
        }

        return $grades;
    }

    protected function isTranscriptCourse( $courseCode ) {

        if (strstr($courseCode, 'ECA') !== false) return false;
        if (strstr($courseCode, 'MAM-') !== false) return false;
        if (strstr($courseCode, 'MTM') !== false) return false;
        if (strstr($courseCode, 'ERP-') !== false) return false;
        if (strstr($courseCode, 'ADD-') !== false) return false;
        if (strstr($courseCode, 'OUT-') !== false) return false;
        if (strstr($courseCode, 'FIN-') !== false) return false;
        if (strstr($courseCode, 'ENV-') !== false) return false;
        if (strstr($courseCode, 'SPRT') !== false) return false;
        if ($courseCode == 'STUDY') return false;
        if ($courseCode == 'CHI') return false;

        return true;
    }
}
