<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Indexer;

use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\Indexer\FieldsetInterface;
use Magento\Framework\Indexer\Handler\AttributeHandler;
use MageOS\Company\Model\Company;

/**
 * Adds the company EAV attributes flagged "is_used_in_grid" to the company_grid index.
 *
 * Static attributes are declared in indexer.xml.
 */
class AttributeProvider implements FieldsetInterface
{
    /**
     * @param EavConfig $eavConfig
     */
    public function __construct(
        private readonly EavConfig $eavConfig
    ) {
    }

    /**
     * @inheritDoc
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function addDynamicData(array $data)
    {
        foreach ($this->eavConfig->getEntityAttributes(Company::ENTITY) as $attribute) {
            if ($attribute->isStatic() || !$attribute->getData('is_used_in_grid')) {
                continue;
            }
            $code = (string)$attribute->getAttributeCode();
            $data['fields'][$code] = [
                'name' => $code,
                'handler' => AttributeHandler::class,
                'origin' => $code,
                'type' => $this->getType($attribute),
                'dataType' => $attribute->getBackendType(),
                'filters' => [],
                'entity' => Company::ENTITY,
                'bind' => null,
            ];
        }

        return $data;
    }

    /**
     * Index type of a column: searchable columns go to the fulltext index, filterable ones get a B-tree index.
     *
     * TEXT columns cannot have a plain index, so they are only searchable or virtual.
     *
     * @param AbstractAttribute $attribute
     * @return string
     */
    private function getType(AbstractAttribute $attribute): string
    {
        if ($attribute->getData('is_searchable_in_grid')) {
            return 'searchable';
        }
        if ($attribute->getData('is_filterable_in_grid') && $attribute->getBackendType() !== 'text') {
            return 'filterable';
        }

        return 'virtual';
    }
}
