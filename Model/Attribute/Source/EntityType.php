<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Attribute\Source;

use Magento\Framework\Data\OptionSourceInterface;
use MageOS\Company\Model\Attribute\FormCodes;

/**
 * Managed entity types (company, company address).
 */
class EntityType implements OptionSourceInterface
{
    /**
     * @param FormCodes $formCodes
     */
    public function __construct(
        private readonly FormCodes $formCodes
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
        foreach ($this->formCodes->getEntityTypes() as $code => $label) {
            $options[] = ['value' => $code, 'label' => $label];
        }

        return $options;
    }
}
