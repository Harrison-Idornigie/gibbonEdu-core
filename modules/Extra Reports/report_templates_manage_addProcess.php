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

use Gibbon\Domain\System\SettingGateway;

require_once '../../gibbon.php';

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Extra Reports/report_templates_manage.php';

if (isActionAccessible($guid, $connection2, '/modules/Extra Reports/report_templates_manage_add.php') == false) {
    // Access denied
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $active = $_POST['active'] ?? 'N';
    $content = $_POST['content'] ?? '';
    $importFile = $_POST['importFile'] ?? '';

    // Validate required fields
    if (empty($name)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Validate template name (alphanumeric, underscores and spaces only)
    if (!preg_match('/^[a-zA-Z0-9_ ]+$/', $name)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    try {
        // Handle file import if selected
        if (!empty($importFile)) {
            $templatesDir = __DIR__ . '/templates/reportCards/';
            $filePath = $templatesDir . $importFile;
            if (file_exists($filePath)) {
                $content = file_get_contents($filePath);
            }
        }

        // Ensure we have content either from form or file
        if (empty($content)) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit;
        }

        $data = array(
            'name' => $name,
            'description' => $description,
            'active' => $active,
            'content' => $content,
            'sections' => '[]',
            'chartSections' => '[]'
        );
        
        // Check if name already exists
        $checkData = array('name' => $name);
        $sql = "SELECT COUNT(*) FROM extraReportTemplate WHERE name=:name";
        $result = $connection2->prepare($sql);
        $result->execute($checkData);
        
        if ($result->fetchColumn() > 0) {
            $URL .= '&return=error7';
            header("Location: {$URL}");
            exit;
        }

        // Insert new template
        $sql = "INSERT INTO extraReportTemplate SET name=:name, description=:description, active=:active, content=:content, sections=:sections, chartSections=:chartSections";
        $result = $connection2->prepare($sql);
        $result->execute($data);

        // Success
        $URL .= '&return=success0';
        header("Location: {$URL}");
        exit;
    } catch (PDOException $e) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }
}
