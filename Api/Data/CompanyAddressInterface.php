<?php

declare(strict_types=1);

namespace MageOS\Company\Api\Data;

use Magento\Framework\Api\CustomAttributesDataInterface;

/**
 * Company address entity.
 *
 * Attribute codes mirror customer_address so a company address can later be copied into a quote address.
 *
 * @api
 */
interface CompanyAddressInterface extends CustomAttributesDataInterface
{
    public const ENTITY_ID = 'entity_id';
    public const PARENT_ID = 'parent_id';
    public const IS_ACTIVE = 'is_active';
    public const FIRSTNAME = 'firstname';
    public const LASTNAME = 'lastname';
    public const COMPANY = 'company';
    public const STREET = 'street';
    public const CITY = 'city';
    public const COUNTRY_ID = 'country_id';
    public const REGION = 'region';
    public const REGION_ID = 'region_id';
    public const POSTCODE = 'postcode';
    public const TELEPHONE = 'telephone';
    public const VAT_ID = 'vat_id';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * Get address ID.
     *
     * @return int|null
     */
    public function getId(): ?int;

    /**
     * Set address ID.
     *
     * @param int $id
     * @return $this
     */
    public function setId($id);

    /**
     * Get owner company entity ID.
     *
     * @return int|null
     */
    public function getParentId(): ?int;

    /**
     * Set owner company entity ID.
     *
     * @param int $companyId
     * @return $this
     */
    public function setParentId(int $companyId): self;

    /**
     * Is address active.
     *
     * @return bool
     */
    public function getIsActive(): bool;

    /**
     * Set address active flag.
     *
     * @param bool $isActive
     * @return $this
     */
    public function setIsActive(bool $isActive): self;

    /**
     * Get contact first name.
     *
     * @return string|null
     */
    public function getFirstname(): ?string;

    /**
     * Set contact first name.
     *
     * @param string $firstname
     * @return $this
     */
    public function setFirstname(string $firstname): self;

    /**
     * Get contact last name.
     *
     * @return string|null
     */
    public function getLastname(): ?string;

    /**
     * Set contact last name.
     *
     * @param string $lastname
     * @return $this
     */
    public function setLastname(string $lastname): self;

    /**
     * Get company name printed on the address.
     *
     * @return string|null
     */
    public function getCompany(): ?string;

    /**
     * Set company name printed on the address.
     *
     * @param string|null $company
     * @return $this
     */
    public function setCompany(?string $company): self;

    /**
     * Get street lines.
     *
     * @return string[]
     */
    public function getStreet(): array;

    /**
     * Set street lines.
     *
     * @param string[] $street
     * @return $this
     */
    public function setStreet(array $street): self;

    /**
     * Get city.
     *
     * @return string|null
     */
    public function getCity(): ?string;

    /**
     * Set city.
     *
     * @param string $city
     * @return $this
     */
    public function setCity(string $city): self;

    /**
     * Get country code (ISO 3166-1 alpha-2).
     *
     * @return string|null
     */
    public function getCountryId(): ?string;

    /**
     * Set country code (ISO 3166-1 alpha-2).
     *
     * @param string $countryId
     * @return $this
     */
    public function setCountryId(string $countryId): self;

    /**
     * Get region name.
     *
     * @return string|null
     */
    public function getRegion(): ?string;

    /**
     * Set region name.
     *
     * @param string|null $region
     * @return $this
     */
    public function setRegion(?string $region): self;

    /**
     * Get region ID.
     *
     * @return int|null
     */
    public function getRegionId(): ?int;

    /**
     * Set region ID.
     *
     * @param int|null $regionId
     * @return $this
     */
    public function setRegionId(?int $regionId): self;

    /**
     * Get postcode.
     *
     * @return string|null
     */
    public function getPostcode(): ?string;

    /**
     * Set postcode.
     *
     * @param string|null $postcode
     * @return $this
     */
    public function setPostcode(?string $postcode): self;

    /**
     * Get telephone.
     *
     * @return string|null
     */
    public function getTelephone(): ?string;

    /**
     * Set telephone.
     *
     * @param string $telephone
     * @return $this
     */
    public function setTelephone(string $telephone): self;

    /**
     * Get VAT number.
     *
     * @return string|null
     */
    public function getVatId(): ?string;

    /**
     * Set VAT number.
     *
     * @param string|null $vatId
     * @return $this
     */
    public function setVatId(?string $vatId): self;

    /**
     * Get extension attributes.
     *
     * @return \MageOS\Company\Api\Data\CompanyAddressExtensionInterface|null
     */
    public function getExtensionAttributes(): ?CompanyAddressExtensionInterface;

    /**
     * Set extension attributes.
     *
     * @param \MageOS\Company\Api\Data\CompanyAddressExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(CompanyAddressExtensionInterface $extensionAttributes): self;
}
