<?php

declare(strict_types=1);

namespace MageOS\Company\ViewModel;

use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use Magento\Eav\Model\Attribute as EavAttribute;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use MageOS\Company\Model\Company;
use MageOS\Company\Model\FormFactory;

/**
 * Field definitions of a storefront company or address form, built from the attributes of its form code
 * (rendered by register/field.phtml), plus the country/region widget data.
 */
class AttributeFields implements ArgumentInterface
{
    /**
     * @param FormFactory $formFactory
     * @param CountryCollectionFactory $countryCollectionFactory
     * @param DirectoryHelper $directoryHelper
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly FormFactory $formFactory,
        private readonly CountryCollectionFactory $countryCollectionFactory,
        private readonly DirectoryHelper $directoryHelper,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Field definitions of a form.
     *
     * @param string $entityType
     * @param string $formCode
     * @param string $scope Request scope of the field names (e.g. "company" → company[name]).
     * @param array<string,mixed> $values Current values by attribute code.
     * @param string[] $skip Attribute codes rendered elsewhere.
     * @return array<int,array<string,mixed>>
     */
    public function getFields(
        string $entityType,
        string $formCode,
        string $scope,
        array $values = [],
        array $skip = []
    ): array {
        $form = $this->formFactory->create();
        if ($entityType !== Company::ENTITY) {
            $form->setEntityType($entityType);
        }
        $form->setFormCode($formCode);

        $fields = [];
        /** @var EavAttribute $attribute */
        foreach ($form->getAttributes() as $code => $attribute) {
            if (in_array($code, $skip, true) || $attribute->getFrontendInput() === 'hidden') {
                continue;
            }
            $value = $values[$code] ?? null;
            if ($attribute->getFrontendInput() === 'multiline' && is_string($value)) {
                $value = explode("\n", $value);
            }
            $fields[] = [
                'code' => $code,
                'name' => sprintf('%s[%s]', $scope, $code),
                'id' => sprintf('%s_%s', $scope, $code),
                'label' => (string)__($attribute->getStoreLabel() ?: $attribute->getFrontendLabel()),
                'input' => (string)$attribute->getFrontendInput(),
                'required' => (bool)$attribute->getIsRequired(),
                'class' => trim((string)$attribute->getFrontend()->getClass()),
                'options' => $this->getOptions($attribute),
                'lines' => max(1, (int)$attribute->getMultilineCount()),
                'value' => $value,
            ];
        }

        return $fields;
    }

    /**
     * Country options of the current store.
     *
     * @return array<int,array{value: string, label: string}>
     */
    public function getCountryOptions(): array
    {
        $options = [];
        foreach ($this->countryCollectionFactory->create()->loadByStore()->toOptionArray(false) as $option) {
            $options[] = ['value' => (string)$option['value'], 'label' => (string)$option['label']];
        }

        return $options;
    }

    /**
     * Default country of the store.
     *
     * @return string
     */
    public function getDefaultCountry(): string
    {
        return (string)$this->directoryHelper->getDefaultCountry();
    }

    /**
     * Configuration of the regionUpdater widget for fields named {scope}_country_id, {scope}_region_id, ...
     *
     * @param string $scope
     * @param string $formSelector
     * @param string|int|null $regionId
     * @return array<string,mixed>
     */
    public function getRegionUpdaterConfig(string $scope, string $formSelector, $regionId): array
    {
        return [
            'optionalRegionAllowed' => $this->scopeConfig->isSetFlag(
                'general/region/display_all',
                ScopeInterface::SCOPE_STORE
            ),
            'regionListId' => '#' . $scope . '_region_id',
            'regionInputId' => '#' . $scope . '_region',
            'postcodeId' => '#' . $scope . '_postcode',
            'form' => $formSelector,
            'regionJson' => json_decode((string)$this->directoryHelper->getRegionJson(), true),
            'defaultRegion' => (string)$regionId,
            'countriesWithOptionalZip' => $this->directoryHelper->getCountriesWithOptionalZip(),
        ];
    }

    /**
     * Options of a select attribute, without the empty option.
     *
     * @param EavAttribute $attribute
     * @return array<int,array{value: string, label: string}>
     */
    private function getOptions(EavAttribute $attribute): array
    {
        if (!in_array($attribute->getFrontendInput(), ['select', 'multiselect'], true)
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
