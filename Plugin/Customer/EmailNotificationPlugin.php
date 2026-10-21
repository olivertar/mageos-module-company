<?php

declare(strict_types=1);

namespace MageOS\Company\Plugin\Customer;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\EmailNotificationInterface;
use MageOS\Company\Model\Registration\WelcomeEmailDeferral;

/**
 * Captures the account email of a customer created by a company application (see WelcomeEmailDeferral).
 */
class EmailNotificationPlugin
{
    /**
     * @param WelcomeEmailDeferral $welcomeEmailDeferral
     */
    public function __construct(
        private readonly WelcomeEmailDeferral $welcomeEmailDeferral
    ) {
    }

    /**
     * Record the email instead of sending it while an application is being registered.
     *
     * @param EmailNotificationInterface $subject
     * @param callable $proceed
     * @param CustomerInterface $customer
     * @param string $type
     * @param string $backUrl
     * @param int|string|null $storeId
     * @param int|string|null $sendemailStoreId
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundNewAccount(
        EmailNotificationInterface $subject,
        callable $proceed,
        CustomerInterface $customer,
        $type = EmailNotificationInterface::NEW_ACCOUNT_EMAIL_REGISTERED,
        $backUrl = '',
        $storeId = 0,
        $sendemailStoreId = null
    ): void {
        if ($this->welcomeEmailDeferral->isCapturing()) {
            $this->welcomeEmailDeferral->setCapturedType((string)$type);
            return;
        }
        $proceed($customer, $type, $backUrl, $storeId, $sendemailStoreId);
    }
}
