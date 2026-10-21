<?php

declare(strict_types=1);

namespace MageOS\Company\Ui\DataProvider\Company;

use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Api\Filter;
use Magento\Ui\DataProvider\AbstractDataProvider;
use MageOS\Company\Model\Company;
use MageOS\Company\Model\ResourceModel\Company\Grid\Collection;
use MageOS\Company\Model\ResourceModel\Company\Grid\CollectionFactory;

/**
 * Data provider of the admin company grid (company_listing), backed by the company_grid_flat index.
 */
class ListingDataProvider extends AbstractDataProvider
{
    /**
     * @var string[]|null
     */
    private ?array $multiselectCodes = null;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param EavConfig $eavConfig
     * @param array<string,mixed> $meta
     * @param array<string,mixed> $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly EavConfig $eavConfig,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    /**
     * The grid collection.
     *
     * @return Collection
     */
    private function getGridCollection(): Collection
    {
        /** @var Collection $collection */
        $collection = $this->collection;

        return $collection;
    }

    /**
     * @inheritDoc
     *
     * @return array{totalRecords: int, items: array<int,array<string,mixed>>}
     */
    public function getData()
    {
        $items = [];
        foreach ($this->getGridCollection()->getItems() as $item) {
            $items[] = $item->getData();
        }

        return [
            'totalRecords' => $this->getGridCollection()->getSize(),
            'items' => $items,
        ];
    }

    /**
     * @inheritDoc
     *
     * @return void
     */
    public function addFilter(Filter $filter)
    {
        $field = (string)$filter->getField();
        if ($field === 'fulltext') {
            $value = trim((string)$filter->getValue());
            if ($value !== '') {
                $this->getGridCollection()->addKeywordFilter($value);
            }
            return;
        }
        if (!$this->getGridCollection()->isGridColumn($field)) {
            return;
        }
        $condition = $filter->getConditionType() ?: 'eq';
        if ($condition === 'eq' && in_array($field, $this->getMultiselectCodes(), true)) {
            $condition = 'finset';
        }
        $this->getGridCollection()->addFieldToFilter($field, [$condition => $filter->getValue()]);
    }

    /**
     * @inheritDoc
     */
    public function addOrder($field, $direction)
    {
        // the collection renders orders as raw SQL expressions: only known columns
        if (!$this->getGridCollection()->isGridColumn((string)$field)) {
            return;
        }
        $this->getGridCollection()->setOrder('main_table.' . $field, $direction);
    }

    /**
     * Codes of multiple select attributes: their values are comma separated option IDs.
     *
     * @return string[]
     */
    private function getMultiselectCodes(): array
    {
        if ($this->multiselectCodes === null) {
            $this->multiselectCodes = [];
            foreach ($this->eavConfig->getEntityAttributes(Company::ENTITY) as $attribute) {
                if ($attribute->getFrontendInput() === 'multiselect') {
                    $this->multiselectCodes[] = (string)$attribute->getAttributeCode();
                }
            }
        }

        return $this->multiselectCodes;
    }
}
