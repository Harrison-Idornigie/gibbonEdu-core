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
    $formGroup = $_POST['formGroup'] ?? '';
    $template = $_POST['template'] ?? '';

    // Validate required values
    if (empty($reportingPeriod) || empty($formGroup) || empty($template)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }

    // Add selected values to URL for redirect
    $URL .= "&reportingPeriod=".urlencode($reportingPeriod);
    $URL .= "&formGroup=".urlencode($formGroup);
    $URL .= "&template=".urlencode($template);
    
    // Success - redirect back to form group selection with values preserved
    header("Location: {$URL}");
    exit;
}
