<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Notification;

use Magento\AdminNotification\Model\InboxFactory;
use MageOS\Company\Api\Data\CompanyInterface;
use Psr\Log\LoggerInterface;

/**
 * Admin panel notifications (System Messages inbox). Failures are logged only.
 */
class AdminInbox
{
    /**
     * @param InboxFactory $inboxFactory
     * @param AdminUrl $adminUrl
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly InboxFactory $inboxFactory,
        private readonly AdminUrl $adminUrl,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Notify a new company application.
     *
     * @param CompanyInterface $company
     * @return void
     */
    public function notifyApplication(CompanyInterface $company): void
    {
        $this->add(
            (string)__('New company application: %1', $company->getName()),
            (string)__('The company "%1" applied for an account and is waiting for review.', $company->getName()),
            $company
        );
    }

    /**
     * Notify a new message from a company user.
     *
     * @param CompanyInterface $company
     * @param string $authorName
     * @return void
     */
    public function notifyMessage(CompanyInterface $company, string $authorName): void
    {
        $this->add(
            (string)__('New message from %1', $company->getName()),
            (string)__('%1 (%2) sent a new message.', $authorName, $company->getName()),
            $company
        );
    }

    /**
     * Add an inbox notice linking to the company.
     *
     * @param string $title
     * @param string $description
     * @param CompanyInterface $company
     * @return void
     */
    private function add(string $title, string $description, CompanyInterface $company): void
    {
        try {
            $this->inboxFactory->create()->addNotice(
                $title,
                $description,
                $this->adminUrl->getCompanyUrl((int)$company->getId())
            );
        } catch (\Throwable $e) {
            $this->logger->error('Company admin notification failed: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
}
