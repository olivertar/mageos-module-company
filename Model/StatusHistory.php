<?php

declare(strict_types=1);

namespace MageOS\Company\Model;

use Magento\Framework\Model\AbstractModel;
use MageOS\Company\Api\Data\StatusHistoryInterface;
use MageOS\Company\Model\ResourceModel\StatusHistory as StatusHistoryResource;

/**
 * Company status change record.
 */
class StatusHistory extends AbstractModel implements StatusHistoryInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'company_status_history';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(StatusHistoryResource::class);
    }

    /**
     * @inheritDoc
     */
    public function getHistoryId(): ?int
    {
        return $this->getIntOrNull(self::HISTORY_ID);
    }

    /**
     * @inheritDoc
     */
    public function getCompanyEntityId(): ?int
    {
        return $this->getIntOrNull(self::COMPANY_ENTITY_ID);
    }

    /**
     * @inheritDoc
     */
    public function setCompanyEntityId(int $companyId): self
    {
        return $this->setData(self::COMPANY_ENTITY_ID, $companyId);
    }

    /**
     * @inheritDoc
     */
    public function getFromStatus(): ?int
    {
        return $this->getIntOrNull(self::FROM_STATUS);
    }

    /**
     * @inheritDoc
     */
    public function setFromStatus(?int $status): self
    {
        return $this->setData(self::FROM_STATUS, $status);
    }

    /**
     * @inheritDoc
     */
    public function getToStatus(): int
    {
        return (int)$this->getData(self::TO_STATUS);
    }

    /**
     * @inheritDoc
     */
    public function setToStatus(int $status): self
    {
        return $this->setData(self::TO_STATUS, $status);
    }

    /**
     * @inheritDoc
     */
    public function getComment(): ?string
    {
        return $this->getData(self::COMMENT);
    }

    /**
     * @inheritDoc
     */
    public function setComment(?string $comment): self
    {
        return $this->setData(self::COMMENT, $comment);
    }

    /**
     * @inheritDoc
     */
    public function getActorType(): string
    {
        return (string)($this->getData(self::ACTOR_TYPE) ?? self::ACTOR_SYSTEM);
    }

    /**
     * @inheritDoc
     */
    public function setActorType(string $actorType): self
    {
        return $this->setData(self::ACTOR_TYPE, $actorType);
    }

    /**
     * @inheritDoc
     */
    public function getActorId(): ?int
    {
        return $this->getIntOrNull(self::ACTOR_ID);
    }

    /**
     * @inheritDoc
     */
    public function setActorId(?int $actorId): self
    {
        return $this->setData(self::ACTOR_ID, $actorId);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * Read a nullable integer field.
     *
     * @param string $key
     * @return int|null
     */
    private function getIntOrNull(string $key): ?int
    {
        $value = $this->getData($key);

        return $value === null ? null : (int)$value;
    }
}
