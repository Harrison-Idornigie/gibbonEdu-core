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

    $table->addHeaderAction('add', __('Add Template'))
        ->setURL($session->get('absoluteURL').'/modules/Extra Reports/report_templates_manage_add.php')
        ->addParam('hx-boost', 'true')
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
        ->addParam('hx-boost', 'true')
        ->format(function($values, $actions) use ($session) {
            $actions->addAction('edit', __('Edit'))
                ->setURL($session->get('absoluteURL').'/modules/Extra Reports/report_templates_manage_edit.php')
                ->addParam('template', $values['templateID']);

            $actions->addAction('delete', __('Delete'))
                ->setURL($session->get('absoluteURL').'/modules/Extra Reports/report_templates_manage_delete.php')
                ->addParam('template', $values['templateID']);
        });

    // Add migration notice if file-based templates exist
    $templatesDir = __DIR__ . '/templates/reportCards/';
    if (!is_dir($templatesDir)) {
        // Create templates directory if it doesn't exist
        mkdir($templatesDir, 0755, true);
    }
    $templateFiles = glob($templatesDir . '*.php');
    if (!empty($templateFiles)) {
        $page->addWarning(__('Legacy file-based templates were found. Please use the Add Template button to migrate them to the database.'));
    }

    // Render table
    if ($isHtmx) {
        // For HTMX requests, only return the table content
        echo $table->getOutput();
    } else {
        // For full page loads, render the entire page
        echo $table->render($templates);
    }
}
