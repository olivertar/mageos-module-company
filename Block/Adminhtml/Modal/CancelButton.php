<?php

declare(strict_types=1);

namespace MageOS\Company\Block\Adminhtml\Modal;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * "Cancel" button of a modal form inserted in the company form.
 */
class CancelButton implements ButtonProviderInterface
{
    /**
     * @param string $modalName Full UI name of the modal to close.
     */
    public function __construct(
        private readonly string $modalName
    ) {
    }

    /**
     * @inheritDoc
     *
     * @return array<string,mixed>
     */
    public function getButtonData()
    {
        return [
            'label' => __('Cancel'),
            'on_click' => '',
            'data_attribute' => [
                'mage-init' => [
                    'Magento_Ui/js/form/button-adapter' => [
                        'actions' => [
                            ['targetName' => $this->modalName, 'actionName' => 'closeModal'],
                        ],
                    ],
                ],
            ],
            'sort_order' => 30,
        ];
    }
}
