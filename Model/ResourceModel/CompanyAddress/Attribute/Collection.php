<?php

declare(strict_types=1);

namespace MageOS\Company\Model\ResourceModel\CompanyAddress\Attribute;

use MageOS\Company\Model\CompanyAddress;
use MageOS\Company\Model\ResourceModel\Attribute\Collection as CompanyAttributeCollection;

/**
 * Company address EAV attribute collection.
 */
class Collection extends CompanyAttributeCollection
{
    /**
     * @var string
     */
    protected $_entityTypeCode = CompanyAddress::ENTITY;
}
