<?php

declare(strict_types=1);

namespace MageOS\Company\Ui\DataProvider\Address;

use Magento\Directory\Model\CountryFactory;
use Magento\Framework\Api\Filter;
use Magento\Framework\DB\Select;
use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Model\ResourceModel\CompanyAddress\Collection;
use MageOS\Company\Model\ResourceModel\CompanyAddress\CollectionFactory;

/**
 * Addresses of one company, listed in the company form (company_address_listing).
 */
class ListingDataProvider extends AbstractDataProvider
{
    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param RequestInterface $request
     * @param CompanyRepositoryInterface $companyRepository
     * @param CountryFactory $countryFactory
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
        private readonly CountryFactory $countryFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $collection = $collectionFactory->create();
        $collection->addAttributeToSelect('*');
        // @phpstan-ignore-next-line The parent property is typed as a flat collection; this one is EAV.
        $this->collection = $collection;
    }

    /**
     * The company address EAV collection.
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
        $companyId = (int)$this->request->getParam('company_id');
        if (!$companyId) {
            return ['totalRecords' => 0, 'items' => []];
        }
        $company = $this->companyRepository->getById($companyId);
        $defaults = [
            'billing' => $company->getDefaultBilling(),
            'shipping' => $company->getDefaultShipping(),
            'legal' => $company->getLegalAddress(),
        ];
        $this->getEavCollection()->addAttributeToFilter('parent_id', ['eq' => $companyId]);

        $items = [];
        $countryNames = [];
        foreach ($this->getEavCollection()->getItems() as $address) {
            $item = $address->getData();
            $addressId = (int)$address->getId();
            $item['street'] = implode(', ', array_filter(explode("\n", (string)($item['street'] ?? ''))));
            $countryId = (string)($item['country_id'] ?? '');
            $countryNames[$countryId] ??= $countryId
                ? (string)$this->countryFactory->create()->loadByCode($countryId)->getName()
                : '';
            $item['country'] = $countryNames[$countryId];
            $item['defaults'] = implode(', ', array_keys(array_filter(
                [
                    (string)__('Billing') => $defaults['billing'] === $addressId,
                    (string)__('Shipping') => $defaults['shipping'] === $addressId,
                    (string)__('Legal') => $defaults['legal'] === $addressId,
                ]
            )));
            $items[] = $item;
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
        if ($field === 'fulltext') {
            $value = trim((string)$filter->getValue());
            if ($value === '') {
                return;
            }
            $conditions = [];
            foreach (['firstname', 'lastname', 'company', 'street', 'city', 'postcode', 'telephone'] as $code) {
                $conditions[] = ['attribute' => $code, 'like' => '%' . $value . '%'];
            }
            $this->getEavCollection()->addAttributeToFilter($conditions);
            return;
        }
        if (in_array($field, ['defaults', 'country'], true)) {
            return;
        }
        $this->getEavCollection()->addAttributeToFilter(
            $field,
            [$filter->getConditionType() => $filter->getValue()],
            'left'
        );
    }

    /**
     * @inheritDoc
     */
    public function addOrder($field, $direction)
    {
        if (in_array($field, ['defaults', 'country'], true)) {
            return;
        }
        $this->getEavCollection()->addAttributeToSort(
            $field,
            strtoupper((string)$direction) === 'ASC' ? Select::SQL_ASC : Select::SQL_DESC
        );
    }
}
