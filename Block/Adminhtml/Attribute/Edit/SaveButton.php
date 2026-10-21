<?php

declare(strict_types=1);

namespace MageOS\Company\Block\Adminhtml\Attribute\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * "Save Attribute" button.
 */
class SaveButton implements ButtonProviderInterface
{
    /**
     * @inheritDoc
     *
     * @return array<string,mixed>
     */
    public function getButtonData()
    {
        return [
            'label' => __('Save Attribute'),
            'class' => 'save primary',
            // empty handler: otherwise the UI button renders onclick="location.href = getUrl('')" (route/index/index)
            'on_click' => '',
            'data_attribute' => [
                'mage-init' => ['button' => ['event' => 'save']],
                'form-role' => 'save',
            ],
            'sort_order' => 90,
        ];
    }
}
