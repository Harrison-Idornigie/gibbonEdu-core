<?php
use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Domain\School\SchoolYearTermGateway;

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
    $gibbonSchoolYearTermID = $_GET['gibbonSchoolYearTermID'] ?? '';

    // Get available terms for the current school year
    $termGateway = $container->get(SchoolYearTermGateway::class);
    $terms = $termGateway->selectTermsBySchoolYear((int) $session->get('gibbonSchoolYearID'))->fetchAll();
    
    $termOptions = array_reduce($terms, function($group, $item) {
        $group[$item['gibbonSchoolYearTermID']] = $item['name'];
        return $group;
    }, []);

    // Filter form
    $form = Form::create('filter', $session->get('absoluteURL').'/index.php', 'get');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('q', '/modules/'.$session->get('module').'/report_cards_view.php');

    $row = $form->addRow();
        $row->addLabel('gibbonSchoolYearTermID', __('Term'));
        $row->addSelect('gibbonSchoolYearTermID')
            ->fromArray($termOptions)
            ->selected($gibbonSchoolYearTermID)
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
                gibbonFormGroup.name as formGroup, assessment.gibbonSchoolYearTermID,
                assessment.template, assessment.assessmentData, assessment.comment, 
                assessment.extraReportAssessmentID, assessment.timestamp
            FROM gibbonPerson 
            JOIN extraReportAssessment as assessment ON (assessment.gibbonPersonIDStudent=gibbonPerson.gibbonPersonID)
            JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID)
            JOIN gibbonFormGroup ON (gibbonFormGroup.gibbonFormGroupID=gibbonStudentEnrolment.gibbonFormGroupID)
            WHERE gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID 
            AND gibbonPerson.status='Full'";

    if (!empty($gibbonFormGroupID)) {
        $data['gibbonFormGroupID'] = $gibbonFormGroupID;
        $sql .= " AND gibbonStudentEnrolment.gibbonFormGroupID=:gibbonFormGroupID";
    }

    if (!empty($gibbonSchoolYearTermID)) {
        $data['gibbonSchoolYearTermID'] = $gibbonSchoolYearTermID;
        $sql .= " AND assessment.gibbonSchoolYearTermID=:gibbonSchoolYearTermID";
    }

    if (!empty($search)) {
        $data['search1'] = "%$search%";
        $data['search2'] = "%$search%";
        $data['search3'] = "%$search%";
        $sql .= " AND (preferredName LIKE :search1 OR surname LIKE :search2 OR assessment.template LIKE :search3)";
    }

    $sql .= " ORDER BY assessment.gibbonSchoolYearTermID, surname, preferredName";
    $result = $pdo->select($sql, $data);

    // Render table
    $table = DataTable::create('assessments');
    $table->setTitle(__('Assessments'));

    $table->addColumn('formGroup', __('Form Group'));
    
    $table->addColumn('student', __('Student'))
        ->format(function($row) {
            return Format::name('', $row['preferredName'], $row['surname'], 'Student', true);
        });
        
    $table->addColumn('gibbonSchoolYearTermID', __('Term'))
        ->format(function($row) use ($termGateway) {
            $termData = $termGateway->getByID($row['gibbonSchoolYearTermID']);
            return $termData['name'] ?? $row['gibbonSchoolYearTermID'];
        });
        
    $table->addColumn('template', __('Template'))
        ->format(function($row) {
            return ucfirst($row['template']);
        });
        
    $table->addColumn('assessmentData', __('Assessment Data'))
        ->format(function($row) {
            if (empty($row['assessmentData'])) return '';
            
            $jsonData = json_decode($row['assessmentData'], true);
            if (!$jsonData) return '';
            
            // Get assessment scores for mapping numbers back to text
            $scoreMap = getAssessmentScores();
            
            $output = '';
            foreach ($jsonData as $section => $subsections) {
                // Add section header
                $output .= Format::tag(ucfirst($section), 'message') . '<br/>';
                
                foreach ($subsections as $subsection => $data) {
                    if (isset($data['score'])) {
                        // Map numeric score back to text
                        $scoreText = $scoreMap[$data['score']] ?? '';
                        
                        // Determine score class based on numeric value
                        $scoreClass = match($data['score']) {
                            '1' => 'error',
                            '2' => 'warning',
                            '3' => 'success',
                            default => 'message'
                        };
                        
                        // Display subsection and score
                        $output .= Format::small(
                            ucfirst($subsection) . ': ' . Format::tag($scoreText, $scoreClass)
                        );
                        
                        // Add comment if available
                        if (!empty($data['comment'])) {
                            $output .= '<br/>' . Format::small(
                                'Comment: ' . $data['comment'], 
                                'text-gray-600'
                            );
                        }
                        $output .= '<br/>';
                    }
                }
                $output .= '<br/>';
            }
            return $output;
        });
        
    $table->addColumn('comment', __('Comment'));
    
    $table->addColumn('timestamp', __('Last Updated'))
        ->format(Format::using('dateTime', 'timestamp'));

    $table->addActionColumn()
        ->addParam('gibbonPersonID')
        ->addParam('gibbonSchoolYearTermID')
        ->addParam('template')
        ->format(function ($row, $actions) use ($session) {
            $actions->addAction('view', __('View'))
                ->setURL('/modules/Extra Reports/report_cards_enter_student.php')
                ->setIcon('page_white_text');

            if (isActionAccessible($guid, $connection2, '/modules/Extra Reports/report_cards_enter.php')) {
                $actions->addAction('edit', __('Edit'))
                    ->setURL('/modules/Extra Reports/report_cards_enter_student.php')
                    ->setIcon('config');

                $actions->addAction('delete', __('Delete'))
                    ->setURL('/modules/Extra Reports/report_cards_delete.php')
                    ->setIcon('garbage')
                    ->setClass('text-red-600')
                    ->modalWindow()
                    ->addParam('extraReportAssessmentID', $row['extraReportAssessmentID']);
            }
        });

    echo $table->render($result->toDataSet());
}
?>
