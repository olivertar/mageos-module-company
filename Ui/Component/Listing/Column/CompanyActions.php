<?php

declare(strict_types=1);

namespace MageOS\Company\Ui\Component\Listing\Column;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Row actions of the company grid.
 */
class CompanyActions extends Column
{
    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param AuthorizationInterface $authorization
     * @param Escaper $escaper
     * @param array<string,mixed> $components
     * @param array<string,mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly AuthorizationInterface $authorization,
        private readonly Escaper $escaper,
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
        $canDelete = $this->authorization->isAllowed('MageOS_Company::company_delete');
        foreach ($dataSource['data']['items'] as &$item) {
            if (!isset($item['entity_id'])) {
                continue;
            }
            $item[$name]['edit'] = [
                'href' => $this->urlBuilder->getUrl('mageos_company/company/edit', ['id' => $item['entity_id']]),
                'label' => __('Edit'),
            ];
            if ($canDelete) {
                $title = $this->escaper->escapeHtml((string)($item['name'] ?? ''));
                $item[$name]['delete'] = [
                    'href' => $this->urlBuilder->getUrl(
                        'mageos_company/company/delete',
                        ['id' => $item['entity_id']]
                    ),
                    'label' => __('Delete'),
                    'post' => true,
                    'confirm' => [
                        'title' => __('Delete %1', $title),
                        'message' => __('Are you sure you want to delete the company "%1"?', $title),
                    ],
                ];
            }
        }

        return $dataSource;
    }
}
