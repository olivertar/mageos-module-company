<?php

declare(strict_types=1);

namespace MageOS\Company\Block\Adminhtml\Company\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * "Delete" button of the company form.
 */
class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @inheritDoc
     *
     * @return array<string,mixed>
     */
    public function getButtonData()
    {
        $companyId = $this->getCompanyId();
        if (!$companyId || !$this->authorization->isAllowed('MageOS_Company::company_delete')) {
            return [];
        }

        return [
            'label' => __('Delete Company'),
            'class' => 'delete',
            'on_click' => sprintf(
                "deleteConfirm('%s', '%s', {data: {}})",
                $this->escaper->escapeJs(
                    (string)__(
                        'Are you sure you want to delete this company? '
                        . 'Its addresses and user assignments will be deleted too.'
                    )
                ),
                $this->escaper->escapeJs($this->getUrl('*/*/delete', ['id' => $companyId]))
            ),
            'sort_order' => 20,
        ];
    }
}
