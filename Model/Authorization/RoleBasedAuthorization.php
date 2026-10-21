<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Authorization;

use Magento\Framework\Exception\LocalizedException;
use MageOS\Company\Api\CompanyAuthorizationInterface;
use MageOS\Company\Api\Data\CompanyCustomerInterface;

/**
 * Default authorization: company admins can do everything, users can view the company and its addresses, and
 * read and send messages.
 */
class RoleBasedAuthorization implements CompanyAuthorizationInterface
{
    /**
     * Resources of the "user" role.
     */
    private const USER_RESOURCES = [
        self::RESOURCE_VIEW,
        self::RESOURCE_ADDRESS_VIEW,
        self::RESOURCE_MESSAGES_VIEW,
        self::RESOURCE_MESSAGES_SEND,
    ];

    /**
     * @param CompanyContext $companyContext
     */
    public function __construct(
        private readonly CompanyContext $companyContext
    ) {
    }

    /**
     * @inheritDoc
     */
    public function isAllowed(int $customerId, string $resource): bool
    {
        try {
            $this->companyContext->getCompany($customerId);
        } catch (LocalizedException) {
            return false;
        }
        if ($this->companyContext->getRole($customerId) === CompanyCustomerInterface::ROLE_ADMIN) {
            return true;
        }

        return in_array($resource, self::USER_RESOURCES, true);
    }
}
