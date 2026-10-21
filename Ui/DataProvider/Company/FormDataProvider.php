<?php

declare(strict_types=1);

namespace MageOS\Company\Ui\DataProvider\Company;

use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Api\Filter;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use MageOS\Company\Api\CompanyManagementInterface;
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Controller\Adminhtml\Company\Save;
use MageOS\Company\Model\Company;
use MageOS\Company\Setup\CompanySetup;
use MageOS\Company\Ui\DataProvider\Form\AttributeMetaProvider;

/**
 * Data provider of the admin company form (company_form).
 */
class FormDataProvider extends AbstractDataProvider
{
    private const FIELDSET = 'general';

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
     * @param CompanyRepositoryInterface $companyRepository
     * @param CompanyManagementInterface $companyManagement
     * @param CustomerCollectionFactory $customerCollectionFactory
     * @param AttributeMetaProvider $attributeMetaProvider
     * @param AuthorizationInterface $authorization
     * @param DataPersistorInterface $dataPersistor
     * @param StoreManagerInterface $storeManager
     * @param array<string,mixed> $meta
     * @param array<string,mixed> $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        private readonly RequestInterface $request,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly CompanyManagementInterface $companyManagement,
        private readonly CustomerCollectionFactory $customerCollectionFactory,
        private readonly AttributeMetaProvider $attributeMetaProvider,
        private readonly AuthorizationInterface $authorization,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly StoreManagerInterface $storeManager,
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
        $companyId = $this->getCompanyId();
        $data = [];
        if ($companyId) {
            try {
                $data = $this->toFormData($this->companyRepository->getById($companyId));
            } catch (NoSuchEntityException) {
                $companyId = 0;
            }
        }
        $persisted = $this->dataPersistor->get(Save::DATA_PERSISTOR_KEY);
        if (is_array($persisted)) {
            $data = array_merge($data, $persisted);
            $this->dataPersistor->clear(Save::DATA_PERSISTOR_KEY);
        }
        $this->loadedData = [($companyId ?: '') => ['company' => $data]];

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
        $companyId = $this->getCompanyId();
        $fields = $this->attributeMetaProvider->getFieldsMeta(Company::ENTITY, CompanySetup::FORM_ADMIN_COMPANY);

        $this->setFieldConfig($fields, CompanyInterface::COMPANY_ID, [
            'disabled' => !$this->authorization->isAllowed('MageOS_Company::company_erp'),
            'notice' => __('Code of the company in the ERP. Leave empty until the company is linked.'),
        ]);
        $this->setFieldConfig($fields, CompanyInterface::STATUS, [
            'disabled' => $companyId || !$this->authorization->isAllowed('MageOS_Company::company_approve'),
            'default' => (string)CompanyInterface::STATUS_PENDING,
            'notice' => $companyId ? __('Use the status buttons at the top of the page to change the status.') : '',
        ]);
        $this->setFieldConfig($fields, CompanyInterface::WEBSITE_ID, [
            'disabled' => (bool)$companyId,
            'default' => (string)$this->storeManager->getDefaultStoreView()?->getWebsiteId(),
            'notice' => $companyId ? __('The website cannot be changed after the company is created.') : '',
        ]);
        if ($companyId) {
            $fields[CompanyInterface::SUPER_USER_ID] = ['arguments' => ['data' => ['config' => [
                'componentType' => 'field',
                'formElement' => 'select',
                'dataType' => 'text',
                'dataScope' => CompanyInterface::SUPER_USER_ID,
                'label' => __('Super User'),
                'sortOrder' => 55,
                'options' => $this->getUserOptions($companyId),
                'caption' => __('-- No super user --'),
                'notice' => __('Main contact of the company. Always has the admin role.'),
            ]]]];
        }

        $meta[self::FIELDSET]['children'] = array_replace_recursive(
            $fields,
            $meta[self::FIELDSET]['children'] ?? []
        );
        foreach (['addresses', 'users', 'messages', 'history'] as $fieldset) {
            $meta[$fieldset]['arguments']['data']['config']['visible'] = (bool)$companyId;
        }
        if (!$this->authorization->isAllowed('MageOS_Company::messages')) {
            $meta['messages']['arguments']['data']['config']['visible'] = false;
        }

        return $meta;
    }

    /**
     * Requested company ID.
     *
     * @return int
     */
    private function getCompanyId(): int
    {
        return $this->filteredId ?? (int)$this->request->getParam($this->getRequestFieldName());
    }

    /**
     * Scalar company values for the form.
     *
     * @param CompanyInterface $company
     * @return array<string,mixed>
     */
    private function toFormData(CompanyInterface $company): array
    {
        /** @var Company $company */
        $data = [];
        foreach ($company->getData() as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Merge configuration into a field, if the field is in the form.
     *
     * @param array<string,mixed> $fields
     * @param string $code
     * @param array<string,mixed> $config
     * @return void
     */
    private function setFieldConfig(array &$fields, string $code, array $config): void
    {
        if (isset($fields[$code])) {
            $fields[$code]['arguments']['data']['config'] = array_merge(
                $fields[$code]['arguments']['data']['config'],
                $config
            );
        }
    }

    /**
     * Company users as select options.
     *
     * @param int $companyId
     * @return array<int,array{value: string, label: string}>
     */
    private function getUserOptions(int $companyId): array
    {
        $customerIds = [];
        foreach ($this->companyManagement->getCustomers($companyId) as $relation) {
            $customerIds[] = $relation->getCustomerId();
        }
        if (!$customerIds) {
            return [];
        }
        $customers = $this->customerCollectionFactory->create()
            ->addAttributeToSelect(['firstname', 'lastname'])
            ->addFieldToFilter('entity_id', ['in' => $customerIds]);
        $options = [];
        foreach ($customers as $customer) {
            $options[] = [
                'value' => (string)$customer->getId(),
                'label' => sprintf('%s (%s)', $customer->getName(), $customer->getEmail()),
            ];
        }

        return $options;
    }
}
