<?php

declare(strict_types=1);

namespace MageOS\Company\Ui\DataProvider\Address;

use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory as RegionCollectionFactory;
use Magento\Framework\Api\Filter;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Ui\DataProvider\AbstractDataProvider;
use MageOS\Company\Api\CompanyAddressRepositoryInterface;
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Api\Data\CompanyAddressInterface;
use MageOS\Company\Model\CompanyAddress;
use MageOS\Company\Setup\CompanySetup;
use MageOS\Company\Ui\DataProvider\Form\AttributeMetaProvider;

/**
 * Data provider of the company address modal form (company_address_form).
 */
class FormDataProvider extends AbstractDataProvider
{
    private const FIELDSET = 'general';

    /**
     * Company columns holding a default address, shown as toggles in the form.
     */
    public const DEFAULT_FLAGS = ['default_billing', 'default_shipping', 'legal_address'];

    /**
     * @var array<int|string,array<string,mixed>>|null
     */
    private ?array $loadedData = null;

    /**
     * Record ID passed by the form component as a primary key filter.
     *
     * @var int|null
     */
    private ?int $filteredId = null;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param RequestInterface $request
     * @param CompanyAddressRepositoryInterface $addressRepository
     * @param CompanyRepositoryInterface $companyRepository
     * @param AttributeMetaProvider $attributeMetaProvider
     * @param CountryCollectionFactory $countryCollectionFactory
     * @param RegionCollectionFactory $regionCollectionFactory
     * @param DirectoryHelper $directoryHelper
     * @param array<string,mixed> $meta
     * @param array<string,mixed> $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        private readonly RequestInterface $request,
        private readonly CompanyAddressRepositoryInterface $addressRepository,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly AttributeMetaProvider $attributeMetaProvider,
        private readonly CountryCollectionFactory $countryCollectionFactory,
        private readonly RegionCollectionFactory $regionCollectionFactory,
        private readonly DirectoryHelper $directoryHelper,
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
        $addressId = $this->filteredId ?? (int)$this->request->getParam($this->getRequestFieldName());
        $this->loadedData = ['' => ['country_id' => $this->directoryHelper->getDefaultCountry()]];
        if (!$addressId) {
            return $this->loadedData;
        }
        try {
            $address = $this->addressRepository->getById($addressId);
            $company = $this->companyRepository->getById((int)$address->getParentId());
        } catch (NoSuchEntityException) {
            return $this->loadedData;
        }
        /** @var CompanyAddress $address */
        $data = [];
        foreach ($address->getData() as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $data[$key] = $value;
            }
        }
        $data[CompanyAddressInterface::STREET] = $address->getStreet();
        $defaults = [
            'default_billing' => $company->getDefaultBilling(),
            'default_shipping' => $company->getDefaultShipping(),
            'legal_address' => $company->getLegalAddress(),
        ];
        foreach ($defaults as $flag => $defaultId) {
            $data[$flag] = $defaultId === $addressId ? '1' : '0';
        }
        $this->loadedData = [$addressId => $data];

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
        $fields = $this->attributeMetaProvider->getFieldsMeta(
            CompanyAddress::ENTITY,
            CompanySetup::FORM_ADMIN_ADDRESS
        );
        unset($fields[CompanyAddressInterface::REGION]);

        if (isset($fields[CompanyAddressInterface::STREET])) {
            $fields[CompanyAddressInterface::STREET] = $this->getStreetMeta(
                $fields[CompanyAddressInterface::STREET]['arguments']['data']['config']
            );
        }
        if (isset($fields[CompanyAddressInterface::COUNTRY_ID])) {
            $config = &$fields[CompanyAddressInterface::COUNTRY_ID]['arguments']['data']['config'];
            $config['options'] = $this->countryCollectionFactory->create()->toOptionArray();
            $config['default'] = $this->directoryHelper->getDefaultCountry();
            unset($config);
        }
        if (isset($fields[CompanyAddressInterface::POSTCODE])) {
            $fields[CompanyAddressInterface::POSTCODE]['arguments']['data']['config']['component']
                = 'Magento_Ui/js/form/element/post-code';
        }
        $fields[CompanyAddressInterface::REGION_ID] = ['arguments' => ['data' => ['config' => [
            'componentType' => 'field',
            'formElement' => 'select',
            'component' => 'Magento_Ui/js/form/element/region',
            'dataType' => 'text',
            'dataScope' => CompanyAddressInterface::REGION_ID,
            'label' => __('State/Province'),
            'sortOrder' => 65,
            'options' => $this->regionCollectionFactory->create()->toOptionArray(),
            'filterBy' => [
                'target' => '${ $.provider }:${ $.parentScope }.country_id',
                'field' => 'country_id',
            ],
            'customEntry' => CompanyAddressInterface::REGION,
            'validation' => ['required-entry' => true],
        ]]]];
        $sortOrder = 1;
        foreach (self::DEFAULT_FLAGS as $flag) {
            $fields[$flag] = ['arguments' => ['data' => ['config' => [
                'componentType' => 'field',
                'formElement' => 'checkbox',
                'dataType' => 'boolean',
                'dataScope' => $flag,
                'label' => $this->getFlagLabel($flag),
                'sortOrder' => $sortOrder++,
                'prefer' => 'toggle',
                'valueMap' => ['true' => '1', 'false' => '0'],
                'default' => '0',
            ]]]];
        }

        $meta[self::FIELDSET]['children'] = array_replace_recursive(
            $fields,
            $meta[self::FIELDSET]['children'] ?? []
        );

        return $meta;
    }

    /**
     * Street as a group of one input per line.
     *
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    private function getStreetMeta(array $config): array
    {
        $children = [];
        $lines = max(1, (int)($config['size'] ?? 1));
        for ($line = 0; $line < $lines; $line++) {
            $children[(string)$line] = ['arguments' => ['data' => ['config' => [
                'componentType' => 'field',
                'formElement' => 'input',
                'dataType' => 'text',
                'dataScope' => (string)$line,
                'label' => $line === 0 ? $config['label'] : '',
                'sortOrder' => $line,
                'validation' => $line === 0 ? $config['validation'] : [],
                'additionalClasses' => $line === 0 ? '' : 'additional',
            ]]]];
        }

        return [
            'arguments' => ['data' => ['config' => [
                'componentType' => 'container',
                'component' => 'Magento_Ui/js/form/components/group',
                'label' => $config['label'],
                'required' => $config['required'],
                'dataScope' => CompanyAddressInterface::STREET,
                'sortOrder' => $config['sortOrder'],
                'type' => 'group',
                'additionalClasses' => 'street',
            ]]],
            'children' => $children,
        ];
    }

    /**
     * Label of a default address flag.
     *
     * @param string $flag
     * @return \Magento\Framework\Phrase
     */
    private function getFlagLabel(string $flag): \Magento\Framework\Phrase
    {
        return match ($flag) {
            'default_billing' => __('Default Billing Address'),
            'default_shipping' => __('Default Shipping Address'),
            default => __('Legal Address'),
        };
    }
}
