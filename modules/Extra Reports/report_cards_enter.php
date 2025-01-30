<?php
use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\School\SchoolYearTermGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Extra Reports/report_cards_enter.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Enter Assessments'));

    $reportingPeriod = $_GET['reportingPeriod'] ?? '';
    $formGroup = $_GET['formGroup'] ?? '';
    $template = $_GET['template'] ?? '';
    $search = $_GET['search'] ?? '';

    // Filter form
    $form = Form::create('filter', $session->get('absoluteURL').'/index.php', 'get');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    
    $form->addHiddenValue('q', '/modules/'.$session->get('module').'/report_cards_enter.php');
    
    // Get available terms for the current school year
    $termGateway = $container->get(SchoolYearTermGateway::class);
    $terms = $termGateway->selectTermsBySchoolYear((int) $session->get('gibbonSchoolYearID'))->fetchAll();
    
    $termOptions = array_reduce($terms, function($group, $item) {
        $group[$item['gibbonSchoolYearTermID']] = $item['name'];
        return $group;
    }, []);
    
    $row = $form->addRow();
        $row->addLabel('reportingPeriod', __('Term'));
        $row->addSelect('reportingPeriod')
            ->fromArray($termOptions)
            ->placeholder()
            ->required()
            ->selected($reportingPeriod);
    
    $row = $form->addRow();
        $row->addLabel('formGroup', __('Form Group'));
        $row->addSelectFormGroup('formGroup', $session->get('gibbonSchoolYearID'))
            ->placeholder()
            ->required()
            ->selected($formGroup);

    $row = $form->addRow();
        $row->addLabel('template', __('Template'));
        $row->addSelect('template')
            ->fromArray(getReportCardTemplates())
            ->placeholder()
            ->required()
            ->selected($template);

    $row = $form->addRow();
        $row->addLabel('search', __('Search'))
            ->description(__('Search by student name'));
        $row->addTextField('search')
            ->setValue($search);

    $row = $form->addRow();
        $row->addSearchSubmit($session->get('absoluteURL'), __('Clear Filters'));

    echo $form->getOutput();

    // Check if required filters are set
    if (!empty($formGroup) && !empty($reportingPeriod) && !empty($template)) {
        // Query students
        $data = [
            'gibbonFormGroupID' => $formGroup,
            'gibbonSchoolYearID' => $session->get('gibbonSchoolYearID')
        ];
        
        $sql = "SELECT gibbonPerson.gibbonPersonID, surname, preferredName 
                FROM gibbonPerson 
                JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID) 
                WHERE gibbonFormGroupID=:gibbonFormGroupID 
                AND gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID 
                AND gibbonPerson.status='Full'";

        if (!empty($search)) {
            $data['search'] = '%'.$search.'%';
            $sql .= " AND (preferredName LIKE :search OR surname LIKE :search)";
        }
        
        $sql .= " ORDER BY surname, preferredName";
        
        $students = $pdo->select($sql, $data);

        if ($students && $students->rowCount() > 0) {
            // Render table
            $table = DataTable::create('students');
            $table->setTitle(__('Students'));

            $table->addColumn('name', __('Name'))
                ->format(Format::using('name', ['', 'preferredName', 'surname', 'Student', false]));

            // Add status column to show if student has been assessed
            $table->addColumn('status', __('Status'))
                ->format(function($row) use ($pdo, $reportingPeriod) {
                    if (empty($reportingPeriod)) return Format::tag(__('Not Assessed'), 'warning');
                    
                    $sql = "SELECT extraReportAssessmentID 
                            FROM extraReportAssessment 
                            WHERE gibbonPersonIDStudent=:gibbonPersonID 
                            AND gibbonSchoolYearTermID=:gibbonSchoolYearTermID";
                    
                    $data = [
                        'gibbonPersonID' => $row['gibbonPersonID'],
                        'gibbonSchoolYearTermID' => $reportingPeriod
                    ];
                    
                    $result = $pdo->select($sql, $data);
                    
                    return ($result && $result->rowCount() > 0)
                        ? Format::tag(__('Assessed'), 'success')
                        : Format::tag(__('Not Assessed'), 'warning');
                });

            $table->addActionColumn()
                ->addParam('q', '/modules/'.$session->get('module').'/report_cards_enter_student.php')
                ->addParam('gibbonPersonID')
                ->addParam('gibbonSchoolYearTermID', $reportingPeriod)
                ->addParam('template', $template)
                ->format(function ($row, $actions) use ($session) {
                    $actions->addAction('edit', __('Enter Assessment'))
                        ->setURL('/modules/'.$session->get('module').'/report_cards_enter_student.php');
                });

            echo $table->render($students->toDataSet());
        } else {
            echo "<div class='error'>";
            echo __('No students found.');
            echo "</div>";
        }
    } else if (isset($_GET['Go'])) {
        echo "<div class='error'>";
        echo __('Please select all required fields: Term, Form Group, and Template.');
        echo "</div>";
    }
}
?>
