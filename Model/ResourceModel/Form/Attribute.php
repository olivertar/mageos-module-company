<?php

declare(strict_types=1);

namespace MageOS\Company\Model\ResourceModel\Form;

use Magento\Eav\Model\ResourceModel\Form\Attribute as EavFormAttribute;

/**
 * Attribute ↔ form relation resource model (company_form_attribute).
 */
class Attribute extends EavFormAttribute
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('company_form_attribute', 'attribute_id');
    }
}
