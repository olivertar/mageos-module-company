<?php

declare(strict_types=1);

namespace MageOS\Company\Model\ResourceModel\Company\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

/**
 * Admin company grid rows, read from the company_grid_flat index.
 *
 * Super user and counters are joined at query time: they change with other entities (customers, messages).
 * Main table, resource model and ID field are set in di.xml.
 */
class Collection extends SearchResult
{
    /**
     * Columns computed at query time: not filterable or sortable.
     */
    public const JOINED_FIELDS = ['super_user_name', 'super_user_email', 'users_count', 'unread_messages'];

    /**
     * @var array<string,array<string,string>>
     */
    protected $_map = ['fields' => ['entity_id' => 'main_table.entity_id']];

    /**
     * @var string[]|null
     */
    private ?array $gridColumns = null;

    /**
     * Whether a field is a column of the index table: only those can be filtered or sorted from the grid.
     *
     * @param string $field
     * @return bool
     */
    public function isGridColumn(string $field): bool
    {
        if ($this->gridColumns === null) {
            $this->gridColumns = array_keys($this->getConnection()->describeTable($this->getMainTable()));
        }

        return in_array($field, $this->gridColumns, true);
    }

    /**
     * @inheritDoc
     */
    protected function _initSelect()
    {
        parent::_initSelect();
        $connection = $this->getConnection();
        $usersCount = $connection->select()
            ->from(['cc' => $this->getTable('company_customer')], ['COUNT(*)'])
            ->where('cc.company_entity_id = main_table.entity_id');
        $unreadMessages = $connection->select()
            ->from(['msg' => $this->getTable('company_message')], ['COUNT(*)'])
            ->where('msg.company_entity_id = main_table.entity_id')
            ->where('msg.read_at_admin IS NULL');
        $this->getSelect()
            ->joinLeft(
                ['su' => $this->getTable('customer_entity')],
                'su.entity_id = main_table.super_user_id',
                [
                    'super_user_name' => $connection->getConcatSql(['su.firstname', 'su.lastname'], ' '),
                    'super_user_email' => 'su.email',
                ]
            )
            ->columns([
                'users_count' => new \Zend_Db_Expr('(' . $usersCount . ')'),
                'unread_messages' => new \Zend_Db_Expr('(' . $unreadMessages . ')'),
            ]);
    }

    /**
     * @inheritDoc
     *
     * @param string|array<int,string> $field
     * @param string|int|array<string,mixed>|null $condition
     * @return $this
     */
    public function addFieldToFilter($field, $condition = null)
    {
        if (is_string($field) && !str_contains($field, '.')) {
            $field = 'main_table.' . $field;
        }

        return parent::addFieldToFilter($field, $condition);
    }

    /**
     * Keyword search: substring match on the columns of the fulltext index.
     *
     * @param string $value
     * @return $this
     */
    public function addKeywordFilter(string $value): self
    {
        $conditions = [];
        foreach ($this->getFulltextIndexColumns() as $column) {
            $conditions[] = $this->_getConditionSql(
                $this->getConnection()->quoteIdentifier('main_table.' . $column),
                ['like' => '%' . $value . '%']
            );
        }
        if ($conditions) {
            $this->getSelect()->where(implode(' OR ', $conditions));
        }

        return $this;
    }

    /**
     * Columns of the fulltext index (attributes flagged "searchable in grid").
     *
     * @return string[]
     */
    private function getFulltextIndexColumns(): array
    {
        foreach ($this->getConnection()->getIndexList($this->getMainTable()) as $index) {
            if (strtoupper((string)$index['INDEX_TYPE']) === 'FULLTEXT') {
                return $index['COLUMNS_LIST'];
            }
        }

        return [];
    }
}
