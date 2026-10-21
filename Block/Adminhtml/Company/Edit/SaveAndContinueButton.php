<?php

declare(strict_types=1);

namespace MageOS\Company\Block\Adminhtml\Company\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * "Save and Continue Edit" button of the company form.
 */
class SaveAndContinueButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @inheritDoc
     *
     * @return array<string,mixed>
     */
    public function getButtonData()
    {
        if (!$this->authorization->isAllowed('MageOS_Company::company_edit')) {
            return [];
        }

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
