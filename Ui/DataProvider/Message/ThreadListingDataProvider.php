<?php

declare(strict_types=1);

namespace MageOS\Company\Ui\DataProvider\Message;

use Magento\Framework\Api\Filter;
use Magento\Framework\DB\Select;
use Magento\Ui\DataProvider\AbstractDataProvider;
use MageOS\Company\Api\Data\MessageInterface;
use MageOS\Company\Model\ResourceModel\Company\Collection;
use MageOS\Company\Model\ResourceModel\Company\CollectionFactory;

/**
 * Data provider of the company conversations grid (company_message_listing): one row per company with messages.
 */
class ThreadListingDataProvider extends AbstractDataProvider
{
    /**
     * Columns computed per company from company_message.
     */
    private const AGGREGATES = ['messages_count', 'unread_count', 'last_message_at', 'last_message'];

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param array<string,mixed> $meta
     * @param array<string,mixed> $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $collection = $collectionFactory->create();
        $this->addAggregates($collection);
        // @phpstan-ignore-next-line The parent property is typed as a flat collection; this one is EAV.
        $this->collection = $collection;
    }

    /**
     * The company EAV collection.
     *
     * @return Collection
     */
    private function getEavCollection(): Collection
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
        foreach ($this->getEavCollection()->getItems() as $item) {
            $data = $item->getData();
            $data['last_message'] = mb_strimwidth((string)($data['last_message'] ?? ''), 0, 120, '…');
            $items[] = $data;
        }

        return [
            'totalRecords' => $this->getEavCollection()->getSize(),
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
        $value = $filter->getValue();
        if ($field === 'unread_count') {
            if ((string)$value !== '') {
                $condition = '(' . $this->getMessageSelect('COUNT(*)', true) . ')';
                $this->getEavCollection()->getSelect()->where($value ? "$condition > 0" : "$condition = 0");
            }
            return;
        }
        if ($field === 'fulltext') {
            $value = trim((string)$value);
            if ($value !== '') {
                $this->getEavCollection()->addAttributeToFilter([
                    ['attribute' => 'name', 'like' => '%' . $value . '%'],
                    ['attribute' => 'email', 'like' => '%' . $value . '%'],
                ]);
            }
            return;
        }
        if (in_array($field, self::AGGREGATES, true)) {
            return;
        }
        $this->getEavCollection()->addAttributeToFilter($field, [$filter->getConditionType() => $value]);
    }

    /**
     * @inheritDoc
     */
    public function addOrder($field, $direction)
    {
        $direction = strtoupper((string)$direction) === 'ASC' ? Select::SQL_ASC : Select::SQL_DESC;
        if (in_array($field, self::AGGREGATES, true)) {
            $this->getEavCollection()->getSelect()->order($field . ' ' . $direction);
            return;
        }
        $this->getEavCollection()->addAttributeToSort($field, $direction);
    }

    /**
     * Restrict to companies with messages and add the per-company message columns.
     *
     * @param Collection $collection
     * @return void
     */
    private function addAggregates(Collection $collection): void
    {
        $collection->getSelect()
            ->where('EXISTS (' . $this->getMessageSelect('1', false, $collection) . ')')
            ->columns([
                'messages_count' => new \Zend_Db_Expr(
                    '(' . $this->getMessageSelect('COUNT(*)', false, $collection) . ')'
                ),
                'unread_count' => new \Zend_Db_Expr(
                    '(' . $this->getMessageSelect('COUNT(*)', true, $collection) . ')'
                ),
                'last_message_at' => new \Zend_Db_Expr(
                    '(' . $this->getMessageSelect('MAX(msg.created_at)', false, $collection) . ')'
                ),
                'last_message' => new \Zend_Db_Expr('(' . $this->getMessageSelect('msg.body', false, $collection)
                    ->order(['msg.created_at DESC', 'msg.message_id DESC'])
                    ->limit(1) . ')'),
            ]);
    }

    /**
     * Correlated select on the messages of the current company row.
     *
     * @param string $column
     * @param bool $unreadOnly
     * @param Collection|null $collection
     * @return Select
     */
    private function getMessageSelect(string $column, bool $unreadOnly, ?Collection $collection = null): Select
    {
        $collection = $collection ?? $this->getEavCollection();
        $select = $collection->getConnection()->select()
            ->from(['msg' => $collection->getTable('company_message')], [new \Zend_Db_Expr($column)])
            ->where('msg.' . MessageInterface::COMPANY_ENTITY_ID . ' = e.entity_id');
        if ($unreadOnly) {
            $select->where('msg.' . MessageInterface::READ_AT_ADMIN . ' IS NULL');
        }

        return $select;
    }
}
