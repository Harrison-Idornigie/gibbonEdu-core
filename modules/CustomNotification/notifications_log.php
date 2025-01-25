<?php
use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\CustomNotification\Domain\NotificationLogGateway;
use Gibbon\Domain\DataSet;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/CustomNotification/notifications_log.php') === false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs
    ->add(__('Notification Log'));

$notificationLogGateway = $container->get(NotificationLogGateway::class);

// CRITERIA
$criteria = $notificationLogGateway->newQueryCriteria(true)
    ->sortBy(['timestamp'], 'DESC')
    ->filterBy('eventType', $_GET['eventType'] ?? '')
    ->filterBy('status', $_GET['status'] ?? '')
    ->filterBy('dateStart', $_GET['dateStart'] ?? '')
    ->filterBy('dateEnd', $_GET['dateEnd'] ?? '')
    ->fromPOST();

// Filter form
$form = Form::create('filter', $session->get('absoluteURL').'/index.php', 'get');
$form->setFactory(DatabaseFormFactory::create($pdo));
$form->setClass('noIntBorder fullWidth');

$form->addHiddenValue('q', '/modules/CustomNotification/notifications_log.php');

// Event Type filter
$sql = "SELECT DISTINCT eventType as value, eventType as name FROM CustomNotificationLog ORDER BY eventType";
$results = $pdo->select($sql);
$eventTypes = $results->fetchAll(\PDO::FETCH_KEY_PAIR);

$row = $form->addRow();
    $row->addLabel('eventType', __('Event Type'));
    $row->addSelect('eventType')
        ->fromArray(['' => __('All Event Types')] + $eventTypes)
        ->selected($_GET['eventType'] ?? '');

// Status filter
$row = $form->addRow();
    $row->addLabel('status', __('Status'));
    $row->addSelect('status')
        ->fromArray([
            '' => __('All Statuses'),
            'Sent' => __('Sent'),
            'Failed' => __('Failed')
        ])
        ->selected($_GET['status'] ?? '');

// Date range
$row = $form->addRow();
    $row->addLabel('dateRange', __('Date Range'));
    $col = $row->addColumn()->addClass('right');
    $col->addDate('dateStart')
        ->setClass('shortWidth')
        ->setValue($_GET['dateStart'] ?? '')
        ->placeholder(__('Start Date'));
    $col->addDate('dateEnd')
        ->setClass('shortWidth')
        ->setValue($_GET['dateEnd'] ?? '')
        ->placeholder(__('End Date'));

$row = $form->addRow();
    $row->addSearchSubmit($gibbon->session);

echo $form->getOutput();

// QUERY
$notifications = $notificationLogGateway->queryNotificationLogs($criteria);

// DATA TABLE
$table = DataTable::createPaginated('notificationLog', $criteria);
$table->setTitle(__('Notification Log'));

$table->addColumn('timestamp', __('Date/Time'))
    ->format(Format::using('dateTime'));
$table->addColumn('eventType', __('Event Type'));
$table->addColumn('recipientType', __('Recipient Type'));
$table->addColumn('recipient', __('Recipient'))
    ->format(function($row) {
        if (empty($row['username'])) return '';
        return Format::name('', $row['preferredName'], $row['surname'], 'Student', false, true);
    });
$table->addColumn('notificationType', __('Type'));
$table->addColumn('status', __('Status'))
    ->format(function($row) {
        if ($row['status'] == 'Sent') {
            return '<span class="tag success">'.__('Sent').'</span>';
        } else {
            return '<span class="tag error">'.__('Failed').'</span>';
        }
    });

$table->addColumn('message', __('Message'))
    ->format(function($row) {
        $output = '<div class="text-xs">';
        $output .= substr($row['message'], 0, 100);
        if (strlen($row['message']) > 100) {
            $output .= '...';
        }
        if (!empty($row['error'])) {
            $output .= '<br/><span class="text-error">'.$row['error'].'</span>';
        }
        $output .= '</div>';
        return $output;
    });

echo $table->render($notifications);
