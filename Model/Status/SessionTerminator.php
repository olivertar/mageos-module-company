<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Status;

use Magento\Customer\Api\SessionCleanerInterface;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use MageOS\Company\Api\Data\CompanyCustomerInterface;
use MageOS\Company\Model\ResourceModel\CompanyCustomer\CollectionFactory as RelationCollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Logs out company users: storefront sessions and web API tokens.
 */
class SessionTerminator
{
    /**
     * @param RelationCollectionFactory $relationCollectionFactory
     * @param CustomerTokenServiceInterface $customerTokenService
     * @param SessionCleanerInterface $sessionCleaner
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly RelationCollectionFactory $relationCollectionFactory,
        private readonly CustomerTokenServiceInterface $customerTokenService,
        private readonly SessionCleanerInterface $sessionCleaner,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Terminate the sessions and tokens of all company users.
     *
     * @param int $companyId
     * @return void
     */
    public function terminate(int $companyId): void
    {
        $relations = $this->relationCollectionFactory->create()
            ->addFieldToFilter(CompanyCustomerInterface::COMPANY_ENTITY_ID, ['eq' => $companyId]);
        foreach ($relations as $relation) {
            $this->terminateCustomer((int)$relation->getData(CompanyCustomerInterface::CUSTOMER_ID));
        }
    }

    /**
     * Terminate the sessions and tokens of one customer.
     *
     * @param int $customerId
     * @return void
     */
    public function terminateCustomer(int $customerId): void
    {
        try {
            $this->customerTokenService->revokeCustomerAccessToken($customerId);
            $this->sessionCleaner->clearFor($customerId);
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf('Could not log out company user %d: %s', $customerId, $e->getMessage()),
                ['exception' => $e]
            );
        }
    }
}
