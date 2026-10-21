<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Authorization;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Api\Data\CompanyCustomerInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Model\CompanyCustomer;
use MageOS\Company\Model\CompanyCustomerFactory;
use MageOS\Company\Model\ResourceModel\CompanyCustomer as CompanyCustomerResource;

/**
 * Company of a customer acting on its own company (storefront, "mine" APIs).
 *
 * The company always comes from the customer, never from the input: it must be approved and the customer must be
 * an active user of it.
 */
class CompanyContext
{
    /**
     * @param CompanyCustomerFactory $relationFactory
     * @param CompanyCustomerResource $relationResource
     * @param CompanyRepositoryInterface $companyRepository
     */
    public function __construct(
        private readonly CompanyCustomerFactory $relationFactory,
        private readonly CompanyCustomerResource $relationResource,
        private readonly CompanyRepositoryInterface $companyRepository
    ) {
    }

    /**
     * Active company of a customer.
     *
     * @param int $customerId
     * @return CompanyInterface
     * @throws LocalizedException When the customer has no usable company.
     */
    public function getCompany(int $customerId): CompanyInterface
    {
        $relation = $this->getRelation($customerId);
        if ($relation === null || $relation->getStatus() !== CompanyCustomerInterface::STATUS_ACTIVE) {
            throw new LocalizedException(__('Your account does not belong to an active company.'));
        }
        try {
            $company = $this->companyRepository->getById((int)$relation->getCompanyEntityId());
        } catch (NoSuchEntityException) {
            throw new LocalizedException(__('Your account does not belong to an active company.'));
        }
        if ($company->getStatus() !== CompanyInterface::STATUS_APPROVED) {
            throw new LocalizedException(__('Your company account is not approved.'));
        }

        return $company;
    }

    /**
     * Whether a customer has a usable company.
     *
     * @param int $customerId
     * @return bool
     */
    public function hasCompany(int $customerId): bool
    {
        try {
            $this->getCompany($customerId);

            return true;
        } catch (LocalizedException) {
            return false;
        }
    }

    /**
     * Whether the customer is an inactive user of a company.
     *
     * @param int $customerId
     * @return bool
     */
    public function isInactiveUser(int $customerId): bool
    {
        $relation = $this->getRelation($customerId);

        return $relation !== null && $relation->getStatus() === CompanyCustomerInterface::STATUS_INACTIVE;
    }

    /**
     * Company role of a customer, or null when it has no company.
     *
     * @param int $customerId
     * @return string|null
     */
    public function getRole(int $customerId): ?string
    {
        return $this->getRelation($customerId)?->getRole();
    }

    /**
     * Company relation of a customer.
     *
     * @param int $customerId
     * @return CompanyCustomer|null
     */
    private function getRelation(int $customerId): ?CompanyCustomer
    {
        if (!$customerId) {
            return null;
        }
        $relation = $this->relationFactory->create();
        $this->relationResource->load($relation, $customerId);

        return $relation->getCustomerId() ? $relation : null;
    }
}
