<?php

declare(strict_types=1);

namespace MageOS\Company\ViewModel;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Api\Data\MessageInterface;
use MageOS\Company\Api\MessageManagementInterface;
use MageOS\Company\Controller\Message\Send;
use MageOS\Company\Model\Authorization\CompanyContext;
use MageOS\Company\Model\Config;

/**
 * Company message thread of the logged in customer. Internal notes are never loaded.
 */
class Messages implements ArgumentInterface
{
    /**
     * @var MessageInterface[]|null
     */
    private ?array $thread = null;

    /**
     * @var array<int,string>
     */
    private array $customerNames = [];

    /**
     * @param CustomerSession $customerSession
     * @param CompanyContext $companyContext
     * @param MessageManagementInterface $messageManagement
     * @param CustomerRepositoryInterface $customerRepository
     * @param Config $config
     * @param DataPersistorInterface $dataPersistor
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly CompanyContext $companyContext,
        private readonly MessageManagementInterface $messageManagement,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly Config $config,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * Company of the customer, or null.
     *
     * @return CompanyInterface|null
     */
    public function getCompany(): ?CompanyInterface
    {
        try {
            return $this->companyContext->getCompany((int)$this->customerSession->getCustomerId());
        } catch (LocalizedException) {
            return null;
        }
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
     * Author label as seen by the company.
     *
     * @param MessageInterface $message
     * @return string
     */
    public function getAuthorLabel(MessageInterface $message): string
    {
        $type = $message->getAuthorType();
        if ($type === MessageInterface::AUTHOR_ADMIN) {
            return (string)__('Customer Service');
        }
        if ($type === MessageInterface::AUTHOR_SYSTEM) {
            return (string)__('Notification');
        }
        $authorId = (int)$message->getAuthorId();
        if ($authorId === (int)$this->customerSession->getCustomerId()) {
            return (string)__('You');
        }
        if (!isset($this->customerNames[$authorId])) {
            try {
                $customer = $this->customerRepository->getById($authorId);
                $this->customerNames[$authorId] = trim($customer->getFirstname() . ' ' . $customer->getLastname());
            } catch (NoSuchEntityException) {
                $this->customerNames[$authorId] = (string)__('Former user');
            }
        }

        return $this->customerNames[$authorId];
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
        $draft = (string)$this->dataPersistor->get(Send::DRAFT_KEY);
        $this->dataPersistor->clear(Send::DRAFT_KEY);

        return $draft;
    }

    /**
     * Form action.
     *
     * @return string
     */
    public function getPostUrl(): string
    {
        return $this->urlBuilder->getUrl('company/message/send', ['_secure' => true]);
    }
}
