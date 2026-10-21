<?php

declare(strict_types=1);

namespace MageOS\Company\Model\ResourceModel\Company;

use Magento\Eav\Model\Entity\Collection\VersionControl\AbstractCollection;
use MageOS\Company\Model\Company;
use MageOS\Company\Model\ResourceModel\Company as CompanyResource;

/**
 * Company EAV collection.
 */
class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(Company::class, CompanyResource::class);
    }
}
