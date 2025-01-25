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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Forms\Form;
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\API\Domain\APIKeyGateway;

if (isActionAccessible($guid, $connection2, '/modules/API/api_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Manage API'));

    // Get API settings
    $settingGateway = $container->get(SettingGateway::class);
    $apiKeyRequired = $settingGateway->getSettingByScope('API', 'apiKey');
    $allowedIPs = $settingGateway->getSettingByScope('API', 'allowedIPs');

    // API Settings Form
    $form = Form::create('apiSettings', $session->get('absoluteURL').'/modules/API/api_settingsProcess.php');
    
    $form->addRow()->addHeading(__('API Settings'));

    $row = $form->addRow();
        $row->addLabel('apiKey', __('Require API Key'));
        $row->addYesNo('apiKey')->selected($apiKeyRequired);

    $row = $form->addRow();
        $row->addLabel('allowedIPs', __('Allowed IPs'))
            ->description(__('Leave empty to allow all IPs. Use comma to separate multiple IPs.'));
        $row->addTextArea('allowedIPs')->setValue($allowedIPs)->setRows(3);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();

    // API Keys Table
    $apiKeyGateway = $container->get(APIKeyGateway::class);
    $criteria = $apiKeyGateway->newQueryCriteria()
        ->sortBy(['name'])
        ->fromPOST();

    $apiKeys = $apiKeyGateway->queryAPIKeys($criteria);

    $table = DataTable::createPaginated('apiKeys', $criteria);
    $table->setTitle(__('API Keys'));

    $table->addHeaderAction('add', __('Add'))
        ->setURL('/modules/API/api_manage_add.php')
        ->displayLabel();

    $table->addColumn('name', __('Name'));
    $table->addColumn('dateCreated', __('Date Created'))
        ->format(Format::using('date', 'dateCreated'));
    $table->addColumn('lastAccessed', __('Last Accessed'))
        ->format(Format::using('dateTime', 'lastAccessed'));
    $table->addColumn('active', __('Active'))
        ->format(Format::using('yesNo', 'active'));

    // Actions
    $table->addActionColumn()
        ->addParam('id')
        ->format(function ($row, $actions) {
            $actions->addAction('edit', __('Edit'))
                ->setURL('/modules/API/api_manage_edit.php');
            $actions->addAction('delete', __('Delete'))
                ->setURL('/modules/API/api_manage_delete.php');
        });

    echo $table->render($apiKeys);
}
