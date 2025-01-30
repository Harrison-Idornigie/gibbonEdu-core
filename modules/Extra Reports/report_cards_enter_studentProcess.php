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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Domain\System\SettingGateway;

include '../../gibbon.php';

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/report_cards_enter_student.php';

if (isActionAccessible($guid, $connection2, '/modules/Extra Reports/report_cards_enter.php') == false) {
    // Access denied
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    error_log("Extra Reports: POST data received: " . print_r($_POST, true));
    
    $reportingPeriod = $_POST['reportingPeriod'] ?? '';
    $gibbonPersonID = $_POST['gibbonPersonID'] ?? '';
    $template = $_POST['template'] ?? '';

    error_log("Extra Reports: Extracted values - gibbonPersonID: $gibbonPersonID, reportingPeriod: $reportingPeriod, template: $template");

    // Add these parameters to URL for redirect
    $URL .= "&gibbonPersonID={$gibbonPersonID}&reportingPeriod={$reportingPeriod}&template={$template}";
 
    // Validate required values
    if (empty($reportingPeriod) || empty($gibbonPersonID) || empty($template)) {
        error_log("Extra Reports: Missing required values - Student: $gibbonPersonID, Period: $reportingPeriod, Template: $template");
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Load template to get sections
    $templateFile = __DIR__ . "/templates/reportCards/{$template}Report.php";
    if (!file_exists($templateFile)) {
        error_log("Extra Reports: Template file not found - $templateFile");
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    require $templateFile;

    if (!isset($sections) || !is_array($sections)) {
        error_log("Extra Reports: Template sections not defined properly");
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    try {
        global $pdo;
        
        if (!isset($pdo)) {
            throw new Exception('Database connection not available.');
        }

        $inserted = 0;
        $updated = 0;
        
        // Process each section
        foreach ($sections as $sectionKey => $section) {
            foreach ($section['items'] as $item) {
                $scoreKey = $sectionKey.'_'.$item;
                $commentKey = $sectionKey.'_'.$item.'_comment';
                
                $score = $_POST[$scoreKey] ?? '';
                $comment = $_POST[$commentKey] ?? '';

                error_log("Extra Reports: Processing assessment - Section: $sectionKey, Item: $item, Score: $score");

                if (!empty($score)) {
                    // Check if assessment exists
                    $data = [
                        'gibbonPersonID' => $gibbonPersonID,
                        'reportingPeriod' => $reportingPeriod,
                        'section' => $sectionKey,
                        'item' => $item
                    ];
                    
                    $sql = "SELECT assessmentID 
                            FROM extraReportAssessment 
                            WHERE gibbonPersonID=:gibbonPersonID 
                            AND reportingPeriod=:reportingPeriod 
                            AND section=:section 
                            AND item=:item";
                            
                    $result = $pdo->select($sql, $data);
                    $existing = $result->fetch();
                    
                    if ($existing && isset($existing['assessmentID'])) {
                        // Update existing
                        $data = [
                            'score' => $score,
                            'comment' => $comment,
                            'assessmentID' => $existing['assessmentID']
                        ];
                        
                        $sql = "UPDATE extraReportAssessment 
                                SET score=:score, comment=:comment 
                                WHERE assessmentID=:assessmentID";
                                
                        $updated += $pdo->update($sql, $data);
                        error_log("Extra Reports: Updated assessment ID: ".$existing['assessmentID']);
                    } else {
                        // Insert new
                        $data = [
                            'gibbonPersonID' => $gibbonPersonID,
                            'reportingPeriod' => $reportingPeriod,
                            'section' => $sectionKey,
                            'item' => $item,
                            'score' => $score,
                            'comment' => $comment
                        ];
                        
                        $sql = "INSERT INTO extraReportAssessment 
                                (gibbonPersonID, reportingPeriod, section, item, score, comment) 
                                VALUES 
                                (:gibbonPersonID, :reportingPeriod, :section, :item, :score, :comment)";
                                
                        $inserted += $pdo->insert($sql, $data);
                        error_log("Extra Reports: Inserted new assessment");
                    }
                }
            }
        }

        error_log("Extra Reports: Finished processing - Inserted: $inserted, Updated: $updated");

        // Success
        $URL .= '&return=success0';
        header("Location: {$URL}");
        exit;
    } catch (Exception $e) {
        error_log("Extra Reports: Error saving assessments - " . $e->getMessage());
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }
}
