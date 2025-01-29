<?php
use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Extra Reports/report_cards_enter.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Enter Assessments'));

    // Get URL parameters
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $search = $_GET['search'] ?? '';
    $gibbonFormGroupID = $_GET['gibbonFormGroupID'] ?? '';
    $reportingPeriod = $_GET['reportingPeriod'] ?? '';
    $template = $_GET['template'] ?? '';

    // Filter form
    $form = Form::create('filter', $session->get('absoluteURL').'/index.php', 'get');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('q', '/modules/'.$session->get('module').'/report_cards_enter.php');

    $row = $form->addRow();
        $row->addLabel('reportingPeriod', __('Reporting Period'));
        $row->addSelect('reportingPeriod')
            ->fromArray(getReportingPeriods())
            ->selected($reportingPeriod)
            ->required();

    $row = $form->addRow();
        $row->addLabel('gibbonFormGroupID', __('Form Group'));
        $row->addSelectFormGroup('gibbonFormGroupID', $gibbonSchoolYearID)
            ->selected($gibbonFormGroupID)
            ->required()
            ->placeholder();

    $row = $form->addRow();
        $row->addLabel('search', __('Search'))
            ->description(__('Preferred Name, Surname'));
        $row->addTextField('search')
            ->setValue($search);

    $row = $form->addRow();
        $row->addLabel('template', __('Template'));
        $row->addSelect('template')
            ->fromArray(getReportCardTemplates())
            ->selected($template)
            ->required();

    $row = $form->addRow();
        $row->addSearchSubmit($session);

    echo $form->getOutput();

    // Show student list if form group is selected
    if (!empty($gibbonFormGroupID)) {
        // Build query
        $data = ['gibbonFormGroupID' => $gibbonFormGroupID, 'gibbonSchoolYearID' => $gibbonSchoolYearID];
        $sql = "SELECT DISTINCT gibbonPerson.gibbonPersonID, surname, preferredName, gibbonFormGroup.name as formGroup,
                    (SELECT COUNT(*) FROM extraReportAssessment 
                     WHERE studentID=gibbonPerson.gibbonPersonID 
                     AND reportingPeriod=:reportingPeriod) as assessmentCount
                FROM gibbonPerson 
                JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID)
                JOIN gibbonFormGroup ON (gibbonFormGroup.gibbonFormGroupID=gibbonStudentEnrolment.gibbonFormGroupID)
                WHERE gibbonStudentEnrolment.gibbonFormGroupID=:gibbonFormGroupID 
                AND gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonPerson.status='Full'";

        if (!empty($search)) {
            $data['search1'] = "%$search%";
            $data['search2'] = "%$search%";
            $sql .= " AND (preferredName LIKE :search1 OR surname LIKE :search2)";
        }

        $data['reportingPeriod'] = $reportingPeriod;
        $sql .= " ORDER BY surname, preferredName";
        
        $result = $pdo->select($sql, $data);

        // Render table
        $table = DataTable::create('students');
        $table->setTitle(__('Students'));

        $table->addColumn('formGroup', __('Form Group'));
        $table->addColumn('student', __('Student'))
            ->format(function($row) {
                return Format::name('', $row['preferredName'], $row['surname'], 'Student', true);
            });
        
        $table->addColumn('status', __('Status'))
            ->format(function($row) {
                if ($row['assessmentCount'] > 0) {
                    return Format::tag(__('Assessed'), 'success');
                } else {
                    return Format::tag(__('Not Assessed'), 'warning');
                }
            });

        $table->addActionColumn()
            ->addParam('q', '/modules/'.$session->get('module').'/report_cards_enter_student.php')
            ->addParam('gibbonPersonID')
            ->addParam('reportingPeriod', $reportingPeriod)
            ->addParam('template', $template)
            ->format(function ($row, $actions) {
                if ($row['assessmentCount'] > 0) {
                    $actions->addAction('edit', __('Edit Assessment'))
                        ->setIcon('edit')
                        ->setURL('/index.php');
                } else {
                    $actions->addAction('add', __('Enter Assessment'))
                        ->setIcon('page_new')
                        ->setURL('/index.php');
                }
            });

        echo $table->render($result->toDataSet());
    }
}
?>
