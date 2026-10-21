<?php

declare(strict_types=1);

namespace MageOS\Company\Api;

use MageOS\Company\Api\Data\CompanyAddressInterface;
use MageOS\Company\Api\Data\CompanyCustomerInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Api\Data\MessageInterface;

/**
 * Operations of a company user on its own company ("mine" web API routes).
 *
 * The customer ID is forced from the token by the web API; the company always comes from the customer. Every
 * operation is checked with CompanyAuthorizationInterface and only the attributes of the storefront form codes
 * are taken from payloads.
 *
 * @api
 */
interface MyCompanyManagementInterface
{
    /**
     * Get the company of the customer.
     *
     * @param int $customerId
     * @return \MageOS\Company\Api\Data\CompanyInterface
     * @throws \Magento\Framework\Exception\AuthorizationException
     */
    public function get(int $customerId): CompanyInterface;

    /**
     * Update the company profile (company_account_edit attributes; omitted or null keep their value, '' clears).
     *
     * @param int $customerId
     * @param \MageOS\Company\Api\Data\CompanyInterface $company
     * @return \MageOS\Company\Api\Data\CompanyInterface
     * @throws \Magento\Framework\Exception\AuthorizationException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function update(int $customerId, CompanyInterface $company): CompanyInterface;

    /**
     * Addresses of the company.
     *
     * @param int $customerId
     * @return \MageOS\Company\Api\Data\CompanyAddressInterface[]
     * @throws \Magento\Framework\Exception\AuthorizationException
     */
    public function getAddresses(int $customerId): array;

    /**
     * Address of the company.
     *
     * @param int $customerId
     * @param int $addressId
     * @return \MageOS\Company\Api\Data\CompanyAddressInterface
     * @throws \Magento\Framework\Exception\AuthorizationException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getAddress(int $customerId, int $addressId): CompanyAddressInterface;

    /**
     * Create (no ID) or update an address of the company (company_address_edit attributes).
     *
     * @param int $customerId
     * @param \MageOS\Company\Api\Data\CompanyAddressInterface $address
     * @return \MageOS\Company\Api\Data\CompanyAddressInterface
     * @throws \Magento\Framework\Exception\AuthorizationException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function saveAddress(int $customerId, CompanyAddressInterface $address): CompanyAddressInterface;

    /**
     * Delete an address of the company.
     *
     * @param int $customerId
     * @param int $addressId
     * @return bool
     * @throws \Magento\Framework\Exception\AuthorizationException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteAddress(int $customerId, int $addressId): bool;

    /**
     * Make an address the default billing, shipping or legal address of the company.
     *
     * @param int $customerId
     * @param int $addressId
     * @param string $type "default_billing", "default_shipping" or "legal_address".
     * @return \MageOS\Company\Api\Data\CompanyInterface
     * @throws \Magento\Framework\Exception\AuthorizationException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function setDefaultAddress(int $customerId, int $addressId, string $type): CompanyInterface;

    /**
     * Users of the company.
     *
     * @param int $customerId
     * @return \MageOS\Company\Api\Data\CompanyCustomerInterface[]
     * @throws \Magento\Framework\Exception\AuthorizationException
     */
    public function getUsers(int $customerId): array;

    /**
     * Invite someone to join the company. The person joins when accepting the emailed link.
     *
     * @param int $customerId
     * @param string $email
     * @param string $role
     * @param string|null $jobTitle
     * @return bool
     * @throws \Magento\Framework\Exception\AuthorizationException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function inviteUser(int $customerId, string $email, string $role = 'user', ?string $jobTitle = null): bool;

    /**
     * Change the role, job title and status of a company user.
     *
     * @param int $customerId
     * @param int $userId Customer ID of the company user.
     * @param string $role
     * @param string|null $jobTitle Null keeps the current job title.
     * @param int|null $status Null keeps the current status.
     * @return \MageOS\Company\Api\Data\CompanyCustomerInterface
     * @throws \Magento\Framework\Exception\AuthorizationException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function updateUser(
        int $customerId,
        int $userId,
        string $role,
        ?string $jobTitle = null,
        ?int $status = null
    ): CompanyCustomerInterface;

    /**
     * Remove a user from the company (the customer account is kept).
     *
     * @param int $customerId
     * @param int $userId
     * @return bool
     * @throws \Magento\Framework\Exception\AuthorizationException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function removeUser(int $customerId, int $userId): bool;

    /**
     * Messages of the company thread, oldest first (internal notes are never returned).
     *
     * @param int $customerId
     * @return \MageOS\Company\Api\Data\MessageInterface[]
     * @throws \Magento\Framework\Exception\AuthorizationException
     */
    public function getMessages(int $customerId): array;

    /**
     * Send a message to the store.
     *
     * @param int $customerId
     * @param string $body
     * @return \MageOS\Company\Api\Data\MessageInterface
     * @throws \Magento\Framework\Exception\AuthorizationException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function sendMessage(int $customerId, string $body): MessageInterface;

    /**
     * Mark the store messages as read.
     *
     * @param int $customerId
     * @return int Number of messages marked.
     * @throws \Magento\Framework\Exception\AuthorizationException
     */
    public function markMessagesRead(int $customerId): int;
}
