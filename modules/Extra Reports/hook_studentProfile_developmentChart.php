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

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Domain\System\SettingGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

function getStudentAssessmentData($connection2, $gibbonPersonID) {
    try {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT assessment_data, report_card_id 
                FROM gibbonExtraReportCards 
                WHERE gibbonPersonID=:gibbonPersonID 
                ORDER BY timestamp DESC LIMIT 1";
        
        $result = $connection2->prepare($sql);
        $result->execute($data);
        
        return $result->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

function hook_studentProfile_developmentChart(array $args) {
    global $container;
    
    $gibbonPersonID = $args['gibbonPersonID'] ?? '';
    $connection2 = $container->get('db')->getConnection();
    
    // Get the latest assessment data
    $assessmentData = getStudentAssessmentData($connection2, $gibbonPersonID);
    if (empty($assessmentData)) {
        return '';
    }

    // Start building the output
    $output = '<h3>';
    $output .= __('Development Chart');
    $output .= '</h3>';

    $output .= '<div class="w-full">';
    
    // Add the SVG chart
    $output .= '<div class="relative w-full" style="padding-bottom: 100%;">';
    $output .= '<div class="absolute inset-0 flex items-center justify-center">';
    
    // Include the development chart visualization
    ob_start();
    include __DIR__ . '/report_cards_view_details.php';
    $chartOutput = ob_get_clean();
    
    $output .= $chartOutput;
    $output .= '</div>';
    $output .= '</div>';

    // Add a link to view full report
    $output .= '<div class="text-right mt-2">';
    $output .= '<a href="' . Format::link('/modules/Extra Reports/report_cards_view_details.php', [
        'gibbonPersonID' => $gibbonPersonID,
        'report_card_id' => $assessmentData['report_card_id']
    ]) . '" class="button">';
    $output .= __('View Full Report');
    $output .= '</a>';
    $output .= '</div>';

    return $output;
}
