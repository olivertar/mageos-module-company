<?php

declare(strict_types=1);

namespace MageOS\Company\Model\ResourceModel\Attribute;

use Magento\Eav\Model\ResourceModel\Attribute\Collection as EavAttributeCollection;
use MageOS\Company\Model\Company;

/**
 * Company EAV attribute collection.
 */
class Collection extends EavAttributeCollection
{
    /**
     * @var string
     */
    protected $_entityTypeCode = Company::ENTITY;

    /**
     * @inheritDoc
     */
    protected function _getEntityTypeCode()
    {
        return $this->_entityTypeCode;
    }

    /**
     * @inheritDoc
     */
    protected function _getEavWebsiteTable()
    {
        return $this->getTable('company_eav_attribute_website');
    }
}
