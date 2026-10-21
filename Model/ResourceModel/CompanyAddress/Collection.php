<?php

declare(strict_types=1);

namespace MageOS\Company\Model\ResourceModel\CompanyAddress;

use Magento\Eav\Model\Entity\Collection\VersionControl\AbstractCollection;
use MageOS\Company\Model\CompanyAddress;
use MageOS\Company\Model\ResourceModel\CompanyAddress as CompanyAddressResource;

/**
 * Company address EAV collection.
 */
class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(CompanyAddress::class, CompanyAddressResource::class);
    }
}
