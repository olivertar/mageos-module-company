<?php

declare(strict_types=1);

namespace MageOS\Company\Ui\Component\Listing\Message;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * "Open" action of the conversations grid: the thread is in the company form.
 */
class Actions extends Column
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
        foreach ($dataSource['data']['items'] ?? [] as &$item) {
            $item[$this->getData('name')]['open'] = [
                'href' => $this->urlBuilder->getUrl('mageos_company/company/edit', ['id' => $item['entity_id']]),
                'label' => __('Open Conversation'),
            ];
        }

        return $dataSource;
    }
}
