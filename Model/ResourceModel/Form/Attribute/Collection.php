<?php

declare(strict_types=1);

namespace MageOS\Company\Model\ResourceModel\Form\Attribute;

use Magento\Eav\Model\ResourceModel\Form\Attribute\Collection as EavFormAttributeCollection;
use MageOS\Company\Model\Attribute;
use MageOS\Company\Model\Company;
use MageOS\Company\Model\ResourceModel\Form\Attribute as FormAttributeResource;

/**
 * Attributes used in a company or company address form.
 *
 * Defaults to the company entity type; call setEntityType() for company_address.
 */
class Collection extends EavFormAttributeCollection
{
    /**
     * @var string
     */
    protected $_moduleName = Attribute::MODULE_NAME;

    /**
     * @var string
     */
    protected $_entityTypeCode = Company::ENTITY;

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_init(Attribute::class, FormAttributeResource::class);
    }

    /**
     * @inheritDoc
     */
    protected function _getEavWebsiteTable()
    {
        return $this->getTable('company_eav_attribute_website');
    }
}
