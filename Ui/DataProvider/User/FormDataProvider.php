<?php

declare(strict_types=1);

namespace MageOS\Company\Ui\DataProvider\User;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Ui\DataProvider\AbstractDataProvider;
use MageOS\Company\Api\Data\CompanyCustomerInterface;
use MageOS\Company\Model\CompanyCustomer;
use MageOS\Company\Model\CompanyCustomerFactory;
use MageOS\Company\Model\ResourceModel\CompanyCustomer as CompanyCustomerResource;
use MageOS\Company\Model\ResourceModel\CompanyCustomer\CollectionFactory;

/**
 * Data provider of the company user modal form (company_user_form).
 */
class FormDataProvider extends AbstractDataProvider
{
    /**
     * @var array<int|string,array<string,mixed>>|null
     */
    private ?array $loadedData = null;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param RequestInterface $request
     * @param CompanyCustomerFactory $relationFactory
     * @param CompanyCustomerResource $relationResource
     * @param CustomerRepositoryInterface $customerRepository
     * @param array<string,mixed> $meta
     * @param array<string,mixed> $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly RequestInterface $request,
        private readonly CompanyCustomerFactory $relationFactory,
        private readonly CompanyCustomerResource $relationResource,
        private readonly CustomerRepositoryInterface $customerRepository,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
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
        $this->loadedData = ['' => [
            'role' => CompanyCustomerInterface::ROLE_USER,
            'status' => (string)CompanyCustomerInterface::STATUS_ACTIVE,
        ]];
        $customerId = (int)$this->request->getParam($this->getRequestFieldName());
        $companyId = (int)$this->request->getParam('company_id');
        if (!$customerId) {
            return $this->loadedData;
        }
        /** @var CompanyCustomer $relation */
        $relation = $this->relationFactory->create();
        $this->relationResource->load($relation, $customerId);
        if (!$relation->getCustomerId() || $relation->getCompanyEntityId() !== $companyId) {
            return $this->loadedData;
        }
        try {
            $customer = $this->customerRepository->getById($customerId);
        } catch (NoSuchEntityException) {
            return $this->loadedData;
        }
        $this->loadedData = [$customerId => [
            'customer_id' => (string)$customerId,
            'company_id' => (string)$companyId,
            'email' => $customer->getEmail(),
            'role' => $relation->getRole(),
            'job_title' => $relation->getJobTitle(),
            'status' => (string)$relation->getStatus(),
        ]];

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
        if ((int)$this->request->getParam($this->getRequestFieldName())) {
            $meta['general']['children']['email']['arguments']['data']['config']['disabled'] = true;
            $meta['general']['children']['email']['arguments']['data']['config']['notice'] = '';
        }

        return $meta;
    }
}
