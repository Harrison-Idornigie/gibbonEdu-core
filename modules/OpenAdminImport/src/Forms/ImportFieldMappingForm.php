<?php
namespace Gibbon\Module\OpenAdminImport\Forms;

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;

/**
 * Import Field Mapping Form
 *
 * @version v29
 * @since   v29
 */
class ImportFieldMappingForm
{
    protected $form;
    protected $csvHeaders;
    protected $importFields;

    /**
     * Creates a form with the default factory and renderer.
     */
    public static function create($id, $action, $method = 'post', $class = 'standardForm'): self
    {
        return self::createWithOptions($id, $action, [], [], $method, $class);
    }

    /**
     * Creates a form specifically for field mapping.
     */
    public static function createWithOptions($id, $action, array $csvHeaders, array $importFields, $method = 'post', $class = 'standardForm'): self
    {
        global $container, $page;

        // Create base form with factory
        $form = Form::create($id, $action, $method, $class);
        $factory = $container->get(DatabaseFormFactory::class);
        $form->setFactory($factory);

        // Create instance and set properties
        $instance = new self($form);
        $instance->csvHeaders = $csvHeaders;
        $instance->importFields = $instance->validateImportFields($importFields);

        // Add hidden values
        $form->addHiddenValue('address', $_GET['q'] ?? '');

        // Add collapsible section
        $row = $form->addRow();
        $heading = $row->addColumn()->addHeading(__('Field Mapping Preview'));
        $heading->append(' <i title="' . __('Show/Hide') . '" class="toggleDetails fas fa-chevron-down ml-2"></i>');
        $heading->addClass('toggleDetails font-bold');

        // Add Alpine.js wrapper div
        $row = $form->addRow();
        $row->addContent('<div x-data="importMapping">');

        // Build mapping table
        $row = $form->addRow();
        $table = $row->addTable()->setClass('w-full');
        
        $header = $table->addHeaderRow();
        $header->addContent(__('CSV Field'));
        $header->addContent(__('Database Field'));
        $header->addContent(__('Required'));
        $header->addContent(__('Preview'));

        if (!empty($instance->csvHeaders) && !empty($instance->importFields)) {
            // Build select options
            $selectOptions = ['' => __('Do not import')];
            foreach ($instance->importFields as $field => $config) {
                $label = $config['label'] ?? $field;
                $selectOptions[$field] = __($label);
            }

            foreach ($instance->csvHeaders as $index => $header) {
                $row = $table->addRow()->setClass('mappingRow');

                $row->addContent($header)
                    ->setClass('csvField')
                    ->wrap('<div class="dragHandle">', '</div>');

                $col = $row->addColumn();
                $col->addSelect('fields['.$index.']')
                    ->fromArray($selectOptions)
                    ->setClass('w-full')
                    ->addValidation('Validate.Custom', ['function(el) { return validateMapping(el); }']);

                $row->addContent('<i class="fas fa-asterisk text-red-500 hidden"></i>')
                    ->setClass('requiredField');

                $row->addContent('')
                    ->setClass('previewField')
                    ->wrap('<div class="previewValue">', '</div>');
            }
        }

        // Close Alpine.js wrapper div
        $row = $form->addRow();
        $row->addContent('</div>');

        // Add validation script
        $page->scripts->add('importMapping', '
            function validateMapping(el) {
                // Get all selected values
                const selects = document.querySelectorAll(\'select[name^="fields"]\');
                const values = Array.from(selects).map(select => select.value).filter(value => value !== "");
                
                // Check for duplicates
                const duplicates = values.filter((value, index) => values.indexOf(value) !== index);
                if (duplicates.length > 0) {
                    return {
                        valid: false,
                        message: "Each database field can only be mapped once"
                    };
                }

                // Check required fields
                const requiredFields = '.json_encode($instance->getRequiredFields()).';
                const mappedFields = values;
                const missingRequired = requiredFields.filter(field => !mappedFields.includes(field));
                
                if (missingRequired.length > 0) {
                    return {
                        valid: false,
                        message: "Required fields are missing: " + missingRequired.join(", ")
                    };
                }

                return {
                    valid: true,
                    message: ""
                };
            }
        ');

        // Add Alpine.js component
        $page->scripts->add('importMappingComponent', '
            document.addEventListener("alpine:init", () => {
                Alpine.data("importMapping", () => ({
                    init() {
                        this.initDragAndDrop();
                        this.initPreview();
                    },
                    initDragAndDrop() {
                        new Sortable(document.querySelector("table"), {
                            handle: ".dragHandle",
                            animation: 150,
                            onEnd: (evt) => {
                                this.updateFieldOrder();
                            }
                        });
                    },
                    initPreview() {
                        const previewRows = document.querySelectorAll(".previewValue");
                        previewRows.forEach(row => {
                            // Add preview data handling
                        });
                    },
                    updateFieldOrder() {
                        const rows = document.querySelectorAll(".mappingRow");
                        rows.forEach((row, index) => {
                            const select = row.querySelector("select");
                            if (select) {
                                const newName = `fields[${index}]`;
                                select.name = newName;
                            }
                        });
                    }
                }));
            });
        ');

        return $instance;
    }

    /**
     * Private constructor to enforce using the static create method.
     */
    private function __construct(Form $form)
    {
        $this->form = $form;
    }

    /**
     * Get the output of the form.
     */
    public function getOutput()
    {
        return $this->form->getOutput();
    }

    /**
     * Get the internal form instance
     * @return Form
     */
    public function getForm(): Form
    {
        return $this->form;
    }

    /**
     * Magic method to delegate unknown method calls to the form instance
     */
    public function __call($method, $args)
    {
        if (method_exists($this->form, $method)) {
            return call_user_func_array([$this->form, $method], $args);
        }
        throw new \BadMethodCallException("Method $method does not exist");
    }

    /**
     * Magic method to delegate property access to the form instance
     */
    public function __get($name)
    {
        if ($name === 'form') {
            return $this->form;
        }
        if (property_exists($this->form, $name)) {
            return $this->form->$name;
        }
        throw new \OutOfBoundsException("Property $name does not exist");
    }

    /**
     * Magic method to delegate property setting to the form instance
     */
    public function __set($name, $value)
    {
        if ($name === 'form') {
            $this->form = $value;
            return;
        }
        if (property_exists($this->form, $name)) {
            $this->form->$name = $value;
            return;
        }
        throw new \OutOfBoundsException("Property $name does not exist");
    }

    /**
     * Validates and normalizes import fields.
     */
    private function validateImportFields(array $fields): array
    {
        return array_filter($fields, function($field) {
            return !empty($field) && is_string($field);
        });
    }

    /**
     * Get list of required fields from the import configuration
     * @return array
     */
    private function getRequiredFields(): array
    {
        $required = [];
        foreach ($this->importFields as $field => $config) {
            if (!empty($config['args']['required']) && $config['args']['required'] === true) {
                $required[] = $field;
            }
        }
        return $required;
    }
}
