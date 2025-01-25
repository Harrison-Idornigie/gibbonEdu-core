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
$table = DataTable::create('events');
$table->setTitle(__('Notification Events'));

$table->addColumn('name', __('Name'));
$table->addColumn('type', __('Type'));
$table->addColumn('recipients', __('Recipients'));
$table->addColumn('active', __('Active'))
    ->format(function($row) {
        return $row['active'] == 'Y' ? __('Yes') : __('No');
    });

$table->addActionColumn()
    ->addParam('id')
    ->format(function ($row, $actions) {
        $actions->addAction('edit', __('Edit'))
            ->setURL('/modules/CustomNotification/notifications_manage_edit.php')
            ->setIcon('config');
            
        $actions->addAction('delete', __('Delete'))
            ->setURL('/modules/CustomNotification/notifications_manage_delete.php')
            ->setIcon('garbage');
    });

// Get notification events
try {
    $data = [];
    $sql = "SELECT id, name, type, recipients, active 
            FROM CustomNotificationEvent 
            WHERE active='Y'
            ORDER BY name";
    $result = $pdo->select($sql, $data);
    
    $events = [];
    while ($row = $result->fetch()) {
        $events[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'type' => __($row['type']),
            'recipients' => __($row['recipients']),
            'active' => $row['active']
        ];
    }
} catch (PDOException $e) {
    $page->addError($e->getMessage());
}

echo $table->render($events);
