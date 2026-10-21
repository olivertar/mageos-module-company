<?php

declare(strict_types=1);

namespace MageOS\Company\Block\Adminhtml\Company\Edit;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Model\Status\ActionRunner;

/**
 * Status action button (approve, reject, block, unblock, reopen), shown when the company status allows it.
 *
 * Opens a prompt for the comment or reason, then posts to company/changeStatus.
 */
class StatusButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @param UrlInterface $urlBuilder
     * @param RequestInterface $request
     * @param AuthorizationInterface $authorization
     * @param Escaper $escaper
     * @param CompanyRepositoryInterface $companyRepository
     * @param ActionRunner $actionRunner
     * @param string $action
     * @param string $label
     * @param string $promptTitle
     * @param int $sortOrder
     * @param string $cssClass
     */
    public function __construct(
        UrlInterface $urlBuilder,
        RequestInterface $request,
        AuthorizationInterface $authorization,
        Escaper $escaper,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly ActionRunner $actionRunner,
        private readonly string $action = ActionRunner::APPROVE,
        private readonly string $label = 'Approve',
        private readonly string $promptTitle = 'Approve Company',
        private readonly int $sortOrder = 30,
        private readonly string $cssClass = ''
    ) {
        parent::__construct($urlBuilder, $request, $authorization, $escaper);
    }

    /**
     * @inheritDoc
     *
     * @return array<string,mixed>
     */
    public function getButtonData()
    {
        $companyId = $this->getCompanyId();
        if (!$companyId || !$this->authorization->isAllowed('MageOS_Company::company_approve')) {
            return [];
        }
        try {
            $status = $this->companyRepository->getById($companyId)->getStatus();
        } catch (NoSuchEntityException) {
            return [];
        }
        if (!$this->actionRunner->isAvailable($this->action, $status)) {
            return [];
        }
        $reasonRequired = $this->actionRunner->isReasonRequired($this->action);

        return [
            'label' => __($this->label),
            'class' => trim('company-status-' . $this->action . ' ' . $this->cssClass),
            // empty handler: otherwise the UI button renders onclick="location.href = getUrl('')" (route/index/index)
            'on_click' => '',
            'sort_order' => $this->sortOrder,
            'data_attribute' => [
                'mage-init' => [
                    'MageOS_Company/js/company/status-action' => [
                        'url' => $this->getUrl('mageos_company/company/changeStatus', ['id' => $companyId]),
                        'action' => $this->action,
                        'title' => (string)__($this->promptTitle),
                        'label' => (string)($reasonRequired ? __('Reason') : __('Comment (optional)')),
                        'reasonRequired' => $reasonRequired,
                    ],
                ],
            ],
        ];
    }
}
