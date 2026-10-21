<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Message;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use MageOS\Company\Api\CompanyManagementInterface;
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Api\Data\CompanyCustomerInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Api\Data\MessageInterface;
use MageOS\Company\Api\MessageRepositoryInterface;
use MageOS\Company\Model\Notification\AdminInbox;
use MageOS\Company\Model\Notification\EmailSender;
use Psr\Log\LoggerInterface;

/**
 * Consumer of company.message.notify: tells the other party about a new message.
 *
 * - Store → company: email to every active company user. While the company is pending (D12) the email carries
 *   the full message, as the users cannot sign in to read the thread.
 * - Company → store: email to the configured admin recipients and an admin panel notification.
 */
class NotifyConsumer
{
    /**
     * @param MessageRepositoryInterface $messageRepository
     * @param CompanyRepositoryInterface $companyRepository
     * @param CompanyManagementInterface $companyManagement
     * @param CustomerRepositoryInterface $customerRepository
     * @param EmailSender $emailSender
     * @param AdminInbox $adminInbox
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly MessageRepositoryInterface $messageRepository,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly CompanyManagementInterface $companyManagement,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly EmailSender $emailSender,
        private readonly AdminInbox $adminInbox,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Process a queued message ID.
     *
     * @param string $messageId
     * @return void
     */
    public function process(string $messageId): void
    {
        try {
            $message = $this->messageRepository->getById((int)$messageId);
            $company = $this->companyRepository->getById((int)$message->getCompanyEntityId());
        } catch (NoSuchEntityException) {
            // deleted before the notification ran
            return;
        }
        if ($message->getIsInternal()) {
            return;
        }

        match ($message->getAuthorType()) {
            MessageInterface::AUTHOR_ADMIN => $this->notifyCompany($company, $message),
            MessageInterface::AUTHOR_CUSTOMER => $this->notifyAdmins($company, $message),
            default => null,
        };
    }

    /**
     * Email the active users of the company.
     *
     * @param CompanyInterface $company
     * @param MessageInterface $message
     * @return void
     */
    private function notifyCompany(CompanyInterface $company, MessageInterface $message): void
    {
        $recipients = [];
        foreach ($this->companyManagement->getCustomers((int)$company->getId()) as $relation) {
            if ($relation->getStatus() !== CompanyCustomerInterface::STATUS_ACTIVE) {
                continue;
            }
            try {
                $customer = $this->customerRepository->getById((int)$relation->getCustomerId());
                $recipients[] = [
                    (string)$customer->getEmail(),
                    trim($customer->getFirstname() . ' ' . $customer->getLastname()),
                ];
            } catch (NoSuchEntityException) {
                $this->logger->info(sprintf('Company user %d no longer exists.', $relation->getCustomerId()));
            }
        }
        if (!$recipients) {
            $recipients[] = [(string)$company->getEmail(), (string)$company->getName()];
        }
        $this->emailSender->notifyUsersOfMessage(
            $company,
            $recipients,
            $message->getBody(),
            $company->getStatus() === CompanyInterface::STATUS_APPROVED
        );
    }

    /**
     * Email the admin recipients and add an admin panel notification.
     *
     * @param CompanyInterface $company
     * @param MessageInterface $message
     * @return void
     */
    private function notifyAdmins(CompanyInterface $company, MessageInterface $message): void
    {
        try {
            $customer = $this->customerRepository->getById((int)$message->getAuthorId());
            $authorName = trim($customer->getFirstname() . ' ' . $customer->getLastname());
        } catch (NoSuchEntityException) {
            $authorName = (string)__('Deleted customer #%1', $message->getAuthorId());
        }
        $this->emailSender->notifyAdminsOfMessage($company, $authorName, $message->getBody());
        $this->adminInbox->notifyMessage($company, $authorName);
    }
}
