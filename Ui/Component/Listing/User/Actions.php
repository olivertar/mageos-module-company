<?php

declare(strict_types=1);

namespace MageOS\Company\Ui\Component\Listing\User;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Row actions of the company user listing.
 */
class Actions extends Column
{
    private const MODAL = 'company_form.company_form.users.users_modal';
    private const LOADER = self::MODAL . '.users_form_loader';

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param AuthorizationInterface $authorization
     * @param array<string,mixed> $components
     * @param array<string,mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly AuthorizationInterface $authorization,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @inheritDoc
     *
     * @param array<string,mixed> $dataSource
     * @return array<string,mixed>
     */
    public function prepareDataSource(array $dataSource)
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        $name = $this->getData('name');
        $canEdit = $this->authorization->isAllowed('MageOS_Company::company_edit');
        $canViewCustomer = $this->authorization->isAllowed('Magento_Customer::manage');
        foreach ($dataSource['data']['items'] as &$item) {
            if (!isset($item['customer_id'])) {
                continue;
            }
            $params = ['company_id' => $item['company_entity_id'], 'customer_id' => $item['customer_id']];
            if ($canEdit) {
                $item[$name]['edit'] = [
                    'callback' => [
                        ['provider' => self::LOADER, 'target' => 'destroyInserted'],
                        ['provider' => self::MODAL, 'target' => 'openModal'],
                        ['provider' => self::LOADER, 'target' => 'render', 'params' => $params],
                    ],
                    'href' => '#',
                    'label' => __('Edit'),
                    'hidden' => false,
                ];
            }
            if ($canViewCustomer) {
                $item[$name]['view_customer'] = [
                    'href' => $this->urlBuilder->getUrl('customer/index/edit', ['id' => $item['customer_id']]),
                    'label' => __('View Customer'),
                    'target' => '_blank',
                ];
            }
            if ($canEdit) {
                $item[$name]['remove'] = [
                    'href' => $this->urlBuilder->getUrl(
                        'mageos_company/user/remove',
                        ['company_id' => $item['company_entity_id'], 'id' => $item['customer_id']]
                    ),
                    'label' => __('Remove from Company'),
                    'isAjax' => true,
                    'confirm' => [
                        'title' => __('Remove user'),
                        'message' => __('Are you sure you want to remove this customer from the company?'),
                    ],
                ];
            }
        }

        return $dataSource;
    }
}
