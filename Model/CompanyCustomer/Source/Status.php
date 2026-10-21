<?php

declare(strict_types=1);

namespace MageOS\Company\Model\CompanyCustomer\Source;

use Magento\Framework\Data\OptionSourceInterface;
use MageOS\Company\Api\Data\CompanyCustomerInterface;

/**
 * Company user statuses.
 */
class Status implements OptionSourceInterface
{
    /**
     * @inheritDoc
     *
     * @return array<int, array{value: int, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray()
    {
        return [
            ['value' => CompanyCustomerInterface::STATUS_ACTIVE, 'label' => __('Active')],
            ['value' => CompanyCustomerInterface::STATUS_INACTIVE, 'label' => __('Inactive')],
        ];
    }
}
