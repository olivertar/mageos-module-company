<?php

declare(strict_types=1);

namespace MageOS\Company\Block\Adminhtml\Company\Edit;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\User\Model\ResourceModel\User as UserResource;
use Magento\User\Model\UserFactory;
use MageOS\Company\Api\Data\MessageInterface;
use MageOS\Company\Api\MessageManagementInterface;

/**
 * Message thread of the edited company, with the reply form. Viewing it marks the company messages as read.
 */
class Messages extends Template
{
    /**
     * @var array<string,string>
     */
    private array $authorNames = [];

    /**
     * @var MessageInterface[]|null
     */
    private ?array $thread = null;

    /**
     * @param Context $context
     * @param MessageManagementInterface $messageManagement
     * @param CustomerRepositoryInterface $customerRepository
     * @param UserFactory $userFactory
     * @param UserResource $userResource
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly MessageManagementInterface $messageManagement,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly UserFactory $userFactory,
        private readonly UserResource $userResource,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Company ID: set by the send controller, or the edited company.
     *
     * @return int
     */
    public function getCompanyId(): int
    {
        return (int)($this->getData('company_id') ?: $this->getRequest()->getParam('id'));
    }

    /**
     * Thread including internal notes, oldest first. Marks the company messages as read.
     *
     * @return MessageInterface[]
     */
    public function getThread(): array
    {
        if ($this->thread === null) {
            $this->thread = [];
            $companyId = $this->getCompanyId();
            if ($companyId) {
                try {
                    $this->thread = $this->messageManagement->getThread($companyId, true);
                    $this->messageManagement->markRead($companyId, MessageInterface::AUTHOR_ADMIN);
                } catch (NoSuchEntityException) {
                    $this->thread = [];
                }
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
        return $message->getReadAtAdmin() === null;
    }

    /**
     * Author label.
     *
     * @param MessageInterface $message
     * @return string
     */
    public function getAuthorLabel(MessageInterface $message): string
    {
        $type = $message->getAuthorType();
        $authorId = $message->getAuthorId();
        $key = $type . ':' . $authorId;
        if (!isset($this->authorNames[$key])) {
            $this->authorNames[$key] = $this->resolveAuthorName($type, $authorId);
        }

        return $this->authorNames[$key];
    }

    /**
     * Send URL.
     *
     * @return string
     */
    public function getSendUrl(): string
    {
        return $this->getUrl('mageos_company/message/send');
    }

    /**
     * Build the author label.
     *
     * @param string $type
     * @param int|null $authorId
     * @return string
     */
    private function resolveAuthorName(string $type, ?int $authorId): string
    {
        if ($type === MessageInterface::AUTHOR_ADMIN) {
            if ($authorId === null) {
                return (string)__('Integration');
            }
            $user = $this->userFactory->create();
            if ($authorId !== null) {
                $this->userResource->load($user, $authorId);
            }

            return (string)__('Admin: %1', $user->getId() ? (string)$user->getUserName() : '#' . $authorId);
        }
        if ($type === MessageInterface::AUTHOR_CUSTOMER) {
            try {
                $customer = $this->customerRepository->getById((int)$authorId);

                return trim($customer->getFirstname() . ' ' . $customer->getLastname())
                    . ' <' . $customer->getEmail() . '>';
            } catch (NoSuchEntityException) {
                return (string)__('Deleted customer #%1', $authorId);
            }
        }

        return (string)__('System');
    }
}
