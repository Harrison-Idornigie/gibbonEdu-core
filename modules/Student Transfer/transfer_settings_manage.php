<?php
/*
Gibbon: the flexible, open school platform
*/

use Gibbon\Forms\Form;
use Gibbon\Domain\System\SettingGateway;

// Module includes
require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

$connection2 = $container->get('db')->getConnection();

if (isActionAccessible($guid, $connection2, '/modules/Student Transfer/transfer_settings_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__('Student Transfer Settings'));

    // Get settings
    $settingGateway = $container->get(SettingGateway::class);
    
    // Create form
    $form = Form::create('studentTransferSettings', $session->get('absoluteURL').'/modules/Student Transfer/transfer_settings_manageProcess.php');
    
    $form->addHiddenValue('address', $session->get('address'));

    $row = $form->addRow();
        $row->addHeading(__('Security Settings'));

    $row = $form->addRow();
        $row->addLabel('encryptionKey', __('Encryption Key'))
            ->description(__('Secure key used for encrypting and signing transfer packages.'));
        $row->addTextField('encryptionKey')
            ->setValue($settingGateway->getSettingByScope('Student Transfer', 'encryptionKey'))
            ->readOnly();

    $row = $form->addRow();
        $row->addLabel('regenerateKey', __('Regenerate Key'))
            ->description(__('Generate a new encryption key. Warning: This will invalidate all existing transfer packages.'));
        $row->addCheckbox('regenerateKey')
            ->setValue('Y')
            ->description(__('Yes, regenerate the encryption key'));

    $row = $form->addRow();
        $row->addHeading(__('Transfer Settings'));

    $row = $form->addRow();
        $row->addLabel('requiredDocuments', __('Required Documents'))
            ->description(__('Comma-separated list of required documents for transfer.'));
        $row->addTextArea('requiredDocuments')
            ->setValue($settingGateway->getSettingByScope('Student Transfer', 'requiredDocuments'))
            ->setRows(3);

    $row = $form->addRow();
        $row->addLabel('destinationSchools', __('Destination Schools'))
            ->description(__('Comma-separated list of available destination schools.'));
        $row->addTextArea('destinationSchools')
            ->setValue($settingGateway->getSettingByScope('Student Transfer', 'destinationSchools'))
            ->setRows(3);

    $row = $form->addRow();
        $row->addLabel('retentionPeriodCompleted', __('Completed Transfer Retention'))
            ->description(__('Number of days to retain completed transfers before archiving.'));
        $row->addNumber('retentionPeriodCompleted')
            ->setValue($settingGateway->getSettingByScope('Student Transfer', 'retentionPeriodCompleted'))
            ->minimum(1)
            ->maximum(3650);

    $row = $form->addRow();
        $row->addLabel('enableBatchTransfers', __('Enable Batch Transfers'))
            ->description(__('Allow administrators to process multiple transfers at once.'));
        $row->addYesNo('enableBatchTransfers')
            ->selected($settingGateway->getSettingByScope('Student Transfer', 'enableBatchTransfers'));

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
