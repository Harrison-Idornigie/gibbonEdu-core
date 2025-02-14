<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

use Gibbon\Domain\System\SettingGateway;
use CourseSelection\SchoolYearNavigation;
use CourseSelection\Domain\OfferingsGateway;

// Module Bootstrap
require 'module.php';

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/offerings_manage.php') == false) {
    //Acess denied
    echo "<div class='error'>" ;
        echo __('You do not have access to this action.');
    echo "</div>" ;
} else {
    $page->breadcrumbs
    	->add(__m('Manage Course Offerings'));

	$settingGateway = $container->get(SettingGateway::class);
    $gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $settingGateway->getSettingByScope('Course Selection', 'activeSchoolYear');

    $page->navigator->addSchoolYearNavigation($gibbonSchoolYearID);

    echo "<p class='text-right mb-2 text-xs'>";
        $navigation = new SchoolYearNavigation($pdo, $gibbon->session);
        $nextYear = $navigation->getNextYear($gibbonSchoolYearID);
        if (!empty($nextYear)) {
            echo "<a href='" . $session->get('absoluteURL') . '/modules/'.$session->get('module')."/offerings_manage_copyProcess.php?gibbonSchoolYearID=$gibbonSchoolYearID&gibbonSchoolYearIDNext=".$nextYear['gibbonSchoolYearID']."' onclick='return confirm(\"Are you sure you want to do this? All course offerings, but not their requests, will be copied.\")'>" . __('Copy All To Next Year') . "<img style='margin-left: 5px' title='" . __('Copy All To Next Year') . "' src='./themes/" . $session->get('gibbonThemeName') . "/img/copy.png'/></a> | ";
        }
        echo "<a href='".$session->get('absoluteURL').'/index.php?q=/modules/'.$session->get('module')."/offerings_manage_addEdit.php&gibbonSchoolYearID=".$gibbonSchoolYearID."'>".__($guid, 'Add')."<img style='margin-left: 5px' title='".__($guid, 'Add')."' src='./themes/".$session->get('gibbonThemeName')."/img/page_new.png'/></a>";
    echo '</p>';

    $gateway = $container->get('CourseSelection\Domain\OfferingsGateway');
    $offerings = $gateway->selectAllBySchoolYear($gibbonSchoolYearID);

    if ($offerings->rowCount() == 0) {
        echo '<div class="error">';
        echo __("There are no records to display.") ;
        echo '</div>';
    } else {
        echo '<table class="fullWidth colorOddEven" cellspacing="0">';

        echo '<tr class="head">';
            echo '<th>';
                echo __('School Year');
            echo '</th>';
            echo '<th>';
                echo __('Name');
            echo '</th>';
            echo '<th>';
                echo __('Year Groups');
            echo '</th>';
            echo '<th style="width: 80px;">';
                echo __('Actions');
            echo '</th>';
        echo '</tr>';

        while ($offering = $offerings->fetch()) {
            echo '<tr>';
                echo '<td>'.$offering['schoolYearName'].'</td>';
                echo '<td>'.$offering['name'].'</td>';
                echo '<td>'.$offering['yearGroupNames'].'</td>';
                echo '<td>';
                    echo "<a href='".$session->get('absoluteURL')."/index.php?q=/modules/".$session->get('module')."/offerings_manage_addEdit.php&courseSelectionOfferingID=".$offering['courseSelectionOfferingID']."'><img title='".__('Edit')."' src='./themes/".$session->get('gibbonThemeName')."/img/config.png'/></a> &nbsp;";

                    echo "<a class='thickbox' href='".$session->get('absoluteURL')."/fullscreen.php?q=/modules/".$session->get('module')."/offerings_manage_delete.php&courseSelectionOfferingID=".$offering['courseSelectionOfferingID']."&width=650&height=200'><img title='".__('Delete')."' src='./themes/".$session->get('gibbonThemeName')."/img/garbage.png'/></a>";
                echo '</td>';
            echo '</tr>';
        }

        echo '</table>';
    }
}
