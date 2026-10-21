<?php

declare(strict_types=1);

namespace MageOS\Company\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use MageOS\Company\Api\Data\MessageInterface;
use MageOS\Company\Api\Data\MessageSearchResultsInterface;

/**
 * Company message persistence. Use MessageManagementInterface to send messages: it validates and notifies.
 *
 * @api
 */
interface MessageRepositoryInterface
{
    /**
     * Save a message.
     *
     * @param \MageOS\Company\Api\Data\MessageInterface $message
     * @return \MageOS\Company\Api\Data\MessageInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(MessageInterface $message): MessageInterface;

    /**
     * Get a message by ID.
     *
     * @param int $messageId
     * @return \MageOS\Company\Api\Data\MessageInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $messageId): MessageInterface;

    /**
     * Search messages.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \MageOS\Company\Api\Data\MessageSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): MessageSearchResultsInterface;
}
