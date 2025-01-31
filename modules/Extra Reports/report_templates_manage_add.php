<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
Gibbon, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\System\SettingGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Extra Reports/report_templates_manage_add.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__('Manage Report Templates'), 'report_templates_manage.php')
        ->add(__('Add Template'));

    // Get list of file-based templates
    $templatesDir = __DIR__ . '/templates/reportCards/';
    $templateFiles = glob($templatesDir . '*.php');
    $fileTemplates = [];
    foreach ($templateFiles as $file) {
        $fileTemplates[basename($file)] = basename($file, '.php');
    }

    // Create form
    $form = Form::create('templateAdd', $session->get('absoluteURL').'/modules/Extra Reports/report_templates_manage_addProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('address', $session->get('address'));

    // Template details
    $row = $form->addRow();
    $row->addLabel('name', __('Name'))->description(__('Must be unique'));
    $row->addTextField('name')
        ->required()
        ->maxLength(100);

    $row = $form->addRow();
    $row->addLabel('description', __('Description'));
    $row->addTextArea('description')
        ->setRows(3);

    $row = $form->addRow();
    $row->addLabel('active', __('Active'));
    $row->addYesNo('active')->setValue('Y');

    if (!empty($fileTemplates)) {
        $row = $form->addRow();
        $row->addLabel('importFile', __('Import From File'))
            ->description(__('Select a file-based template to import its content'));
        $row->addSelect('importFile')
            ->fromArray($fileTemplates)
            ->placeholder();
    }

    $row = $form->addRow();
    $row->addLabel('content', __('Template Code'));
    $col = $row->addColumn();
    $col->addTextArea('content')
        ->setClass('w-full font-mono text-sm')
        ->setRows(15);

    // Add import functionality
    if (!empty($fileTemplates)) {
        $page->scripts->add('inline', "
            document.querySelector('select[name=importFile]').addEventListener('change', function() {
                if (!this.value) return;
                
                fetch('" . $session->get('absoluteURL') . "/modules/Extra Reports/templates/reportCards/' + this.value)
                    .then(response => response.text())
                    .then(content => {
                        document.querySelector('textarea[name=content]').value = content;
                        if (!document.querySelector('input[name=name]').value) {
                            document.querySelector('input[name=name]').value = this.options[this.selectedIndex].text;
                        }
                    });
            });
        ");
    }

    $row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

    echo $form->getOutput();
}
