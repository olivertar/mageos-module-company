<?php

declare(strict_types=1);

namespace MageOS\Company\Ui\DataProvider\Form;

use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Ui\DataProvider\EavValidationRules;
use MageOS\Company\Model\ResourceModel\Form\Attribute\Collection as FormAttributeCollection;
use MageOS\Company\Model\ResourceModel\Form\Attribute\CollectionFactory as FormAttributeCollectionFactory;

/**
 * Builds UI form field metadata from the EAV attributes assigned to a form code.
 */
class AttributeMetaProvider
{
    /**
     * Frontend input → UI form element.
     */
    private const FORM_ELEMENTS = [
        'text' => 'input',
        'textarea' => 'textarea',
        'select' => 'select',
        'multiselect' => 'multiselect',
        'boolean' => 'checkbox',
        'date' => 'date',
        'multiline' => 'input',
    ];

    /**
     * @param FormAttributeCollectionFactory $collectionFactory
     * @param EavValidationRules $validationRules
     */
    public function __construct(
        private readonly FormAttributeCollectionFactory $collectionFactory,
        private readonly EavValidationRules $validationRules
    ) {
    }

    /**
     * Visible attributes of a form, sorted.
     *
     * @param string $entityTypeCode
     * @param string $formCode
     * @return AbstractAttribute[] Keyed by attribute code.
     */
    public function getAttributes(string $entityTypeCode, string $formCode): array
    {
        /** @var FormAttributeCollection $collection */
        $collection = $this->collectionFactory->create();
        $collection->setEntityType($entityTypeCode)
            ->addFormCodeFilter($formCode)
            ->setSortOrder();
        $collection->addFieldToFilter('ca.is_visible', ['eq' => 1]);

        $attributes = [];
        foreach ($collection as $attribute) {
            /** @var AbstractAttribute $attribute */
            $attributes[(string)$attribute->getAttributeCode()] = $attribute;
        }

        return $attributes;
    }

    /**
     * UI field metadata of a form, keyed by attribute code.
     *
     * @param string $entityTypeCode
     * @param string $formCode
     * @return array<string,mixed>
     */
    public function getFieldsMeta(string $entityTypeCode, string $formCode): array
    {
        $meta = [];
        foreach ($this->getAttributes($entityTypeCode, $formCode) as $code => $attribute) {
            $config = $this->getFieldConfig($attribute);
            if ($config === null) {
                continue;
            }
            $meta[$code] = ['arguments' => ['data' => ['config' => $config]]];
        }

        return $meta;
    }

    /**
     * Field configuration of one attribute, or null if it has no UI representation.
     *
     * @param AbstractAttribute $attribute
     * @return array<string,mixed>|null
     */
    private function getFieldConfig(AbstractAttribute $attribute): ?array
    {
        $input = (string)$attribute->getFrontendInput();
        $formElement = self::FORM_ELEMENTS[$input] ?? null;
        if ($formElement === null) {
            return null;
        }
        $code = (string)$attribute->getAttributeCode();
        $required = (bool)$attribute->getIsRequired();
        $config = [
            'componentType' => 'field',
            'formElement' => $formElement,
            'dataType' => $input === 'boolean' ? 'boolean' : 'text',
            'dataScope' => $code,
            'label' => __($attribute->getStoreLabel() ?: (string)$attribute->getFrontendLabel()),
            'sortOrder' => (int)$attribute->getData('sort_order'),
            'visible' => true,
            'required' => $required,
            'validation' => $this->validationRules->build($attribute, ['required' => $required]),
        ];
        if ($attribute->getDefaultValue() !== null && $attribute->getDefaultValue() !== '') {
            $config['default'] = $attribute->getDefaultValue();
        }
        if ($attribute->usesSource()) {
            $config['options'] = $this->getOptions($attribute);
        }
        if ($input === 'boolean') {
            $config['prefer'] = 'toggle';
            $config['valueMap'] = ['true' => '1', 'false' => '0'];
        }
        if ($input === 'multiline') {
            $config['size'] = (int)$attribute->getMultilineCount();
        }
        if ($input === 'date') {
            $config['options'] = ['dateFormat' => 'y-MM-dd'];
        }

        return $config;
    }

    /**
     * Source options with string values, as the UI select expects.
     *
     * @param AbstractAttribute $attribute
     * @return array<int,array<string,mixed>>
     */
    private function getOptions(AbstractAttribute $attribute): array
    {
        $options = [];
        foreach ($attribute->getSource()->getAllOptions() as $option) {
            if (!is_array($option) || !array_key_exists('value', $option) || is_array($option['value'])) {
                continue;
            }
            $option['value'] = (string)$option['value'];
            $option['label'] = (string)($option['label'] ?? '');
            $options[] = $option;
        }

        return $options;
    }
}
