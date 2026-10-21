<?php

declare(strict_types=1);

namespace MageOS\Company\Model\ResourceModel;

use Magento\Eav\Model\ResourceModel\Attribute as EavAttributeResource;

/**
 * Company/company address EAV attribute resource model.
 */
class Attribute extends EavAttributeResource
{
    /**
     * @inheritDoc
     */
    protected function _getEavWebsiteTable()
    {
        return $this->getTable('company_eav_attribute_website');
    }

    /**
     * @inheritDoc
     */
    protected function _getFormAttributeTable()
    {
        return $this->getTable('company_form_attribute');
    }
}
