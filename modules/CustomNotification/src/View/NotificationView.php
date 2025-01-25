<?php
/*
Gibbon: the flexible, open school platform
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

namespace Gibbon\Module\CustomNotification\View;

use Gibbon\View\Page;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Forms\Form;
use Gibbon\Domain\DataSet;

/**
 * NotificationView
 *
 * @version v23
 * @since   v23
 */
class NotificationView
{
    protected $page;

    public function __construct(Page $page)
    {
        $this->page = $page;
    }

    public function renderNotificationTable(DataSet $notifications): string
    {
        $table = DataTable::create('notifications');
        $table->setTitle(__('Notifications'));

        $table->addColumn('timestamp', __('Date'))
            ->format(Format::using('dateTime'));
            
        $table->addColumn('eventType', __('Type'));
        
        $table->addColumn('message', __('Message'))
            ->format(function ($notification) {
                return Format::text($notification['message']);
            });

        $table->addColumn('status', __('Status'))
            ->format(function ($notification) {
                return $notification['status'] == 'Pending' 
                    ? Format::tag(__('Pending'), 'warning')
                    : Format::tag(__('Sent'), 'success');
            });

        return $table->render($notifications);
    }

    public function renderSubscriptionForm(): string
    {
        $form = Form::create('subscriptions', '');

        $form->setTitle(__('Notification Preferences'));
        $form->setDescription(__('Manage your notification preferences below.'));

        $form->addHiddenValue('address', $_SESSION[$guid]['address']);

        $row = $form->addRow();
            $row->addLabel('email', __('Email Notifications'));
            $row->addYesNo('email')->selected('Y');

        $row = $form->addRow();
            $row->addLabel('sms', __('SMS Notifications'));
            $row->addYesNo('sms')->selected('N');

        $row = $form->addRow();
            $row->addLabel('frequency', __('Frequency'));
            $row->addSelect('frequency')
                ->fromArray([
                    'immediately' => __('Immediately'),
                    'daily' => __('Daily Summary'),
                    'weekly' => __('Weekly Summary')
                ])
                ->selected('immediately')
                ->required();

        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit();

        return $form->getOutput();
    }
}
