<?php

declare(strict_types=1);

namespace MageOS\Company\Ui\Component\Listing;

use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns as UiColumns;
use MageOS\Company\Model\Company;

/**
 * Company grid columns: the XML columns plus one column per EAV attribute flagged "is_used_in_grid".
 */
class Columns extends UiColumns
{
    /**
     * Sort order of the first attribute column (fixed columns before and after it are declared in XML).
     */
    private const FIRST_ATTRIBUTE_SORT_ORDER = 100;

    /**
     * Frontend input → column data type.
     */
    private const DATA_TYPES = [
        'select' => 'select',
        'multiselect' => 'select',
        'boolean' => 'select',
        'date' => 'date',
    ];

    /**
     * Column data type → grid filter.
     */
    private const FILTERS = ['text' => 'text', 'select' => 'select', 'date' => 'dateRange'];

    /**
     * Column data type → JS component.
     */
    private const COMPONENTS = [
        'text' => 'Magento_Ui/js/grid/columns/column',
        'select' => 'Magento_Ui/js/grid/columns/select',
        'date' => 'Magento_Ui/js/grid/columns/date',
    ];

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param EavConfig $eavConfig
     * @param array<string,mixed> $components
     * @param array<string,mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        private readonly UiComponentFactory $uiComponentFactory,
        private readonly EavConfig $eavConfig,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $components, $data);
    }

    /**
     * @inheritDoc
     */
    public function prepare()
    {
        $sortOrder = self::FIRST_ATTRIBUTE_SORT_ORDER;
        foreach ($this->getGridAttributes() as $code => $attribute) {
            if (isset($this->components[$code])) {
                continue;
            }
            $config = $this->getColumnConfig($attribute, $sortOrder++);
            $column = $this->uiComponentFactory->create($code, 'column', [
                'data' => ['config' => $config, 'js_config' => ['component' => $config['component']]],
                'context' => $this->getContext(),
            ]);
            $column->prepare();
            $this->addComponent($code, $column);
        }
        parent::prepare();
    }

    /**
     * Non-static company attributes in the grid, sorted.
     *
     * @return array<string,AbstractAttribute>
     */
    private function getGridAttributes(): array
    {
        $attributes = [];
        foreach ($this->eavConfig->getEntityAttributes(Company::ENTITY) as $attribute) {
            if (!$attribute->isStatic() && $attribute->getData('is_used_in_grid')) {
                $attributes[(string)$attribute->getAttributeCode()] = $attribute;
            }
        }
        uasort(
            $attributes,
            static fn ($a, $b) => (int)$a->getData('sort_order') <=> (int)$b->getData('sort_order')
        );

        return $attributes;
    }

    /**
     * Column configuration of an attribute.
     *
     * @param AbstractAttribute $attribute
     * @param int $sortOrder
     * @return array<string,mixed>
     */
    private function getColumnConfig(AbstractAttribute $attribute, int $sortOrder): array
    {
        $dataType = self::DATA_TYPES[(string)$attribute->getFrontendInput()] ?? 'text';
        $config = [
            'label' => __($attribute->getStoreLabel() ?: (string)$attribute->getFrontendLabel()),
            'dataType' => $dataType,
            'component' => self::COMPONENTS[$dataType],
            'align' => 'left',
            'sortOrder' => $sortOrder,
            'visible' => (bool)$attribute->getData('is_visible_in_grid'),
        ];
        if ($attribute->getData('is_filterable_in_grid')) {
            $config['filter'] = self::FILTERS[$dataType];
        }
        if ($dataType === 'select') {
            $config['options'] = $this->getOptions($attribute);
        }
        if ($dataType === 'date') {
            $config['dateFormat'] = 'MMM d, y';
            $config['timezone'] = false;
        }

        return $config;
    }

    /**
     * Source options with string values.
     *
     * @param AbstractAttribute $attribute
     * @return array<int,array{value: string, label: string}>
     */
    private function getOptions(AbstractAttribute $attribute): array
    {
        $options = [];
        foreach ($attribute->getSource()->getAllOptions() as $option) {
            if (is_array($option) && isset($option['value']) && !is_array($option['value'])
                && (string)$option['value'] !== ''
            ) {
                $options[] = ['value' => (string)$option['value'], 'label' => (string)$option['label']];
            }
        }

        return $options;
    }
}
