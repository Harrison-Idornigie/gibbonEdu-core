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

use Gibbon\Forms\Form;
use Gibbon\Services\Format;

/**
 * Get list of available report card templates from the database
 *
 * @param PDO $pdo Database connection
 * @return array Array of template names indexed by template ID
 */
function getReportCardTemplates($pdo = null) {
    global $pdo;
    
    try {
        $sql = "SELECT templateID, name 
                FROM extraReportTemplate 
                WHERE active = 'Y' 
                ORDER BY name";
        $result = $pdo->select($sql);
        
        return $result->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get the list of reporting periods
 *
 * @return array
 */
function getReportingPeriods() {
    return [
        'Term 1' => 'Term 1',
        'Term 2' => 'Term 2',
        'Term 3' => 'Term 3',
        'Term 4' => 'Term 4'
    ];
}

/**
 * Get the list of assessment scores and their meanings
 *
 * @return array
 */
function getAssessmentScores() {
    return [
        '1' => 'Does not meet the MINIMUM Standards',
        '2' => 'Meets some of the MINIMUM Standards',
        '3' => 'Meeting the MINIMUM Standards'
    ];
}

/**
 * Check if a template file exists
 *
 * @param string $template Template name without 'Report.php'
 * @param string $absolutePath Gibbon's absolute path
 * @return bool
 */
function templateExists($template, $absolutePath) {
    $templateFile = "/modules/Extra Reports/templates/reportCards/{$template}Report.php";
    return file_exists($absolutePath.$templateFile);
}

/**
 * Get the full path to a template file
 *
 * @param string $template Template name without 'Report.php'
 * @param string $absolutePath Gibbon's absolute path
 * @return string
 */
function getTemplatePath($template, $absolutePath) {
    return $absolutePath."/modules/Extra Reports/templates/reportCards/{$template}Report.php";
}
?>
