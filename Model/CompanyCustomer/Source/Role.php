<?php

declare(strict_types=1);

namespace MageOS\Company\Model\CompanyCustomer\Source;

use Magento\Framework\Data\OptionSourceInterface;
use MageOS\Company\Api\Data\CompanyCustomerInterface;

/**
 * Company user roles.
 */
class Role implements OptionSourceInterface
{
    /**
     * @inheritDoc
     *
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray()
    {
        return [
            ['value' => CompanyCustomerInterface::ROLE_ADMIN, 'label' => __('Company Admin')],
            ['value' => CompanyCustomerInterface::ROLE_USER, 'label' => __('Company User')],
        ];
    }
}
