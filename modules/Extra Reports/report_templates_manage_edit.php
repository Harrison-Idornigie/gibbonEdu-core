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

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Database\Connection;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Extra Reports/report_templates_manage_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $templateID = $_GET['template'] ?? '';
    
    if (empty($templateID)) {
        $page->addError(__('No template selected.'));
        return;
    }

    $page->breadcrumbs
        ->add(__('Manage Report Templates'), 'report_templates_manage.php')
        ->add(__('Edit Template'));

    // Get template from database
    try {
        $data = ['templateID' => $templateID];
        $sql = "SELECT name, description, sections, chartSections, active FROM extraReportTemplate WHERE templateID=:templateID";
        $result = $connection2->prepare($sql);
        $result->execute($data);
        
        if ($result->rowCount() != 1) {
            $page->addError(__('Template not found.'));
            return;
        }

        $template = $result->fetch();

        // Decode JSON data with error checking
        $sections = json_decode($template['sections'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $sections = [];
        }
        
        $chartSections = json_decode($template['chartSections'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $chartSections = [];
        }

        // Convert database keys to frontend format
        $convertedSections = [];
        foreach ($sections as $key => $section) {
            $frontendKey = ($key == 'social_emotional') ? 'emotional' : $key;
            $convertedSections[$frontendKey] = $section;
        }

        $convertedChartSections = [];
        foreach ($chartSections as $key => $chartSection) {
            $baseKey = str_replace(' (chart)', '', $key);
            $frontendKey = ($baseKey == 'social_emotional') ? 'emotional' : $baseKey;
            $convertedChartSections["$frontendKey (chart)"] = $chartSection;
        }

        // Ensure all required sections exist with default structure
        $defaultSections = [
            'spiritual' => ['title' => 'Spiritual', 'items' => []],
            'emotional' => ['title' => 'Social Emotional', 'items' => []],
            'physical' => ['title' => 'Physical', 'items' => []],
            'mental' => ['title' => 'Mental', 'items' => []]
        ];

        $defaultChartSections = [
            'spiritual (chart)' => ['title' => 'Spiritual Development', 'subsections' => []],
            'emotional (chart)' => ['title' => 'Social Emotional Development', 'subsections' => []],
            'physical (chart)' => ['title' => 'Physical Development', 'subsections' => []],
            'mental (chart)' => ['title' => 'Mental Development', 'subsections' => []]
        ];

        // Convert legacy array format to object format if needed
        if (is_array($convertedSections) && isset($convertedSections[0])) {
            $convertedSections = [];
            foreach ($sections as $section) {
                $type = $section['type'] === 'social_emotional' ? 'emotional' : $section['type'];
                $convertedSections[$type] = [
                    'title' => $section['title'],
                    'items' => is_array($section['items']) ? $section['items'] : array_values((array)$section['items'])
                ];
            }
        }

        if (is_array($convertedChartSections) && isset($convertedChartSections[0])) {
            $convertedChartSections = [];
            foreach ($chartSections as $section) {
                $type = $section['type'] === 'social_emotional' ? 'emotional' : $section['type'];
                $chartType = $type . ' (chart)';
                $subsections = [];
                if (!empty($section['subsections'])) {
                    if (is_array($section['subsections'])) {
                        foreach ($section['subsections'] as $sub) {
                            if (is_string($sub)) {
                                $subsections[$sub] = $sub;
                            } elseif (isset($sub['name'])) {
                                $subsections[$sub['name']] = $sub['name'];
                            }
                        }
                    } else {
                        $subsections = (array)$section['subsections'];
                    }
                }
                $convertedChartSections[$chartType] = [
                    'title' => $section['title'],
                    'subsections' => $subsections
                ];
            }
        }

        // Merge with defaults to ensure all sections exist
        $convertedSections = array_replace_recursive($defaultSections, $convertedSections);
        $convertedChartSections = array_replace_recursive($defaultChartSections, $convertedChartSections);

        // Log sections and chart sections for debugging
        error_log('Sections loaded: ' . print_r($convertedSections, true));
        error_log('Chart sections loaded: ' . print_r($convertedChartSections, true));

        // Add template editor script and data
        $page->scripts->add('template-editor-js', 'modules/Extra Reports/js/template-editor.js');
        
        // Pass converted data to frontend
        echo "<script>
            window.templateData = ".json_encode([
                'sections' => $convertedSections,
                'chartSections' => $convertedChartSections
            ]).";
        </script>";

        // Create a Gibbon database connection wrapper and form factory
        $db = new Connection($connection2);
        $factory = DatabaseFormFactory::create($db);

        // Form
        $form = Form::create('templateEdit', $session->get('absoluteURL').'/modules/Extra Reports/report_templates_manage_editProcess.php');
        $form->setFactory($factory);
        $form->addHiddenValue('address', $session->get('address'));
        $form->addHiddenValue('templateID', $templateID);

        $row = $form->addRow();
        $row->addLabel('name', __('Name'))
            ->description(__('Must be unique.'))
            ->required();
        $row->addTextField('name')
            ->required()
            ->maxLength(50)
            ->setValue($template['name']);

        $row = $form->addRow();
        $row->addLabel('description', __('Description'));
        $row->addTextArea('description')
            ->setRows(3)
            ->setValue($template['description']);

        $row = $form->addRow();
        $row->addLabel('active', __('Active'));
        $row->addYesNo('active')
            ->required()
            ->selected($template['active']);

        // Add template editor with Alpine.js
        $col = $form->addRow()->addColumn();
        $col->addContent('
    <div x-data="templateEditor(window.templateData)" x-init="$nextTick(() => init())" class="w-full">
        <!-- Hidden fields for form submission -->
        <input type="hidden" name="sections" :value="JSON.stringify(sections)">
        <input type="hidden" name="chartSections" :value="JSON.stringify(chartSections)">
        
        <!-- Assessment Sections -->
        <div class="mt-4">
            <h3 class="text-lg font-bold mb-2">'.__('General Assessment Sections').'</h3>
            <p class="text-gray-600 mb-4">'.__('Add "I can..." statements to assess developmental areas.').'</p>
            
            <!-- 2x2 Grid Layout for Standard Sections -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <!-- Spiritual Section -->
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="font-bold">Spiritual</h4>
                        <button @click="addItem(\'spiritual\')" type="button" class="bg-green-500 text-white px-3 py-1 rounded text-sm">Add Item</button>
                    </div>
                    <template x-for="(item, index) in sections.spiritual.items" :key="index">
                        <div class="flex items-center mb-2">
                            <input type="text" :value="item" @input="sections.spiritual.items[index] = $event.target.value" class="flex-1 border rounded px-2 py-1 mr-2" placeholder="e.g. I can participate in Land Based activities">
                            <button @click="removeItem(\'spiritual\', index)" type="button" class="text-red-500 hover:text-red-700">×</button>
                        </div>
                    </template>
                </div>

                <!-- Social Emotional Section -->
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="font-bold">Social Emotional</h4>
                        <button @click="addItem(\'emotional\')" type="button" class="bg-green-500 text-white px-3 py-1 rounded text-sm">Add Item</button>
                    </div>
                    <template x-for="(item, index) in sections.emotional.items" :key="index">
                        <div class="flex items-center mb-2">
                            <input type="text" :value="item" @input="sections.emotional.items[index] = $event.target.value" class="flex-1 border rounded px-2 py-1 mr-2" placeholder="e.g. I can solve problems">
                            <button @click="removeItem(\'emotional\', index)" type="button" class="text-red-500 hover:text-red-700">×</button>
                        </div>
                    </template>
                </div>

                <!-- Physical Section -->
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="font-bold">Physical</h4>
                        <button @click="addItem(\'physical\')" type="button" class="bg-green-500 text-white px-3 py-1 rounded text-sm">Add Item</button>
                    </div>
                    <template x-for="(item, index) in sections.physical.items" :key="index">
                        <div class="flex items-center mb-2">
                            <input type="text" :value="item" @input="sections.physical.items[index] = $event.target.value" class="flex-1 border rounded px-2 py-1 mr-2" placeholder="e.g. I can run and jump">
                            <button @click="removeItem(\'physical\', index)" type="button" class="text-red-500 hover:text-red-700">×</button>
                        </div>
                    </template>
                </div>

                <!-- Mental Section -->
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="font-bold">Mental</h4>
                        <button @click="addItem(\'mental\')" type="button" class="bg-green-500 text-white px-3 py-1 rounded text-sm">Add Item</button>
                    </div>
                    <template x-for="(item, index) in sections.mental.items" :key="index">
                        <div class="flex items-center mb-2">
                            <input type="text" :value="item" @input="sections.mental.items[index] = $event.target.value" class="flex-1 border rounded px-2 py-1 mr-2" placeholder="e.g. I can count to 10">
                            <button @click="removeItem(\'mental\', index)" type="button" class="text-red-500 hover:text-red-700">×</button>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Development Chart Sections -->
            <div class="mt-8">
                <h3 class="text-lg font-bold mb-2">'.__('Development Chart Sections').'</h3>
                <p class="text-gray-600 mb-4">'.__('Add focus areas for each development category (e.g. "Self-Awareness", "Problem Solving", "Motor Skills").').'</p>
                
                <!-- 2x2 Grid Layout for Chart Sections -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Spiritual Development -->
                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="font-bold">Spiritual Development</h4>
                            <button @click="addSubsection(\'spiritual (chart)\')" type="button" class="bg-green-500 text-white px-3 py-1 rounded text-sm">Add Area</button>
                        </div>
                        <template x-for="(value, key) in chartSections[\'spiritual (chart)\'].subsections" :key="key">
                            <div class="flex items-center mb-2">
                                <input type="text" :value="value" @input="chartSections[\'spiritual (chart)\'].subsections[key] = $event.target.value" class="flex-1 border rounded px-2 py-1 mr-2" placeholder="e.g. Indigenous Pedagogies">
                                <button @click="removeSubsection(\'spiritual (chart)\', key)" type="button" class="text-red-500 hover:text-red-700">×</button>
                            </div>
                        </template>
                    </div>

                    <!-- Social Emotional Development -->
                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="font-bold">Social Emotional Development</h4>
                            <button @click="addSubsection(\'emotional (chart)\')" type="button" class="bg-green-500 text-white px-3 py-1 rounded text-sm">Add Area</button>
                        </div>
                        <template x-for="(value, key) in chartSections[\'emotional (chart)\'].subsections" :key="key">
                            <div class="flex items-center mb-2">
                                <input type="text" :value="value" @input="chartSections[\'emotional (chart)\'].subsections[key] = $event.target.value" class="flex-1 border rounded px-2 py-1 mr-2" placeholder="e.g. Self-Awareness">
                                <button @click="removeSubsection(\'emotional (chart)\', key)" type="button" class="text-red-500 hover:text-red-700">×</button>
                            </div>
                        </template>
                    </div>

                    <!-- Physical Development -->
                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="font-bold">Physical Development</h4>
                            <button @click="addSubsection(\'physical (chart)\')" type="button" class="bg-green-500 text-white px-3 py-1 rounded text-sm">Add Area</button>
                        </div>
                        <template x-for="(value, key) in chartSections[\'physical (chart)\'].subsections" :key="key">
                            <div class="flex items-center mb-2">
                                <input type="text" :value="value" @input="chartSections[\'physical (chart)\'].subsections[key] = $event.target.value" class="flex-1 border rounded px-2 py-1 mr-2" placeholder="e.g. Motor Skills">
                                <button @click="removeSubsection(\'physical (chart)\', key)" type="button" class="text-red-500 hover:text-red-700">×</button>
                            </div>
                        </template>
                    </div>

                    <!-- Mental Development -->
                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="font-bold">Mental Development</h4>
                            <button @click="addSubsection(\'mental (chart)\')" type="button" class="bg-green-500 text-white px-3 py-1 rounded text-sm">Add Area</button>
                        </div>
                        <template x-for="(value, key) in chartSections[\'mental (chart)\'].subsections" :key="key">
                            <div class="flex items-center mb-2">
                                <input type="text" :value="value" @input="chartSections[\'mental (chart)\'].subsections[key] = $event.target.value" class="flex-1 border rounded px-2 py-1 mr-2" placeholder="e.g. Problem Solving">
                                <button @click="removeSubsection(\'mental (chart)\', key)" type="button" class="text-red-500 hover:text-red-700">×</button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
    ');

    // Add a fixed submit button row
    $row = $form->addRow()->addClass('sticky bottom-0 bg-white border-t border-gray-300 py-4 px-6 mt-4');
    $row->addSubmit();

    echo $form->getOutput();
} catch (PDOException $e) {
    $page->addError(__('Could not load template.'));
}
}
