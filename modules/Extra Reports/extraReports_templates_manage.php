<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

require_once __DIR__ . '/../../gibbon.php';

use Gibbon\Forms\Form;
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;

if (isActionAccessible($guid, $connection2, '/modules/Extra Reports/extraReports_templates_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Manage Report Templates'));

    // Get all report templates created by Extra Reports
    $sql = "SELECT t.*, eps.paperSize as customPaperSize 
            FROM gibbonReportTemplate t 
            LEFT JOIN extraReportsPaperSize eps ON eps.gibbonReportTemplateID = t.gibbonReportTemplateID 
            WHERE t.moduleID = 'Extra Reports'
            ORDER BY t.name";
    
    $result = $pdo->select($sql);

    // Create table
    $table = DataTable::create('reportTemplates');
    $table->setTitle(__('Report Templates'));

    $table->addHeaderAction('add', __('Add'))
        ->setURL('/modules/Extra Reports/extraReports_templates_manage_add.php')
        ->displayLabel();

    $table->addColumn('name', __('Name'));
    $table->addColumn('orientation', __('Orientation'))
        ->format(function($row) {
            return $row['orientation'] == 'P' ? __('Portrait') : __('Landscape');
        });
    $table->addColumn('pageSize', __('Page Size'))
        ->format(function($row) {
            return $row['customPaperSize'] ?? $row['pageSize'];
        });
    $table->addColumn('active', __('Active'))
        ->format(Format::using('yesNo', 'active'));

    // Add edit action
    $table->addActionColumn()
        ->addParam('gibbonReportTemplateID')
        ->format(function ($row, $actions) {
            $actions->addAction('edit', __('Edit'))
                ->setURL('/modules/Extra Reports/extraReports_templates_manage_edit.php');
            
            $actions->addAction('delete', __('Delete'))
                ->setURL('/modules/Extra Reports/extraReports_templates_manage_delete.php')
                ->modalWindow(650, 400);
        });

    echo $table->render($result->toDataSet());
}
