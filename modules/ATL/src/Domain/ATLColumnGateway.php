<?php
namespace Gibbon\Module\ATL\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * Technician Gateway
 *
 * @version v20
 * @since   v20
 */
class ATLColumnGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'atlColumn';
    private static $primaryKey = 'atlColumnID';
    private static $searchableColumns = ['atlColumnID', 'issueName', 'description'];

    public function getATLRubricByStudent($gibbonSchoolYearID, $gibbonPersonID, $roleCategory = 'Other')
    {
        $data = array('gibbonSchoolYearID' => $gibbonSchoolYearID, 'gibbonPersonID' => $gibbonPersonID);
        $sql = "SELECT
                gibbonPerson.gibbonPersonID,
                surname,
                preferredName,
                gibbonCourseClass.gibbonCourseClassID,
                atlColumn.gibbonRubricID
            FROM gibbonPerson
                JOIN gibbonStudentEnrolment ON (gibbonPerson.gibbonPersonID=gibbonStudentEnrolment.gibbonPersonID)
                JOIN gibbonFormGroup ON (gibbonStudentEnrolment.gibbonFormGroupID=gibbonFormGroup.gibbonFormGroupID)
                JOIN gibbonCourseClassPerson ON (gibbonCourseClassPerson.gibbonPersonID=gibbonPerson.gibbonPersonID)
                JOIN gibbonCourseClass ON (gibbonCourseClassPerson.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID)
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID AND gibbonCourse.gibbonSchoolYearID=gibbonStudentEnrolment.gibbonSchoolYearID)
                JOIN atlColumn ON (atlColumn.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID)
                JOIN gibbonRubric ON (gibbonRubric.gibbonRubricID=atlColumn.gibbonRubricID)
            WHERE status='Full'
                AND gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonPerson.gibbonPersonID=:gibbonPersonID 
                AND gibbonRubric.active='Y'";

        if ($roleCategory != 'Staff') {
            $data['today']  = date('Y-m-d');
            $sql .= " AND atlColumn.completeDate<=:today ";
        }

        $sql .= "
                AND gibbonCourseClassPerson.role='Student'
                AND gibbonCourseClass.reportable='Y' 
                AND gibbonCourseClassPerson.reportable='Y'
            ORDER BY atlColumn.completeDate DESC
            LIMIT 0, 1";

        return $this->db()->selectOne($sql, $data);
    }

    public function selectATLEntriesByStudent($gibbonSchoolYearID, $gibbonPersonID, $roleCategory = 'Other')
    {
        $data = ['gibbonPersonID' => $gibbonPersonID, 'gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT DISTINCT 
            atlColumn.*, 
            atlEntry.*, 
            gibbonCourse.name AS course,
            gibbonCourseClass.nameShort AS class,
            gibbonPerson.dateStart 
        FROM gibbonCourse 
            JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseID=gibbonCourse.gibbonCourseID) 
            JOIN gibbonCourseClassPerson ON (gibbonCourseClassPerson.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID) JOIN atlColumn ON (atlColumn.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID) 
            JOIN atlEntry ON (atlEntry.atlColumnID=atlColumn.atlColumnID) 
            JOIN gibbonPerson ON (atlEntry.gibbonPersonIDStudent=gibbonPerson.gibbonPersonID) 
        WHERE gibbonCourseClassPerson.gibbonPersonID=:gibbonPersonID 
            AND atlEntry.gibbonPersonIDStudent=:gibbonPersonID 
            AND gibbonSchoolYearID=:gibbonSchoolYearID ";

        if ($roleCategory != 'Staff') {
            $data['today']  = date('Y-m-d');
            $sql .= " AND atlColumn.completeDate<=:today ";
        }
        
        $sql .= " AND gibbonCourseClassPerson.role='Student'
            AND gibbonCourseClass.reportable='Y' 
            AND gibbonCourseClassPerson.reportable='Y' 
        ORDER BY atlColumn.completeDate DESC, gibbonCourse.nameShort, gibbonCourseClass.nameShort";

        return $this->db()->select($sql, $data);
    }
    
}
