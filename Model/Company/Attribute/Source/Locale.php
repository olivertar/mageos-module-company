<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Company\Attribute\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
use Magento\Framework\Locale\ListsInterface;

/**
 * Locale options for the preferred_language attribute.
 */
class Locale extends AbstractSource
{
    /**
     * @param ListsInterface $localeLists
     */
    public function __construct(
        private readonly ListsInterface $localeLists
    ) {
    }

    /**
     * @inheritDoc
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getAllOptions()
    {
        if ($this->_options === null) {
            $this->_options = array_merge(
                [['value' => '', 'label' => ' ']],
                $this->localeLists->getOptionLocales()
            );
        }

        return $this->_options;
    }
}
