<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Attribute\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * System (installed by the module) or custom (created by the merchant) attribute; maps is_user_defined.
 */
class Origin implements OptionSourceInterface
{
    /**
     * @inheritDoc
     *
     * @return array<int,array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray()
    {
        return [
            ['value' => '0', 'label' => __('System')],
            ['value' => '1', 'label' => __('Custom')],
        ];
    }
}
