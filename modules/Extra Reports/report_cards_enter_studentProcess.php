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

        // Collect all assessment data from the form
        $assessmentData = [];
        
        // Debug log ALL POST data in detail
        error_log("Extra Reports: === START POST DATA DUMP ===");
        foreach ($_POST as $key => $value) {
            error_log("Extra Reports: POST field - Key: '$key', Value: '$value'");
            if (strpos($key, 'assessment_') === 0) {
                // Extract section and type from the field name
                $fieldParts = explode('_', $key);
                $lastPart = array_pop($fieldParts); // Get type (score/comment)
                $hash = array_pop($fieldParts); // Get hash
                array_shift($fieldParts); // Remove 'assessment'
                $section = implode('_', $fieldParts); // Rejoin remaining parts for section name
                
                error_log("Extra Reports: - Section: '{$section}'");
                error_log("Extra Reports: - Hash: '{$hash}'");
                error_log("Extra Reports: - Type: '{$lastPart}'");
                
                if (isset($itemMap[$hash])) {
                    error_log("Extra Reports: - Maps to: Section='{$itemMap[$hash]['section']}', Item='{$itemMap[$hash]['name']}'");
                } else {
                    error_log("Extra Reports: - NO MAPPING FOUND FOR HASH");
                }
            }
        }
        error_log("Extra Reports: === END POST DATA DUMP ===");
        
        // Initialize all sections from template with their items
        foreach ($sections as $sectionKey => $section) {
            $assessmentData[$sectionKey] = [];
            foreach ($section['items'] as $item) {
                $assessmentData[$sectionKey][$item] = [
                    'score' => null,
                    'comment' => ''
                ];
            }
            error_log("Extra Reports: Initialized section: $sectionKey with " . count($section['items']) . " items");
        }
        
        // Initialize development sections if they exist
        if (isset($developmentSections)) {
            $assessmentData['development'] = [];
            foreach ($developmentSections as $sectionKey => $section) {
                if (isset($section['subsections'])) {
                    $assessmentData['development'][$sectionKey] = [];
                    foreach ($section['subsections'] as $subsectionKey => $subsectionName) {
                        $assessmentData['development'][$sectionKey][$subsectionName] = [
                            'score' => null,
                            'comment' => ''
                        ];
                    }
                    error_log("Extra Reports: Initialized development section: $sectionKey");
                }
            }
        }
        
        // Process POST data
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'assessment_') === 0) {
                // Extract section and type from the field name
                $fieldParts = explode('_', $key);
                $type = array_pop($fieldParts); // Get type (score/comment)
                $hash = array_pop($fieldParts); // Get hash
                array_shift($fieldParts); // Remove 'assessment'
                
                // Check if this is a development field
                if ($fieldParts[0] === 'development') {
                    array_shift($fieldParts); // Remove 'development'
                    $sectionKey = implode('_', $fieldParts); // Get section key
                    
                    error_log("Extra Reports: Processing development field - Section: $sectionKey, Hash: $hash, Type: $type");
                    
                    // Find matching subsection by hash
                    if (isset($developmentSections[$sectionKey]['subsections'])) {
                        foreach ($developmentSections[$sectionKey]['subsections'] as $subsectionName) {
                            if (md5($subsectionName) === $hash) {
                                if ($type === 'score') {
                                    $score = is_numeric($value) && in_array((int)$value, [1, 2, 3]) ? (int)$value : null;
                                    $assessmentData['development'][$sectionKey][$subsectionName]['score'] = $score;
                                } else {
                                    $assessmentData['development'][$sectionKey][$subsectionName]['comment'] = $value;
                                }
                                error_log("Extra Reports: Stored development data - Section: $sectionKey, Subsection: $subsectionName, Type: $type, Value: " . ($type === 'score' ? $score : substr($value, 0, 20) . '...'));
                                break;
                            }
                        }
                    }
                } else {
                    // Handle regular sections
                    $section = implode('_', $fieldParts);
                    if (isset($itemMap[$hash])) {
                        $mappedSection = $itemMap[$hash]['section'];
                        $itemName = $itemMap[$hash]['name'];
                        
                        if ($type === 'score') {
                            $score = is_numeric($value) && in_array((int)$value, [1, 2, 3]) ? (int)$value : null;
                            $assessmentData[$mappedSection][$itemName]['score'] = $score;
                        } else {
                            $assessmentData[$mappedSection][$itemName]['comment'] = $value;
                        }
                        error_log("Extra Reports: Stored regular data - Section: $mappedSection, Item: $itemName, Type: $type, Value: " . ($type === 'score' ? $value : substr($value, 0, 20) . '...'));
                    }
                }
            }
        }
        
        error_log("Extra Reports: Final assessment data structure: " . print_r($assessmentData, true));

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
                'assessmentData' => json_encode($assessmentData),
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
                'assessmentData' => json_encode($assessmentData),
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
