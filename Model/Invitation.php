<?php

declare(strict_types=1);

namespace MageOS\Company\Model;

use Magento\Framework\Model\AbstractModel;
use MageOS\Company\Model\ResourceModel\Invitation as InvitationResource;

/**
 * Invitation to join a company as a user. Only the SHA-256 of the token is stored.
 */
class Invitation extends AbstractModel
{
    public const INVITATION_ID = 'invitation_id';
    public const COMPANY_ENTITY_ID = 'company_entity_id';
    public const EMAIL = 'email';
    public const ROLE = 'role';
    public const JOB_TITLE = 'job_title';
    public const TOKEN_HASH = 'token_hash';
    public const INVITED_BY = 'invited_by';
    public const EXPIRES_AT = 'expires_at';
    public const ACCEPTED_AT = 'accepted_at';
    public const CREATED_AT = 'created_at';

    /**
     * @var string
     */
    protected $_eventPrefix = 'company_invitation';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(InvitationResource::class);
    }

    /**
     * Company entity ID.
     *
     * @return int
     */
    public function getCompanyEntityId(): int
    {
        return (int)$this->getData(self::COMPANY_ENTITY_ID);
    }

    /**
     * Invited email.
     *
     * @return string
     */
    public function getEmail(): string
    {
        return (string)$this->getData(self::EMAIL);
    }

    /**
     * Role given on acceptance.
     *
     * @return string
     */
    public function getRole(): string
    {
        return (string)$this->getData(self::ROLE);
    }

    /**
     * Job title given on acceptance.
     *
     * @return string|null
     */
    public function getJobTitle(): ?string
    {
        $value = $this->getData(self::JOB_TITLE);

        return $value === null ? null : (string)$value;
    }

    /**
     * Whether the invitation was accepted.
     *
     * @return bool
     */
    public function isAccepted(): bool
    {
        return $this->getData(self::ACCEPTED_AT) !== null;
    }

    /**
     * Whether the invitation expired (computed by the database when loaded by token).
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return (bool)$this->getData('is_expired');
    }
}
