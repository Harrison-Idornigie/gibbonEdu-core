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

// System-wide requirements
require_once __DIR__ . '/../../gibbon.php';

$gibbonReportTemplateID = $_POST['gibbonReportTemplateID'] ?? '';
$paperSize = $_POST['paperSize'] ?? '';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/Extra Reports/extraReports_manage_edit.php&gibbonReportTemplateID='.$gibbonReportTemplateID;

if (isActionAccessible($guid, $connection2, '/modules/Extra Reports/extraReports_manage.php') == false) {
    // Access denied
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} 

if (empty($gibbonReportTemplateID) || empty($paperSize)) {
    // Invalid parameters
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

try {
    // First, check if a record exists
    $data = ['templateID' => $gibbonReportTemplateID];
    $sql = "SELECT id FROM extraReportsPaperSize WHERE gibbonReportTemplateID=:templateID";
    $result = $pdo->selectOne($sql, $data);

    if (!empty($result)) {
        // Update existing record
        $data['paperSize'] = $paperSize;
        $sql = "UPDATE extraReportsPaperSize SET paperSize=:paperSize WHERE gibbonReportTemplateID=:templateID";
        $pdo->update($sql, $data);
    } else {
        // Insert new record
        $data['paperSize'] = $paperSize;
        $sql = "INSERT INTO extraReportsPaperSize (gibbonReportTemplateID, paperSize) VALUES (:templateID, :paperSize)";
        $pdo->insert($sql, $data);
    }

    // Success
    $URL .= '&return=success0';
    header("Location: {$URL}");
    exit;
} catch (Exception $e) {
    // Failed
    $URL .= '&return=error2';
    header("Location: {$URL}");
    exit;
}
