<?php

declare(strict_types=1);

namespace MageOS\Company\Model;

use Magento\Framework\Model\AbstractModel;
use MageOS\Company\Api\Data\MessageInterface;
use MageOS\Company\Model\ResourceModel\Message as MessageResource;

/**
 * Message of a company thread.
 */
class Message extends AbstractModel implements MessageInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'company_message';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(MessageResource::class);
    }

    /**
     * @inheritDoc
     */
    public function getMessageId(): ?int
    {
        return $this->getIntOrNull(self::MESSAGE_ID);
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
    public function getAuthorType(): string
    {
        return (string)($this->getData(self::AUTHOR_TYPE) ?? self::AUTHOR_SYSTEM);
    }

    /**
     * @inheritDoc
     */
    public function setAuthorType(string $authorType): self
    {
        return $this->setData(self::AUTHOR_TYPE, $authorType);
    }

    /**
     * @inheritDoc
     */
    public function getAuthorId(): ?int
    {
        return $this->getIntOrNull(self::AUTHOR_ID);
    }

    /**
     * @inheritDoc
     */
    public function setAuthorId(?int $authorId): self
    {
        return $this->setData(self::AUTHOR_ID, $authorId);
    }

    /**
     * @inheritDoc
     */
    public function getBody(): string
    {
        return (string)$this->getData(self::BODY);
    }

    /**
     * @inheritDoc
     */
    public function setBody(string $body): self
    {
        return $this->setData(self::BODY, $body);
    }

    /**
     * @inheritDoc
     */
    public function getIsInternal(): bool
    {
        return (bool)$this->getData(self::IS_INTERNAL);
    }

    /**
     * @inheritDoc
     */
    public function setIsInternal(bool $isInternal): self
    {
        return $this->setData(self::IS_INTERNAL, (int)$isInternal);
    }

    /**
     * @inheritDoc
     */
    public function getReadAtAdmin(): ?string
    {
        return $this->getData(self::READ_AT_ADMIN);
    }

    /**
     * @inheritDoc
     */
    public function getReadAtCustomer(): ?string
    {
        return $this->getData(self::READ_AT_CUSTOMER);
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
