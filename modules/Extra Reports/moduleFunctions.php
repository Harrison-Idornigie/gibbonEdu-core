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
 * Get the list of available report card templates
 *
 * @return array
 */
function getReportCardTemplates() {
    $templates = [];
    $dir = __DIR__ . '/templates/reportCards';
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if (substr($file, -4) == '.php') {
                $name = substr($file, 0, -4);
                if (substr($name, -6) == 'Report') {
                    $name = substr($name, 0, -6);
                }
                $templates[$name] = ucfirst($name);
            }
        }
    }
    return $templates;
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
        '0' => 'Does not meet the MINIMUM Standards',
        '1' => 'Meets some of the MINIMUM Standards',
        '2' => 'Meeting the MINIMUM Standards'
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
