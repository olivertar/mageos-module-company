<?php

declare(strict_types=1);

namespace MageOS\Company\Api\Data;

/**
 * Company status change record.
 *
 * @api
 */
interface StatusHistoryInterface
{
    public const HISTORY_ID = 'history_id';
    public const COMPANY_ENTITY_ID = 'company_entity_id';
    public const FROM_STATUS = 'from_status';
    public const TO_STATUS = 'to_status';
    public const COMMENT = 'comment';
    public const ACTOR_TYPE = 'actor_type';
    public const ACTOR_ID = 'actor_id';
    public const CREATED_AT = 'created_at';

    public const ACTOR_ADMIN = 'admin';
    public const ACTOR_CUSTOMER = 'customer';
    public const ACTOR_SYSTEM = 'system';
    public const ACTOR_INTEGRATION = 'integration';

    /**
     * Get history record ID.
     *
     * @return int|null
     */
    public function getHistoryId(): ?int;

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
     * Get previous status. Null for the first record of a company.
     *
     * @return int|null
     */
    public function getFromStatus(): ?int;

    /**
     * Set previous status.
     *
     * @param int|null $status
     * @return $this
     */
    public function setFromStatus(?int $status): self;

    /**
     * Get new status.
     *
     * @return int
     */
    public function getToStatus(): int;

    /**
     * Set new status.
     *
     * @param int $status
     * @return $this
     */
    public function setToStatus(int $status): self;

    /**
     * Get comment or reason.
     *
     * @return string|null
     */
    public function getComment(): ?string;

    /**
     * Set comment or reason.
     *
     * @param string|null $comment
     * @return $this
     */
    public function setComment(?string $comment): self;

    /**
     * Get actor type (see ACTOR_* constants).
     *
     * @return string
     */
    public function getActorType(): string;

    /**
     * Set actor type (see ACTOR_* constants).
     *
     * @param string $actorType
     * @return $this
     */
    public function setActorType(string $actorType): self;

    /**
     * Get actor ID (admin user, customer or integration ID).
     *
     * @return int|null
     */
    public function getActorId(): ?int;

    /**
     * Set actor ID.
     *
     * @param int|null $actorId
     * @return $this
     */
    public function setActorId(?int $actorId): self;

    /**
     * Get creation date.
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;
}
