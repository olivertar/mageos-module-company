<?php

declare(strict_types=1);

namespace MageOS\Company\Api;

use Magento\Customer\Api\Data\CustomerInterface;
use MageOS\Company\Api\Data\CompanyAddressInterface;
use MageOS\Company\Api\Data\CompanyCustomerInterface;
use MageOS\Company\Api\Data\CompanyInterface;

/**
 * Manage the customers (users) of a company.
 *
 * @api
 */
interface CompanyManagementInterface
{
    /**
     * Register a company application.
     *
     * A customer with an ID is an existing customer that must not belong to a company; otherwise the customer is
     * created with the given password. The customer becomes the company super user. The company starts pending,
     * or approved when auto-approval is enabled; status, ERP code and website are not taken from the input.
     *
     * @param \MageOS\Company\Api\Data\CompanyInterface $company
     * @param \Magento\Customer\Api\Data\CustomerInterface $customer
     * @param string|null $password
     * @param \MageOS\Company\Api\Data\CompanyAddressInterface|null $legalAddress
     * @return \MageOS\Company\Api\Data\CompanyInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function register(
        CompanyInterface $company,
        CustomerInterface $customer,
        ?string $password = null,
        ?CompanyAddressInterface $legalAddress = null
    ): CompanyInterface;

    /**
     * Create a company (admin/integration).
     *
     * It starts pending: use CompanyApprovalInterface to approve it, and setErpCode() for the ERP code.
     *
     * @param \MageOS\Company\Api\Data\CompanyInterface $company
     * @return \MageOS\Company\Api\Data\CompanyInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function create(CompanyInterface $company): CompanyInterface;

    /**
     * Update a company (admin/integration) with the values sent.
     *
     * Omitted or null fields keep their value, an empty string clears it. Status, ERP code and website cannot be
     * changed here.
     *
     * @param int $companyId
     * @param \MageOS\Company\Api\Data\CompanyInterface $company
     * @return \MageOS\Company\Api\Data\CompanyInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function update(int $companyId, CompanyInterface $company): CompanyInterface;

    /**
     * Set the ERP code of a company; null or an empty string unlinks it.
     *
     * @param int $companyId
     * @param string|null $erpCode
     * @return \MageOS\Company\Api\Data\CompanyInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function setErpCode(int $companyId, ?string $erpCode): CompanyInterface;

    /**
     * Get the company a customer belongs to.
     *
     * @param int $customerId
     * @return \MageOS\Company\Api\Data\CompanyInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getByCustomerId(int $customerId): CompanyInterface;

    /**
     * Assign a customer to a company, or update its role/job title if already assigned to it.
     *
     * @param int $companyId
     * @param int $customerId
     * @param string $role
     * @param string|null $jobTitle
     * @return \MageOS\Company\Api\Data\CompanyCustomerInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function assignCustomer(
        int $companyId,
        int $customerId,
        string $role = CompanyCustomerInterface::ROLE_USER,
        ?string $jobTitle = null
    ): CompanyCustomerInterface;

    /**
     * Remove a customer from a company.
     *
     * @param int $companyId
     * @param int $customerId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function unassignCustomer(int $companyId, int $customerId): bool;

    /**
     * Activate or deactivate a company user. Inactive users cannot sign in nor act for the company.
     *
     * The super user and the last active admin cannot be deactivated.
     *
     * @param int $companyId
     * @param int $customerId
     * @param int $status See CompanyCustomerInterface::STATUS_* constants.
     * @return \MageOS\Company\Api\Data\CompanyCustomerInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function setCustomerStatus(int $companyId, int $customerId, int $status): CompanyCustomerInterface;

    /**
     * Get the customers of a company.
     *
     * @param int $companyId
     * @return \MageOS\Company\Api\Data\CompanyCustomerInterface[]
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCustomers(int $companyId): array;

    /**
     * Make a customer the super user of the company (assigning it as admin when needed).
     *
     * @param int $companyId
     * @param int $customerId
     * @return \MageOS\Company\Api\Data\CompanyInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function setSuperUser(int $companyId, int $customerId): CompanyInterface;
}
