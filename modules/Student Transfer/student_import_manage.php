<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
*/

use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Domain\DataSet;
use Gibbon\Module\StudentTransfer\Domain\TransferImportGateway;

if (isActionAccessible($guid, $connection2, "/modules/Student Transfer/student_import_manage.php") == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('Manage Student Imports'));

    // Add Import button
    echo "<div class='flex justify-between items-center mb-4'>";
    echo "<div></div>"; // Empty div for flex spacing
    echo "<a href='".$session->get('absoluteURL')."/index.php?q=/modules/Student Transfer/transfer_manage_import.php' class='button'>";
    echo "<img src='".$session->get('absoluteURL')."/themes/".$session->get('gibbonThemeName')."/img/icons/plus.png' class='w-4 h-4 mr-1'>";
    echo __('Import Transferred Student');
    echo "</a>";
    echo "</div>";

    $transferImportGateway = $container->get(TransferImportGateway::class);

    // Get user data for display
    $sql = "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.title, gibbonPerson.surname, gibbonPerson.preferredName 
            FROM gibbonPerson 
            WHERE status='Full'";
    $users = $pdo->select($sql)->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_UNIQUE);

    $criteria = $transferImportGateway->newQueryCriteria()
        ->sortBy(['timestampCreated'], 'DESC')
        ->fromPOST();

    $imports = $transferImportGateway->queryImports($criteria);

    $table = DataTable::create('studentImports');
    $table->setTitle(__('Student Imports'));

    $table->addColumn('timestamp', __('Date'))
        ->format(function($values) {
            return Format::dateTime($values['timestampCreated']);
        });

    $table->addColumn('user', __('User'))
        ->format(function($values) use ($users) {
            $user = $users[$values['gibbonPersonIDCreated']] ?? [];
            return Format::name('', $user['preferredName'] ?? '', $user['surname'] ?? '', 'Staff', false, true);
        });

    $table->addColumn('schoolNameFrom', __('From School'));

    $table->addColumn('student', __('Student'))
        ->format(function($values) {
            if (empty($values['studentData'])) return '';
            $data = json_decode($values['studentData'], true);
            return Format::name('', $data['personal']['firstName'], $data['personal']['surname'], 'Student', false, true);
        });

    $table->addColumn('status', __('Status'))
        ->format(function($values) {
            if ($values['status'] == 'Complete') {
                return Format::tag(__('Success'), 'success');
            } else if ($values['status'] == 'Failed') {
                return Format::tag(__('Failed'), 'error');
            } else {
                return Format::tag(__($values['status']), 'message');
            }
        });

    $table->addColumn('progress', __('Progress'))
        ->format(function($values) {
            if (empty($values['importProgress'])) return '';
            
            $progress = json_decode($values['importProgress'], true);
            $html = '<div class="flex flex-col gap-1">';
            $html .= '<div class="text-xs '.($progress['status'] == 'Complete' ? 'text-green-600' : 'text-gray-600').'">';
            $html .= ($progress['status'] == 'Complete' ? '✓' : '○').' '.$progress['stage'];
            $html .= '</div>';
            $html .= '</div>';
            return $html;
        });

    $table->addActionColumn()
        ->addParam('gibbonStudentTransferImportID')
        ->format(function($values, $actions) {
            $actions->addAction('view', __('View'))
                ->setURL('/modules/Student Transfer/student_import_history_view.php')
                ->modalWindow(800, 550);

            if (!empty($values['importProgress'])) {
                $progress = json_decode($values['importProgress'], true);
                if (!empty($progress['applicationID'])) {
                    $actions->addAction('application', __('Application'))
                        ->setURL('/index.php')
                        ->addParam('q', '/modules/Students/applicationForm_manage_edit.php')
                        ->addParam('gibbonApplicationFormID', $progress['applicationID'])
                        ->setIcon('attendance');
                }
            }
        });

    echo $table->render($imports);
}
