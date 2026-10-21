<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Attribute;

use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use MageOS\Company\Model\Attribute;
use MageOS\Company\Model\AttributeFactory;
use MageOS\Company\Model\ResourceModel\Attribute as AttributeResource;

/**
 * Persistence of company and company address attributes, with their forms and grid flags.
 */
class AttributeRepository
{
    /**
     * @param AttributeFactory $attributeFactory
     * @param AttributeResource $attributeResource
     * @param EavConfig $eavConfig
     * @param FormCodes $formCodes
     */
    public function __construct(
        private readonly AttributeFactory $attributeFactory,
        private readonly AttributeResource $attributeResource,
        private readonly EavConfig $eavConfig,
        private readonly FormCodes $formCodes
    ) {
    }

    /**
     * Load a company or company address attribute.
     *
     * @param int $attributeId
     * @return Attribute
     * @throws NoSuchEntityException
     */
    public function getById(int $attributeId): Attribute
    {
        $attribute = $this->attributeFactory->create();
        $this->attributeResource->load($attribute, $attributeId);
        if (!$attribute->getId() || $this->getEntityTypeCode($attribute) === null) {
            throw new NoSuchEntityException(__('The attribute no longer exists.'));
        }

        return $attribute;
    }

    /**
     * New attribute of a managed entity type.
     *
     * @param string $entityTypeCode
     * @return Attribute
     * @throws LocalizedException
     */
    public function create(string $entityTypeCode): Attribute
    {
        if (!isset($this->formCodes->getEntityTypes()[$entityTypeCode])) {
            throw new LocalizedException(__('Select a valid entity type.'));
        }
        $entityType = $this->eavConfig->getEntityType($entityTypeCode);
        $attribute = $this->attributeFactory->create();
        $attribute->setEntityTypeId((int)$entityType->getId());
        $attribute->setEntityType($entityType);

        return $attribute;
    }

    /**
     * Code of the managed entity type of an attribute, null if it belongs to another entity.
     *
     * @param Attribute $attribute
     * @return string|null
     */
    public function getEntityTypeCode(Attribute $attribute): ?string
    {
        foreach (array_keys($this->formCodes->getEntityTypes()) as $code) {
            if ((int)$this->eavConfig->getEntityType($code)->getId() === (int)$attribute->getEntityTypeId()) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Whether the entity type already has an attribute with this code.
     *
     * @param string $entityTypeCode
     * @param string $attributeCode
     * @return bool
     */
    public function exists(string $entityTypeCode, string $attributeCode): bool
    {
        $attribute = $this->attributeFactory->create();
        $this->attributeResource->loadByCode(
            $attribute,
            (int)$this->eavConfig->getEntityType($entityTypeCode)->getId(),
            $attributeCode
        );

        return (bool)$attribute->getId();
    }

    /**
     * Save an attribute.
     *
     * @param Attribute $attribute
     * @return Attribute
     * @throws CouldNotSaveException
     */
    public function save(Attribute $attribute): Attribute
    {
        try {
            $this->attributeResource->save($attribute);
        } catch (LocalizedException $e) {
            throw new CouldNotSaveException(__($e->getMessage()), $e);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('The attribute could not be saved.'), $e);
        }

        return $attribute;
    }

    /**
     * Delete a custom attribute and its values.
     *
     * @param Attribute $attribute
     * @return void
     * @throws CouldNotDeleteException
     */
    public function delete(Attribute $attribute): void
    {
        if (!$attribute->getIsUserDefined()) {
            throw new CouldNotDeleteException(__('System attributes cannot be deleted.'));
        }
        try {
            $this->attributeResource->delete($attribute);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('The attribute could not be deleted.'), $e);
        }
    }
}
