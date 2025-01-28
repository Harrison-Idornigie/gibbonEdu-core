<?php
use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\System\SettingGateway;

if (isActionAccessible($guid, $connection2, '/modules/CustomNotification/notifications_manage.php') === false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs
    ->add(__('Manage Notifications'));

$settingGateway = $container->get(SettingGateway::class);

// Get settings
$enableAttendanceNotifications = $settingGateway->getSettingByScope('CustomNotification', 'enableAttendanceNotifications');
$enableStudentAttendanceNotifications = $settingGateway->getSettingByScope('CustomNotification', 'enableStudentAttendanceNotifications');
$allowParentUnsubscribe = $settingGateway->getSettingByScope('CustomNotification', 'allowParentUnsubscribe');
$mandatoryTypes = explode(',', $settingGateway->getSettingByScope('CustomNotification', 'mandatoryNotificationTypes'));
$attendanceCheckFrequency = $settingGateway->getSettingByScope('CustomNotification', 'attendanceCheckFrequency');

// Settings form
$form = Form::create('settings', $session->get('absoluteURL').'/modules/CustomNotification/notifications_manage_settingsProcess.php');
$form->setTitle(__('Settings'));

$row = $form->addRow();
    $row->addLabel('enableAttendanceNotifications', __('Enable Attendance Notifications'))
        ->description(__('Should notifications be sent when attendance is marked?'));
    $row->addYesNo('enableAttendanceNotifications')
        ->selected($enableAttendanceNotifications);

$row = $form->addRow();
    $row->addLabel('enableStudentAttendanceNotifications', __('Enable Student Attendance Notifications'))
        ->description(__('Should students receive notifications about their own absences?'));
    $row->addYesNo('enableStudentAttendanceNotifications')
        ->selected($enableStudentAttendanceNotifications);

$row = $form->addRow();
    $row->addLabel('allowParentUnsubscribe', __('Allow Parents to Unsubscribe'))
        ->description(__('Can parents opt out of attendance notifications?'));
    $row->addYesNo('allowParentUnsubscribe')
        ->selected($allowParentUnsubscribe);

$row = $form->addRow();
    $row->addLabel('attendanceCheckFrequency', __('Attendance Check Frequency (Minutes)'))
        ->description(__('How often to check for new attendance records'));
    $row->addNumber('attendanceCheckFrequency')
        ->setValue($attendanceCheckFrequency)
        ->minimum(1)
        ->maximum(60)
        ->required();

$row = $form->addRow();
    $row->addLabel('mandatoryTypes', __('Mandatory Notification Types'))
        ->description(__('Which notification types cannot be unsubscribed from (comma-separated list)'));
    $row->addTextField('mandatoryTypes')
        ->setValue(implode(',', $mandatoryTypes))
        ->maxLength(255);

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

echo $form->getOutput();

// Add horizontal rule between settings and events
echo "<hr/>";

// Current notification events table
$table = DataTable::create('notificationEvents');
$table->setTitle(__('Notification Events'));

$table->addColumn('name', __('Event'))
    ->format(function($row) {
        return ucfirst($row['name']);
    });

$table->addColumn('notifyBy', __('Notification Method'))
    ->format(function($row) {
        return ucfirst($row['notifyBy'] ?? 'Both');
    });

$table->addColumn('availableTo', __('Available To'))
    ->format(function($row) {
        return str_replace(',', ', ', $row['availableTo'] ?? 'Parents,Students');
    });

$table->addColumn('template', __('Template'))
    ->format(function($row) {
        return substr($row['template'] ?? '', 0, 50) . (strlen($row['template'] ?? '') > 50 ? '...' : '');
    });

$table->addColumn('active', __('Active'))
    ->format(function($row) {
        return $row['active'] == 'Y' ? __('Yes') : __('No');
    });

// Add actions
$table->addActionColumn()
    ->addParam('id')
    ->format(function ($row, $actions) use ($guid) {
        $actions->addAction('edit', __('Edit Template'))
            ->setURL('/modules/CustomNotification/notifications_manage_template.php')
            ->setIcon('edit');
            
        $actions->addAction('toggle', __('Toggle Active'))
            ->setURL('/modules/CustomNotification/notifications_manage_toggle.php')
            ->setIcon($row['active'] == 'Y' ? 'check' : 'x')
            ->addParam('id', $row['id']);
    });

// Get notification events
try {
    $sql = "SELECT id, name, type as notifyBy, recipients as availableTo, template, active 
            FROM CustomNotificationEvent 
            ORDER BY name";
    $result = $pdo->select($sql);
    
    echo $table->render($result->toDataSet());
} catch (PDOException $e) {
    $page->addError($e->getMessage());
}

// Add help text about placeholders
$placeholders = [
    '[studentName]' => __('Student\'s full name'),
    '[date]' => __('Date of absence'),
    '[type]' => __('Type of absence'),
    '[reason]' => __('Reason for absence'),
    '[comment]' => __('Additional comments')
];
    
$help = '<strong>'.__('Available Placeholders').':</strong><br/>';
foreach ($placeholders as $placeholder => $description) {
    $help .= "<code>$placeholder</code> - $description<br/>";
}
$page->addAlert('info', $help);
