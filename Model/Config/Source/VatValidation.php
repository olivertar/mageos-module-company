<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use MageOS\Company\Model\Config;

/**
 * VAT number validation modes.
 */
class VatValidation implements OptionSourceInterface
{
    /**
     * @inheritDoc
     *
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray()
    {
        return [
            ['value' => Config::VAT_VALIDATION_NONE, 'label' => __('None')],
            ['value' => Config::VAT_VALIDATION_FORMAT, 'label' => __('Format Only')],
        ];
    }
}
