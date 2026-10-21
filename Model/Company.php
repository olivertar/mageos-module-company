<?php

declare(strict_types=1);

namespace MageOS\Company\Model;

use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use MageOS\Company\Api\Data\CompanyExtensionInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Model\ResourceModel\Company as CompanyResource;

/**
 * Company EAV model.
 */
class Company extends AbstractExtensibleModel implements CompanyInterface, IdentityInterface
{
    use EavCustomAttributesTrait;

    public const ENTITY = 'company';
    public const CACHE_TAG = 'company';

    /**
     * @var string
     */
    protected $_eventPrefix = 'company';

    /**
     * @var string
     */
    protected $_eventObject = 'company';

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param EavConfig $eavConfig
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        private EavConfig $eavConfig,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(CompanyResource::class);
    }

    /**
     * @inheritDoc
     */
    protected function getEavEntityTypeCode(): string
    {
        return self::ENTITY;
    }

    /**
     * @inheritDoc
     */
    public function beforeSave()
    {
        $erpCode = trim((string)$this->getData(self::COMPANY_ID));
        $this->setData(self::COMPANY_ID, $erpCode === '' ? null : $erpCode);
        $this->setData(self::EMAIL, trim((string)$this->getData(self::EMAIL)));
        if (!$this->getData('attribute_set_id')) {
            $this->setData(
                'attribute_set_id',
                $this->eavConfig->getEntityType(self::ENTITY)->getDefaultAttributeSetId()
            );
        }

        return parent::beforeSave();
    }

    /**
     * @inheritDoc
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * @inheritDoc
     */
    public function getId(): ?int
    {
        $id = parent::getId();

        return $id === null ? null : (int)$id;
    }

    /**
     * @inheritDoc
     */
    public function getName(): ?string
    {
        return $this->getData(self::NAME);
    }

    /**
     * @inheritDoc
     */
    public function setName(string $name): self
    {
        return $this->setData(self::NAME, $name);
    }

    /**
     * @inheritDoc
     */
    public function getCompanyId(): ?string
    {
        return $this->getData(self::COMPANY_ID);
    }

    /**
     * @inheritDoc
     */
    public function setCompanyId(?string $companyId): self
    {
        return $this->setData(self::COMPANY_ID, $companyId);
    }

    /**
     * @inheritDoc
     */
    public function getStatus(): int
    {
        return (int)$this->getData(self::STATUS);
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
    public function getEmail(): ?string
    {
        return $this->getData(self::EMAIL);
    }

    /**
     * @inheritDoc
     */
    public function setEmail(string $email): self
    {
        return $this->setData(self::EMAIL, $email);
    }

    /**
     * @inheritDoc
     */
    public function getWebsiteId(): ?int
    {
        return $this->getIntOrNull(self::WEBSITE_ID);
    }

    /**
     * @inheritDoc
     */
    public function setWebsiteId(int $websiteId): self
    {
        return $this->setData(self::WEBSITE_ID, $websiteId);
    }

    /**
     * @inheritDoc
     */
    public function getSuperUserId(): ?int
    {
        return $this->getIntOrNull(self::SUPER_USER_ID);
    }

    /**
     * @inheritDoc
     */
    public function setSuperUserId(?int $customerId): self
    {
        return $this->setData(self::SUPER_USER_ID, $customerId);
    }

    /**
     * @inheritDoc
     */
    public function getDefaultBilling(): ?int
    {
        return $this->getIntOrNull(self::DEFAULT_BILLING);
    }

    /**
     * @inheritDoc
     */
    public function setDefaultBilling(?int $addressId): self
    {
        return $this->setData(self::DEFAULT_BILLING, $addressId);
    }

    /**
     * @inheritDoc
     */
    public function getDefaultShipping(): ?int
    {
        return $this->getIntOrNull(self::DEFAULT_SHIPPING);
    }

    /**
     * @inheritDoc
     */
    public function setDefaultShipping(?int $addressId): self
    {
        return $this->setData(self::DEFAULT_SHIPPING, $addressId);
    }

    /**
     * @inheritDoc
     */
    public function getLegalAddress(): ?int
    {
        return $this->getIntOrNull(self::LEGAL_ADDRESS);
    }

    /**
     * @inheritDoc
     */
    public function setLegalAddress(?int $addressId): self
    {
        return $this->setData(self::LEGAL_ADDRESS, $addressId);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function getExtensionAttributes(): ?CompanyExtensionInterface
    {
        /** @var CompanyExtensionInterface|null $extensionAttributes */
        $extensionAttributes = $this->_getExtensionAttributes();

        return $extensionAttributes;
    }

    /**
     * @inheritDoc
     */
    public function setExtensionAttributes(CompanyExtensionInterface $extensionAttributes): self
    {
        return $this->_setExtensionAttributes($extensionAttributes);
    }

    /**
     * Read an integer field that may be empty.
     *
     * @param string $key
     * @return int|null
     */
    private function getIntOrNull(string $key): ?int
    {
        $value = $this->getData($key);

        return $value === null || $value === '' ? null : (int)$value;
    }
}
