<?php

declare(strict_types=1);

namespace MageOS\Company\Model;

use Magento\Directory\Model\RegionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use MageOS\Company\Api\Data\CompanyAddressExtensionInterface;
use MageOS\Company\Api\Data\CompanyAddressInterface;
use MageOS\Company\Model\ResourceModel\CompanyAddress as CompanyAddressResource;

/**
 * Company address EAV model.
 */
class CompanyAddress extends AbstractExtensibleModel implements CompanyAddressInterface
{
    use EavCustomAttributesTrait;

    public const ENTITY = 'company_address';

    /**
     * @var string
     */
    protected $_eventPrefix = 'company_address';

    /**
     * @var string
     */
    protected $_eventObject = 'company_address';

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param EavConfig $eavConfig
     * @param RegionFactory $regionFactory
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
        private RegionFactory $regionFactory,
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
        $this->_init(CompanyAddressResource::class);
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
        $this->setData(self::STREET, implode("\n", $this->getStreet()));
        $this->syncRegionName();
        if (!$this->getData('attribute_set_id')) {
            $this->setData(
                'attribute_set_id',
                $this->eavConfig->getEntityType(self::ENTITY)->getDefaultAttributeSetId()
            );
        }

        return parent::beforeSave();
    }

    /**
     * Store the region name of a directory region, as customer addresses do.
     *
     * @return void
     */
    private function syncRegionName(): void
    {
        $regionId = $this->getRegionId();
        if (!$regionId) {
            return;
        }
        $region = $this->regionFactory->create()->load($regionId);
        if ($region->getId() && $region->getCountryId() === $this->getCountryId()) {
            $this->setRegion((string)$region->getName());
        }
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
    public function getParentId(): ?int
    {
        $value = $this->getData(self::PARENT_ID);

        return $value === null ? null : (int)$value;
    }

    /**
     * @inheritDoc
     */
    public function setParentId(int $companyId): self
    {
        return $this->setData(self::PARENT_ID, $companyId);
    }

    /**
     * @inheritDoc
     */
    public function getIsActive(): bool
    {
        return (bool)($this->getData(self::IS_ACTIVE) ?? true);
    }

    /**
     * @inheritDoc
     */
    public function setIsActive(bool $isActive): self
    {
        return $this->setData(self::IS_ACTIVE, (int)$isActive);
    }

    /**
     * @inheritDoc
     */
    public function getFirstname(): ?string
    {
        return $this->getData(self::FIRSTNAME);
    }

    /**
     * @inheritDoc
     */
    public function setFirstname(string $firstname): self
    {
        return $this->setData(self::FIRSTNAME, $firstname);
    }

    /**
     * @inheritDoc
     */
    public function getLastname(): ?string
    {
        return $this->getData(self::LASTNAME);
    }

    /**
     * @inheritDoc
     */
    public function setLastname(string $lastname): self
    {
        return $this->setData(self::LASTNAME, $lastname);
    }

    /**
     * @inheritDoc
     */
    public function getCompany(): ?string
    {
        return $this->getData(self::COMPANY);
    }

    /**
     * @inheritDoc
     */
    public function setCompany(?string $company): self
    {
        return $this->setData(self::COMPANY, $company);
    }

    /**
     * @inheritDoc
     */
    public function getStreet(): array
    {
        $street = $this->getData(self::STREET);
        if (is_array($street)) {
            return $this->cleanStreetLines($street);
        }
        if ($street === null || $street === '') {
            return [];
        }

        return explode("\n", (string)$street);
    }

    /**
     * @inheritDoc
     */
    public function setStreet(array $street): self
    {
        return $this->setData(self::STREET, implode("\n", $this->cleanStreetLines($street)));
    }

    /**
     * Trim street lines and drop the empty ones.
     *
     * @param mixed[] $street
     * @return string[]
     */
    private function cleanStreetLines(array $street): array
    {
        $lines = array_map(static fn($line): string => trim((string)$line), $street);

        return array_values(array_filter($lines, static fn(string $line): bool => $line !== ''));
    }

    /**
     * @inheritDoc
     */
    public function getCity(): ?string
    {
        return $this->getData(self::CITY);
    }

    /**
     * @inheritDoc
     */
    public function setCity(string $city): self
    {
        return $this->setData(self::CITY, $city);
    }

    /**
     * @inheritDoc
     */
    public function getCountryId(): ?string
    {
        return $this->getData(self::COUNTRY_ID);
    }

    /**
     * @inheritDoc
     */
    public function setCountryId(string $countryId): self
    {
        return $this->setData(self::COUNTRY_ID, $countryId);
    }

    /**
     * @inheritDoc
     */
    public function getRegion(): ?string
    {
        return $this->getData(self::REGION);
    }

    /**
     * @inheritDoc
     */
    public function setRegion(?string $region): self
    {
        return $this->setData(self::REGION, $region);
    }

    /**
     * @inheritDoc
     */
    public function getRegionId(): ?int
    {
        $value = $this->getData(self::REGION_ID);

        return $value === null || $value === '' ? null : (int)$value;
    }

    /**
     * @inheritDoc
     */
    public function setRegionId(?int $regionId): self
    {
        return $this->setData(self::REGION_ID, $regionId);
    }

    /**
     * @inheritDoc
     */
    public function getPostcode(): ?string
    {
        return $this->getData(self::POSTCODE);
    }

    /**
     * @inheritDoc
     */
    public function setPostcode(?string $postcode): self
    {
        return $this->setData(self::POSTCODE, $postcode);
    }

    /**
     * @inheritDoc
     */
    public function getTelephone(): ?string
    {
        return $this->getData(self::TELEPHONE);
    }

    /**
     * @inheritDoc
     */
    public function setTelephone(string $telephone): self
    {
        return $this->setData(self::TELEPHONE, $telephone);
    }

    /**
     * @inheritDoc
     */
    public function getVatId(): ?string
    {
        return $this->getData(self::VAT_ID);
    }

    /**
     * @inheritDoc
     */
    public function setVatId(?string $vatId): self
    {
        return $this->setData(self::VAT_ID, $vatId);
    }

    /**
     * @inheritDoc
     */
    public function getExtensionAttributes(): ?CompanyAddressExtensionInterface
    {
        /** @var CompanyAddressExtensionInterface|null $extensionAttributes */
        $extensionAttributes = $this->_getExtensionAttributes();

        return $extensionAttributes;
    }

    /**
     * @inheritDoc
     */
    public function setExtensionAttributes(CompanyAddressExtensionInterface $extensionAttributes): self
    {
        return $this->_setExtensionAttributes($extensionAttributes);
    }
}
