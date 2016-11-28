<?php
namespace Salesforce;

class Metadata 
{
    /**
     * Original source of metadata
     *
     * @var array
     */
    private $_source;

    /**
     * Array of metadata fields
     *
     * @var array
     */
    protected $fields;

    const INTEGER_TYPE = 'int';
    const STRING_TYPE = 'string';
    const BOOLEAN_TYPE = 'boolean';
    const DOUBLE_TYPE = 'double';
    const DATE_TYPE = 'date';
    const DATETIME_TYPE = 'datetime';
    const TEXTAREA_TYPE = 'textarea';
    const ADDRESS_TYPE = 'address';
    const PHONE_TYPE = 'phone';
    const URL_TYPE = 'url';
    const CURRENCY_TYPE = 'currency';
    const PICKLIST_TYPE = 'picklist';
    const IDENTITY_TYPE = 'id';
    const REFERENCE_TYPE = 'reference';

    public function __construct($metadata) {

        $this->_source = $metadata;
        $this->parseFields($metadata->fields);

    }

    /**
     * Parse metadata for fields
     * @param  array Array of fields
     * @return void
     */
    protected function parseFields(array $fields) {
        $this->fields = [];
        foreach($fields as $rawField) {

            $field = (object) [
                'name' => $rawField->name,
                'label' => $rawField->label,
                'nullable' => $rawField->nillable,
                'type' => $rawField->type,
                'default' => $rawField->defaultValue,
            ];

            switch($rawField->type) {
                case self::INTEGER_TYPE:
                    $field->digits = $rawField->digits;
                    break;

                case self::DOUBLE_TYPE:
                    $field->scale = $rawField->scale;
                    $field->precision = $rawField->precision;
                    break;

                case self::REFERENCE_TYPE:
                    $field->relationship = (object) [
                        'referenceTo' => $rawField->referenceTo,
                        'name' => $rawField->relationshipName,
                    ];
                    break;

                case self::PICKLIST_TYPE:
                    $field->values = array_map(function($var) {
                        return [
                            'label' => $var->label,
                            'value' => $var->value,
                        ];
                    }, $rawField->picklistValues);
                    break;

                case self::IDENTITY_TYPE:
                case self::BOOLEAN_TYPE:
                case self::STRING_TYPE:
                case self::TEXTAREA_TYPE:
                case self::ADDRESS_TYPE:
                case self::PHONE_TYPE:
                case self::URL_TYPE:
                case self::CURRENCY_TYPE:
                case self::DATE_TYPE:
                case self::DATETIME_TYPE:
                    break;
            }

            $key = mb_strtolower($rawField->name, 'UTF-8');
            $this->fields[$key] = $field;
        }
    }

    /**
     * Does metadata contain the field name passed
     * @param  string Field name to reference
     * @return bool
     */
    public function hasField($fieldName) {
        return isset($this->fields[$fieldName]);
    }

    /**
     * @param  string
     * @param  mixed
     * @return bool
     */
    public function validateFieldValue($fieldName, $value) {
        return true;
    }

}