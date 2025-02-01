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
use Gibbon\Domain\System\SettingGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Extra Reports/report_templates_manage_add.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__('Manage Report Templates'), 'report_templates_manage.php')
        ->add(__('Add Template'));

    // Get preload template if specified
    $preloadID = $_GET['preload'] ?? '';
    $preloadData = null;
    
    if (!empty($preloadID)) {
        try {
            $data = array('templateID' => $preloadID);
            $sql = "SELECT name, description, sections, chartSections FROM extraReportTemplate WHERE templateID=:templateID";
            $result = $connection2->prepare($sql);
            $result->execute($data);
            
            if ($result && $row = $result->fetch()) {
                $preloadData = $row;
                // Modify name for duplicate
                $preloadData['name'] = $row['name'] . ' (Copy)';
            }
        } catch (PDOException $e) {
            // Handle error silently - preload is optional
        }
    }

    // Initialize template data
    $page->scripts->add('template-data', 'window.templateData = ' . json_encode([
        'sections' => [
            'spiritual' => [
                'title' => 'Spiritual',
                'items' => []
            ],
            'emotional' => [
                'title' => 'Emotional',
                'items' => []
            ],
            'physical' => [
                'title' => 'Physical',
                'items' => []
            ],
            'mental' => [
                'title' => 'Mental',
                'items' => []
            ]
        ],
        'chartSections' => [
            'mental (chart)' => [
                'title' => 'Mental',
                'subsections' => []
            ],
            'emotional (chart)' => [
                'title' => 'Emotional',
                'subsections' => []
            ],
            'spiritual (chart)' => [
                'title' => 'Spiritual',
                'subsections' => []
            ],
            'physical (chart)' => [
                'title' => 'Physical',
                'subsections' => []
            ]
        ]
    ]), ['type' => 'inline', 'priority' => 0]);

    $page->scripts->add('template-editor', 'modules/Extra Reports/js/template-editor.js', ['priority' => 1]);
    
    // Get list of file-based templates
    $templatesDir = __DIR__ . '/templates/reportCards/';
    $templateFiles = glob($templatesDir . '*.php');
    $fileTemplates = [];
    foreach ($templateFiles as $file) {
        $fileTemplates[basename($file)] = basename($file, '.php');
    }

    // Create form
    $form = Form::create('templateAdd', $session->get('absoluteURL').'/modules/Extra Reports/report_templates_manage_addProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $session->get('address'));
    $form->addClass('w-full');

    // Basic Information
    $row = $form->addRow();
    $row->addHeading(__('Template Details'));

    $row = $form->addRow();
    $col = $row->addColumn();
    $col->addLabel('name', __('Name'))
        ->append(__('Must be unique'));
    
    $col = $row->addColumn()->addClass('flex-1');
    $col->addTextField('name')
        ->required()
        ->maxLength(100)
        ->setValue($preloadData['name'] ?? '')
        ->append(__('The name of the report template, which will be displayed in the list of available templates.'));

    $row = $form->addRow();
    $col = $row->addColumn();
    $col->addLabel('description', __('Description'));
    
    $col = $row->addColumn()->addClass('flex-1');
    $col->addTextArea('description')
        ->setRows(3)
        ->setValue($preloadData['description'] ?? '')
        ->append(__('A brief description of the report template, which will be displayed in the list of available templates.'));

    // Import Template
    if ($preloadData === null) {
        $row = $form->addRow();
        $row->addHeading(__('Import Template'));

        $row = $form->addRow();
        $col = $row->addColumn();
        $col->addLabel('importTemplate', __('Import From File'));
        
        $col = $row->addColumn()->addClass('flex-1');
        $select = $col->addSelect('importTemplate')
            ->placeholder()
            ->fromArray($fileTemplates);
            
        // Add the Alpine.js event handler using data attributes
        $select->getElement()
            ->setAttribute('x-data', '{}')
            ->setAttribute('@change', 'importTemplate($event.target.value)')
            ->append(__('Select a file-based template to import its content into the current template.'));
    }

    // Sections Editor
    $row = $form->addRow();
    $row->addHeading(__('Assessment Sections'));

    // Add dynamic section editor using Alpine.js
    $standardSections = ['spiritual', 'emotional', 'physical', 'mental'];
    
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
                            <input type="text" x-model="sections.spiritual.items[index]" class="flex-1 border rounded px-2 py-1 mr-2" placeholder="e.g. I can participate in Land Based activities">
                            <button @click="removeItem(\'spiritual\', index)" class="text-red-500">
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>

                <!-- Emotional Section -->
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="font-bold">Emotional</h4>
                        <button @click="addItem(\'emotional\')" type="button" class="bg-green-500 text-white px-3 py-1 rounded text-sm">Add Item</button>
                    </div>
                    <template x-for="(item, index) in sections.emotional.items" :key="index">
                        <div class="flex items-center mb-2">
                            <input type="text" x-model="sections.emotional.items[index]" class="flex-1 border rounded px-2 py-1 mr-2" placeholder="e.g. I can solve problems">
                            <button @click="removeItem(\'emotional\', index)" class="text-red-500">
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
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
                            <input type="text" x-model="sections.physical.items[index]" class="flex-1 border rounded px-2 py-1 mr-2" placeholder="e.g. I can use writing tools">
                            <button @click="removeItem(\'physical\', index)" class="text-red-500">
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
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
                            <input type="text" x-model="sections.mental.items[index]" class="flex-1 border rounded px-2 py-1 mr-2" placeholder="e.g. I can recognize some letters">
                            <button @click="removeItem(\'mental\', index)" class="text-red-500">
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
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
                        <template x-for="(value, key) in Object.entries(chartSections[\'spiritual (chart)\'].subsections)" :key="key">
                            <div class="flex items-center mb-2">
                                <input type="text" x-model="chartSections[\'spiritual (chart)\'].subsections[value[0]]" class="flex-1 border rounded px-2 py-1 mr-2" placeholder="e.g. Indigenous Pedagogies">
                                <button @click="removeSubsection(\'spiritual (chart)\', value[0])" class="text-red-500">
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </div>

                    <!-- Emotional Development -->
                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="font-bold">Social Emotional Development</h4>
                            <button @click="addSubsection(\'emotional (chart)\')" type="button" class="bg-green-500 text-white px-3 py-1 rounded text-sm">Add Area</button>
                        </div>
                        <template x-for="(value, key) in Object.entries(chartSections[\'emotional (chart)\'].subsections)" :key="key">
                            <div class="flex items-center mb-2">
                                <input type="text" x-model="chartSections[\'emotional (chart)\'].subsections[value[0]]" class="flex-1 border rounded px-2 py-1 mr-2" placeholder="e.g. Self-Awareness">
                                <button @click="removeSubsection(\'emotional (chart)\', value[0])" class="text-red-500">
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </div>

                    <!-- Physical Development -->
                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="font-bold">Physical Development</h4>
                            <button @click="addSubsection(\'physical (chart)\')" type="button" class="bg-green-500 text-white px-3 py-1 rounded text-sm">Add Area</button>
                        </div>
                        <template x-for="(value, key) in Object.entries(chartSections[\'physical (chart)\'].subsections)" :key="key">
                            <div class="flex items-center mb-2">
                                <input type="text" x-model="chartSections[\'physical (chart)\'].subsections[value[0]]" class="flex-1 border rounded px-2 py-1 mr-2" placeholder="e.g. Gross Motor">
                                <button @click="removeSubsection(\'physical (chart)\', value[0])" class="text-red-500">
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </div>

                    <!-- Mental Development -->
                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="font-bold">Mental Development</h4>
                            <button @click="addSubsection(\'mental (chart)\')" type="button" class="bg-green-500 text-white px-3 py-1 rounded text-sm">Add Area</button>
                        </div>
                        <template x-for="(value, key) in Object.entries(chartSections[\'mental (chart)\'].subsections)" :key="key">
                            <div class="flex items-center mb-2">
                                <input type="text" x-model="chartSections[\'mental (chart)\'].subsections[value[0]]" class="flex-1 border rounded px-2 py-1 mr-2" placeholder="e.g. Focus">
                                <button @click="removeSubsection(\'mental (chart)\', value[0])" class="text-red-500">
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
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
    $col = $row->addColumn();
    $col->addSubmit(__('Submit'))
        ->addClass('bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded shadow-lg')
        ->prepend('<i class="fas fa-save mr-2"></i>');

    echo $form->getOutput();
}
