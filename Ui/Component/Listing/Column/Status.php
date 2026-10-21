<?php

declare(strict_types=1);

namespace MageOS\Company\Ui\Component\Listing\Column;

use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Model\Company\Attribute\Source\Status as StatusSource;

/**
 * Company status rendered as a severity badge.
 */
class Status extends Column
{
    /**
     * Status → admin grid severity class.
     */
    private const SEVERITY = [
        CompanyInterface::STATUS_PENDING => 'grid-severity-minor',
        CompanyInterface::STATUS_APPROVED => 'grid-severity-notice',
        CompanyInterface::STATUS_REJECTED => 'grid-severity-critical',
        CompanyInterface::STATUS_BLOCKED => 'grid-severity-major',
    ];

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param StatusSource $statusSource
     * @param Escaper $escaper
     * @param array<string,mixed> $components
     * @param array<string,mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly StatusSource $statusSource,
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
        foreach ($dataSource['data']['items'] as &$item) {
            if (!isset($item[$name])) {
                continue;
            }
            $status = (int)$item[$name];
            $label = (string)$this->statusSource->getOptionText($status);
            $item[$name] = sprintf(
                '<span class="%s"><span>%s</span></span>',
                self::SEVERITY[$status] ?? 'grid-severity-minor',
                $this->escaper->escapeHtml($label)
            );
        }

        return $dataSource;
    }
}
