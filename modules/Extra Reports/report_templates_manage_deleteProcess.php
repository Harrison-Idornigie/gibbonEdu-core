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

if (isActionAccessible($guid, $connection2, '/modules/Extra Reports/report_templates_manage_delete.php') == false) {
    // Access denied
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $templateID = $_POST['template'] ?? '';

    // Validate required fields
    if (empty($templateID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    try {
        // Check if template exists and is not in use
        $data = array('templateID' => $templateID);
        
        // Get template details first
        $sql = "SELECT name FROM extraReportTemplate WHERE templateID=:templateID";
        $result = $connection2->prepare($sql);
        $result->execute($data);
        
        if ($result->rowCount() != 1) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit;
        }
        
        $template = $result->fetch();
        
        // Check if template is in use
        $data = array('template' => $template['name']);
        $sql = "SELECT COUNT(*) FROM extraReportAssessment WHERE template=:template";
        $result = $connection2->prepare($sql);
        $result->execute($data);
        
        if ($result->fetchColumn() > 0) {
            $URL .= '&return=error3';
            header("Location: {$URL}");
            exit;
        }

        // Delete template
        $data = array('templateID' => $templateID);
        $sql = "DELETE FROM extraReportTemplate WHERE templateID=:templateID";
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
