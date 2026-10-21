<?php

declare(strict_types=1);

namespace MageOS\Company\Model\CompanyCustomer;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Config\Share as ShareConfig;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Model\ResourceModel\CompanyCustomer as CompanyCustomerResource;

/**
 * Checks that a customer can be attached to a company.
 */
class AssignmentValidator
{
    /**
     * @param CustomerRepositoryInterface $customerRepository
     * @param ShareConfig $shareConfig
     * @param CompanyCustomerResource $relationResource
     */
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly ShareConfig $shareConfig,
        private readonly CompanyCustomerResource $relationResource
    ) {
    }

    /**
     * Validate the assignment of a customer to a company.
     *
     * @param CompanyInterface $company
     * @param int $customerId
     * @return void
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function validate(CompanyInterface $company, int $customerId): void
    {
        $customer = $this->customerRepository->getById($customerId);

        if (!$this->shareConfig->isGlobalScope()
            && (int)$customer->getWebsiteId() !== (int)$company->getWebsiteId()
        ) {
            throw new LocalizedException(
                __('The customer "%1" belongs to a different website than the company.', $customerId)
            );
        }

        $currentCompanyId = $this->relationResource->getCompanyIdByCustomerId($customerId);
        if ($currentCompanyId !== null && $currentCompanyId !== $company->getId()) {
            throw new LocalizedException(
                __('The customer "%1" already belongs to another company.', $customerId)
            );
        }
    }
}
