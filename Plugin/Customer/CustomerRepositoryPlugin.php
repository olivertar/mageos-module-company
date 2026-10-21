<?php

declare(strict_types=1);

namespace MageOS\Company\Plugin\Customer;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerExtensionFactory;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerSearchResultsInterface;
use MageOS\Company\Api\Data\CompanyCustomerInterface;
use MageOS\Company\Model\ResourceModel\CompanyCustomer\CollectionFactory;

/**
 * Exposes the customer's company relation as the "company_attributes" extension attribute.
 *
 * Read only on purpose: the relation is changed through CompanyManagementInterface only, so a customer
 * cannot join a company by saving its own account (e.g. PUT /V1/customers/me).
 */
class CustomerRepositoryPlugin
{
    /**
     * @param CollectionFactory $relationCollectionFactory
     * @param CustomerExtensionFactory $extensionFactory
     */
    public function __construct(
        private readonly CollectionFactory $relationCollectionFactory,
        private readonly CustomerExtensionFactory $extensionFactory
    ) {
    }

    /**
     * Add company attributes to a customer loaded by ID.
     *
     * @param CustomerRepositoryInterface $subject
     * @param CustomerInterface $customer
     * @return CustomerInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetById(
        CustomerRepositoryInterface $subject,
        CustomerInterface $customer
    ): CustomerInterface {
        $this->addCompanyAttributes([$customer]);

        return $customer;
    }

    /**
     * Add company attributes to a customer loaded by email.
     *
     * @param CustomerRepositoryInterface $subject
     * @param CustomerInterface $customer
     * @return CustomerInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGet(
        CustomerRepositoryInterface $subject,
        CustomerInterface $customer
    ): CustomerInterface {
        $this->addCompanyAttributes([$customer]);

        return $customer;
    }

    /**
     * Add company attributes to a list of customers.
     *
     * @param CustomerRepositoryInterface $subject
     * @param CustomerSearchResultsInterface $searchResults
     * @return CustomerSearchResultsInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetList(
        CustomerRepositoryInterface $subject,
        CustomerSearchResultsInterface $searchResults
    ): CustomerSearchResultsInterface {
        $this->addCompanyAttributes($searchResults->getItems());

        return $searchResults;
    }

    /**
     * Load relations in one query and attach them to the customers.
     *
     * @param CustomerInterface[] $customers
     * @return void
     */
    private function addCompanyAttributes(array $customers): void
    {
        $customerIds = [];
        foreach ($customers as $customer) {
            if ($customer->getId()) {
                $customerIds[] = (int)$customer->getId();
            }
        }
        if (!$customerIds) {
            return;
        }

        $collection = $this->relationCollectionFactory->create();
        $collection->addFieldToFilter(CompanyCustomerInterface::CUSTOMER_ID, ['in' => $customerIds]);
        $relations = [];
        /** @var CompanyCustomerInterface $relation */
        foreach ($collection->getItems() as $relation) {
            $relations[$relation->getCustomerId()] = $relation;
        }

        foreach ($customers as $customer) {
            $relation = $relations[(int)$customer->getId()] ?? null;
            if ($relation === null) {
                continue;
            }
            $extensionAttributes = $customer->getExtensionAttributes() ?? $this->extensionFactory->create();
            $extensionAttributes->setCompanyAttributes($relation);
            $customer->setExtensionAttributes($extensionAttributes);
        }
    }
}
