<?php

declare(strict_types=1);

namespace MageOS\Company\Api;

use MageOS\Company\Api\Data\MessageInterface;

/**
 * Company message thread: one thread per company between the store admins and the company users.
 *
 * @api
 */
interface MessageManagementInterface
{
    /**
     * Send a message to a company thread.
     *
     * Customer authors must be active users of the (approved) company and are rate limited. Only admins can write
     * internal notes. The other party is notified asynchronously.
     *
     * @param int $companyId
     * @param string $body Plain text.
     * @param string $authorType See MessageInterface::AUTHOR_* constants.
     * @param int|null $authorId
     * @param bool $isInternal
     * @return \MageOS\Company\Api\Data\MessageInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function send(
        int $companyId,
        string $body,
        string $authorType,
        ?int $authorId = null,
        bool $isInternal = false
    ): MessageInterface;

    /**
     * Send a message or internal note as the calling admin user or integration (web API).
     *
     * @param int $companyId
     * @param string $body Plain text.
     * @param bool $isInternal
     * @return \MageOS\Company\Api\Data\MessageInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function sendAsAdmin(int $companyId, string $body, bool $isInternal = false): MessageInterface;

    /**
     * Send a message as the applicant (super user) of a pending or rejected company.
     *
     * Not exposed in the web API: the caller must have authenticated the applicant (application status link).
     *
     * @param int $companyId
     * @param string $body Plain text.
     * @return \MageOS\Company\Api\Data\MessageInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function sendAsApplicant(int $companyId, string $body): MessageInterface;

    /**
     * Messages of a company thread, oldest first.
     *
     * @param int $companyId
     * @param bool $includeInternal Internal notes are for admins only.
     * @return \MageOS\Company\Api\Data\MessageInterface[]
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getThread(int $companyId, bool $includeInternal = false): array;

    /**
     * Mark the messages of the other party as read.
     *
     * @param int $companyId
     * @param string $readerType MessageInterface::AUTHOR_ADMIN or MessageInterface::AUTHOR_CUSTOMER.
     * @return int Number of messages marked.
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function markRead(int $companyId, string $readerType): int;

    /**
     * Number of unread messages for a reader.
     *
     * @param int $companyId
     * @param string $readerType MessageInterface::AUTHOR_ADMIN or MessageInterface::AUTHOR_CUSTOMER.
     * @return int
     */
    public function getUnreadCount(int $companyId, string $readerType): int;
}
