<?php

declare(strict_types=1);

namespace MageOS\Company\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use MageOS\Company\Api\Data\MessageInterface;
use MageOS\Company\Api\Data\MessageSearchResultsInterface;
use MageOS\Company\Api\Data\MessageSearchResultsInterfaceFactory;
use MageOS\Company\Api\MessageRepositoryInterface;
use MageOS\Company\Model\ResourceModel\Message as MessageResource;
use MageOS\Company\Model\ResourceModel\Message\CollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Company message persistence.
 */
class MessageRepository implements MessageRepositoryInterface
{
    /**
     * @param MessageFactory $messageFactory
     * @param MessageResource $messageResource
     * @param CollectionFactory $collectionFactory
     * @param MessageSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly MessageFactory $messageFactory,
        private readonly MessageResource $messageResource,
        private readonly CollectionFactory $collectionFactory,
        private readonly MessageSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(MessageInterface $message): MessageInterface
    {
        /** @var Message $message */
        try {
            $this->messageResource->save($message);
        } catch (\Exception $e) {
            $this->logger->critical($e);
            throw new CouldNotSaveException(__('Could not save the message.'), $e);
        }

        return $this->getById((int)$message->getId());
    }

    /**
     * @inheritDoc
     */
    public function getById(int $messageId): MessageInterface
    {
        $message = $this->messageFactory->create();
        $this->messageResource->load($message, $messageId);
        if (!$message->getId()) {
            throw NoSuchEntityException::singleField(MessageInterface::MESSAGE_ID, $messageId);
        }

        return $message;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): MessageSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        /** @var MessageInterface[] $items */
        $items = array_values($collection->getItems());
        $searchResults->setItems($items);
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }
}
