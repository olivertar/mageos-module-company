<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Notification;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Model\Config;
use MageOS\Company\Model\Registration\StatusLink;
use Psr\Log\LoggerInterface;

/**
 * Company notification emails. Failures are logged and never interrupt the operation that triggered them.
 */
class EmailSender
{
    /**
     * @param TransportBuilder $transportBuilder
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param CustomerRepositoryInterface $customerRepository
     * @param AdminUrl $adminUrl
     * @param StatusLink $statusLink
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly TransportBuilder $transportBuilder,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly AdminUrl $adminUrl,
        private readonly StatusLink $statusLink,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Notify the company contact (super user, or the company email when there is none).
     *
     * While the company is pending or rejected, the email carries the application status link ("status_url").
     *
     * @param CompanyInterface $company
     * @param string $type One of the Config::EMAIL_* customer notifications.
     * @param string|null $comment
     * @return void
     */
    public function notifyCompany(CompanyInterface $company, string $type, ?string $comment = null): void
    {
        $websiteId = (int)$company->getWebsiteId();
        [$email, $name] = $this->getContact($company);
        $this->send($type, $websiteId, [[$email, $name]], [
            'company_name' => (string)$company->getName(),
            'customer_name' => $name,
            'comment' => (string)$comment,
            'status_url' => (string)$this->statusLink->getUrl($company),
        ]);
    }

    /**
     * Notify the configured admin recipients of a new application.
     *
     * @param CompanyInterface $company
     * @return void
     */
    public function notifyAdminsOfApplication(CompanyInterface $company): void
    {
        $websiteId = (int)$company->getWebsiteId();
        $recipients = array_map(
            static fn (string $email): array => [$email, ''],
            $this->config->getAdminRecipients($websiteId)
        );
        if (!$recipients) {
            return;
        }
        [$applicantEmail, $applicantName] = $this->getContact($company);
        $this->send(Config::EMAIL_ADMIN_NEW_APPLICATION, $websiteId, $recipients, [
            'company_name' => (string)$company->getName(),
            'company_email' => (string)$company->getEmail(),
            'applicant_name' => $applicantName,
            'applicant_email' => $applicantEmail,
            'company_url' => $this->adminUrl->getCompanyUrl((int)$company->getId()),
        ]);
    }

    /**
     * Notify company users of a new message from the store.
     *
     * @param CompanyInterface $company
     * @param string[][] $recipients List of [email, name].
     * @param string $body
     * @param bool $canViewThread Whether the users can read the thread in their account (approved company).
     * @return void
     */
    public function notifyUsersOfMessage(
        CompanyInterface $company,
        array $recipients,
        string $body,
        bool $canViewThread
    ): void {
        foreach ($recipients as [$email, $name]) {
            $this->send(Config::EMAIL_NEW_MESSAGE_CUSTOMER, (int)$company->getWebsiteId(), [[$email, $name]], [
                'company_name' => (string)$company->getName(),
                'customer_name' => $name,
                'body' => $body,
                'can_view_thread' => $canViewThread,
                'status_url' => $canViewThread ? '' : (string)$this->statusLink->getUrl($company),
            ]);
        }
    }

    /**
     * Notify the configured admin recipients of a new message from a company user.
     *
     * @param CompanyInterface $company
     * @param string $authorName
     * @param string $body
     * @return void
     */
    public function notifyAdminsOfMessage(CompanyInterface $company, string $authorName, string $body): void
    {
        $websiteId = (int)$company->getWebsiteId();
        $recipients = array_map(
            static fn (string $email): array => [$email, ''],
            $this->config->getAdminRecipients($websiteId)
        );
        if (!$recipients) {
            return;
        }
        $this->send(Config::EMAIL_NEW_MESSAGE_ADMIN, $websiteId, $recipients, [
            'company_name' => (string)$company->getName(),
            'author_name' => $authorName,
            'body' => $body,
            'company_url' => $this->adminUrl->getCompanyUrl((int)$company->getId()),
        ]);
    }

    /**
     * Send an invitation to join a company.
     *
     * @param CompanyInterface $company
     * @param string $email
     * @param string $token
     * @param string $inviterName
     * @return void
     */
    public function sendInvitation(CompanyInterface $company, string $email, string $token, string $inviterName): void
    {
        $websiteId = (int)$company->getWebsiteId();
        $store = $this->getDefaultStore($websiteId);
        $this->send(Config::EMAIL_INVITATION, $websiteId, [[$email, '']], [
            'company_name' => (string)$company->getName(),
            'inviter_name' => $inviterName,
            'accept_url' => $store->getUrl('company/invitation/accept', [
                '_query' => ['token' => $token],
                '_nosid' => true,
                '_secure' => true,
            ]),
            'lifetime' => $this->config->getInvitationLifetime($websiteId),
        ]);
    }

    /**
     * Default store of a website.
     *
     * @param int $websiteId
     * @return \Magento\Store\Model\Store
     */
    private function getDefaultStore(int $websiteId): \Magento\Store\Model\Store
    {
        $groupId = (string)$this->storeManager->getWebsite($websiteId)->getDefaultGroupId();
        /** @var \Magento\Store\Model\Store $store */
        $store = $this->storeManager->getStore($this->storeManager->getGroup($groupId)->getDefaultStoreId());

        return $store;
    }

    /**
     * Send an email to each recipient.
     *
     * @param string $type
     * @param int $websiteId
     * @param string[][] $recipients List of [email, name].
     * @param array<string,mixed> $vars
     * @return void
     */
    private function send(string $type, int $websiteId, array $recipients, array $vars): void
    {
        try {
            $templateId = $this->config->getEmailTemplate($type, $websiteId);
            if ($templateId === '') {
                return;
            }
            $storeId = (int)$this->getDefaultStore($websiteId)->getId();
            foreach ($recipients as [$email, $name]) {
                $this->transportBuilder
                    ->setTemplateIdentifier($templateId)
                    ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
                    ->setTemplateVars($vars + ['store' => $this->storeManager->getStore($storeId)])
                    ->setFromByScope($this->config->getEmailIdentity($websiteId), $storeId)
                    ->addTo($email, $name)
                    ->getTransport()
                    ->sendMessage();
            }
        } catch (\Throwable $e) {
            $this->logger->error(sprintf('Company email "%s" could not be sent: %s', $type, $e->getMessage()), [
                'exception' => $e,
            ]);
        }
    }

    /**
     * Email and name of the company contact.
     *
     * @param CompanyInterface $company
     * @return array{0: string, 1: string}
     */
    private function getContact(CompanyInterface $company): array
    {
        $superUserId = $company->getSuperUserId();
        if ($superUserId !== null) {
            try {
                $customer = $this->customerRepository->getById($superUserId);

                return [
                    (string)$customer->getEmail(),
                    trim($customer->getFirstname() . ' ' . $customer->getLastname()),
                ];
            } catch (NoSuchEntityException) {
                $superUserId = null;
            }
        }

        return [(string)$company->getEmail(), (string)$company->getName()];
    }
}
