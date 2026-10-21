<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Attribute;

use Magento\Eav\Model\Entity\Attribute\Source\Table;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Company\Model\Attribute;
use MageOS\Company\Model\Company;

/**
 * Create and edit company/company address attributes from the admin form.
 *
 * Custom attributes are fully editable. System attributes only allow changes that the module code does not rely on:
 * labels, sort order, options of option-based attributes, grid flags and, for optional EAV attributes, their forms.
 */
class AttributeEditor
{
    public const CODE_PATTERN = '/^[a-z][a-z0-9_]{1,29}$/';

    /**
     * Codes that would clash with entity fields, grid columns or API payloads.
     */
    private const RESERVED_CODES = [
        'id', 'uid', 'entity_id', 'parent_id', 'is_active', 'attribute_set_id', 'custom_attributes',
        'extension_attributes', 'store_labels', 'super_user_name', 'super_user_email', 'users_count',
        'unread_messages', 'default_billing', 'default_shipping', 'legal_address', 'created_at', 'updated_at',
        'region_name', 'role', 'job_title',
    ];

    /**
     * Allowed "input_validation" rules for text attributes.
     */
    public const INPUT_VALIDATIONS = ['alphanumeric', 'alphanum-with-spaces', 'numeric', 'alpha', 'url', 'email'];

    /**
     * @param AttributeRepository $attributeRepository
     * @param InputTypes $inputTypes
     * @param FormCodes $formCodes
     * @param StoreManagerInterface $storeManager
     * @param ResourceConnection $resourceConnection
     * @param Json $json
     */
    public function __construct(
        private readonly AttributeRepository $attributeRepository,
        private readonly InputTypes $inputTypes,
        private readonly FormCodes $formCodes,
        private readonly StoreManagerInterface $storeManager,
        private readonly ResourceConnection $resourceConnection,
        private readonly Json $json
    ) {
    }

    /**
     * What can be changed in an attribute.
     *
     * @param Attribute $attribute
     * @return array<string,bool>
     */
    public function getPermissions(Attribute $attribute): array
    {
        $userDefined = (bool)$attribute->getIsUserDefined();
        $static = $attribute->getBackendType() === 'static';
        $input = (string)$attribute->getFrontendInput();
        $isNew = !$attribute->getId();
        $sourceModel = (string)$attribute->getSourceModel();

        return [
            'code' => $isNew,
            'input' => $isNew,
            'required' => $userDefined,
            'visible' => $userDefined,
            'validation' => $userDefined && in_array($input, InputTypes::TEXT_INPUTS, true),
            'default_value' => $userDefined && in_array($input, InputTypes::TEXT_INPUTS, true),
            'forms' => $userDefined || (!$static && !$attribute->getIsRequired() && $input !== 'hidden'),
            'options' => in_array($input, InputTypes::OPTION_INPUTS, true)
                && ($sourceModel === '' || $sourceModel === Table::class),
            'grid' => !$static && $this->attributeRepository->getEntityTypeCode($attribute) === Company::ENTITY,
            'delete' => $userDefined && !$isNew,
        ];
    }

    /**
     * Create or update an attribute from posted form data.
     *
     * @param array<string,mixed> $data
     * @return Attribute
     * @throws LocalizedException
     */
    public function save(array $data): Attribute
    {
        $attributeId = (int)($data['attribute_id'] ?? 0);
        if ($attributeId) {
            $attribute = $this->attributeRepository->getById($attributeId);
        } else {
            $attribute = $this->createAttribute($data);
        }
        $entityTypeCode = (string)$this->attributeRepository->getEntityTypeCode($attribute);
        $permissions = $this->getPermissions($attribute);
        $errors = [];

        $this->applyLabels($attribute, $data, $errors);
        if (array_key_exists('sort_order', $data)) {
            $attribute->setData('sort_order', (int)$data['sort_order']);
        }
        if ($permissions['required']) {
            $attribute->setIsRequired(!empty($data['is_required']));
        }
        if ($permissions['visible'] && array_key_exists('is_visible', $data)) {
            $attribute->setData('is_visible', (int)!empty($data['is_visible']));
        }
        if ($permissions['default_value']) {
            $default = trim((string)($data['default_value'] ?? ''));
            $attribute->setData('default_value', $default === '' ? null : $default);
        }
        if ($permissions['validation']) {
            $attribute->setData('validate_rules', $this->buildValidateRules($attribute, $data, $errors));
        }
        if ($permissions['forms']) {
            $forms = array_map('strval', (array)($data['used_in_forms'] ?? []));
            $attribute->setData(
                'used_in_forms',
                array_values(array_intersect($forms, array_keys($this->formCodes->getForms($entityTypeCode))))
            );
        }
        if ($permissions['options'] && !empty($data['options_submitted'])) {
            $this->applyOptions($attribute, $data, $errors);
        }
        if ($permissions['grid']) {
            $this->applyGridFlags($attribute, $data);
        }
        if ($errors) {
            $exception = new InputException();
            foreach ($errors as $error) {
                $exception->addError($error);
            }
            throw $exception;
        }

        return $this->attributeRepository->save($attribute);
    }

    /**
     * Delete a custom attribute.
     *
     * @param int $attributeId
     * @return void
     * @throws LocalizedException
     */
    public function delete(int $attributeId): void
    {
        $this->attributeRepository->delete($this->attributeRepository->getById($attributeId));
    }

    /**
     * New custom attribute with validated code and input type.
     *
     * @param array<string,mixed> $data
     * @return Attribute
     * @throws LocalizedException
     */
    private function createAttribute(array $data): Attribute
    {
        $entityTypeCode = (string)($data['entity_type_code'] ?? '');
        $attribute = $this->attributeRepository->create($entityTypeCode);

        $code = trim((string)($data['attribute_code'] ?? ''));
        if (!preg_match(self::CODE_PATTERN, $code)) {
            throw new LocalizedException(__(
                'The attribute code must have 2 to 30 characters: lowercase letters (a-z), numbers (0-9) or '
                . 'underscores (_), starting with a letter.'
            ));
        }
        if ($this->isReserved($entityTypeCode, $code) || $this->attributeRepository->exists($entityTypeCode, $code)) {
            throw new LocalizedException(__('The attribute code "%1" is already in use.', $code));
        }

        $input = (string)($data['frontend_input'] ?? '');
        if (!$this->inputTypes->isAllowed($input)) {
            throw new LocalizedException(__('Select a valid input type.'));
        }
        $storage = $this->inputTypes->getStorage($input);
        $entityType = $attribute->getEntityType();

        $attribute->addData([
            'attribute_code' => $code,
            'frontend_input' => $input,
            'backend_type' => $storage['backend_type'],
            'backend_model' => $storage['backend_model'],
            'source_model' => $storage['source_model'],
            'is_user_defined' => 1,
            'is_system' => 0,
            'is_visible' => 1,
            'is_unique' => 0,
            'attribute_set_id' => (int)$entityType->getDefaultAttributeSetId(),
            'attribute_group_id' => $this->getDefaultGroupId((int)$entityType->getDefaultAttributeSetId()),
        ]);

        return $attribute;
    }

    /**
     * Whether a code clashes with an entity column or a reserved name.
     *
     * @param string $entityTypeCode
     * @param string $code
     * @return bool
     */
    private function isReserved(string $entityTypeCode, string $code): bool
    {
        if (in_array($code, self::RESERVED_CODES, true)) {
            return true;
        }
        $table = $entityTypeCode === Company::ENTITY ? 'company_entity' : 'company_address_entity';
        $columns = $this->resourceConnection->getConnection()->describeTable(
            $this->resourceConnection->getTableName($table)
        );

        return isset($columns[$code]);
    }

    /**
     * Default attribute group of an attribute set.
     *
     * @param int $attributeSetId
     * @return int
     */
    private function getDefaultGroupId(int $attributeSetId): int
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName('eav_attribute_group'), ['attribute_group_id'])
            ->where('attribute_set_id = ?', $attributeSetId)
            ->order(['default_id DESC', 'sort_order ASC'])
            ->limit(1);

        return (int)$connection->fetchOne($select);
    }

    /**
     * Default label and per store view labels.
     *
     * @param Attribute $attribute
     * @param array<string,mixed> $data
     * @param Phrase[] $errors
     * @return void
     */
    private function applyLabels(Attribute $attribute, array $data, array &$errors): void
    {
        $label = trim((string)($data['frontend_label'] ?? ''));
        if ($label === '') {
            $errors[] = __('"%1" is required.', __('Default Label'));
            return;
        }
        $labels = [0 => $label];
        $storeLabels = is_array($data['store_labels'] ?? null) ? $data['store_labels'] : [];
        foreach ($this->storeManager->getStores() as $store) {
            $storeLabel = trim((string)($storeLabels[$store->getId()] ?? ''));
            if ($storeLabel !== '') {
                $labels[(int)$store->getId()] = $storeLabel;
            }
        }
        $attribute->setData('frontend_label', $labels);
    }

    /**
     * Validation rules of a text attribute.
     *
     * @param Attribute $attribute
     * @param array<string,mixed> $data
     * @param Phrase[] $errors
     * @return array<string,int|string>
     */
    private function buildValidateRules(Attribute $attribute, array $data, array &$errors): array
    {
        $rules = [];
        $current = $attribute->getData('validate_rules');
        if (is_string($current) && $current !== '') {
            $rules = (array)$this->json->unserialize($current);
        }
        unset($rules['input_validation'], $rules['min_text_length'], $rules['max_text_length']);

        $validation = (string)($data['input_validation'] ?? '');
        if ($validation !== '' && $attribute->getFrontendInput() === 'text') {
            if (!in_array($validation, self::INPUT_VALIDATIONS, true)) {
                $errors[] = __('Select a valid input validation.');
            } else {
                $rules['input_validation'] = $validation;
            }
        }
        $min = trim((string)($data['min_text_length'] ?? ''));
        $max = trim((string)($data['max_text_length'] ?? ''));
        foreach (['min_text_length' => $min, 'max_text_length' => $max] as $rule => $value) {
            if ($value === '') {
                continue;
            }
            if (!ctype_digit($value)) {
                $errors[] = __('Text lengths must be whole numbers.');
                continue;
            }
            $rules[$rule] = (int)$value;
        }
        if (isset($rules['min_text_length'], $rules['max_text_length'])
            && $rules['min_text_length'] > $rules['max_text_length']
        ) {
            $errors[] = __('The minimum text length cannot be greater than the maximum.');
        }

        return $rules;
    }

    /**
     * Options of a dropdown/multiple select attribute, in the format of the EAV attribute resource.
     *
     * Only applied when the form says it posted the options ("options_submitted"): an empty list then deletes them all.
     *
     * @param Attribute $attribute
     * @param array<string,mixed> $data
     * @param Phrase[] $errors
     * @return void
     */
    private function applyOptions(Attribute $attribute, array $data, array &$errors): void
    {
        $existing = [];
        if ($attribute->getId()) {
            /** @var Table $source */
            $source = $attribute->getSource();
            foreach ($source->getAllOptions(false, true) as $option) {
                $existing[(string)$option['value']] = true;
            }
        }
        $option = ['value' => [], 'order' => [], 'delete' => []];
        $default = [];
        $labels = [];
        $rows = is_array($data['options'] ?? null) ? array_values($data['options']) : [];
        foreach ($rows as $index => $row) {
            if (!is_array($row) || !empty($row['delete'])) {
                continue;
            }
            $label = trim((string)($row['label'] ?? ''));
            if ($label === '') {
                $errors[] = __('Option labels cannot be empty.');
                continue;
            }
            if (isset($labels[mb_strtolower($label)])) {
                $errors[] = __('The option "%1" is duplicated.', $label);
                continue;
            }
            $labels[mb_strtolower($label)] = true;
            $optionId = (string)($row['option_id'] ?? '');
            $key = isset($existing[$optionId]) ? $optionId : 'option_' . $index;
            unset($existing[$optionId]);
            $option['value'][$key] = [0 => $label];
            $option['order'][$key] = (int)($row['position'] ?? $index) + 1;
            if (!empty($row['is_default'])) {
                $default[] = $key;
            }
        }
        foreach (array_keys($existing) as $optionId) {
            $option['value'][$optionId] = [0 => (string)$optionId];
            $option['delete'][$optionId] = '1';
        }
        if ($attribute->getFrontendInput() === 'select') {
            $default = array_slice($default, 0, 1);
        }
        $attribute->setData('option', $option);
        $attribute->setData('default', $default);
        if (!$default) {
            $attribute->setData('default_value', null);
        }
    }

    /**
     * Grid flags; a column that is not in the grid cannot be visible, filterable or searchable.
     *
     * @param Attribute $attribute
     * @param array<string,mixed> $data
     * @return void
     */
    private function applyGridFlags(Attribute $attribute, array $data): void
    {
        $used = !empty($data['is_used_in_grid']);
        $attribute->setData('is_used_in_grid', (int)$used);
        $attribute->setData('is_visible_in_grid', (int)($used && !empty($data['is_visible_in_grid'])));
        $attribute->setData('is_filterable_in_grid', (int)($used && !empty($data['is_filterable_in_grid'])));
        $searchable = $used && !empty($data['is_searchable_in_grid'])
            && in_array($attribute->getBackendType(), ['varchar', 'text'], true)
            && !in_array($attribute->getFrontendInput(), InputTypes::OPTION_INPUTS, true);
        $attribute->setData('is_searchable_in_grid', (int)$searchable);
    }
}
