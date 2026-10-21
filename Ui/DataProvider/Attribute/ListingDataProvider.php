<?php

declare(strict_types=1);

namespace MageOS\Company\Ui\DataProvider\Attribute;

use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Collection;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\CollectionFactory;
use Magento\Framework\Api\Filter;
use Magento\Framework\DB\Select;
use Magento\Ui\DataProvider\AbstractDataProvider;
use MageOS\Company\Model\Attribute\FormCodes;

/**
 * Data provider of the company attribute grid (company_attribute_listing).
 *
 * Lists the visible attributes of the company and company address entity types, plus every custom attribute.
 */
class ListingDataProvider extends AbstractDataProvider
{
    /**
     * Grid field → SQL column.
     */
    private const FIELD_MAP = [
        'attribute_id' => 'main_table.attribute_id',
        'attribute_code' => 'main_table.attribute_code',
        'frontend_label' => 'main_table.frontend_label',
        'frontend_input' => 'main_table.frontend_input',
        'is_required' => 'main_table.is_required',
        'is_user_defined' => 'main_table.is_user_defined',
        'entity_type_code' => 'et.entity_type_code',
        'sort_order' => 'ca.sort_order',
        'is_used_in_grid' => 'ca.is_used_in_grid',
    ];

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param EavConfig $eavConfig
     * @param FormCodes $formCodes
     * @param array<string,mixed> $meta
     * @param array<string,mixed> $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        EavConfig $eavConfig,
        FormCodes $formCodes,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $entityTypeIds = [];
        foreach (array_keys($formCodes->getEntityTypes()) as $code) {
            $entityTypeIds[] = (int)$eavConfig->getEntityType($code)->getId();
        }
        /** @var Collection $collection */
        $collection = $collectionFactory->create();
        $collection->getSelect()
            ->join(
                ['ca' => $collection->getTable('company_eav_attribute')],
                'ca.attribute_id = main_table.attribute_id',
                ['sort_order', 'is_used_in_grid', 'is_visible']
            )
            ->join(
                ['et' => $collection->getTable('eav_entity_type')],
                'et.entity_type_id = main_table.entity_type_id',
                ['entity_type_code']
            )
            ->where('main_table.entity_type_id IN (?)', $entityTypeIds)
            ->where('ca.is_visible = 1 OR main_table.is_user_defined = 1');
        foreach (self::FIELD_MAP as $field => $column) {
            $collection->addFilterToMap($field, $column);
        }
        $this->collection = $collection;
    }

    /**
     * @inheritDoc
     *
     * @return void
     */
    public function addFilter(Filter $filter)
    {
        $field = (string)$filter->getField();
        if (!isset(self::FIELD_MAP[$field])) {
            return;
        }
        $this->collection->addFieldToFilter(
            self::FIELD_MAP[$field],
            [$filter->getConditionType() => $filter->getValue()]
        );
    }

    /**
     * @inheritDoc
     */
    public function addOrder($field, $direction)
    {
        if (isset(self::FIELD_MAP[$field])) {
            $direction = strtoupper((string)$direction) === 'ASC' ? Select::SQL_ASC : Select::SQL_DESC;
            $this->collection->getSelect()->order(self::FIELD_MAP[$field] . ' ' . $direction);
        }
    }
}
