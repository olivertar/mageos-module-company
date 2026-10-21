<?php

declare(strict_types=1);

namespace MageOS\Company\Model;

use Magento\Eav\Model\Form as EavForm;

/**
 * Extracts, validates and applies posted data according to the attributes of a company form code.
 *
 * Defaults to the company entity type; call setEntityType(CompanyAddress::ENTITY) for address forms.
 */
class Form extends EavForm
{
    /**
     * @var string
     */
    protected $_moduleName = Attribute::MODULE_NAME;

    /**
     * @var string
     */
    protected $_entityTypeCode = Company::ENTITY;
}
