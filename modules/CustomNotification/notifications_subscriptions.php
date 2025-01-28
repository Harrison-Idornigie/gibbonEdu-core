<?php
use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\Students\StudentGateway;
use Gibbon\Module\CustomNotification\Domain\NotificationSubscriptionGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/CustomNotification/notifications_subscriptions.php') === false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs
    ->add(__('Manage My Subscriptions'));

$settingGateway = $container->get(SettingGateway::class);
$studentGateway = $container->get(StudentGateway::class);
$subscriptionGateway = $container->get(NotificationSubscriptionGateway::class);

// Get settings
$allowParentUnsubscribe = $settingGateway->getSettingByScope('CustomNotification', 'allowParentUnsubscribe');
$mandatoryTypes = explode(',', $settingGateway->getSettingByScope('CustomNotification', 'mandatoryNotificationTypes'));

// Get available event types
$sql = "SELECT DISTINCT name as value, name as name FROM CustomNotificationEvent WHERE active='Y' ORDER BY name";
$result = $pdo->select($sql);
$eventTypes = $result->fetchAll(\PDO::FETCH_KEY_PAIR);

// Get list of all students this user can subscribe to
$students = [];
$role = $session->get('gibbonRoleIDCurrent');

// Get role info
$data = ['gibbonRoleID' => $role];
$sql = "SELECT category FROM gibbonRole WHERE gibbonRoleID=:gibbonRoleID";
$roleCategory = $pdo->selectOne($sql, $data);

if ($roleCategory == 'Staff') {
    // Staff can see all active students
    $sql = "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.surname, gibbonPerson.preferredName 
            FROM gibbonPerson 
            JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID)
            WHERE gibbonPerson.status='Full'
            AND gibbonStudentEnrolment.gibbonSchoolYearID=(SELECT gibbonSchoolYearID FROM gibbonSchoolYear WHERE status='Current')
            ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";
    $result = $pdo->select($sql);
    while ($student = $result->fetch()) {
        $students[$student['gibbonPersonID']] = Format::name('', $student['preferredName'], $student['surname'], 'Student');
    }
} elseif ($role == 4) { // Parent
    $data = ['gibbonPersonID' => $session->get('gibbonPersonID')];
    $sql = "SELECT DISTINCT student.gibbonPersonID, student.surname, student.preferredName 
            FROM gibbonFamilyChild 
            JOIN gibbonFamily ON (gibbonFamilyChild.gibbonFamilyID=gibbonFamily.gibbonFamilyID)
            JOIN gibbonFamilyAdult ON (gibbonFamilyAdult.gibbonFamilyID=gibbonFamily.gibbonFamilyID)
            JOIN gibbonPerson as student ON (student.gibbonPersonID=gibbonFamilyChild.gibbonPersonID)
            WHERE gibbonFamilyAdult.gibbonPersonID=:gibbonPersonID 
            AND student.status='Full'
            ORDER BY student.surname, student.preferredName";
    $result = $pdo->select($sql, $data);
    while ($student = $result->fetch()) {
        $students[$student['gibbonPersonID']] = Format::name('', $student['preferredName'], $student['surname'], 'Student');
    }
} elseif ($role == 3) { // Student
    // Students can only subscribe to their own notifications
    if ($settingGateway->getSettingByScope('CustomNotification', 'enableStudentAttendanceNotifications') == 'Y') {
        $students[$session->get('gibbonPersonID')] = __('My Notifications');
    }
}

// Add subscription form
$form = Form::create('subscriptionForm', $session->get('absoluteURL').'/modules/CustomNotification/notifications_subscriptionsProcess.php');
$form->setTitle(__('Add Subscription'));

$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('gibbonPersonID', $session->get('gibbonPersonID'));

$row = $form->addRow();
    $row->addLabel('eventType', __('Event Type'))
        ->description(__('Choose which events to be notified about'));
    $row->addSelect('eventType')
        ->fromArray($eventTypes)
        ->required()
        ->placeholder();

if (!empty($students)) {
    $row = $form->addRow();
        $row->addLabel('studentID', __('Student'))
            ->description(__('Optionally choose a specific student to monitor'));
        $row->addSelect('studentID')
            ->fromArray(['' => __('All Students')] + $students)
            ->placeholder();
}

// Add student selector for attendance notifications
if (!empty($eventTypes['attendance'])) {
    $row = $form->addRow();
        $row->addLabel('studentID', __('Students'))
            ->description(__('Select specific students or leave empty for all students'));
        $row->addSelect('studentID')
            ->fromArray($students)
            ->selectMultiple()
            ->setSize(6)
            ->placeholder(__('All Students'));
}

$row = $form->addRow();
    $row->addLabel('notifyBy', __('Notify By'));
    $row->addSelect('notifyBy')
        ->fromArray([
            'Email' => __('Email Only'),
            'SMS' => __('SMS Only'),
            'Both' => __('Both Email & SMS')
        ])
        ->required();

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

echo $form->getOutput();

// QUERY
$criteria = $subscriptionGateway->newQueryCriteria()
    ->sortBy(['timestamp'], 'DESC')
    ->fromPOST('subscriptions');

$subscriptions = $subscriptionGateway->querySubscriptions($criteria, $session->get('gibbonPersonID'));

// DATA TABLE
$table = DataTable::createPaginated('subscriptions', $criteria);
$table->setTitle(__('Current Subscriptions'));

$table->addColumn('eventType', __('Event Type'));

$table->addColumn('student', __('Student'))
    ->format(function($row) {
        if (empty($row['studentID'])) return __('All Students');
        return Format::name('', $row['studentPreferredName'], $row['studentSurname'], 'Student');
    });

$table->addColumn('notifyBy', __('Notify By'));

$table->addColumn('active', __('Active'))
    ->format(function($row) {
        return $row['active'] == 'Y' ? __('Yes') : __('No');
    });

// ACTIONS
$table->addActionColumn()
    ->addParam('id')
    ->format(function ($row, $actions) use ($mandatoryTypes) {
        if (!in_array($row['eventType'], $mandatoryTypes)) {
            $actions->addAction('delete', __('Delete'))
                    ->setURL('/modules/CustomNotification/notifications_subscriptions_deleteProcess.php');
        }
    });

echo $table->render($subscriptions);
