<?php

declare(strict_types=1);

namespace MageOS\Company\Ui\DataProvider\User;

use Magento\Framework\Api\Filter;
use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Api\Data\CompanyCustomerInterface;
use MageOS\Company\Model\ResourceModel\CompanyCustomer\Collection;
use MageOS\Company\Model\ResourceModel\CompanyCustomer\CollectionFactory;

/**
 * Users (assigned customers) of one company, listed in the company form (company_user_listing).
 */
class ListingDataProvider extends AbstractDataProvider
{
    /**
     * Listing field → SQL column.
     */
    private const COLUMNS = [
        'customer_id' => 'main_table.customer_id',
        'role' => 'main_table.role',
        'job_title' => 'main_table.job_title',
        'status' => 'main_table.status',
        'created_at' => 'main_table.created_at',
        'firstname' => 'ce.firstname',
        'lastname' => 'ce.lastname',
        'email' => 'ce.email',
    ];

    /**
     * @var Collection
     */
    protected $collection;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param RequestInterface $request
     * @param CompanyRepositoryInterface $companyRepository
     * @param array<string,mixed> $meta
     * @param array<string,mixed> $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly RequestInterface $request,
        private readonly CompanyRepositoryInterface $companyRepository,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
        $this->collection->getSelect()->join(
            ['ce' => $this->collection->getTable('customer_entity')],
            'ce.entity_id = main_table.customer_id',
            ['firstname', 'lastname', 'email']
        );
    }

    /**
     * @inheritDoc
     *
     * @return array{totalRecords: int, items: array<int,array<string,mixed>>}
     */
    public function getData()
    {
        $companyId = (int)$this->request->getParam('company_id');
        if (!$companyId) {
            return ['totalRecords' => 0, 'items' => []];
        }
        $superUserId = $this->companyRepository->getById($companyId)->getSuperUserId();
        $this->collection->addFieldToFilter(
            'main_table.' . CompanyCustomerInterface::COMPANY_ENTITY_ID,
            ['eq' => $companyId]
        );

        $items = [];
        foreach ($this->collection->getItems() as $relation) {
            $item = $relation->getData();
            $item['name'] = trim(($item['firstname'] ?? '') . ' ' . ($item['lastname'] ?? ''));
            $item['is_super_user'] = (int)$item['customer_id'] === $superUserId ? __('Yes') : '';
            $items[] = $item;
        }

        return [
            'totalRecords' => $this->collection->getSize(),
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
            if ($value === '') {
                return;
            }
            $this->collection->addFieldToFilter(
                ['ce.firstname', 'ce.lastname', 'ce.email', 'main_table.job_title'],
                array_fill(0, 4, ['like' => '%' . $value . '%'])
            );
            return;
        }
        if (isset(self::COLUMNS[$field])) {
            $this->collection->addFieldToFilter(
                self::COLUMNS[$field],
                [$filter->getConditionType() => $filter->getValue()]
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function addOrder($field, $direction)
    {
        if ($field === 'name') {
            $this->collection->setOrder('ce.firstname', $direction);
            $this->collection->setOrder('ce.lastname', $direction);
            return;
        }
        if (isset(self::COLUMNS[$field])) {
            $this->collection->setOrder(self::COLUMNS[$field], $direction);
        }
    }
}
