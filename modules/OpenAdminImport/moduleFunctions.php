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

use Gibbon\Services\Format;
use Symfony\Component\Yaml\Yaml;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\OpenAdminImport\Domain\ImportGateway;

/**
 * Get list of available import types
 *
 * @param \PDO $connection2
 * @param string $guid
 * @return array
 */
function getImportTypeList($connection2, $guid)
{
    $importTypes = [];
    
    // Get import types from the imports directory
    $path = __DIR__ . '/imports/';
    if (is_dir($path)) {
        $files = scandir($path);
        foreach ($files as $file) {
            if (substr($file, -4) === '.yml') {
                $type = substr($file, 0, -4);
                $importTypes[$type] = [
                    'name' => Format::capitalize($type),
                    'file' => $file,
                    'path' => $path . $file
                ];
            }
        }
    }

    return $importTypes;
}

/**
 * Load import configuration from YAML file
 *
 * @param string $type
 * @return array|null
 */
function getImportConfig($type) 
{
    $path = __DIR__ . '/imports/' . $type . '.yml';
    if (!file_exists($path)) {
        return null;
    }

    try {
        return Yaml::parseFile($path);
    } catch (\Exception $e) {
        return null;
    }
}

/**
 * Get list of available import modes
 *
 * @return array
 */
function getImportModes()
{
    return [
        'insert' => __('Insert Only'),
        'update' => __('Update Only'),
        'sync' => __('Sync (Insert & Update)')
    ];
}

/**
 * Format import status for display
 *
 * @param array $results
 * @return string
 */
function formatImportStatus($results)
{
    if (empty($results)) {
        return Format::tag(__('Failed'), 'error');
    }

    if ($results['success'] > 0 && $results['failed'] == 0) {
        return Format::tag(__('Success'), 'success');
    } elseif ($results['success'] > 0 && $results['failed'] > 0) {
        return Format::tag(__('Partial'), 'warning');
    } else {
        return Format::tag(__('Failed'), 'error');
    }
}
