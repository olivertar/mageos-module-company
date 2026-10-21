<?php

declare(strict_types=1);

namespace MageOS\Company\Api\Data;

use Magento\Framework\Api\CustomAttributesDataInterface;

/**
 * Company entity.
 *
 * Static fields are exposed through getters/setters; EAV attributes (legal_name, vat_id, phone, ...)
 * are exposed as custom attributes.
 *
 * @api
 */
interface CompanyInterface extends CustomAttributesDataInterface
{
    public const ENTITY_ID = 'entity_id';
    public const NAME = 'name';
    public const COMPANY_ID = 'company_id';
    public const STATUS = 'status';
    public const EMAIL = 'email';
    public const WEBSITE_ID = 'website_id';
    public const SUPER_USER_ID = 'super_user_id';
    public const DEFAULT_BILLING = 'default_billing';
    public const DEFAULT_SHIPPING = 'default_shipping';
    public const LEGAL_ADDRESS = 'legal_address';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public const STATUS_PENDING = 0;
    public const STATUS_APPROVED = 1;
    public const STATUS_REJECTED = 2;
    public const STATUS_BLOCKED = 3;

    /**
     * Get company entity ID.
     *
     * @return int|null
     */
    public function getId(): ?int;

    /**
     * Set company entity ID.
     *
     * @param int $id
     * @return $this
     */
    public function setId($id);

    /**
     * Get company name.
     *
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * Set company name.
     *
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self;

    /**
     * Get external (ERP) company code. Empty until the company is linked with the ERP.
     *
     * @return string|null
     */
    public function getCompanyId(): ?string;

    /**
     * Set external (ERP) company code.
     *
     * @param string|null $companyId
     * @return $this
     */
    public function setCompanyId(?string $companyId): self;

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
     * Get company email.
     *
     * @return string|null
     */
    public function getEmail(): ?string;

    /**
     * Set company email.
     *
     * @param string $email
     * @return $this
     */
    public function setEmail(string $email): self;

    /**
     * Get website ID.
     *
     * @return int|null
     */
    public function getWebsiteId(): ?int;

    /**
     * Set website ID.
     *
     * @param int $websiteId
     * @return $this
     */
    public function setWebsiteId(int $websiteId): self;

    /**
     * Get super user (customer ID).
     *
     * @return int|null
     */
    public function getSuperUserId(): ?int;

    /**
     * Set super user (customer ID).
     *
     * @param int|null $customerId
     * @return $this
     */
    public function setSuperUserId(?int $customerId): self;

    /**
     * Get default billing address ID.
     *
     * @return int|null
     */
    public function getDefaultBilling(): ?int;

    /**
     * Set default billing address ID.
     *
     * @param int|null $addressId
     * @return $this
     */
    public function setDefaultBilling(?int $addressId): self;

    /**
     * Get default shipping address ID.
     *
     * @return int|null
     */
    public function getDefaultShipping(): ?int;

    /**
     * Set default shipping address ID.
     *
     * @param int|null $addressId
     * @return $this
     */
    public function setDefaultShipping(?int $addressId): self;

    /**
     * Get legal address ID.
     *
     * @return int|null
     */
    public function getLegalAddress(): ?int;

    /**
     * Set legal address ID.
     *
     * @param int|null $addressId
     * @return $this
     */
    public function setLegalAddress(?int $addressId): self;

    /**
     * Get creation date.
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * Get last update date.
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string;

    /**
     * Get extension attributes.
     *
     * @return \MageOS\Company\Api\Data\CompanyExtensionInterface|null
     */
    public function getExtensionAttributes(): ?CompanyExtensionInterface;

    /**
     * Set extension attributes.
     *
     * @param \MageOS\Company\Api\Data\CompanyExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(CompanyExtensionInterface $extensionAttributes): self;
}
