<?php

declare(strict_types=1);

namespace MageOS\Company\Block\Adminhtml\Company\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * "Back" button of the company form.
 */
class BackButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @inheritDoc
     *
     * @return array<string,mixed>
     */
    public function getButtonData()
    {
        return [
            'label' => __('Back'),
            'on_click' => sprintf("location.href = '%s';", $this->getUrl('*/*/')),
            'class' => 'back',
            'sort_order' => 10,
        ];
    }
}
