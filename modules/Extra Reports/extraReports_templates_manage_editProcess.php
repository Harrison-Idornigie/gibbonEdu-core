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

$gibbonReportTemplateID = $_POST['gibbonReportTemplateID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/Extra Reports/extraReports_templates_manage_edit.php&gibbonReportTemplateID='.$gibbonReportTemplateID;

if (isActionAccessible($guid, $connection2, '/modules/Extra Reports/extraReports_templates_manage.php') == false) {
    // Access denied
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

// Verify this template belongs to Extra Reports
$sql = "SELECT COUNT(*) as count FROM gibbonReportTemplate WHERE gibbonReportTemplateID=:templateID AND moduleID='Extra Reports'";
$result = $pdo->selectOne($sql, ['templateID' => $gibbonReportTemplateID]);

if (empty($result) || $result['count'] == 0) {
    $URL .= '&return=error2';
    header("Location: {$URL}");
    exit;
}

// Get form data
$data = [
    'gibbonReportTemplateID' => $gibbonReportTemplateID,
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
    // Check if template name already exists (excluding this template)
    $data['name'] = trim($data['name']);
    $existingTemplate = $pdo->selectOne(
        "SELECT COUNT(*) as count FROM gibbonReportTemplate 
        WHERE name=:name AND moduleID='Extra Reports' AND gibbonReportTemplateID!=:templateID", 
        ['name' => $data['name'], 'templateID' => $gibbonReportTemplateID]
    );
    
    if ($existingTemplate['count'] > 0) {
        $URL .= '&return=error7';
        header("Location: {$URL}");
        exit;
    }

    // Update template
    $sql = "UPDATE gibbonReportTemplate SET 
            name=:name, 
            active=:active, 
            pageSize=:pageSize, 
            orientation=:orientation, 
            description=:description 
            WHERE gibbonReportTemplateID=:gibbonReportTemplateID 
            AND moduleID='Extra Reports'";
    
    $updated = $pdo->update($sql, $data);

    if ($updated) {
        // Handle paper size changes
        if ($data['pageSize'] == 'A3') {
            // Insert or update A3 paper size
            $sql = "INSERT INTO extraReportsPaperSize (gibbonReportTemplateID, paperSize) 
                    VALUES (:templateID, 'A3') 
                    ON DUPLICATE KEY UPDATE paperSize='A3'";
            $pdo->insert($sql, ['templateID' => $gibbonReportTemplateID]);
        } else {
            // Remove A3 paper size record if it exists
            $sql = "DELETE FROM extraReportsPaperSize WHERE gibbonReportTemplateID=:templateID";
            $pdo->delete($sql, ['templateID' => $gibbonReportTemplateID]);
        }

        // Success
        $URL .= '&return=success0';
    } else {
        // Failed to update
        $URL .= '&return=error2';
    }
} catch (Exception $e) {
    // Failed
    $URL .= '&return=error2';
}

header("Location: {$URL}");
exit;
