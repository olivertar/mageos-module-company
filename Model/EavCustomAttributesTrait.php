<?php

declare(strict_types=1);

namespace MageOS\Company\Model;

/**
 * Exposes non-static EAV attributes as custom attributes and keeps both representations in sync.
 *
 * The EAV resource model reads and writes flat values ($model->getData('phone')), while service
 * contracts use custom attributes ($model->getCustomAttribute('phone')). Whatever is written last wins.
 *
 * Requires the using class to extend AbstractExtensibleModel and to have an $eavConfig property.
 */
trait EavCustomAttributesTrait
{
    /**
     * Get the EAV entity type code of the model.
     *
     * @return string
     */
    abstract protected function getEavEntityTypeCode(): string;

    /**
     * Get all EAV attributes of the entity, keyed by code (used by the EAV version control snapshot).
     *
     * @return \Magento\Eav\Model\Entity\Attribute\AbstractAttribute[]
     */
    public function getAttributes(): array
    {
        /** @var \Magento\Eav\Model\Entity\AbstractEntity $resource */
        $resource = $this->_getResource();

        return $resource->loadAllAttributes($this)->getSortedAttributes();
    }

    /**
     * @inheritDoc
     */
    protected function getCustomAttributesCodes()
    {
        if ($this->customAttributesCodes === null) {
            $codes = [];
            foreach ($this->eavConfig->getEntityAttributes($this->getEavEntityTypeCode()) as $code => $attribute) {
                if (!$attribute->isStatic()) {
                    $codes[] = (string)$code;
                }
            }
            $this->customAttributesCodes = $codes;
        }

        return $this->customAttributesCodes;
    }

    /**
     * @inheritDoc
     */
    public function setCustomAttribute($attributeCode, $attributeValue)
    {
        parent::setCustomAttribute($attributeCode, $attributeValue);
        if (in_array($attributeCode, $this->getCustomAttributesCodes(), true)) {
            $this->_data[$attributeCode] = $attributeValue;
        }

        return $this;
    }

    /**
     * @inheritDoc
     *
     * @param string|array<string,mixed> $key
     * @param mixed $value
     * @return $this
     */
    public function setData($key, $value = null)
    {
        parent::setData($key, $value);
        $this->syncCustomAttributes(is_array($key) ? $key : [$key => $value]);

        return $this;
    }

    /**
     * Propagate written values between flat data and custom attributes.
     *
     * @param array<string,mixed> $data
     * @return void
     */
    private function syncCustomAttributes(array $data): void
    {
        $codes = $this->getCustomAttributesCodes();
        foreach (array_keys($data) as $key) {
            if ($key === self::CUSTOM_ATTRIBUTES) {
                foreach ((array)($this->_data[self::CUSTOM_ATTRIBUTES] ?? []) as $code => $attribute) {
                    $this->_data[$code] = $attribute->getValue();
                }
            } elseif (in_array($key, $codes, true)) {
                unset($this->_data[self::CUSTOM_ATTRIBUTES][$key]);
            }
        }
    }
}
