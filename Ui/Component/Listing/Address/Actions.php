<?php

declare(strict_types=1);

namespace MageOS\Company\Ui\Component\Listing\Address;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Row actions of the company address listing.
 */
class Actions extends Column
{
    private const MODAL = 'company_form.company_form.addresses.addresses_modal';
    private const LOADER = self::MODAL . '.addresses_form_loader';

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
        if (!isset($dataSource['data']['items']) || !$this->authorization->isAllowed('MageOS_Company::company_edit')) {
            return $dataSource;
        }
        $name = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            if (!isset($item['entity_id'])) {
                continue;
            }
            $params = ['company_id' => $item['parent_id'], 'id' => $item['entity_id']];
            $item[$name]['edit'] = [
                'callback' => [
                    ['provider' => self::LOADER, 'target' => 'destroyInserted'],
                    ['provider' => self::MODAL, 'target' => 'openModal'],
                    [
                        'provider' => self::LOADER,
                        'target' => 'render',
                        'params' => ['entity_id' => $item['entity_id'], 'company_id' => $item['parent_id']],
                    ],
                ],
                'href' => '#',
                'label' => __('Edit'),
                'hidden' => false,
            ];
            foreach ($this->getDefaultTypes() as $type => $labels) {
                $item[$name]['default_' . $type] = [
                    'href' => $this->urlBuilder->getUrl(
                        'mageos_company/address/setDefault',
                        $params + ['type' => $type]
                    ),
                    'label' => $labels[0],
                    'isAjax' => true,
                    'confirm' => ['title' => $labels[0], 'message' => $labels[1]],
                ];
            }
            $item[$name]['delete'] = [
                'href' => $this->urlBuilder->getUrl('mageos_company/address/delete', $params),
                'label' => __('Delete'),
                'isAjax' => true,
                'confirm' => [
                    'title' => __('Delete address'),
                    'message' => __('Are you sure you want to delete the address?'),
                ],
            ];
        }

        return $dataSource;
    }

    /**
     * Default address types with their action label and confirmation message.
     *
     * @return array<string,array{0: \Magento\Framework\Phrase, 1: \Magento\Framework\Phrase}>
     */
    private function getDefaultTypes(): array
    {
        return [
            'billing' => [
                __('Set as default billing'),
                __('Are you sure you want to set the address as default billing address?'),
            ],
            'shipping' => [
                __('Set as default shipping'),
                __('Are you sure you want to set the address as default shipping address?'),
            ],
            'legal' => [
                __('Set as legal address'),
                __('Are you sure you want to set the address as the legal address?'),
            ],
        ];
    }
}
