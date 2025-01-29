<?php
include '../../gibbon.php';

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/report_cards_enter.php';

if (isActionAccessible($guid, $connection2, '/modules/Extra Reports/report_cards_enter.php') == false) {
    // Access denied
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Proceed!
    $reportingPeriod = $_POST['reportingPeriod'] ?? '';
    $studentID = $_POST['studentID'] ?? '';
    $template = $_POST['template'] ?? '';

    // Validate required values
    if (empty($reportingPeriod) || empty($studentID) || empty($template)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Load template to get sections
    $templateFile = __DIR__ . "/templates/reportCards/{$template}Report.php";
    if (!file_exists($templateFile)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    require $templateFile;

    try {
        $data = [];
        
        // Prepare batch insert data
        foreach ($sections as $sectionKey => $section) {
            foreach ($section['items'] as $item) {
                $score = $_POST[$sectionKey.'_'.$item] ?? '';
                $comment = $_POST[$sectionKey.'_'.$item.'_comment'] ?? '';

                if (!empty($score)) {
                    // Check if assessment exists
                    $checkData = [
                        'studentID' => $studentID,
                        'reportingPeriod' => $reportingPeriod,
                        'section' => $sectionKey,
                        'item' => $item
                    ];
                    
                    $sql = "SELECT assessmentID 
                            FROM extraReportAssessment 
                            WHERE studentID=:studentID 
                            AND reportingPeriod=:reportingPeriod 
                            AND section=:section 
                            AND item=:item";
                            
                    $result = $pdo->selectOne($sql, $checkData);
                    
                    if (!empty($result)) {
                        // Update existing
                        $data = [
                            'score' => $score,
                            'comment' => $comment,
                            'assessmentID' => $result['assessmentID']
                        ];
                        
                        $sql = "UPDATE extraReportAssessment 
                                SET score=:score, comment=:comment 
                                WHERE assessmentID=:assessmentID";
                                
                        $pdo->update($sql, $data);
                    } else {
                        // Insert new
                        $data = [
                            'studentID' => $studentID,
                            'reportingPeriod' => $reportingPeriod,
                            'section' => $sectionKey,
                            'item' => $item,
                            'score' => $score,
                            'comment' => $comment
                        ];
                        
                        $sql = "INSERT INTO extraReportAssessment 
                                (studentID, reportingPeriod, section, item, score, comment) 
                                VALUES 
                                (:studentID, :reportingPeriod, :section, :item, :score, :comment)";
                                
                        $pdo->insert($sql, $data);
                    }
                }
            }
        }

        // Success
        $URL .= "&return=success0&studentID=$studentID&reportingPeriod=$reportingPeriod&template=$template";
        header("Location: {$URL}");
        exit;
    } catch (Exception $e) {
        // Failed
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }
}
?>
