<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Indexer;

use Magento\Framework\App\ResourceConnection\SourceProviderInterface;
use MageOS\Company\Model\ResourceModel\Company\Indexer\Collection;
use MageOS\Company\Model\ResourceModel\Company\Indexer\CollectionFactory;

/**
 * Companies in batches, for the company_grid indexer.
 *
 * @implements \IteratorAggregate<mixed,mixed>
 */
class Source implements \IteratorAggregate, \Countable, SourceProviderInterface
{
    /**
     * @var Collection
     */
    private Collection $collection;

    /**
     * @param CollectionFactory $collectionFactory
     * @param int $batchSize
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        private readonly int $batchSize = 10000
    ) {
        $this->collection = $collectionFactory->create();
    }

    /**
     * @inheritDoc
     */
    public function getMainTable()
    {
        return $this->collection->getMainTable();
    }

    /**
     * @inheritDoc
     */
    public function getIdFieldName()
    {
        return $this->collection->getIdFieldName();
    }

    /**
     * @inheritDoc
     */
    public function addFieldToSelect($fieldName, $alias = null)
    {
        $this->collection->addFieldToSelect($fieldName, $alias);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getSelect()
    {
        return $this->collection->getSelect();
    }

    /**
     * @inheritDoc
     */
    public function addFieldToFilter($attribute, $condition = null)
    {
        $this->collection->addFieldToFilter($attribute, $condition);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return $this->collection->getSize();
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): \Traversable
    {
        $this->collection->setPageSize($this->batchSize);
        $lastPage = $this->collection->getLastPageNumber();
        $pageNumber = 1;
        do {
            $this->collection->clear();
            $this->collection->setCurPage($pageNumber);
            foreach ($this->collection->getItems() as $key => $value) {
                yield $key => $value;
            }
            $pageNumber++;
        } while ($pageNumber <= $lastPage);
    }
}
