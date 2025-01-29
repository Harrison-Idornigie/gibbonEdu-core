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
use Gibbon\Forms\DatabaseFormFactory;

if (isActionAccessible($guid, $connection2, '/modules/Extra Reports/extraReports_templates_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $gibbonReportTemplateID = $_GET['gibbonReportTemplateID'] ?? '';

    if (empty($gibbonReportTemplateID)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $page->breadcrumbs
        ->add(__('Manage Report Templates'), 'extraReports_templates_manage.php')
        ->add(__('Edit Template'));

    // Get the template
    $sql = "SELECT t.*, eps.paperSize as customPaperSize 
            FROM gibbonReportTemplate t 
            LEFT JOIN extraReportsPaperSize eps ON eps.gibbonReportTemplateID = t.gibbonReportTemplateID 
            WHERE t.gibbonReportTemplateID=:templateID AND t.moduleID='Extra Reports'";
    
    $result = $pdo->selectOne($sql, ['templateID' => $gibbonReportTemplateID]);

    if (empty($result)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    // Create the form
    $form = Form::create('templatesManage', $session->get('absoluteURL').'/modules/Extra Reports/extraReports_templates_manage_editProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('gibbonReportTemplateID', $gibbonReportTemplateID);

    $form->addRow()->addHeading('Basic Information', __('Basic Information'));

    $row = $form->addRow();
        $row->addLabel('name', __('Name'))->description(__('Must be unique'));
        $row->addTextField('name')->maxLength(90)->required()->setValue($result['name']);

    $row = $form->addRow();
        $row->addLabel('active', __('Active'));
        $row->addYesNo('active')->required()->selected($result['active']);

    $row = $form->addRow()->addHeading('Layout Settings', __('Layout Settings'));

    $paperSizes = ['A4' => __('A4'), 'A3' => __('A3'), 'LETTER' => __('US Letter')];
    $row = $form->addRow();
        $row->addLabel('pageSize', __('Page Size'));
        $row->addSelect('pageSize')
            ->fromArray($paperSizes)
            ->required()
            ->selected($result['customPaperSize'] ?? $result['pageSize']);

    $orientations = ['P' => __('Portrait'), 'L' => __('Landscape')];
    $row = $form->addRow();
        $row->addLabel('orientation', __('Orientation'));
        $row->addSelect('orientation')
            ->fromArray($orientations)
            ->required()
            ->selected($result['orientation']);

    $row = $form->addRow();
        $row->addLabel('description', __('Description'));
        $row->addTextArea('description')->setRows(5)->setValue($result['description']);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}
