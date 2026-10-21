<?php

declare(strict_types=1);

namespace MageOS\Company\ViewModel;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Company\Api\CompanyApprovalInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Api\Data\MessageInterface;
use MageOS\Company\Api\MessageManagementInterface;
use MageOS\Company\Controller\Application\Reply;
use MageOS\Company\Model\Config;
use MageOS\Company\Model\Registration\StatusLink;
use MageOS\Company\Model\Status\TransitionPolicy;

/**
 * Public application status page, opened with a signed link (D12).
 */
class ApplicationStatus implements ArgumentInterface
{
    /**
     * @var CompanyInterface|null
     */
    private ?CompanyInterface $company = null;

    /**
     * @var bool
     */
    private bool $resolved = false;

    /**
     * @var string
     */
    private string $error = '';

    /**
     * @var MessageInterface[]|null
     */
    private ?array $thread = null;

    /**
     * @param RequestInterface $request
     * @param StatusLink $statusLink
     * @param StoreManagerInterface $storeManager
     * @param CompanyApprovalInterface $companyApproval
     * @param MessageManagementInterface $messageManagement
     * @param TransitionPolicy $transitionPolicy
     * @param Config $config
     * @param DataPersistorInterface $dataPersistor
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly StatusLink $statusLink,
        private readonly StoreManagerInterface $storeManager,
        private readonly CompanyApprovalInterface $companyApproval,
        private readonly MessageManagementInterface $messageManagement,
        private readonly TransitionPolicy $transitionPolicy,
        private readonly Config $config,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * Company of the link, or null when the link is not valid or the company is no longer pending/rejected.
     *
     * @return CompanyInterface|null
     */
    public function getCompany(): ?CompanyInterface
    {
        if (!$this->resolved) {
            $this->resolved = true;
            try {
                $company = $this->statusLink->resolve(
                    $this->getToken(),
                    (int)$this->storeManager->getWebsite()->getId()
                );
                if (in_array($company->getStatus(), StatusLink::STATUSES, true)) {
                    $this->company = $company;
                } else {
                    $this->error = (string)__('This link is no longer available.');
                }
            } catch (LocalizedException $e) {
                $this->error = $e->getMessage();
            }
        }

        return $this->company;
    }

    /**
     * Why the page cannot be shown.
     *
     * @return string
     */
    public function getError(): string
    {
        $this->getCompany();

        return $this->error;
    }

    /**
     * Token of the link.
     *
     * @return string
     */
    public function getToken(): string
    {
        return (string)$this->request->getParam('token');
    }

    /**
     * Status label.
     *
     * @return string
     */
    public function getStatusLabel(): string
    {
        $company = $this->getCompany();

        return $company ? $this->transitionPolicy->getLabel($company->getStatus()) : '';
    }

    /**
     * Whether the application was rejected (it can be resubmitted).
     *
     * @return bool
     */
    public function isRejected(): bool
    {
        return $this->getCompany()?->getStatus() === CompanyInterface::STATUS_REJECTED;
    }

    /**
     * Reason of the rejection.
     *
     * @return string
     */
    public function getRejectionReason(): string
    {
        $company = $this->getCompany();
        if (!$company || !$this->isRejected()) {
            return '';
        }
        foreach ($this->companyApproval->getHistory((int)$company->getId()) as $entry) {
            if ($entry->getToStatus() === CompanyInterface::STATUS_REJECTED) {
                return (string)$entry->getComment();
            }
        }

        return '';
    }

    /**
     * Thread without internal notes, oldest first. Marks the store messages as read.
     *
     * @return MessageInterface[]
     */
    public function getThread(): array
    {
        if ($this->thread === null) {
            $this->thread = [];
            $company = $this->getCompany();
            if ($company !== null) {
                $this->thread = $this->messageManagement->getThread((int)$company->getId());
                $this->messageManagement->markRead((int)$company->getId(), MessageInterface::AUTHOR_CUSTOMER);
            }
        }

        return $this->thread;
    }

    /**
     * Whether a message was unread before this view.
     *
     * @param MessageInterface $message
     * @return bool
     */
    public function isNew(MessageInterface $message): bool
    {
        return $message->getReadAtCustomer() === null;
    }

    /**
     * Author label as seen by the applicant.
     *
     * @param MessageInterface $message
     * @return string
     */
    public function getAuthorLabel(MessageInterface $message): string
    {
        return (string)match ($message->getAuthorType()) {
            MessageInterface::AUTHOR_ADMIN => __('Customer Service'),
            MessageInterface::AUTHOR_SYSTEM => __('Notification'),
            default => __('You'),
        };
    }

    /**
     * Whether the message was written by the store side.
     *
     * @param MessageInterface $message
     * @return bool
     */
    public function isFromStore(MessageInterface $message): bool
    {
        return $message->getAuthorType() !== MessageInterface::AUTHOR_CUSTOMER;
    }

    /**
     * Maximum body length (0 = unlimited).
     *
     * @return int
     */
    public function getMaxLength(): int
    {
        $company = $this->getCompany();

        return $company ? $this->config->getMessageMaxLength((int)$company->getWebsiteId()) : 0;
    }

    /**
     * Text of a message that could not be sent (read once).
     *
     * @return string
     */
    public function getDraft(): string
    {
        $draft = (string)$this->dataPersistor->get(Reply::DRAFT_KEY);
        $this->dataPersistor->clear(Reply::DRAFT_KEY);

        return $draft;
    }

    /**
     * Reply form action.
     *
     * @return string
     */
    public function getReplyUrl(): string
    {
        return $this->urlBuilder->getUrl('company/application/reply', ['_secure' => true]);
    }

    /**
     * Resubmit form action.
     *
     * @return string
     */
    public function getResubmitUrl(): string
    {
        return $this->urlBuilder->getUrl('company/application/resubmit', ['_secure' => true]);
    }
}
