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

include __DIR__ . '/../../gibbon.php';

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\System\SettingGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

$URL = $_SESSION[$guid]['absoluteURL'].'/index.php?q=/modules/Extra Reports/report_templates_manage.php';

if (isActionAccessible($guid, $connection2, '/modules/Extra Reports/report_templates_manage.php') == false) {
    // Access denied
    header("Location: {$URL}&return=error0");
    exit;
}

// Get selected templates
$selectedTemplates = $_POST['templates'] ?? [];
if (empty($selectedTemplates)) {
    header("Location: {$URL}&return=error1");
    exit;
}

try {
    // Start transaction
    $connection2->beginTransaction();

    foreach ($selectedTemplates as $templateFile) {
        // Validate template file
        $templatePath = __DIR__ . '/templates/reportCards/' . $templateFile;
        if (!file_exists($templatePath)) {
            continue;
        }

        // Read template file
        $content = file_get_contents($templatePath);

        // Extract sections and chartSections
        $sections = [];
        $developmentSections = [];

        // Include the file to get the arrays
        include $templatePath;

        // Generate name from filename
        $name = ucwords(str_replace(['Report.php', 'report.php', '.php', '_'], [' Report', ' Report', '', ' '], $templateFile));

        // Insert into database
        $data = array(
            'name' => $name,
            'description' => sprintf(__('Imported from %s'), $templateFile),
            'active' => 'Y',
            'content' => $content,
            'sections' => json_encode($sections),
            'chartSections' => json_encode($developmentSections),
            'timestamp' => date('Y-m-d H:i:s')
        );

        $sql = "INSERT INTO extraReportTemplate 
                SET name=:name, 
                    description=:description, 
                    active=:active, 
                    content=:content, 
                    sections=:sections, 
                    chartSections=:chartSections, 
                    timestamp=:timestamp";

        $result = $connection2->prepare($sql);
        $result->execute($data);
    }

    // Commit transaction
    $connection2->commit();

    // Success
    header("Location: {$URL}&return=success0");
    exit;

} catch (PDOException $e) {
    // Rollback on error
    $connection2->rollBack();
    header("Location: {$URL}&return=error2");
    exit;
} catch (Exception $e) {
    // Rollback on error
    $connection2->rollBack();
    header("Location: {$URL}&return=error1");
    exit;
}
