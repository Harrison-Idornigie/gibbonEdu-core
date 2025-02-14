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

//USAGE
//Ideally this script should be run shortly after midnight, to alert users to columns that have just gone live

require getcwd().'/../../../gibbon.php';

getSystemSettings($guid, $connection2);

setCurrentSchoolYear($guid, $connection2);

//Set up for i18n via gettext
if (!empty($session->get('i18n')['code'])) { 
    putenv('LC_ALL='.$session->get('i18n')['code']);
    setlocale(LC_ALL, $session->get('i18n')['code']);
    bindtextdomain('gibbon', getcwd().'/../i18n');
    textdomain('gibbon');
}


//Check for CLI, so this cannot be run through browser
if (php_sapi_name() != 'cli') {
    echo __('This script cannot be run from a browser, only via CLI.')."\n\n";
} else {
    //SCAN THROUGH ALL ATLS GOING LIVE TODAY
	$data = array('completeDate' => date('Y-m-d'));
	$sql = 'SELECT atlColumn.*, gibbonCourseClass.nameShort AS class, gibbonCourse.nameShort AS course FROM atlColumn JOIN gibbonCourseClass ON (atlColumn.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID) JOIN gibbonCourse ON (gibbonCourseClass.gibbonCourseID=gibbonCourse.gibbonCourseID) WHERE completeDate=:completeDate';
	$result = $connection2->prepare($sql);
	$result->execute($data);
	
    while ($row = $result->fetch()) {
        $dataPerson = array('gibbonCourseClassID' => $row['gibbonCourseClassID'], 'today' => date('Y-m-d'));
		$sqlPerson = "SELECT gibbonCourseClassPerson.*
			FROM gibbonCourseClassPerson
				JOIN gibbonPerson ON (gibbonCourseClassPerson.gibbonPersonID=gibbonPerson.gibbonPersonID)
				JOIN gibbonCourseClass ON (gibbonCourseClassPerson.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID)
			WHERE (role='Teacher' OR role='Assistant' OR role='Student')
				AND gibbonCourseClassPerson.gibbonCourseClassID=:gibbonCourseClassID
				AND gibbonPerson.status='Full' AND (dateStart IS NULL OR dateStart<=:today) AND (dateEnd IS NULL  OR dateEnd>=:today)
				AND gibbonCourseClass.reportable='Y'
				AND gibbonCourseClassPerson.reportable='Y'";
		$resultPerson = $connection2->prepare($sqlPerson);
		$resultPerson->execute($dataPerson);
        
        $notificationGateway = new \Gibbon\Domain\System\NotificationGateway($pdo);

        while ($rowPerson = $resultPerson->fetch()) {
            if ($rowPerson['role'] == 'Teacher' || $rowPerson['role'] == 'Assistant') {
                $notificationSender = new \Gibbon\Comms\NotificationSender($notificationGateway, $session);
                $notificationText = sprintf(__('Your ATL column for class %1$s has gone live today.'), $row['course'].'.'.$row['class']);
                $notificationSender->addNotification($rowPerson['gibbonPersonID'], $notificationText, 'ATL', '/index.php?q=/modules/ATL/atl_write.php&gibbonCourseClassID='.$row['gibbonCourseClassID']);
				$notificationSender->sendNotifications();
            } else {
            	$notificationSender = new \Gibbon\Comms\NotificationSender($notificationGateway, $session);
                $notificationText = sprintf(__('You have new ATL assessment feedback for class %1$s.'), $row['course'].'.'.$row['class']);
                $notificationSender->addNotification($rowPerson['gibbonPersonID'], $notificationText, 'ATL', '/index.php?q=/modules/ATL/atl_view.php');
				$notificationSender->sendNotifications();
            }
        }
    }
}
