<?php
/*
Gibbon: Course Selection & Timetabling Engine
Copyright (C) 2017, Sandra Kuipers
*/

use Gibbon\Domain\System\SettingGateway;
use CourseSelection\SchoolYearNavigation;
use CourseSelection\Domain\MetaDataGateway;

// Module Bootstrap
require 'module.php';

if (isActionAccessible($guid, $connection2, '/modules/Course Selection/meta_manage.php') == false) {
    //Acess denied
    echo "<div class='error'>" ;
        echo __('You do not have access to this action.');
    echo "</div>" ;
} else {
    $page->breadcrumbs
    	->add(__m('Manage Meta Data'));

	$settingGateway = $container->get(SettingGateway::class);
    $gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $settingGateway->getSettingByScope('Course Selection', 'activeSchoolYear');

    $page->navigator->addSchoolYearNavigation($gibbonSchoolYearID);

    echo "<p class='text-right mb-2 text-xs'>";
        $navigation = new SchoolYearNavigation($pdo, $gibbon->session);
        $nextYear = $navigation->getNextYear($gibbonSchoolYearID);
        if (!empty($nextYear)) {
            echo "<a href='" . $session->get('absoluteURL') . '/modules/'.$session->get('module')."/meta_manage_copyProcess.php?gibbonSchoolYearID=$gibbonSchoolYearID&gibbonSchoolYearIDNext=".$nextYear['gibbonSchoolYearID']."' onclick='return confirm(\"Are you sure you want to do this? All meta data from this year will be copied.\")'>" . __('Copy All To Next Year') . "<img style='margin-left: 5px' title='" . __('Copy All To Next Year') . "' src='./themes/" . $session->get('gibbonThemeName') . "/img/copy.png'/></a> | ";
        }
        echo "<a href='".$session->get('absoluteURL').'/index.php?q=/modules/'.$session->get('module')."/meta_manage_addEdit.php&gibbonSchoolYearID=".$gibbonSchoolYearID."'>".__('Add')."<img style='margin-left: 5px' title='".__('Add')."' src='./themes/".$session->get('gibbonThemeName')."/img/page_new.png'/></a>";
    echo '</p>';

    $gateway = $container->get('CourseSelection\Domain\MetaDataGateway');
    $metaDataList = $gateway->selectAllBySchoolYear($gibbonSchoolYearID);

    if ($metaDataList->rowCount() == 0) {
        echo '<div class="error">';
        echo __("There are no records to display.") ;
        echo '</div>';
    } else {
        echo '<table class="fullWidth colorOddEven" cellspacing="0">';

        echo '<tr class="head">';
            echo '<th>';
                echo __('Course');
            echo '</th>';
            echo '<th>';
                echo __('Name');
            echo '</th>';
            echo '<th>';
                echo __('Enrolment Group');
            echo '</th>';
            echo '<th>';
                echo __('Priority');
            echo '</th>';
            echo '<th>';
                echo __('Tags');
            echo '</th>';
            echo '<th style="width: 80px;">';
                echo __('Actions');
            echo '</th>';
        echo '</tr>';

        while ($metaData = $metaDataList->fetch()) {
            echo '<tr>';
                echo '<td>'.$metaData['nameShort'].'</td>';
                echo '<td>'.$metaData['name'].'</td>';
                echo '<td>'.$metaData['enrolmentGroup'].'</td>';
                echo '<td>'.$metaData['timetablePriority'].'</td>';
                echo '<td>'.$metaData['tags'].'</td>';
                echo '<td>';
                    echo "<a href='".$session->get('absoluteURL')."/index.php?q=/modules/".$session->get('module')."/meta_manage_addEdit.php&gibbonSchoolYearID=".$gibbonSchoolYearID."&courseSelectionMetaDataID=".$metaData['courseSelectionMetaDataID']."'><img title='".__('Edit')."' src='./themes/".$session->get('gibbonThemeName')."/img/config.png'/></a> &nbsp;";

                    echo "<a class='thickbox' href='".$session->get('absoluteURL')."/fullscreen.php?q=/modules/".$session->get('module')."/meta_manage_delete.php&courseSelectionMetaDataID=".$metaData['courseSelectionMetaDataID']."&width=650&height=200'><img title='".__('Delete')."' src='./themes/".$session->get('gibbonThemeName')."/img/garbage.png'/></a>";
                echo '</td>';
            echo '</tr>';
        }

        echo '</table>';
    }
}
