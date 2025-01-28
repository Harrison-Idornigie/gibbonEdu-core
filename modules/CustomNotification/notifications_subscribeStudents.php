<?php
use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\CustomNotification\Domain\NotificationSubscriptionGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $container->get('db'), '/modules/CustomNotification/notifications_subscribeStudents.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    error_log('Access denied to notifications_subscribeStudents.php for user: ' . $_SESSION[$guid]['gibbonPersonID']);
} else {
    try {
        // Enable error reporting for debugging
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        // Get database connection
        $pdo = $container->get('db');
        
        // Set up form factory
        $form = Form::create('filter', $_SESSION[$guid]['absoluteURL'].'/index.php', 'get');
        $form->setFactory(DatabaseFormFactory::create($pdo));
        
        // Check if required table exists
        $tableCheck = $pdo->prepare("SHOW TABLES LIKE 'CustomNotificationSubscription'");
        $tableCheck->execute();
        if (!$tableCheck->fetch()) {
            throw new Exception('Required table CustomNotificationSubscription does not exist. Please reinstall the module.');
        }

        // Verify user session
        if (empty($_SESSION[$guid]['gibbonPersonID'])) {
            throw new Exception('Invalid user session.');
        }

        $page->breadcrumbs
            ->add(__('Manage Notifications'), 'notifications_manage.php')
            ->add(__('Student Subscriptions'));

        // Get current user
        $gibbonPersonID = $_SESSION[$guid]['gibbonPersonID'];
        
        // FILTER FORM
        $search = $_GET['search'] ?? '';
        $gibbonFormGroupID = $_GET['gibbonFormGroupID'] ?? '';
        $subscriptionStatus = $_GET['subscriptionStatus'] ?? '';
        
        $form->setClass('noIntBorder fullWidth');
        $form->addHiddenValue('q', '/modules/CustomNotification/notifications_subscribeStudents.php');
        
        $row = $form->addRow();
        $col = $row->addColumn()->addClass('inline right');
        
        // Form Group Filter
        $sql = "SELECT gibbonFormGroupID as value, name FROM gibbonFormGroup 
                WHERE gibbonSchoolYearID=(SELECT gibbonSchoolYearID FROM gibbonSchoolYear WHERE status='Current') 
                ORDER BY name";
        $col->addSelect('gibbonFormGroupID')
            ->fromQuery($pdo, $sql)
            ->setClass('mediumWidth')
            ->placeholder('Form Group')
            ->selected($gibbonFormGroupID);
        
        // Student Name Search
        $col->addTextField('search')
            ->setClass('mediumWidth')
            ->setValue($search)
            ->placeholder('Search student name');
        
        // Subscription Status Filter
        $col->addSelect('subscriptionStatus')
            ->setClass('mediumWidth')
            ->fromArray([
                '' => 'All Students',
                'subscribed' => 'Subscribed Only',
                'unsubscribed' => 'Not Subscribed Only'
            ])
            ->placeholder('Filter by status')
            ->selected($subscriptionStatus);
        
        $col->addSubmit('Go');

        echo $form->getOutput();

        // QUERY
        $data = array('gibbonPersonID' => $gibbonPersonID);
        $sql = "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.surname, gibbonPerson.preferredName, 
                       gibbonFormGroup.name as formGroup,
                       (SELECT COUNT(*) FROM CustomNotificationSubscription 
                        WHERE gibbonPersonID=:gibbonPersonID 
                        AND targetPersonID=gibbonPerson.gibbonPersonID
                        AND eventType='attendance') as isSubscribed
                FROM gibbonPerson 
                JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID)
                JOIN gibbonFormGroup ON (gibbonStudentEnrolment.gibbonFormGroupID=gibbonFormGroup.gibbonFormGroupID)
                WHERE gibbonPerson.status='Full'
                AND gibbonStudentEnrolment.gibbonSchoolYearID=(SELECT gibbonSchoolYearID FROM gibbonSchoolYear WHERE status='Current')";
        
        if (!empty($search)) {
            $data['search1'] = "%$search%";
            $data['search2'] = "%$search%";
            $sql .= " AND (gibbonPerson.surname LIKE :search1 OR gibbonPerson.preferredName LIKE :search2)";
        }
        
        if (!empty($gibbonFormGroupID)) {
            $data['gibbonFormGroupID'] = $gibbonFormGroupID;
            $sql .= " AND gibbonStudentEnrolment.gibbonFormGroupID=:gibbonFormGroupID";
        }
        
        if ($subscriptionStatus == 'subscribed') {
            $sql .= " AND (SELECT COUNT(*) FROM CustomNotificationSubscription 
                          WHERE gibbonPersonID=:gibbonPersonID 
                          AND targetPersonID=gibbonPerson.gibbonPersonID
                          AND eventType='attendance') > 0";
        } else if ($subscriptionStatus == 'unsubscribed') {
            $sql .= " AND (SELECT COUNT(*) FROM CustomNotificationSubscription 
                          WHERE gibbonPersonID=:gibbonPersonID 
                          AND targetPersonID=gibbonPerson.gibbonPersonID
                          AND eventType='attendance') = 0";
        }
        
        $sql .= " ORDER BY gibbonFormGroup.name, surname, preferredName";
        
        $result = $pdo->prepare($sql);
        $result->execute($data);
        $students = $result->fetchAll();

        // Show total results
        echo "<div class='success'>";
        echo sprintf(__('Total Students: %s'), count($students));
        if (!empty($search)) {
            echo " | " . sprintf(__('Search: "%s"'), $search);
        }
        if (!empty($gibbonFormGroupID)) {
            $formGroupName = $form->getElement('gibbonFormGroupID')->selected();
            echo " | " . sprintf(__('Form Group: %s'), $formGroupName);
        }
        echo "</div>";

        // Create table
        $table = DataTable::create('students');
        
        $table->addColumn('formGroup', __('Form Group'))
            ->sortable(['formGroup']);
        
        $table->addColumn('name', __('Name'))
            ->format(function($row) {
                return Format::name('', $row['preferredName'], $row['surname'], 'Student');
            })
            ->sortable(['surname', 'preferredName']);
        
        $table->addColumn('subscription', __('Subscription'))
            ->format(function($row) {
                return $row['isSubscribed'] > 0 ? 
                    Format::tag(__('Subscribed'), 'success') : 
                    Format::tag(__('Not Subscribed'), 'dull');
            });

        // Add action column
        $table->addActionColumn()
            ->addParam('gibbonPersonID', $_SESSION[$guid]['gibbonPersonID'])
            ->addParam('targetPersonID')
            ->format(function ($row, $actions) {
                if ($row['isSubscribed'] > 0) {
                    $actions->addAction('unsubscribe', __('Unsubscribe'))
                        ->setURL('notifications_subscribeStudentsProcess.php')
                        ->addParam('action', 'unsubscribe')
                        ->addParam('targetPersonID', $row['gibbonPersonID'])
                        ->setIcon('garbage')
                        ->addClass('text-danger');
                } else {
                    $actions->addAction('subscribe', __('Subscribe'))
                        ->setURL('notifications_subscribeStudentsProcess.php')
                        ->addParam('action', 'subscribe')
                        ->addParam('targetPersonID', $row['gibbonPersonID'])
                        ->setIcon('plus')
                        ->addClass('text-success');
                }
            });

        echo $table->render($students);
        
    } catch (Exception $e) {
        error_log('CustomNotification Error: ' . $e->getMessage());
        $page->addError(__('An error occurred while loading the page: ') . $e->getMessage());
    }
}
