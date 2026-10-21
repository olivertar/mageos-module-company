<?php

declare(strict_types=1);

namespace MageOS\Company\Model;

use Magento\Framework\Model\AbstractModel;
use MageOS\Company\Api\Data\CompanyCustomerInterface;
use MageOS\Company\Model\ResourceModel\CompanyCustomer as CompanyCustomerResource;

/**
 * Customer ↔ company relation.
 */
class CompanyCustomer extends AbstractModel implements CompanyCustomerInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'company_customer';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(CompanyCustomerResource::class);
    }

    /**
     * @inheritDoc
     */
    public function getCustomerId(): ?int
    {
        $value = $this->getData(self::CUSTOMER_ID);

        return $value === null ? null : (int)$value;
    }

    /**
     * @inheritDoc
     */
    public function setCustomerId(int $customerId): self
    {
        return $this->setData(self::CUSTOMER_ID, $customerId);
    }

    /**
     * @inheritDoc
     */
    public function getCompanyEntityId(): ?int
    {
        $value = $this->getData(self::COMPANY_ENTITY_ID);

        return $value === null ? null : (int)$value;
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
    public function getRole(): string
    {
        return (string)($this->getData(self::ROLE) ?? self::ROLE_USER);
    }

    /**
     * @inheritDoc
     */
    public function setRole(string $role): self
    {
        return $this->setData(self::ROLE, $role);
    }

    /**
     * @inheritDoc
     */
    public function getJobTitle(): ?string
    {
        return $this->getData(self::JOB_TITLE);
    }

    /**
     * @inheritDoc
     */
    public function setJobTitle(?string $jobTitle): self
    {
        return $this->setData(self::JOB_TITLE, $jobTitle);
    }

    /**
     * @inheritDoc
     */
    public function getStatus(): int
    {
        return (int)($this->getData(self::STATUS) ?? self::STATUS_ACTIVE);
    }

    /**
     * @inheritDoc
     */
    public function setStatus(int $status): self
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }
}
