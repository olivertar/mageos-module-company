<?php

declare(strict_types=1);

namespace MageOS\Company\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Edit action of the company attribute grid.
 */
class AttributeActions extends Column
{
    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param array<string,mixed> $components
     * @param array<string,mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
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
        $name = $this->getData('name');
        foreach ($dataSource['data']['items'] ?? [] as $index => $item) {
            if (!isset($item['attribute_id'])) {
                continue;
            }
            $dataSource['data']['items'][$index][$name]['edit'] = [
                'href' => $this->urlBuilder->getUrl('mageos_company/attribute/edit', ['id' => $item['attribute_id']]),
                'label' => __('Edit'),
            ];
        }

        return $dataSource;
    }
}
