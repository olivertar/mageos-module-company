<?php

declare(strict_types=1);

namespace MageOS\Company\Api\Data;

/**
 * Message of a company thread.
 *
 * @api
 */
interface MessageInterface
{
    public const MESSAGE_ID = 'message_id';
    public const COMPANY_ENTITY_ID = 'company_entity_id';
    public const AUTHOR_TYPE = 'author_type';
    public const AUTHOR_ID = 'author_id';
    public const BODY = 'body';
    public const IS_INTERNAL = 'is_internal';
    public const READ_AT_ADMIN = 'read_at_admin';
    public const READ_AT_CUSTOMER = 'read_at_customer';
    public const CREATED_AT = 'created_at';

    public const AUTHOR_ADMIN = 'admin';
    public const AUTHOR_CUSTOMER = 'customer';
    public const AUTHOR_SYSTEM = 'system';

    /**
     * Get message ID.
     *
     * @return int|null
     */
    public function getMessageId(): ?int;

    /**
     * Get company entity ID.
     *
     * @return int|null
     */
    public function getCompanyEntityId(): ?int;

    /**
     * Set company entity ID.
     *
     * @param int $companyId
     * @return $this
     */
    public function setCompanyEntityId(int $companyId): self;

    /**
     * Get author type (see AUTHOR_* constants).
     *
     * @return string
     */
    public function getAuthorType(): string;

    /**
     * Set author type (see AUTHOR_* constants).
     *
     * @param string $authorType
     * @return $this
     */
    public function setAuthorType(string $authorType): self;

    /**
     * Get author ID (admin user or customer ID; null for system messages).
     *
     * @return int|null
     */
    public function getAuthorId(): ?int;

    /**
     * Set author ID.
     *
     * @param int|null $authorId
     * @return $this
     */
    public function setAuthorId(?int $authorId): self;

    /**
     * Get plain text body.
     *
     * @return string
     */
    public function getBody(): string;

    /**
     * Set plain text body.
     *
     * @param string $body
     * @return $this
     */
    public function setBody(string $body): self;

    /**
     * Whether the message is an internal note, visible to admins only.
     *
     * @return bool
     */
    public function getIsInternal(): bool;

    /**
     * Set whether the message is an internal note.
     *
     * @param bool $isInternal
     * @return $this
     */
    public function setIsInternal(bool $isInternal): self;

    /**
     * Get the date admins read the message.
     *
     * @return string|null
     */
    public function getReadAtAdmin(): ?string;

    /**
     * Get the date the company read the message.
     *
     * @return string|null
     */
    public function getReadAtCustomer(): ?string;

    /**
     * Get creation date.
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;
}
