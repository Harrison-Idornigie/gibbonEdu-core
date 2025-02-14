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
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Domain\System\LogGateway;

// Module includes
include './modules/OpenAdminImport/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/OpenAdminImport/oa_import_history_view.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $gibbonLogID = $_GET['gibbonLogID'] ?? '';

    $logGateway = $container->get(LogGateway::class);
    $values = $logGateway->getByID($gibbonLogID);

    if (empty($values)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    $page->breadcrumbs
        ->add(__('OpenAdmin Import'), 'oa_import_manage.php')
        ->add(__('Import History'), 'oa_import_history.php')
        ->add(__('View Import'));

    // Get the import data
    $importData = !empty($values['serialisedArray']) ? unserialize($values['serialisedArray']) : [];
    $results = $importData['results'] ?? [];

    // OVERVIEW SECTION
    $form = Form::create('importDetails', '');
    $form->setTitle(__('Import Details'));

    $row = $form->addRow();
        $row->addLabel('type', __('Type'));
        $row->addTextField('type')->readonly()->setValue(substr($values['title'], 8));

    $row = $form->addRow();
        $row->addLabel('date', __('Date/Time'));
        $row->addTextField('date')->readonly()->setValue(Format::dateTime($values['timestamp']));

    $row = $form->addRow();
        $row->addLabel('user', __('User'));
        $row->addTextField('user')->readonly()->setValue(Format::name('', $values['preferredName'], $values['surname'], 'Staff'));

    $status = '';
    if (!empty($results)) {
        if ($results['success'] > 0 && $results['failed'] == 0) {
            $status = Format::tag(__('Success'), 'success');
        } elseif ($results['success'] > 0 && $results['failed'] > 0) {
            $status = Format::tag(__('Partial'), 'warning');
        } else {
            $status = Format::tag(__('Failed'), 'error');
        }
    } else {
        $status = Format::tag(__('Failed'), 'error');
    }

    $row = $form->addRow();
        $row->addLabel('status', __('Status'));
        $row->addContent($status);

    $records = empty($results) ? '0' : ($results['success'] ?? 0) . ' / ' . (($results['success'] ?? 0) + ($results['failed'] ?? 0));
    $row = $form->addRow();
        $row->addLabel('records', __('Records'));
        $row->addTextField('records')->readonly()->setValue($records);

    echo $form->getOutput();

    // RESULTS SECTION
    if (!empty($results)) {
        $form = Form::create('importResults', '');
        $form->setTitle(__('Results'));

        if (!empty($results['errors'])) {
            $row = $form->addRow()->addClass('error');
                $row->addLabel('errors', __('Errors'));
                $row->addContent(Format::list($results['errors']));
        }

        if (!empty($results['warnings'])) {
            $row = $form->addRow()->addClass('warning');
                $row->addLabel('warnings', __('Warnings'));
                $row->addContent(Format::list($results['warnings']));
        }

        if (!empty($results['success'])) {
            $row = $form->addRow()->addClass('success');
                $row->addLabel('success', __('Success'));
                $row->addContent(__('Successfully imported {count} records.', ['count' => $results['success']]));
        }

        echo $form->getOutput();
    }

    // SETTINGS SECTION
    if (!empty($importData)) {
        $form = Form::create('importSettings', '');
        $form->setTitle(__('Settings'));

        $settings = [
            'type' => __('Import Type'),
            'mode' => __('Mode'),
            'dryRun' => __('Dry Run'),
            'fieldDelimiter' => __('Field Delimiter'),
            'stringEnclosure' => __('String Enclosure'),
        ];

        foreach ($settings as $key => $name) {
            if (isset($importData[$key])) {
                $row = $form->addRow();
                    $row->addLabel($key, $name);
                    $row->addTextField($key)->readonly()->setValue($importData[$key]);
            }
        }

        echo $form->getOutput();
    }
}
