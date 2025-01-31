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
    
    $gibbonSchoolYearTermID = $_POST['gibbonSchoolYearTermID'] ?? '';
    $gibbonPersonID = $_POST['gibbonPersonID'] ?? '';
    $template = $_POST['template'] ?? '';

    error_log("Extra Reports: Extracted values - gibbonPersonID: $gibbonPersonID, gibbonSchoolYearTermID: $gibbonSchoolYearTermID, template: $template");

    // Add these parameters to URL for redirect
    $URL .= "&gibbonPersonID={$gibbonPersonID}&gibbonSchoolYearTermID={$gibbonSchoolYearTermID}&template={$template}";
 
    // Validate required values
    if (empty($gibbonSchoolYearTermID) || empty($gibbonPersonID) || empty($template)) {
        error_log("Extra Reports: Missing required values - Student: $gibbonPersonID, Term: $gibbonSchoolYearTermID, Template: $template");
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    try {
        global $pdo;
        
        if (!isset($pdo)) {
            throw new Exception('Database connection not available.');
        }

        // Load template to get full item names
        $templateFile = __DIR__ . "/templates/reportCards/{$template}Report.php";
        require $templateFile;
        
        // Debug log the template sections and items
        error_log("Extra Reports: Template loaded with sections: " . print_r(array_keys($sections), true));
        foreach ($sections as $sectionKey => $section) {
            error_log("Extra Reports: Section '$sectionKey' has " . count($section['items']) . " items: " . print_r($section['items'], true));
        }
        
        // Create reverse mapping of md5 hashes to full item names
        $itemMap = [];
        foreach ($sections as $sectionKey => $section) {
            error_log("Extra Reports: Processing section '$sectionKey'");
            foreach ($section['items'] as $item) {
                $hash = md5($item);
                $itemMap[$hash] = [
                    'section' => $sectionKey,
                    'name' => $item
                ];
                error_log("Extra Reports: Added mapping - Hash: $hash, Section: $sectionKey, Item: $item");
            }
        }

        // Initialize data structures
        $regularData = [];
        $developmentData = [];
        $jsonData = [];

        // Process POST data
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'assessment_') === 0) {
                // Extract field parts
                $fieldParts = explode('_', $key);
                $type = array_pop($fieldParts);      // Get type (score/comment)
                $hash = array_pop($fieldParts);      // Get hash
                array_shift($fieldParts);            // Remove 'assessment'

                // Check if this is a development chart field
                $isDevelopment = false;
                if ($fieldParts[0] === 'development') {
                    $isDevelopment = true;
                    array_shift($fieldParts);        // Remove 'development'
                }

                // Reconstruct section name
                $section = implode('_', $fieldParts);
                
                // Handle various (chart) suffix formats for backward compatibility
                if ($isDevelopment) {
                    // First remove any existing variations
                    $section = str_replace(['_chart', '_(chart)', '_( chart)', ' (chart)', ' ( chart)'], '', $section);
                    // Then add the standardized format
                    $section .= ' (chart)';
                }

                // Debug logging
                error_log("Extra Reports: Processing field - Key: $key, Section: $section, Type: $type, Hash: $hash, IsDevelopment: " . ($isDevelopment ? 'true' : 'false'));

                // Find the original item name from the hash
                $itemName = '';
                if ($isDevelopment) {
                    foreach ($developmentSections as $devSection => $details) {
                        if (isset($details['subsections'])) {
                            foreach ($details['subsections'] as $subName) {
                                if (md5($subName) === $hash) {
                                    $itemName = $subName;
                                    break 2;
                                }
                            }
                        }
                    }
                } else {
                    foreach ($sections as $secKey => $secDetails) {
                        if (isset($secDetails['items'])) {
                            foreach ($secDetails['items'] as $item) {
                                if (md5($item) === $hash) {
                                    $itemName = $item;
                                    break 2;
                                }
                            }
                        }
                    }
                }

                if (!empty($itemName)) {
                    if ($isDevelopment) {
                        // Store in development data structure
                        if (!isset($developmentData[$section])) {
                            $developmentData[$section] = [];
                        }
                        if (!isset($developmentData[$section][$itemName])) {
                            $developmentData[$section][$itemName] = [];
                        }
                        $developmentData[$section][$itemName][$type] = $value;
                    } else {
                        // Store in regular data structure
                        if (!isset($regularData[$section])) {
                            $regularData[$section] = [];
                        }
                        if (!isset($regularData[$section][$itemName])) {
                            $regularData[$section][$itemName] = [];
                        }
                        $regularData[$section][$itemName][$type] = $value;
                    }
                }
            }
        }

        // Combine data into final JSON structure
        $jsonData = $regularData;
        if (!empty($developmentData)) {
            $jsonData['development'] = $developmentData;
        }

        // Debug log the final JSON structure
        error_log("Extra Reports: Final JSON structure: " . json_encode($jsonData, JSON_PRETTY_PRINT));

        // Get teacher ID
        $gibbonPersonIDTeacher = $session->get('gibbonPersonID');

        // Check if an assessment already exists
        $data = [
            'gibbonPersonIDStudent' => $gibbonPersonID,
            'gibbonSchoolYearTermID' => $gibbonSchoolYearTermID,
            'template' => $template
        ];

        $sql = "SELECT extraReportAssessmentID FROM extraReportAssessment 
                WHERE gibbonPersonIDStudent=:gibbonPersonIDStudent 
                AND gibbonSchoolYearTermID=:gibbonSchoolYearTermID 
                AND template=:template";
                
        $result = $pdo->select($sql, $data);

        if ($result->rowCount() > 0) {
            // Update existing assessment
            $updateData = [
                'assessmentData' => json_encode($jsonData),
                'comment' => $_POST['comment'] ?? '',
                'gibbonPersonIDTeacher' => $gibbonPersonIDTeacher,
                'extraReportAssessmentID' => $result->fetch()['extraReportAssessmentID']
            ];

            $sql = "UPDATE extraReportAssessment 
                    SET assessmentData=:assessmentData, 
                        comment=:comment,
                        gibbonPersonIDTeacher=:gibbonPersonIDTeacher,
                        timestamp=NOW()
                    WHERE extraReportAssessmentID=:extraReportAssessmentID";
                    
            $pdo->update($sql, $updateData);
        } else {
            // Create new assessment
            $insertData = [
                'gibbonPersonIDStudent' => $gibbonPersonID,
                'gibbonSchoolYearTermID' => $gibbonSchoolYearTermID,
                'template' => $template,
                'assessmentData' => json_encode($jsonData),
                'comment' => $_POST['comment'] ?? '',
                'gibbonPersonIDTeacher' => $gibbonPersonIDTeacher
            ];

            $sql = "INSERT INTO extraReportAssessment 
                    (gibbonPersonIDStudent, gibbonSchoolYearTermID, template, 
                     assessmentData, comment, gibbonPersonIDTeacher, timestamp) 
                    VALUES 
                    (:gibbonPersonIDStudent, :gibbonSchoolYearTermID, :template,
                     :assessmentData, :comment, :gibbonPersonIDTeacher, NOW())";
                     
            $pdo->insert($sql, $insertData);
        }
        
        error_log("Extra Reports: SQL executed successfully");
        $URL .= '&return=success0';
    } catch (Exception $e) {
        error_log("Extra Reports: Error saving assessment - " . $e->getMessage());
        $URL .= '&return=error2';
    }

    header("Location: {$URL}");
    exit;
}
