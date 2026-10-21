<?php

declare(strict_types=1);

namespace MageOS\Company\Model;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\MessageQueue\PublisherInterface;
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Api\Data\CompanyCustomerInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Api\Data\MessageInterface;
use MageOS\Company\Api\MessageManagementInterface;
use MageOS\Company\Api\MessageRepositoryInterface;
use MageOS\Company\Model\ResourceModel\CompanyCustomer as CompanyCustomerResource;
use MageOS\Company\Model\ResourceModel\Message as MessageResource;
use MageOS\Company\Model\ResourceModel\Message\CollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Company message thread.
 *
 * A message is created as read by its own side; internal notes count as read by the company and system messages
 * as read by the admins, so "unread" is always "read column is null".
 */
class MessageManagement implements MessageManagementInterface
{
    public const NOTIFY_TOPIC = 'company.message.notify';

    /**
     * Company statuses in which the applicant can write (D12).
     */
    private const APPLICANT_STATUSES = [CompanyInterface::STATUS_PENDING, CompanyInterface::STATUS_REJECTED];

    /**
     * @param CompanyRepositoryInterface $companyRepository
     * @param MessageRepositoryInterface $messageRepository
     * @param MessageFactory $messageFactory
     * @param MessageResource $messageResource
     * @param CollectionFactory $collectionFactory
     * @param CompanyCustomerResource $relationResource
     * @param CompanyCustomerFactory $relationFactory
     * @param Config $config
     * @param PublisherInterface $publisher
     * @param EventManager $eventManager
     * @param LoggerInterface $logger
     * @param UserContextInterface $userContext
     * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
     */
    public function __construct(
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly MessageRepositoryInterface $messageRepository,
        private readonly MessageFactory $messageFactory,
        private readonly MessageResource $messageResource,
        private readonly CollectionFactory $collectionFactory,
        private readonly CompanyCustomerResource $relationResource,
        private readonly CompanyCustomerFactory $relationFactory,
        private readonly Config $config,
        private readonly PublisherInterface $publisher,
        private readonly EventManager $eventManager,
        private readonly LoggerInterface $logger,
        private readonly UserContextInterface $userContext
    ) {
    }

    /**
     * @inheritDoc
     */
    public function sendAsAdmin(int $companyId, string $body, bool $isInternal = false): MessageInterface
    {
        // integrations write as the store, without an admin user
        $authorId = (int)$this->userContext->getUserType() === UserContextInterface::USER_TYPE_ADMIN
            ? (int)$this->userContext->getUserId()
            : null;

        return $this->send($companyId, $body, MessageInterface::AUTHOR_ADMIN, $authorId, $isInternal);
    }

    /**
     * @inheritDoc
     */
    public function sendAsApplicant(int $companyId, string $body): MessageInterface
    {
        $company = $this->companyRepository->getById($companyId);
        if (!in_array($company->getStatus(), self::APPLICANT_STATUSES, true) || !$company->getSuperUserId()) {
            throw new LocalizedException(__('You cannot send messages to this company.'));
        }

        return $this->doSend(
            $company,
            $body,
            MessageInterface::AUTHOR_CUSTOMER,
            $company->getSuperUserId(),
            false,
            true
        );
    }

    /**
     * @inheritDoc
     */
    public function send(
        int $companyId,
        string $body,
        string $authorType,
        ?int $authorId = null,
        bool $isInternal = false
    ): MessageInterface {
        return $this->doSend(
            $this->companyRepository->getById($companyId),
            $body,
            $authorType,
            $authorId,
            $isInternal,
            false
        );
    }

    /**
     * Validate, save and queue the notification of a message.
     *
     * @param CompanyInterface $company
     * @param string $body
     * @param string $authorType
     * @param int|null $authorId
     * @param bool $isInternal
     * @param bool $asApplicant The customer writes as the applicant of a company not approved yet.
     * @return MessageInterface
     * @throws LocalizedException
     */
    private function doSend(
        CompanyInterface $company,
        string $body,
        string $authorType,
        ?int $authorId,
        bool $isInternal,
        bool $asApplicant
    ): MessageInterface {
        $companyId = (int)$company->getId();
        $websiteId = (int)$company->getWebsiteId();
        $body = trim(str_replace("\r\n", "\n", $body));
        $this->validate($company, $body, $authorType, $authorId, $isInternal, $asApplicant);

        $message = $this->messageFactory->create();
        $message->setCompanyEntityId($companyId)
            ->setAuthorType($authorType)
            ->setAuthorId($authorType === MessageInterface::AUTHOR_SYSTEM ? null : $authorId)
            ->setBody($body)
            ->setIsInternal($isInternal);
        $now = new \Zend_Db_Expr('CURRENT_TIMESTAMP');
        if ($authorType !== MessageInterface::AUTHOR_CUSTOMER) {
            $message->setData(MessageInterface::READ_AT_ADMIN, $now);
        }
        if ($authorType === MessageInterface::AUTHOR_CUSTOMER || $isInternal) {
            $message->setData(MessageInterface::READ_AT_CUSTOMER, $now);
        }
        $message = $this->messageRepository->save($message);

        $this->eventManager->dispatch('company_message_send_after', ['message' => $message, 'company' => $company]);
        if ($authorType !== MessageInterface::AUTHOR_SYSTEM && !$isInternal) {
            try {
                $this->publisher->publish(self::NOTIFY_TOPIC, (string)$message->getMessageId());
            } catch (\Throwable $e) {
                // the message is saved: a notification failure must not fail the request
                $this->logger->error(
                    sprintf('Company message %d not queued: %s', $message->getMessageId(), $e->getMessage()),
                    ['exception' => $e, 'website_id' => $websiteId]
                );
            }
        }

        return $message;
    }

    /**
     * @inheritDoc
     */
    public function getThread(int $companyId, bool $includeInternal = false): array
    {
        $this->companyRepository->getById($companyId);
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(MessageInterface::COMPANY_ENTITY_ID, ['eq' => $companyId])
            ->setOrder(MessageInterface::CREATED_AT, 'ASC')
            ->setOrder(MessageInterface::MESSAGE_ID, 'ASC');
        if (!$includeInternal) {
            $collection->addFieldToFilter(MessageInterface::IS_INTERNAL, ['eq' => 0]);
        }
        /** @var MessageInterface[] $messages */
        $messages = array_values($collection->getItems());

        return $messages;
    }

    /**
     * @inheritDoc
     */
    public function markRead(int $companyId, string $readerType): int
    {
        $this->companyRepository->getById($companyId);

        return $this->messageResource->markRead($companyId, $this->assertReader($readerType));
    }

    /**
     * @inheritDoc
     */
    public function getUnreadCount(int $companyId, string $readerType): int
    {
        return $this->messageResource->countUnread($companyId, $this->assertReader($readerType));
    }

    /**
     * Validate a new message.
     *
     * @param CompanyInterface $company
     * @param string $body
     * @param string $authorType
     * @param int|null $authorId
     * @param bool $isInternal
     * @param bool $asApplicant
     * @return void
     * @throws LocalizedException
     */
    private function validate(
        CompanyInterface $company,
        string $body,
        string $authorType,
        ?int $authorId,
        bool $isInternal,
        bool $asApplicant
    ): void {
        if (!in_array(
            $authorType,
            [MessageInterface::AUTHOR_ADMIN, MessageInterface::AUTHOR_CUSTOMER, MessageInterface::AUTHOR_SYSTEM],
            true
        )) {
            throw new LocalizedException(__('Author type "%1" is not valid.', $authorType));
        }
        if ($body === '') {
            throw new LocalizedException(__('The message cannot be empty.'));
        }
        if ($isInternal && $authorType !== MessageInterface::AUTHOR_ADMIN) {
            throw new LocalizedException(__('Only admins can write internal notes.'));
        }
        $websiteId = (int)$company->getWebsiteId();
        $maxLength = $this->config->getMessageMaxLength($websiteId);
        if ($authorType !== MessageInterface::AUTHOR_SYSTEM && $maxLength && mb_strlen($body) > $maxLength) {
            throw new LocalizedException(__('The message cannot be longer than %1 characters.', $maxLength));
        }
        if ($authorType === MessageInterface::AUTHOR_CUSTOMER) {
            $this->validateCustomerAuthor($company, (int)$authorId, $asApplicant);
        }
    }

    /**
     * A customer writes only to its own approved company (pending or rejected for its applicant), and not too often.
     *
     * @param CompanyInterface $company
     * @param int $customerId
     * @param bool $asApplicant
     * @return void
     * @throws LocalizedException
     */
    private function validateCustomerAuthor(CompanyInterface $company, int $customerId, bool $asApplicant): void
    {
        $relation = $this->relationFactory->create();
        $this->relationResource->load($relation, $customerId);
        if (!$customerId
            || $relation->getCompanyEntityId() !== (int)$company->getId()
            || $relation->getStatus() !== CompanyCustomerInterface::STATUS_ACTIVE
        ) {
            throw new LocalizedException(__('You cannot send messages to this company.'));
        }
        if (!$asApplicant && $company->getStatus() !== CompanyInterface::STATUS_APPROVED) {
            throw new LocalizedException(__('Messages are available once your company is approved.'));
        }
        $rateLimit = $this->config->getMessageRateLimit((int)$company->getWebsiteId());
        if ($rateLimit && $this->messageResource->countRecentByCustomer($customerId) >= $rateLimit) {
            throw new LocalizedException(
                __('You have sent too many messages. Please wait a while before sending another one.')
            );
        }
    }

    /**
     * Validate a reader type.
     *
     * @param string $readerType
     * @return string
     * @throws LocalizedException
     */
    private function assertReader(string $readerType): string
    {
        if (!in_array($readerType, [MessageInterface::AUTHOR_ADMIN, MessageInterface::AUTHOR_CUSTOMER], true)) {
            throw new LocalizedException(__('Reader type "%1" is not valid.', $readerType));
        }

        return $readerType;
    }
}
