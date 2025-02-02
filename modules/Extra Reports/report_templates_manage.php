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
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Domain\System\SettingGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Extra Reports/report_templates_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__('Manage Report Templates'));

    // Get list of file-based templates
    $templatesDir = __DIR__ . '/templates/reportCards/';
    $templateFiles = glob($templatesDir . '*.php');
    $fileTemplates = [];
    foreach ($templateFiles as $file) {
        $fileTemplates[basename($file)] = basename($file, '.php');
    }

    // Show import dialog if no templates exist
    try {
        $data = array();
        $sql = "SELECT COUNT(*) FROM extraReportTemplate";
        $result = $connection2->prepare($sql);
        $result->execute($data);
        $templateCount = $result->fetchColumn();

        if ($templateCount == 0 && !empty($fileTemplates)) {
            // Show import dialog
            $form = Form::create('importDialog', $_SESSION[$guid]['absoluteURL'].'/modules/Extra Reports/report_templates_manage_importProcess.php');
            $form->setFactory(DatabaseFormFactory::create($pdo));
            
            $form->addHiddenValue('address', $session->get('address'));
            
            $row = $form->addRow();
            $row->addHeading(__('Import Templates'))
                ->append(' <i title="' . __('Show/Hide') . '" class="toggleDetails fas fa-chevron-down ml-2"></i>')
                ->addClass('toggleDetails')
                ->addClass('font-bold');

            $row = $form->addRow();
            $row->addContent(__('No templates found in the database. Import existing templates to get started.'))
                ->wrap('<div class="message warning mb-4">', '</div>');

            $row = $form->addRow();
            $col = $row->addColumn()->addClass('flex flex-col space-y-2');
            $col->addLabel('templates', __('Select Templates'))
                ->description(__('Choose one or more templates to import'))
                ->addClass('mb-2');

            foreach ($fileTemplates as $file => $name) {
                $col->addCheckbox('templates[]')
                    ->setValue($file)
                    ->setLabel($name)
                    ->addClass('w-full');
            }

            $row = $form->addRow();
            $row->addSubmit(__('Import'));
            
            echo $form->getOutput();
        }
    } catch (PDOException $e) {
        echo "<div class='error'>".$e->getMessage().'</div>';
    }

    // Get first template for preloading
    $firstTemplate = null;
    try {
        $data = array();
        $sql = "SELECT templateID, name, description, sections, chartSections FROM extraReportTemplate ORDER BY name LIMIT 1";
        $result = $connection2->prepare($sql);
        $result->execute($data);
        $firstTemplate = $result->fetch();
    } catch (PDOException $e) {
        // Handle error silently - template preload is optional
    }

    // Handle HTMX requests
    $isHtmx = isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';
    
    // Get templates from database
    try {
        $data = array();
        $sql = "SELECT templateID, name, description, active, timestamp FROM extraReportTemplate ORDER BY name";
        $result = $connection2->prepare($sql);
        $result->execute($data);
        $templates = $result->fetchAll();
    } catch (PDOException $e) {
        $page->addError(__('Could not load templates from database.'));
    }

    // Create table
    $table = DataTable::create('templates');
    $table->setTitle(__('Report Templates'));

    // Add header actions
    // $table->addHeaderAction('import', __('Import File Template'))
    //     ->setURL('./modules/Extra Reports/report_templates_manage_importProcess.php')
    //     ->setIcon('upload')
    //     ->addClass('bg-blue-600 text-white')
    //     ->displayLabel();

    $table->addHeaderAction('add', __('Add Template'))
        ->setURL('./index.php')
        ->addParam('q', '/modules/Extra Reports/report_templates_manage_add.php')
        ->setIcon('plus')
        ->addClass('bg-blue-600 text-white')
        ->displayLabel();

    $table->addColumn('name', __('Name'));
    
    $table->addColumn('description', __('Description'))
        ->format(function($values) {
            return $values['description'] ?? '';
        });
        
    $table->addColumn('modified', __('Last Modified'))
        ->format(function($values) {
            return Format::date($values['timestamp']);
        });
        
    $table->addColumn('active', __('Active'))
        ->format(function($values) {
            return $values['active'] == 'Y' ? __('Yes') : __('No');
        });

    $table->addActionColumn()
        ->addParam('template')
        ->format(function($values, $actions) {
            $actions->addAction('edit', __('Edit'))
                ->setURL('./index.php')
                ->addParam('q', '/modules/Extra Reports/report_templates_manage_edit.php')
                ->addParam('template', $values['templateID']);

            $actions->addAction('duplicate', __('Duplicate'))
                ->setURL('./index.php')
                ->addParam('q', '/modules/Extra Reports/report_templates_manage_add.php')
                ->addParam('preload', $values['templateID']);

            $actions->addAction('delete', __('Delete'))
                ->setURL('./index.php')
                ->addParam('q', '/modules/Extra Reports/report_templates_manage_delete.php')
                ->addParam('template', $values['templateID'])
                ->setIcon('garbage')
                ->addParam('hx-confirm', __('Are you sure you want to delete this template?'));
        });

  

    // Render table
    echo $table->render($templates);
}
