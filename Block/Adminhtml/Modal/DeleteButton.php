<?php

declare(strict_types=1);

namespace MageOS\Company\Block\Adminhtml\Modal;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Delete/remove button of a modal form inserted in the company form.
 *
 * Shown only when the form edits an existing record (the ID request parameter is set).
 */
class DeleteButton implements ButtonProviderInterface
{
    /**
     * @param RequestInterface $request
     * @param UrlInterface $urlBuilder
     * @param AuthorizationInterface $authorization
     * @param string $formName Full UI name of the form (its JS component handles deleteAddress()).
     * @param string $route Delete controller route.
     * @param string $idParam Request parameter holding the record ID.
     * @param string $label Button label.
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly UrlInterface $urlBuilder,
        private readonly AuthorizationInterface $authorization,
        private readonly string $formName,
        private readonly string $route,
        private readonly string $idParam,
        private readonly string $label = 'Delete'
    ) {
    }

    /**
     * @inheritDoc
     *
     * @return array<string,mixed>
     */
    public function getButtonData()
    {
        $recordId = (int)$this->request->getParam($this->idParam);
        $companyId = (int)$this->request->getParam('company_id');
        if (!$recordId || !$companyId || !$this->authorization->isAllowed('MageOS_Company::company_edit')) {
            return [];
        }

        return [
            'label' => __($this->label),
            'on_click' => '',
            'data_attribute' => [
                'mage-init' => [
                    'Magento_Ui/js/form/button-adapter' => [
                        'actions' => [
                            [
                                'targetName' => $this->formName,
                                'actionName' => 'deleteAddress',
                                'params' => [
                                    $this->urlBuilder->getUrl(
                                        $this->route,
                                        ['company_id' => $companyId, 'id' => $recordId]
                                    ),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'sort_order' => 20,
        ];
    }
}
