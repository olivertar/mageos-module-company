<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Registration;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\EmailNotificationInterface;
use MageOS\Company\Model\ResourceModel\CompanyCustomer as CompanyCustomerResource;
use Psr\Log\LoggerInterface;

/**
 * Controls the Magento account email of customers created by a company application, so the applicant gets a
 * single email from the company workflow:
 * - the welcome email ("you can now sign in") is dropped: the company "approved" email replaces it;
 * - the account confirmation email (when confirmation is required) carries the confirmation link, so it is held
 *   back and sent once the company is approved.
 *
 * While capturing, EmailNotificationPlugin records the email type instead of sending it.
 */
class WelcomeEmailDeferral
{
    /**
     * @var bool
     */
    private bool $capturing = false;

    /**
     * @var string|null
     */
    private ?string $capturedType = null;

    /**
     * @param CompanyCustomerResource $relationResource
     * @param CustomerRepositoryInterface $customerRepository
     * @param EmailNotificationInterface $emailNotification
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CompanyCustomerResource $relationResource,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly EmailNotificationInterface $emailNotification,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Run a callback that creates a customer, capturing its welcome email.
     *
     * @param callable $callback
     * @return mixed The callback result.
     */
    public function capture(callable $callback): mixed
    {
        $this->capturing = true;
        $this->capturedType = null;
        try {
            return $callback();
        } finally {
            $this->capturing = false;
        }
    }

    /**
     * Whether welcome emails are being captured.
     *
     * @return bool
     */
    public function isCapturing(): bool
    {
        return $this->capturing;
    }

    /**
     * Record the type of a captured welcome email.
     *
     * @param string $type
     * @return void
     */
    public function setCapturedType(string $type): void
    {
        $this->capturedType = $type;
    }

    /**
     * Type of the last captured welcome email, if any.
     *
     * @return string|null
     */
    public function getCapturedType(): ?string
    {
        return $this->capturedType;
    }

    /**
     * Keep the captured email of a company user to send it on approval, if it is a confirmation email.
     *
     * @param int $customerId
     * @return void
     */
    public function hold(int $customerId): void
    {
        if ($this->capturedType === EmailNotificationInterface::NEW_ACCOUNT_EMAIL_CONFIRMATION) {
            $this->relationResource->setPendingWelcomeEmail($customerId, $this->capturedType);
        }
    }

    /**
     * Send the account emails held back for the users of a company.
     *
     * @param int $companyId
     * @return void
     */
    public function release(int $companyId): void
    {
        foreach ($this->relationResource->getPendingWelcomeEmails($companyId) as $customerId => $type) {
            try {
                $customer = $this->customerRepository->getById((int)$customerId);
                $this->emailNotification->newAccount($customer, $type, '', $customer->getStoreId());
            } catch (\Throwable $e) {
                $this->logger->error(
                    sprintf('Welcome email of customer %d could not be sent: %s', $customerId, $e->getMessage()),
                    ['exception' => $e]
                );
            }
            $this->relationResource->setPendingWelcomeEmail((int)$customerId, null);
        }
    }
}
