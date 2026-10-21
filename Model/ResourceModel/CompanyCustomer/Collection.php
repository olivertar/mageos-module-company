<?php

declare(strict_types=1);

namespace MageOS\Company\Model\ResourceModel\CompanyCustomer;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use MageOS\Company\Model\CompanyCustomer;
use MageOS\Company\Model\ResourceModel\CompanyCustomer as CompanyCustomerResource;

/**
 * Customer ↔ company relation collection.
 */
class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(CompanyCustomer::class, CompanyCustomerResource::class);
    }
}
