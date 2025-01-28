<?php
/*
Gibbon: the flexible, open school platform
Copyright 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;

if (isActionAccessible($guid, $connection2, '/modules/CustomNotification/notifications_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs
        ->add(__('Manage Notifications'), 'notifications_manage.php')
        ->add(__('Edit Template'));

    $id = $_GET['id'] ?? '';

    if (empty($id)) {
        $page->addError(__('No event selected.'));
        return;
    }

    // Get current template
    try {
        $data = ['id' => $id];
        $sql = "SELECT name, template FROM CustomNotificationEvent WHERE id=:id";
        $result = $connection2->prepare($sql);
        $result->execute($data);
        
        if ($result && $result->rowCount() > 0) {
            $event = $result->fetch();
            
            // Show placeholder help
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

            // Create form
            $form = Form::create('templateEdit', $session->get('absoluteURL').'/modules/CustomNotification/notifications_manage_templateProcess.php');
            $form->setFactory(DatabaseFormFactory::create($pdo));
            
            $form->addHiddenValue('address', $session->get('address'));
            $form->addHiddenValue('id', $id);
            
            $row = $form->addRow();
                $row->addLabel('name', __('Event'));
                $row->addTextField('name')->readonly()->setValue($event['name']);
            
            $row = $form->addRow();
                $col = $row->addColumn();
                $col->addLabel('template', __('Template'));
                $col->addTextArea('template')
                    ->setRows(10)
                    ->setValue($event['template'])
                    ->required();
            
            $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit();
            
            echo $form->getOutput();
            
        } else {
            $page->addError(__('Event not found.'));
        }
    } catch (PDOException $e) {
        $page->addError($e->getMessage());
    }
}
