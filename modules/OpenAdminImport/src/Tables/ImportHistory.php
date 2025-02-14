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

namespace Gibbon\Module\OpenAdminImport\Tables;

use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\DataSet;

/**
 * ImportHistory Table
 *
 * @version v29
 * @since   v29
 */
class ImportHistory
{
    private static $importTypes = [
        'students' => [
            'name' => 'Students',
            'icon' => 'fas fa-users',
        ],
        'staff' => [
            'name' => 'Staff',
            'icon' => 'fas fa-chalkboard-teacher',
        ],
    ];

    private static $statusColors = [
        'Pending' => 'warning',
        'Complete' => 'success',
        'Failed' => 'error',
    ];

    public static function create($criteria)
    {
        $table = DataTable::createPaginated('importHistory', $criteria);
        $table->setTitle(__('Import History'));

        $table->addColumn('type', __('Type'))
            ->sortable(['type'])
            ->format(function ($row) {
                $type = self::$importTypes[$row['type']] ?? ['name' => $row['type'], 'icon' => 'fas fa-file-import'];
                $icon = Format::icon($type['icon'], __($type['name']));
                return $icon . ' ' . __($type['name']);
            });

        $table->addColumn('importType', __('Import Type'))
            ->sortable(['importType']);
        
        $table->addColumn('status', __('Status'))
            ->sortable(['status'])
            ->format(function ($row) {
                return Format::tag($row['status'], self::$statusColors[$row['status']] ?? 'default');
            });

        $table->addColumn('recordCount', __('Records'))
            ->sortable(['recordCount']);
        
        $table->addColumn('importTime', __('Import Time'))
            ->sortable(['importTime'])
            ->format(Format::using('dateTime', ['importTime']));

        return $table;
    }
}
