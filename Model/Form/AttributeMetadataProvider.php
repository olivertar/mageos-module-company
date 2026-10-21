<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Form;

use Magento\Eav\Model\Attribute as EavAttribute;
use Magento\Framework\Exception\LocalizedException;
use MageOS\Company\Model\Company;
use MageOS\Company\Model\CompanyAddress;
use MageOS\Company\Model\FormFactory;
use MageOS\Company\Setup\CompanySetup;

/**
 * Attributes of the storefront forms, for headless frontends to build the company forms.
 */
class AttributeMetadataProvider
{
    /**
     * Storefront form codes and their entity type.
     */
    public const STOREFRONT_FORMS = [
        CompanySetup::FORM_COMPANY_REGISTER => Company::ENTITY,
        CompanySetup::FORM_COMPANY_ACCOUNT_EDIT => Company::ENTITY,
        CompanySetup::FORM_ADDRESS_REGISTER => CompanyAddress::ENTITY,
        CompanySetup::FORM_ADDRESS_EDIT => CompanyAddress::ENTITY,
    ];

    /**
     * @param FormFactory $formFactory
     */
    public function __construct(
        private readonly FormFactory $formFactory
    ) {
    }

    /**
     * Attributes of a storefront form, sorted.
     *
     * @param string $formCode
     * @return array<int,array<string,mixed>>
     * @throws LocalizedException When the form code is not a storefront form.
     */
    public function getAttributes(string $formCode): array
    {
        $entityType = self::STOREFRONT_FORMS[$formCode] ?? null;
        if ($entityType === null) {
            throw new LocalizedException(__('Form "%1" is not available.', $formCode));
        }
        $form = $this->formFactory->create();
        if ($entityType !== Company::ENTITY) {
            $form->setEntityType($entityType);
        }
        $form->setFormCode($formCode);

        $attributes = [];
        /** @var EavAttribute $attribute */
        foreach ($form->getAttributes() as $code => $attribute) {
            $attributes[] = [
                'attribute_code' => (string)$code,
                'entity_type' => $entityType,
                'label' => (string)__($attribute->getStoreLabel() ?: $attribute->getFrontendLabel()),
                'frontend_input' => (string)$attribute->getFrontendInput(),
                'is_required' => (bool)$attribute->getIsRequired(),
                'sort_order' => (int)$attribute->getSortOrder(),
                'multiline_count' => (int)$attribute->getMultilineCount(),
                'validate_rules' => $this->getValidateRules($attribute),
                'options' => $this->getOptions($attribute),
            ];
        }

        return $attributes;
    }

    /**
     * Validation rules as name/value pairs.
     *
     * @param EavAttribute $attribute
     * @return array<int,array{name: string, value: string}>
     */
    private function getValidateRules(EavAttribute $attribute): array
    {
        $rules = [];
        foreach ((array)$attribute->getValidateRules() as $name => $value) {
            $rules[] = ['name' => (string)$name, 'value' => (string)$value];
        }

        return $rules;
    }

    /**
     * Options of a select attribute, without the empty option.
     *
     * @param EavAttribute $attribute
     * @return array<int,array{value: string, label: string}>
     */
    private function getOptions(EavAttribute $attribute): array
    {
        if (!in_array($attribute->getFrontendInput(), ['select', 'multiselect', 'boolean'], true)
            || !$attribute->usesSource()
        ) {
            return [];
        }
        $options = [];
        foreach ($attribute->getSource()->getAllOptions() as $option) {
            if (!is_array($option) || ($option['value'] ?? '') === '' || is_array($option['value'])) {
                continue;
            }
            $options[] = ['value' => (string)$option['value'], 'label' => (string)$option['label']];
        }

        return $options;
    }
}
