<?php

declare(strict_types=1);

namespace MageOS\Company\Test\Unit\Ui\DataProvider\Company;

use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Api\Filter;
use MageOS\Company\Model\ResourceModel\Company\Grid\Collection;
use MageOS\Company\Model\ResourceModel\Company\Grid\CollectionFactory;
use MageOS\Company\Ui\DataProvider\Company\ListingDataProvider;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The grid collection renders orders as raw SQL: only columns of the index table may reach it.
 */
#[AllowMockObjectsWithoutExpectations]
class ListingDataProviderTest extends TestCase
{
    /**
     * @var Collection&MockObject
     */
    private Collection $collection;

    /**
     * @var ListingDataProvider
     */
    private ListingDataProvider $provider;

    protected function setUp(): void
    {
        $this->collection = $this->createMock(Collection::class);
        $this->collection->method('isGridColumn')
            ->willReturnCallback(fn (string $field) => in_array($field, ['entity_id', 'name', 'status'], true));
        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($this->collection);
        $eavConfig = $this->createMock(EavConfig::class);
        $eavConfig->method('getEntityAttributes')->willReturn([]);
        $this->provider = new ListingDataProvider('company_listing', 'entity_id', 'id', $factory, $eavConfig);
    }

    public function testOrdersByGridColumn(): void
    {
        $this->collection->expects($this->once())->method('setOrder')->with('main_table.name', 'DESC');

        $this->provider->addOrder('name', 'DESC');
    }

    public function testIgnoresOrderByUnknownField(): void
    {
        $this->collection->expects($this->never())->method('setOrder');

        $this->provider->addOrder('entity_id, (SELECT SLEEP(5))', 'ASC');
        $this->provider->addOrder('super_user_name', 'ASC');
    }

    public function testIgnoresFilterOnUnknownField(): void
    {
        $this->collection->expects($this->never())->method('addFieldToFilter');

        $this->provider->addFilter($this->filter('su.password_hash', '%a%', 'like'));
        $this->provider->addFilter($this->filter('users_count', '1', 'eq'));
    }

    public function testFiltersByGridColumn(): void
    {
        $this->collection->expects($this->once())->method('addFieldToFilter')->with('status', ['eq' => '1']);

        $this->provider->addFilter($this->filter('status', '1', 'eq'));
    }

    public function testFulltextUsesKeywordFilter(): void
    {
        $this->collection->expects($this->once())->method('addKeywordFilter')->with('acme');
        $this->collection->expects($this->never())->method('addFieldToFilter');

        $this->provider->addFilter($this->filter('fulltext', ' acme ', 'fulltext'));
    }

    /**
     * @param string $field
     * @param string $value
     * @param string $condition
     * @return Filter
     */
    private function filter(string $field, string $value, string $condition): Filter
    {
        return (new Filter())->setField($field)->setValue($value)->setConditionType($condition);
    }
}
