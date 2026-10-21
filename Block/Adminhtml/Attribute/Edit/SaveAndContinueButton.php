<?php

declare(strict_types=1);

namespace MageOS\Company\Block\Adminhtml\Attribute\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * "Save and Continue Edit" button.
 */
class SaveAndContinueButton implements ButtonProviderInterface
{
    /**
     * @inheritDoc
     *
     * @return array<string,mixed>
     */
    public function getButtonData()
    {
        return [
            'label' => __('Save and Continue Edit'),
            'class' => 'save',
            // empty handler: otherwise the UI button renders onclick="location.href = getUrl('')" (route/index/index)
            'on_click' => '',
            'data_attribute' => [
                'mage-init' => ['button' => ['event' => 'saveAndContinueEdit']],
            ],
            'sort_order' => 80,
        ];
    }
}
