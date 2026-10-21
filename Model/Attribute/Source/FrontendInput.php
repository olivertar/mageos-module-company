<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Attribute\Source;

use Magento\Framework\Data\OptionSourceInterface;
use MageOS\Company\Model\Attribute\InputTypes;

/**
 * All frontend inputs used by company attributes, system ones included.
 */
class FrontendInput implements OptionSourceInterface
{
    /**
     * @param InputTypes $inputTypes
     */
    public function __construct(
        private readonly InputTypes $inputTypes
    ) {
    }

    /**
     * @inheritDoc
     *
     * @return array<int,array{value: string, label: string}>
     */
    public function toOptionArray()
    {
        $options = [];
        foreach (['text', 'textarea', 'date', 'boolean', 'select', 'multiselect', 'multiline', 'hidden'] as $input) {
            $options[] = ['value' => $input, 'label' => $this->inputTypes->getLabel($input)];
        }

        return $options;
    }
}
