<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Status;

use Magento\Framework\Exception\LocalizedException;
use MageOS\Company\Api\Data\StatusHistoryInterface;
use MageOS\Company\Model\ResourceModel\StatusHistory as StatusHistoryResource;
use MageOS\Company\Model\ResourceModel\StatusHistory\CollectionFactory;
use MageOS\Company\Model\StatusHistoryFactory;

/**
 * Writes and reads the company status history.
 */
class HistoryRecorder
{
    /**
     * @param StatusHistoryFactory $historyFactory
     * @param StatusHistoryResource $historyResource
     * @param CollectionFactory $collectionFactory
     * @param ActorResolver $actorResolver
     */
    public function __construct(
        private readonly StatusHistoryFactory $historyFactory,
        private readonly StatusHistoryResource $historyResource,
        private readonly CollectionFactory $collectionFactory,
        private readonly ActorResolver $actorResolver
    ) {
    }

    /**
     * Record a status change. The actor defaults to the current user context.
     *
     * @param int $companyId
     * @param int|null $from
     * @param int $to
     * @param string|null $comment
     * @param string|null $actorType
     * @param int|null $actorId
     * @return StatusHistoryInterface
     * @throws LocalizedException
     */
    public function record(
        int $companyId,
        ?int $from,
        int $to,
        ?string $comment = null,
        ?string $actorType = null,
        ?int $actorId = null
    ): StatusHistoryInterface {
        if ($actorType === null) {
            [$actorType, $actorId] = $this->actorResolver->resolve();
        }
        $comment = $comment === null ? null : trim($comment);

        $history = $this->historyFactory->create();
        $history->setCompanyEntityId($companyId)
            ->setFromStatus($from)
            ->setToStatus($to)
            ->setComment($comment === '' ? null : $comment)
            ->setActorType($actorType)
            ->setActorId($actorId);
        $this->historyResource->save($history);

        return $history;
    }

    /**
     * History of a company, newest first.
     *
     * @param int $companyId
     * @return StatusHistoryInterface[]
     */
    public function getHistory(int $companyId): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(StatusHistoryInterface::COMPANY_ENTITY_ID, ['eq' => $companyId])
            ->setOrder(StatusHistoryInterface::CREATED_AT, 'DESC')
            ->setOrder(StatusHistoryInterface::HISTORY_ID, 'DESC');
        /** @var StatusHistoryInterface[] $items */
        $items = array_values($collection->getItems());

        return $items;
    }
}
