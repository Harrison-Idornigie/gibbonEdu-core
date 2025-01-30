<?php
use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Extra Reports/report_cards_view.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('View Assessments'));

    // Get URL parameters
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $search = $_GET['search'] ?? '';
    $gibbonFormGroupID = $_GET['gibbonFormGroupID'] ?? '';
    $reportingPeriod = $_GET['reportingPeriod'] ?? '';

    // Filter form
    $form = Form::create('filter', $session->get('absoluteURL').'/index.php', 'get');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('q', '/modules/'.$session->get('module').'/report_cards_view.php');

    $row = $form->addRow();
        $row->addLabel('reportingPeriod', __('Reporting Period'));
        $row->addSelect('reportingPeriod')
            ->fromArray(getReportingPeriods())
            ->selected($reportingPeriod)
            ->placeholder();

    $row = $form->addRow();
        $row->addLabel('gibbonFormGroupID', __('Form Group'));
        $row->addSelectFormGroup('gibbonFormGroupID', $gibbonSchoolYearID)
            ->selected($gibbonFormGroupID)
            ->placeholder();

    $row = $form->addRow();
        $row->addLabel('search', __('Search'))
            ->description(__('Student Name, Assessment Item'));
        $row->addTextField('search')
            ->setValue($search);

    $row = $form->addRow();
        $row->addSearchSubmit($session);

    echo $form->getOutput();

    // Build query
    $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID];
    $sql = "SELECT DISTINCT gibbonPerson.gibbonPersonID, surname, preferredName, 
                gibbonFormGroup.name as formGroup, extraReportAssessment.reportingPeriod
            FROM gibbonPerson 
            JOIN extraReportAssessment ON (extraReportAssessment.gibbonPersonID=gibbonPerson.gibbonPersonID)
            JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID)
            JOIN gibbonFormGroup ON (gibbonFormGroup.gibbonFormGroupID=gibbonStudentEnrolment.gibbonFormGroupID)
            WHERE gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID 
            AND gibbonPerson.status='Full'";

    if (!empty($gibbonFormGroupID)) {
        $data['gibbonFormGroupID'] = $gibbonFormGroupID;
        $sql .= " AND gibbonStudentEnrolment.gibbonFormGroupID=:gibbonFormGroupID";
    }

    if (!empty($reportingPeriod)) {
        $data['reportingPeriod'] = $reportingPeriod;
        $sql .= " AND extraReportAssessment.reportingPeriod=:reportingPeriod";
    }

    if (!empty($search)) {
        $data['search1'] = "%$search%";
        $data['search2'] = "%$search%";
        $data['search3'] = "%$search%";
        $sql .= " AND (preferredName LIKE :search1 OR surname LIKE :search2 OR extraReportAssessment.item LIKE :search3)";
    }

    $sql .= " ORDER BY extraReportAssessment.reportingPeriod, surname, preferredName";
    $result = $pdo->select($sql, $data);

    // Render table
    $table = DataTable::create('assessments');
    $table->setTitle(__('Assessments'));

    $table->addColumn('formGroup', __('Form Group'));
    $table->addColumn('student', __('Student'))
        ->format(function($row) {
            return Format::name('', $row['preferredName'], $row['surname'], 'Student', true);
        });
    $table->addColumn('reportingPeriod', __('Reporting Period'));
    $table->addColumn('section', __('Section'))
        ->format(function($row) {
            return ucfirst($row['section']);
        });
    $table->addColumn('item', __('Assessment Item'));
    $table->addColumn('score', __('Score'))
        ->format(function($row) {
            switch($row['score']) {
                case 0:
                    return Format::tag(__('Does not meet'), 'error');
                case 1:
                    return Format::tag(__('Meets some'), 'warning');
                case 2:
                    return Format::tag(__('Meets all'), 'success');
                default:
                    return '';
            }
        });
    $table->addColumn('comment', __('Comment'));
    $table->addColumn('timestamp', __('Last Updated'))
        ->format(Format::using('dateTime', ['timestamp' => 'timestamp']));

    $table->addActionColumn()
        ->addParam('q', '/modules/'.$session->get('module').'/report_cards_view_student.php')
        ->addParam('gibbonPersonID')
        ->addParam('reportingPeriod')
        ->format(function ($row, $actions) {
            $actions->addAction('view', __('View'))
                ->setURL('/modules/'.$session->get('module').'/report_cards_view_student.php')
                ->addParam('gibbonPersonID', $row['gibbonPersonID'])
                ->addParam('reportingPeriod', $row['reportingPeriod']);
        });

    echo $table->render($result->toDataSet());
}
?>
