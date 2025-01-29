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

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Extra Reports/extraReports_templates_manage_add.php';

if (isActionAccessible($guid, $connection2, '/modules/Extra Reports/extraReports_templates_manage.php') == false) {
    // Access denied
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

// Get form data
$data = [
    'moduleID'    => 'Extra Reports',  // Set the module identifier
    'name'        => $_POST['name'] ?? '',
    'active'      => $_POST['active'] ?? '',
    'pageSize'    => $_POST['pageSize'] ?? '',
    'orientation' => $_POST['orientation'] ?? '',
    'description' => $_POST['description'] ?? '',
];

// Validate required fields
if (empty($data['name']) || empty($data['active']) || empty($data['pageSize']) || empty($data['orientation'])) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

try {
    // Check if template name already exists for this module
    $data['name'] = trim($data['name']);
    $sql = "SELECT COUNT(*) FROM gibbonReportTemplate WHERE name=:name AND moduleID=:moduleID";
    $result = $pdo->executeQuery(['name' => $data['name'], 'moduleID' => $data['moduleID']], $sql);
    
    if ($result && $result->fetchColumn() > 0) {
        $URL .= '&return=error7';
        header("Location: {$URL}");
        exit;
    }

    // Insert template
    $sql = "INSERT INTO gibbonReportTemplate SET 
            moduleID=:moduleID, 
            name=:name, 
            active=:active, 
            pageSize=:pageSize, 
            orientation=:orientation, 
            description=:description";
    
    $inserted = $pdo->insert($sql, $data);

    if ($inserted) {
        $gibbonReportTemplateID = $pdo->getConnection()->lastInsertID();

        // If A3 is selected, add to extraReportsPaperSize
        if ($data['pageSize'] == 'A3') {
            $sql = "INSERT INTO extraReportsPaperSize (gibbonReportTemplateID, paperSize) VALUES (:templateID, :paperSize)";
            $pdo->insert($sql, [
                'templateID' => $gibbonReportTemplateID,
                'paperSize'  => 'A3'
            ]);
        }

        // Success
        $URL .= "&return=success0&editID=$gibbonReportTemplateID";
    } else {
        // Failed to insert
        $URL .= "&return=error2";
    }
} catch (Exception $e) {
    // Failed
    $URL .= "&return=error2";
}

header("Location: {$URL}");
exit;
