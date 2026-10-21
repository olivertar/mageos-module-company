<?php

declare(strict_types=1);

namespace MageOS\Company\Api\Data;

/**
 * Relation between a customer and its company.
 *
 * @api
 */
interface CompanyCustomerInterface
{
    public const CUSTOMER_ID = 'customer_id';
    public const COMPANY_ENTITY_ID = 'company_entity_id';
    public const ROLE = 'role';
    public const JOB_TITLE = 'job_title';
    public const STATUS = 'status';
    public const CREATED_AT = 'created_at';

    public const ROLE_ADMIN = 'admin';
    public const ROLE_USER = 'user';

    public const STATUS_INACTIVE = 0;
    public const STATUS_ACTIVE = 1;

    /**
     * Get customer ID.
     *
     * @return int|null
     */
    public function getCustomerId(): ?int;

    /**
     * Set customer ID.
     *
     * @param int $customerId
     * @return $this
     */
    public function setCustomerId(int $customerId): self;

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
     * Get role (see ROLE_* constants).
     *
     * @return string
     */
    public function getRole(): string;

    /**
     * Set role (see ROLE_* constants).
     *
     * @param string $role
     * @return $this
     */
    public function setRole(string $role): self;

    /**
     * Get job title.
     *
     * @return string|null
     */
    public function getJobTitle(): ?string;

    /**
     * Set job title.
     *
     * @param string|null $jobTitle
     * @return $this
     */
    public function setJobTitle(?string $jobTitle): self;

    /**
     * Get status (see STATUS_* constants).
     *
     * @return int
     */
    public function getStatus(): int;

    /**
     * Set status (see STATUS_* constants).
     *
     * @param int $status
     * @return $this
     */
    public function setStatus(int $status): self;

    /**
     * Get creation date.
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;
}
