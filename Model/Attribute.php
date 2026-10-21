<?php

declare(strict_types=1);

namespace MageOS\Company\Model;

use Magento\Eav\Model\Attribute as EavAttribute;
use MageOS\Company\Model\ResourceModel\Attribute as AttributeResource;

/**
 * EAV attribute model shared by the company and company_address entity types.
 */
class Attribute extends EavAttribute
{
    public const MODULE_NAME = 'MageOS_Company';

    /**
     * @var string
     */
    protected $_eventPrefix = 'company_entity_attribute';

    /**
     * @var string
     */
    protected $_eventObject = 'attribute';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(AttributeResource::class);
    }
}
