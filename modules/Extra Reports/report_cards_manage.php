<?php
use Gibbon\Forms\Form;
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Module\ExtraReports\ReportCardGenerator;

// Module includes
include __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Extra Reports/report_cards_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Manage Report Cards'));

    // Add multiple students
    $form = Form::create('reportCards', $session->get('absoluteURL').'/modules/Extra Reports/report_cards_generate.php');
    $form->setClass('noIntBorder fullWidth standardForm');

    $form->addHiddenValue('address', $session->get('address'));

    $row = $form->addRow();
        $row->addLabel('reportingPeriod', __('Reporting Period'));
        $row->addSelect('reportingPeriod')
            ->fromArray(['T1' => 'Term 1', 'T2' => 'Term 2', 'T3' => 'Term 3'])
            ->required();

    // Get list of students with their form groups
    $sql = "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.surname, gibbonPerson.preferredName, gibbonFormGroup.name AS formGroup 
            FROM gibbonPerson 
            JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID) 
            JOIN gibbonFormGroup ON (gibbonFormGroup.gibbonFormGroupID=gibbonStudentEnrolment.gibbonFormGroupID) 
            WHERE gibbonPerson.status='Full' 
            AND gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID 
            ORDER BY formGroup, surname, preferredName";
    
    $result = $pdo->select($sql, ['gibbonSchoolYearID' => $session->get('gibbonSchoolYearID')]);
    $students = ($result->rowCount() > 0)? $result->fetchAll() : array();
    $studentArray = array_combine(array_column($students, 'gibbonPersonID'), array_map(function($item) {
        return $item['formGroup'].' - '.$item['surname'].', '.$item['preferredName'];
    }, $students));

    $row = $form->addRow();
        $row->addLabel('students', __('Students'));
        $row->addSelect('students')
            ->fromArray($studentArray)
            ->selectMultiple()
            ->required();

    $row = $form->addRow();
        $row->addLabel('template', __('Report Template'));
        $row->addSelect('template')
            ->fromArray([
                'preKindergarten' => 'Pre-Kindergarten',
                'kindergarten' => 'Kindergarten',
                'gradeOne' => 'Grade One'
            ])
            ->required();

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit('Generate Reports');

    echo $form->getOutput();

    // QUERY
    $criteria = array();
    $criteria['gibbonSchoolYearID'] = $session->get('gibbonSchoolYearID');

    $sql = "SELECT assessment.reportingPeriod, gibbonPerson.gibbonPersonID, gibbonPerson.surname, 
            gibbonPerson.preferredName, gibbonFormGroup.name as formGroup, COUNT(assessment.assessmentID) as assessmentCount
            FROM extraReportAssessment as assessment
            JOIN gibbonPerson ON (assessment.gibbonPersonID=gibbonPerson.gibbonPersonID)
            JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID)
            JOIN gibbonFormGroup ON (gibbonFormGroup.gibbonFormGroupID=gibbonStudentEnrolment.gibbonFormGroupID)
            WHERE gibbonPerson.status='Full'
            AND gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID
            GROUP BY assessment.gibbonPersonID, assessment.reportingPeriod, gibbonFormGroup.name
            ORDER BY assessment.reportingPeriod, gibbonFormGroup.name, gibbonPerson.surname, gibbonPerson.preferredName";

    $result = $pdo->select($sql, $criteria);

    // Data Table
    $table = DataTable::create('reportCards');
    $table->setTitle(__('Recent Report Cards'));

    $table->addColumn('reportingPeriod', __('Term'));
    $table->addColumn('formGroup', __('Form Group'));
    $table->addColumn('student', __('Student'))
        ->format(function($row) {
            return Format::name('', $row['preferredName'], $row['surname'], 'Student', true);
        });
    $table->addColumn('assessmentCount', __('Assessments'))
        ->format(function($row) {
            return $row['assessmentCount'];
        });

    $table->addActionColumn()
        ->addParam('gibbonPersonID')
        ->addParam('reportingPeriod')
        ->format(function ($row, $actions) {
            $actions->addAction('view', __('View'))
                ->setURL('/modules/Extra Reports/report_cards_view.php');
            $actions->addAction('edit', __('Edit'))
                ->setURL('/modules/Extra Reports/report_cards_edit.php');
            $actions->addAction('delete', __('Delete'))
                ->setURL('/modules/Extra Reports/report_cards_delete.php');
        });

    echo $table->render($result->toDataSet());
}
?>
