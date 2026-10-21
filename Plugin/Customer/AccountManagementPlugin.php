<?php

declare(strict_types=1);

namespace MageOS\Company\Plugin\Customer;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use MageOS\Company\Api\CompanyManagementInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Model\Authorization\CompanyContext;

/**
 * Users of a company that is not approved cannot log in.
 *
 * Runs after the password check, so the status message does not reveal anything to someone without the
 * credentials. The storefront shows the message; the token services (REST, GraphQL) replace it with their
 * generic authentication error. "Login as Customer" does not authenticate, so admins can still use it.
 */
class AccountManagementPlugin
{
    /**
     * @param CompanyManagementInterface $companyManagement
     * @param CompanyContext $companyContext
     */
    public function __construct(
        private readonly CompanyManagementInterface $companyManagement,
        private readonly CompanyContext $companyContext
    ) {
    }

    /**
     * Reject the login of users of pending, rejected or blocked companies, and of inactive company users.
     *
     * @param AccountManagementInterface $subject
     * @param CustomerInterface $customer
     * @return CustomerInterface
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterAuthenticate(
        AccountManagementInterface $subject,
        CustomerInterface $customer
    ): CustomerInterface {
        try {
            $company = $this->companyManagement->getByCustomerId((int)$customer->getId());
        } catch (NoSuchEntityException) {
            return $customer;
        }

        $message = match ($company->getStatus()) {
            CompanyInterface::STATUS_APPROVED => null,
            CompanyInterface::STATUS_PENDING => __(
                'Your company account is pending approval. We will email you when it has been reviewed.'
            ),
            CompanyInterface::STATUS_REJECTED => __(
                'Your company application was not approved. Please contact us for more information.'
            ),
            default => __('Your company account is blocked. Please contact us for more information.'),
        };
        if ($message === null && $this->companyContext->isInactiveUser((int)$customer->getId())) {
            $message = __('Your company user account is inactive. Please contact your company administrator.');
        }
        if ($message !== null) {
            throw new LocalizedException($message);
        }

        return $customer;
    }
}
