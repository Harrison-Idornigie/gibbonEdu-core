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

// System-wide requirements
require_once __DIR__ . '/../../gibbon.php';

use Gibbon\Forms\Form;
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Domain\System\SettingGateway;

$_SESSION[$guid]['moduleID'] = getModuleIDFromName($connection2, 'Extra Reports');

if (isActionAccessible($guid, $connection2, '/modules/Extra Reports/extraReports_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Manage Paper Sizes'));

    // Get all report templates
    $sql = "SELECT t.gibbonReportTemplateID, t.name, 
            COALESCE(eps.paperSize, t.pageSize) as paperSize 
            FROM gibbonReportTemplate t 
            LEFT JOIN extraReportsPaperSize eps ON eps.gibbonReportTemplateID = t.gibbonReportTemplateID 
            ORDER BY t.name";
    
    $result = $pdo->select($sql);

    // Create table
    $table = DataTable::create('reportTemplates');
    $table->setTitle(__('Report Templates'));

    $table->addColumn('name', __('Name'));
    $table->addColumn('paperSize', __('Paper Size'))
        ->format(function($row) {
            return $row['paperSize'] ?? 'A4';
        });

    // Add edit action
    $table->addActionColumn()
        ->addParam('gibbonReportTemplateID')
        ->format(function ($row, $actions) use ($guid) {
            $actions->addAction('edit', __('Edit'))
                ->setURL('/modules/Extra Reports/extraReports_manage_edit.php')
                ->addParam('gibbonReportTemplateID', $row['gibbonReportTemplateID']);
        });

    echo $table->render($result->toDataSet());
}
