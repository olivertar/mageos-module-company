<?php

declare(strict_types=1);

namespace MageOS\Company\Ui\DataProvider\Attribute;

use Magento\Eav\Model\Entity\Attribute\Source\Table;
use Magento\Framework\Api\Filter;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use MageOS\Company\Controller\Adminhtml\Attribute\Save;
use MageOS\Company\Model\Attribute;
use MageOS\Company\Model\Attribute\AttributeEditor;
use MageOS\Company\Model\Attribute\AttributeRepository;
use MageOS\Company\Model\Attribute\FormCodes;
use MageOS\Company\Model\Attribute\InputTypes;
use MageOS\Company\Model\Company;

/**
 * Data provider of the company attribute form (company_attribute_form).
 *
 * The fields shown and editable depend on AttributeEditor::getPermissions().
 */
class FormDataProvider extends AbstractDataProvider
{
    private const FORM = 'company_attribute_form.company_attribute_form';

    /**
     * Fields that only make sense for some input types: field path → inputs.
     */
    private const INPUT_DEPENDENT = [
        'properties.default_value' => InputTypes::TEXT_INPUTS,
        'validation' => InputTypes::TEXT_INPUTS,
        'validation.input_validation' => ['text'],
        'options' => InputTypes::OPTION_INPUTS,
    ];

    /**
     * @var array<int|string,array<string,mixed>>|null
     */
    private ?array $loadedData = null;

    /**
     * @var int|null
     */
    private ?int $filteredId = null;

    /**
     * @var Attribute|null
     */
    private ?Attribute $attribute = null;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param RequestInterface $request
     * @param AttributeRepository $attributeRepository
     * @param AttributeEditor $attributeEditor
     * @param FormCodes $formCodes
     * @param InputTypes $inputTypes
     * @param StoreManagerInterface $storeManager
     * @param DataPersistorInterface $dataPersistor
     * @param Json $json
     * @param array<string,mixed> $meta
     * @param array<string,mixed> $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        private readonly RequestInterface $request,
        private readonly AttributeRepository $attributeRepository,
        private readonly AttributeEditor $attributeEditor,
        private readonly FormCodes $formCodes,
        private readonly InputTypes $inputTypes,
        private readonly StoreManagerInterface $storeManager,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly Json $json,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Keep the primary key filter: the record is loaded through the repository, not from a collection.
     *
     * @param Filter $filter
     * @return void
     */
    public function addFilter(Filter $filter)
    {
        if ($filter->getField() === $this->getPrimaryFieldName() && $filter->getValue()) {
            $this->filteredId = (int)$filter->getValue();
        }
    }

    /**
     * @inheritDoc
     *
     * @return array<int|string,array<string,mixed>>
     */
    public function getData()
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }
        $attribute = $this->getAttribute();
        $data = $attribute->getId()
            ? $this->toFormData($attribute)
            : [
                'entity_type_code' => $this->getEntityTypeCode(),
                'frontend_input' => 'text',
                'is_visible' => '1',
                'used_in_forms' => array_keys($this->formCodes->getForms($this->getEntityTypeCode())),
                'options' => [],
            ];
        $data['options_submitted'] = '1';
        $persisted = $this->dataPersistor->get(Save::DATA_PERSISTOR_KEY);
        if (is_array($persisted)) {
            $data = array_merge($data, $persisted);
            $this->dataPersistor->clear(Save::DATA_PERSISTOR_KEY);
        }
        $this->loadedData = [((int)$attribute->getId() ?: '') => ['attribute' => $data]];

        return $this->loadedData;
    }

    /**
     * @inheritDoc
     *
     * @return array<string,mixed>
     */
    public function getMeta()
    {
        $meta = parent::getMeta();
        $attribute = $this->getAttribute();
        $permissions = $this->attributeEditor->getPermissions($attribute);
        $isNew = !$attribute->getId();
        $input = (string)$attribute->getFrontendInput();

        $this->setFieldConfig($meta, 'properties', 'attribute_code', ['disabled' => !$permissions['code']]);
        $this->setFieldConfig($meta, 'properties', 'frontend_input', [
            'disabled' => !$permissions['input'],
            'options' => $isNew ? $this->inputTypes->toOptionArray() : [
                ['value' => $input, 'label' => $this->inputTypes->getLabel($input)],
            ],
            'switcherConfig' => $isNew ? $this->getInputSwitcher() : ['enabled' => false],
        ]);
        $this->setFieldConfig($meta, 'properties', 'default_value', ['visible' => $permissions['default_value']]);
        $this->setFieldConfig($meta, 'storefront', 'is_required', ['disabled' => !$permissions['required']]);
        $this->setFieldConfig($meta, 'storefront', 'is_visible', ['visible' => $permissions['visible']]);
        $this->setFieldConfig($meta, 'storefront', 'used_in_forms', [
            'disabled' => !$permissions['forms'],
            'options' => $this->getFormOptions(),
            'notice' => $permissions['forms']
                ? ''
                : __('The module relies on this attribute: its forms cannot be changed.'),
        ]);
        $this->setFieldConfig($meta, 'validation', 'input_validation', [
            'visible' => $isNew || $input === 'text',
            'options' => $this->getValidationOptions(),
        ]);
        $meta['validation']['arguments']['data']['config']['visible'] = $permissions['validation'];
        $meta['grid']['arguments']['data']['config']['visible'] = $permissions['grid']
            || ($isNew && $this->getEntityTypeCode() === Company::ENTITY);
        $meta['options']['arguments']['data']['config']['visible'] = $permissions['options'];
        $meta['labels']['children'] = $this->getStoreLabelFields();

        return $meta;
    }

    /**
     * Edited attribute, or a new one of the requested entity type.
     *
     * @return Attribute
     */
    private function getAttribute(): Attribute
    {
        if ($this->attribute !== null) {
            return $this->attribute;
        }
        $attributeId = $this->filteredId ?? (int)$this->request->getParam($this->getRequestFieldName());
        if ($attributeId) {
            try {
                return $this->attribute = $this->attributeRepository->getById($attributeId);
            } catch (NoSuchEntityException) {
                $attributeId = 0;
            }
        }
        $entityTypeCode = (string)$this->request->getParam('entity_type');
        if (!isset($this->formCodes->getEntityTypes()[$entityTypeCode])) {
            $entityTypeCode = Company::ENTITY;
        }
        $this->attribute = $this->attributeRepository->create($entityTypeCode);
        $this->attribute->setData('is_user_defined', 1);
        $this->attribute->setData('frontend_input', 'text');

        return $this->attribute;
    }

    /**
     * Entity type of the edited attribute.
     *
     * @return string
     */
    private function getEntityTypeCode(): string
    {
        return (string)$this->attributeRepository->getEntityTypeCode($this->getAttribute());
    }

    /**
     * Form values of an existing attribute.
     *
     * @param Attribute $attribute
     * @return array<string,mixed>
     */
    private function toFormData(Attribute $attribute): array
    {
        $rules = [];
        $rawRules = $attribute->getData('validate_rules');
        if (is_string($rawRules) && $rawRules !== '') {
            $rules = (array)$this->json->unserialize($rawRules);
        } elseif (is_array($rawRules)) {
            $rules = $rawRules;
        }
        $data = [
            'attribute_id' => (string)$attribute->getId(),
            'entity_type_code' => $this->getEntityTypeCode(),
            'attribute_code' => (string)$attribute->getAttributeCode(),
            'frontend_label' => (string)$attribute->getFrontendLabel(),
            'frontend_input' => (string)$attribute->getFrontendInput(),
            'default_value' => (string)$attribute->getDefaultValue(),
            'sort_order' => (string)(int)$attribute->getData('sort_order'),
            'is_required' => (string)(int)$attribute->getIsRequired(),
            'is_visible' => (string)(int)$attribute->getData('is_visible'),
            'used_in_forms' => array_values(array_map('strval', (array)$attribute->getUsedInForms())),
            'input_validation' => (string)($rules['input_validation'] ?? ''),
            'min_text_length' => isset($rules['min_text_length']) ? (string)$rules['min_text_length'] : '',
            'max_text_length' => isset($rules['max_text_length']) ? (string)$rules['max_text_length'] : '',
            'store_labels' => array_map('strval', (array)$attribute->getStoreLabels()),
            'options' => [],
        ];
        foreach (['is_used_in_grid', 'is_visible_in_grid', 'is_filterable_in_grid', 'is_searchable_in_grid'] as $flag) {
            $data[$flag] = (string)(int)$attribute->getData($flag);
        }
        if ($this->attributeEditor->getPermissions($attribute)['options']) {
            $data['options'] = $this->getOptionRows($attribute);
        }

        return $data;
    }

    /**
     * Option rows for the dynamic rows component.
     *
     * @param Attribute $attribute
     * @return array<int,array<string,string>>
     */
    private function getOptionRows(Attribute $attribute): array
    {
        $defaults = array_filter(explode(',', (string)$attribute->getDefaultValue()));
        $rows = [];
        /** @var Table $source Option attributes editable here use the table source. */
        $source = $attribute->getSource();
        foreach ($source->getAllOptions(false, true) as $position => $option) {
            $rows[] = [
                'record_id' => (string)$position,
                'option_id' => (string)$option['value'],
                'label' => (string)$option['label'],
                'is_default' => in_array((string)$option['value'], $defaults, true) ? '1' : '0',
                'position' => (string)$position,
            ];
        }

        return $rows;
    }

    /**
     * Forms of the entity type as options.
     *
     * @return array<int,array{value: string, label: string}>
     */
    private function getFormOptions(): array
    {
        $options = [];
        foreach ($this->formCodes->getForms($this->getEntityTypeCode()) as $code => $label) {
            $options[] = ['value' => $code, 'label' => $label];
        }

        return $options;
    }

    /**
     * Input validation rules as options.
     *
     * @return array<int,array{value: string, label: \Magento\Framework\Phrase}>
     */
    private function getValidationOptions(): array
    {
        return [
            ['value' => '', 'label' => __('None')],
            ['value' => 'alphanumeric', 'label' => __('Alphanumeric')],
            ['value' => 'alphanum-with-spaces', 'label' => __('Alphanumeric with Spaces')],
            ['value' => 'numeric', 'label' => __('Numeric Only')],
            ['value' => 'alpha', 'label' => __('Alpha Only')],
            ['value' => 'url', 'label' => __('URL')],
            ['value' => 'email', 'label' => __('Email')],
        ];
    }

    /**
     * Switcher of a new attribute's input type: shows the fields that apply to the chosen input.
     *
     * @return array<string,mixed>
     */
    private function getInputSwitcher(): array
    {
        $rules = [];
        foreach ($this->inputTypes->toOptionArray() as $option) {
            $actions = [];
            foreach (self::INPUT_DEPENDENT as $path => $inputs) {
                $actions[] = [
                    'target' => self::FORM . '.' . $path,
                    'callback' => 'visible',
                    'params' => [in_array($option['value'], $inputs, true)],
                ];
            }
            $rules[] = ['value' => $option['value'], 'actions' => $actions];
        }

        return ['enabled' => true, 'rules' => $rules];
    }

    /**
     * One label field per store view.
     *
     * @return array<string,mixed>
     */
    private function getStoreLabelFields(): array
    {
        $fields = [];
        $sortOrder = 10;
        foreach ($this->storeManager->getStores() as $store) {
            /** @var \Magento\Store\Model\Store $store */
            $storeId = (int)$store->getId();
            $fields['store_label_' . $storeId] = ['arguments' => ['data' => ['config' => [
                'componentType' => 'field',
                'formElement' => 'input',
                'dataType' => 'text',
                'dataScope' => 'store_labels.' . $storeId,
                'label' => sprintf('%s / %s', $store->getWebsite()->getName(), $store->getName()),
                'sortOrder' => $sortOrder++,
            ]]]];
        }

        return $fields;
    }

    /**
     * Merge configuration into a field declared in the form XML.
     *
     * @param array<string,mixed> $meta
     * @param string $fieldset
     * @param string $field
     * @param array<string,mixed> $config
     * @return void
     */
    private function setFieldConfig(array &$meta, string $fieldset, string $field, array $config): void
    {
        $current = $meta[$fieldset]['children'][$field]['arguments']['data']['config'] ?? [];
        $meta[$fieldset]['children'][$field]['arguments']['data']['config'] = array_merge($current, $config);
    }
}
