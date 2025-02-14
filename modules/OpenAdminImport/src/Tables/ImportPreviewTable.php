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

namespace Gibbon\Module\OpenAdminImport\Tables;

use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Domain\DataSet;

/**
 * Import Preview Table
 *
 * @version v29
 * @since   v29
 */
class ImportPreviewTable extends DataTable
{
    protected $fieldMap;
    protected $importFields;

    /**
     * Creates a table for previewing mapped CSV data.
     *
     * @param array $fieldMap
     * @param array $importFields
     */
    public function __construct(array $fieldMap, array $importFields)
    {
        parent::__construct('importPreview');

        $this->fieldMap = $fieldMap;
        $this->importFields = $importFields;

        $this->setTitle(__('Data Preview'));
        $this->setDescription(__('Preview the first few rows of data after mapping.'));

        // Add columns based on field mapping
        foreach ($fieldMap as $csvField => $dbField) {
            if (empty($dbField)) continue;

            $this->addColumn('col'.$csvField, [
                'name' => $importFields[$dbField] ?? $dbField,
                'description' => $csvField,
                'format' => function ($row) use ($csvField, $dbField) {
                    return $this->formatFieldValue($row[$csvField], $dbField);
                }
            ]);
        }

        // Add validation status column
        $this->addColumn('status', [
            'name' => __('Status'),
            'width' => '120px',
            'format' => function ($row) {
                return $this->validateRow($row)
                    ? Format::tag(__('Valid'), 'success')
                    : Format::tag(__('Invalid'), 'error');
            }
        ]);
    }

    /**
     * Format a field value based on its type.
     *
     * @param mixed $value
     * @param string $fieldType
     * @return string
     */
    protected function formatFieldValue($value, $fieldType)
    {
        if (empty($value)) return Format::small(__('Empty'));

        // Add field-specific formatting here
        switch ($fieldType) {
            case 'date':
                return Format::date($value);
            case 'timestamp':
                return Format::dateTime($value);
            case 'yesno':
                return Format::yesNo($value);
            default:
                return $value;
        }
    }

    /**
     * Validate a row of data.
     *
     * @param array $row
     * @return bool
     */
    protected function validateRow($row)
    {
        foreach ($this->fieldMap as $csvField => $dbField) {
            if (empty($dbField)) continue;

            // Check required fields
            if (in_array($dbField, $this->getRequiredFields()) && empty($row[$csvField])) {
                return false;
            }

            // Add more validation rules here
        }

        return true;
    }

    /**
     * Get a list of required fields.
     *
     * @return array
     */
    protected function getRequiredFields()
    {
        // Add logic to determine required fields
        return [];
    }
}
