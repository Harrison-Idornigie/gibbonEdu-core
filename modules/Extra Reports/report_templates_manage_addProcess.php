<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
Gibbon, Gibbon Education Ltd. (Hong Kong)

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

use Gibbon\Data\Validator;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

// Set URL
$URL = $session->get('absoluteURL') . '/index.php?q=/modules/Extra Reports/report_templates_manage_add.php';

if (isActionAccessible($guid, $connection2, '/modules/Extra Reports/report_templates_manage_add.php') == false) {
    // Access denied
    header("Location: " . $session->get('absoluteURL') . "/index.php?q=/modules/Extra Reports/report_templates_manage.php&return=error0");
    exit;
} else {
    // Proceed!
    $name              = $_POST['name'] ?? '';
    $description       = $_POST['description'] ?? '';
    $sectionsJson      = $_POST['sections'] ?? '[]';
    $chartSectionsJson = $_POST['chartSections'] ?? '[]';

    // Validate required fields
    if (empty($name)) {
        header("Location: {$session->get('absoluteURL')}/index.php?q=/modules/Extra Reports/report_templates_manage_add.php&return=error1");
        exit;
    }

    // Check name uniqueness
    try {
        $data   = ['name' => $name];
        $sql    = "SELECT COUNT(*) FROM extraReportTemplate WHERE name=:name";
        $result = $connection2->prepare($sql);
        $result->execute($data);
        if ($result->fetchColumn() > 0) {
            header("Location: {$session->get('absoluteURL')}/index.php?q=/modules/Extra Reports/report_templates_manage_add.php&return=error7");
            exit;
        }
    } catch (PDOException $e) {
        header("Location: {$session->get('absoluteURL')}/index.php?q=/modules/Extra Reports/report_templates_manage_add.php&return=error2");
        exit;
    }

    // Process sections
    try {
        $sections = json_decode($sectionsJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            header("Location: {$session->get('absoluteURL')}/index.php?q=/modules/Extra Reports/report_templates_manage_add.php&return=error1");
            exit;
        }

        // Validate section types
        $validTypes = ['spiritual', 'emotional', 'physical', 'mental'];

        // Keep sections in their original format since it matches the template
        $templateSections = [];
        foreach ($sections as $type => $section) {
            if (! in_array($type, $validTypes)) {
                continue;
            }

            if (empty($section['title'])) {
                continue;
            }

            $templateSections[$type] = [
                'title' => trim($section['title']),
                'items' => array_values(array_filter($section['items'] ?? [], function ($item) {
                    return ! empty($item);
                }))
            ];
        }

        // Process chart sections
        $chartSections = json_decode($chartSectionsJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            header("Location: {$session->get('absoluteURL')}/index.php?q=/modules/Extra Reports/report_templates_manage_add.php&return=error1");
            exit;
        }

        // Keep chart sections in their original format
        $templateChartSections = [];
        foreach ($chartSections as $type => $section) {
            if (! preg_match('/(mental|emotional|spiritual|physical) \(chart\)/', $type)) {
                continue;
            }

            if (empty($section['title'])) {
                continue;
            }

            // Convert emotional to social_emotional for chart sections
            $sectionType = $type;
            if ($type === 'emotional (chart)') {
                $sectionType = 'social_emotional (chart)';
            }

            $templateChartSections[$sectionType] = [
                'title'       => trim($section['title']),
                'subsections' => array_filter($section['subsections'] ?? [], function ($item) {
                    return ! empty($item);
                })
            ];
        }

        // Insert into database
        $data = [
            'name'          => $name,
            'description'   => $description,
            'sections'      => json_encode($templateSections),
            'chartSections' => json_encode($templateChartSections),
            'active'        => 'Y',
            'timestamp'     => date('Y-m-d H:i:s'),
        ];

        $sql = "INSERT INTO extraReportTemplate
                (name, description, sections, chartSections, active, timestamp)
                VALUES
                (:name, :description, :sections, :chartSections, :active, :timestamp)";

        $result = $connection2->prepare($sql);
        $result->execute($data);

        // Success
        header("Location: {$session->get('absoluteURL')}/index.php?q=/modules/Extra Reports/report_templates_manage.php&return=success0");
        exit;
    } catch (PDOException $e) {
        header("Location: {$session->get('absoluteURL')}/index.php?q=/modules/Extra Reports/report_templates_manage_add.php&return=error2");
        exit;
    } catch (Exception $e) {
        header("Location: {$session->get('absoluteURL')}/index.php?q=/modules/Extra Reports/report_templates_manage_add.php&return=error1");
        exit;
    }
}