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
use Gibbon\Forms\DatabaseFormFactory;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/Extra Reports/report_templates_manage_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $template = $_GET['template'] ?? '';
    
    if (empty($template)) {
        $page->addError(__('No template selected.'));
        return;
    }

    $page->breadcrumbs
        ->add(__('Manage Report Templates'), 'report_templates_manage.php')
        ->add(__('Edit Template'));

    // Get template content
    $templateFile = __DIR__ . '/templates/reportCards/' . basename($template);
    if (!file_exists($templateFile)) {
        $page->addError(__('The specified template cannot be found.'));
        return;
    }

    $templateContent = file_get_contents($templateFile);

    // Create form
    $form = Form::create('templateEdit', $session->get('absoluteURL').'/modules/Extra Reports/report_templates_manage_editProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('template', $template);

    // Template name
    $row = $form->addRow();
    $row->addLabel('name', __('Name'));
    $row->addTextField('name')
        ->setValue(basename($template, '.php'))
        ->required();

    // Visual editor tabs
    $row = $form->addRow();
    $col = $row->addColumn()->addClass('flex flex-col h-screen');
    
    // Add tabs for different sections
    $col->addContent('<div class="flex border-b border-gray-300">
        <button class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border-b-2 border-blue-500" onclick="switchTab(\'layout\')" id="tab-layout">'.__('Layout').'</button>
        <button class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700" onclick="switchTab(\'sections\')" id="tab-sections">'.__('Sections').'</button>
        <button class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700" onclick="switchTab(\'code\')" id="tab-code">'.__('Code').'</button>
    </div>');

    // Layout tab (visual editor)
    $col->addContent('<div id="content-layout" class="flex-1 p-4">
        <div class="grid grid-cols-12 gap-4 h-full">
            <!-- Preview area -->
            <div class="col-span-8 bg-white border rounded-lg shadow-sm p-4">
                <div id="template-preview" class="w-full h-full"></div>
            </div>
            
            <!-- Properties panel -->
            <div class="col-span-4 bg-white border rounded-lg shadow-sm p-4">
                <h3 class="font-bold mb-4">'.__('Properties').'</h3>
                <div id="template-properties"></div>
            </div>
        </div>
    </div>');

    // Sections tab
    $col->addContent('<div id="content-sections" class="flex-1 p-4 hidden">
        <div class="grid grid-cols-12 gap-4 h-full">
            <!-- Available sections -->
            <div class="col-span-4 bg-white border rounded-lg shadow-sm p-4">
                <h3 class="font-bold mb-4">'.__('Available Sections').'</h3>
                <div id="available-sections" class="space-y-2">
                    <div class="p-2 bg-gray-100 rounded cursor-move" draggable="true">Header</div>
                    <div class="p-2 bg-gray-100 rounded cursor-move" draggable="true">Development Chart</div>
                    <div class="p-2 bg-gray-100 rounded cursor-move" draggable="true">Comments</div>
                </div>
            </div>
            
            <!-- Template structure -->
            <div class="col-span-8 bg-white border rounded-lg shadow-sm p-4">
                <h3 class="font-bold mb-4">'.__('Template Structure').'</h3>
                <div id="template-structure" class="min-h-[200px] border-2 border-dashed border-gray-300 p-4">
                    <!-- Sections will be dropped here -->
                </div>
            </div>
        </div>
    </div>');

    // Code tab
    $col->addContent('<div id="content-code" class="flex-1 p-4 hidden">
        <textarea id="template-code" name="content" class="w-full h-full font-mono text-sm p-4 border rounded-lg">'
        .htmlspecialchars($templateContent).
        '</textarea>
    </div>');

    // Add JavaScript for tab switching and drag-drop
    $page->scripts->add('inline', "
        function switchTab(tab) {
            // Hide all content
            document.querySelectorAll('[id^=content-]').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('[id^=tab-]').forEach(el => {
                el.classList.remove('border-b-2', 'border-blue-500', 'text-gray-700');
                el.classList.add('text-gray-500');
            });
            
            // Show selected tab
            document.getElementById('content-' + tab).classList.remove('hidden');
            document.getElementById('tab-' + tab).classList.add('border-b-2', 'border-blue-500', 'text-gray-700');
        }

        // Initialize drag and drop
        document.querySelectorAll('#available-sections [draggable=true]').forEach(el => {
            el.addEventListener('dragstart', e => {
                e.dataTransfer.setData('text/plain', e.target.textContent);
            });
        });

        const dropZone = document.getElementById('template-structure');
        dropZone.addEventListener('dragover', e => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        });

        dropZone.addEventListener('drop', e => {
            e.preventDefault();
            const data = e.dataTransfer.getData('text/plain');
            const div = document.createElement('div');
            div.className = 'p-2 bg-gray-100 rounded mb-2 flex justify-between items-center';
            div.innerHTML = data + '<button onclick=\"this.parentElement.remove()\" class=\"text-red-500 hover:text-red-700\">×</button>';
            dropZone.appendChild(div);
        });
    ");

    $row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

    echo $form->getOutput();
}
