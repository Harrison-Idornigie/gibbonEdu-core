<?php
/*
Gibbon: the flexible, open school platform
Copyright 2010, Gibbon Foundation
*/

use Gibbon\Data\Validator;

include '../../gibbon.php';

if (isActionAccessible($guid, $connection2, '/modules/CustomNotification/notifications_manage.php') == false) {
    // Access denied
    $URL = $session->get('absoluteURL').'/index.php';
    header("Location: {$URL}");
    exit;
}

$_POST = $container->get(Validator::class)->sanitize($_POST);

$URL = $session->get('absoluteURL').'/index.php?q=/modules/CustomNotification/notifications_manage.php';
$id = $_POST['id'] ?? '';

// Check if event exists
try {
    $data = ['id' => $id];
    $sql = "SELECT * FROM CustomNotificationEvent WHERE id=:id";
    $result = $connection2->prepare($sql);
    $result->execute($data);
    
    if ($result && $result->rowCount() > 0) {
        // Update template
        $data = [
            'id' => $id,
            'template' => $_POST['template'] ?? ''
        ];
        
        $sql = "UPDATE CustomNotificationEvent SET template=:template WHERE id=:id";
        $result = $connection2->prepare($sql);
        $result->execute($data);
        
        $URL .= '&return=success0';
    } else {
        $URL .= '&return=error1';
    }
} catch (PDOException $e) {
    $URL .= '&return=error2';
}

header("Location: {$URL}");
exit;
