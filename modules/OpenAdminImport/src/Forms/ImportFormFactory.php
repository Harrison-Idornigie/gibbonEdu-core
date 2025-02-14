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

namespace Gibbon\Module\OpenAdminImport\Forms;

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;

/**
 * Import Form Factory
 *
 * @version v29
 * @since   v29
 */
class ImportFormFactory extends DatabaseFormFactory
{
    /**
     * Create a file upload form for step 1
     *
     * @param string $type
     * @return Form
     */
    public function createFileUploadForm($type)
    {
        $form = Form::create('importStep1', '/modules/OpenAdminImport/oa_import_run.php');
        
        $form->addHiddenValue('address', '/modules/OpenAdminImport/oa_import_run.php');
        $form->addHiddenValue('type', $type);
        $form->addHiddenValue('step', 2);

        $row = $form->addRow();
        $row->addHeading('Step 1 - Upload File', __('Upload CSV File'))
            ->append(' <i title="' . __('Show/Hide') . '" class="toggleDetails fas fa-chevron-down ml-2"></i>')
            ->addClass('toggleDetails')
            ->addClass('font-bold');
        
        $row = $form->addRow();
        $row->addLabel('file', __('CSV File'));
        $row->addFileUpload('file')
            ->required()
            ->accepts('.csv');

        $row = $form->addRow();
        $row->addLabel('fieldDelimiter', __('Field Delimiter'));
        $row->addTextField('fieldDelimiter')
            ->required()
            ->maxLength(1)
            ->setValue(',');

        $row = $form->addRow();
        $row->addLabel('stringEnclosure', __('String Enclosure'));
        $row->addTextField('stringEnclosure')
            ->required()
            ->maxLength(1)
            ->setValue('"');

        $row = $form->addRow();
        $row->addLabel('mode', __('Mode'));
        $row->addSelect('mode')
            ->fromArray(['insert' => __('Insert'), 'update' => __('Update')])
            ->required();

        $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

        return $form;
    }

    /**
     * Create a column mapping form for step 2
     *
     * @param string $type
     * @param array $headers
     * @param array $firstRow
     * @param array $mapping
     * @return Form
     */
    public function createMappingForm($type, $headers, $firstRow, $mapping)
    {
        $form = Form::create('importStep2', '/modules/OpenAdminImport/oa_import_run.php');
        
        $form->addHiddenValue('address', '/modules/OpenAdminImport/oa_import_run.php');
        $form->addHiddenValue('type', $type);
        $form->addHiddenValue('step', 3);
        $form->addHiddenValue('mode', $_POST['mode']);
        $form->addHiddenValue('file', $_FILES['file']['tmp_name']);
        $form->addHiddenValue('fieldDelimiter', $_POST['fieldDelimiter']);
        $form->addHiddenValue('stringEnclosure', $_POST['stringEnclosure']);

        $row = $form->addRow();
        $row->addHeading('Step 2 - Map Columns', __('Map CSV Columns to Gibbon Fields'))
            ->append(' <i title="' . __('Show/Hide') . '" class="toggleDetails fas fa-chevron-down ml-2"></i>')
            ->addClass('toggleDetails')
            ->addClass('font-bold');

        $table = $form->addRow()->addTable()->setClass('colorOddEven w-full');
        
        $header = $table->addHeaderRow();
        $header->addContent(__('Gibbon Field'));
        $header->addContent(__('CSV Column'));
        $header->addContent(__('Sample Data'));

        foreach ($mapping as $gibbonField => $defaultMapping) {
            $row = $table->addRow();
            $row->addContent(__($gibbonField));
            
            $select = $row->addSelect('mapping['.$gibbonField.']')
                ->fromArray(array_combine($headers, $headers))
                ->placeholder();

            foreach ($headers as $index => $header) {
                if (mb_strtolower($header) == mb_strtolower($defaultMapping)) {
                    $select->selected($header);
                    break;
                }
            }

            $row->addContent($firstRow[$index] ?? '');
        }

        $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

        return $form;
    }
}
