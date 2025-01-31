<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

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

use Gibbon\Domain\System\SettingGateway;

require_once '../../gibbon.php';

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Extra Reports/report_templates_manage.php';

if (isActionAccessible($guid, $connection2, '/modules/Extra Reports/report_templates_manage_edit.php') == false) {
    // Access denied
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $template = $_POST['template'] ?? '';
    $name = $_POST['name'] ?? '';
    $content = $_POST['content'] ?? '';

    // Validate required fields
    if (empty($template) || empty($name) || empty($content)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate template name (alphanumeric and underscores only)
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Get template file path
    $templateFile = __DIR__ . '/templates/reportCards/' . basename($template);
    if (!file_exists($templateFile)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Create backup of existing template
    $backupFile = $templateFile . '.bak.' . date('Y-m-d-H-i-s');
    if (!copy($templateFile, $backupFile)) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    try {
        // Write new content to template file
        if (file_put_contents($templateFile, $content) === false) {
            throw new Exception('Failed to write template file');
        }

        // Rename file if name has changed
        $newFile = __DIR__ . '/templates/reportCards/' . $name . '.php';
        if ($templateFile !== $newFile) {
            if (!rename($templateFile, $newFile)) {
                throw new Exception('Failed to rename template file');
            }
        }

        // Success
        $URL .= '&return=success0';
        header("Location: {$URL}");
        exit;
    } catch (Exception $e) {
        // Restore backup if something went wrong
        copy($backupFile, $templateFile);
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }
}
