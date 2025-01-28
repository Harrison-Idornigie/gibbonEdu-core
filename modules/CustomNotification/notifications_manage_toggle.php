<?php
/*
Gibbon: the flexible, open school platform
Copyright 2010, Gibbon Foundation
*/

use Gibbon\Data\Validator;

require_once './gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$URL = $session->get('absoluteURL').'/index.php?q=/modules/CustomNotification/notifications_manage.php';
$id = $_GET['id'] ?? '';

if (isActionAccessible($guid, $connection2, '/modules/CustomNotification/notifications_manage.php') == false) {
    // Access denied
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
} else {
    // Check if event exists
    try {
        $data = ['id' => $id];
        $sql = "SELECT active FROM CustomNotificationEvent WHERE id=:id";
        $result = $connection2->prepare($sql);
        $result->execute($data);
        
        if ($result && $result->rowCount() > 0) {
            $event = $result->fetch();
            
            // Toggle active status
            $data = ['id' => $id, 'active' => ($event['active'] == 'Y' ? 'N' : 'Y')];
            $sql = "UPDATE CustomNotificationEvent SET active=:active WHERE id=:id";
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
}
