<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Attribute;

use Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend;
use Magento\Eav\Model\Entity\Attribute\Backend\Datetime;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use Magento\Eav\Model\Entity\Attribute\Source\Table;

/**
 * Input types available for custom company/company address attributes and their storage.
 */
class InputTypes
{
    /**
     * Frontend input → backend type, backend model and source model.
     */
    private const TYPES = [
        'text' => ['backend_type' => 'varchar', 'backend_model' => null, 'source_model' => null],
        'textarea' => ['backend_type' => 'text', 'backend_model' => null, 'source_model' => null],
        'date' => ['backend_type' => 'datetime', 'backend_model' => Datetime::class, 'source_model' => null],
        'boolean' => ['backend_type' => 'int', 'backend_model' => null, 'source_model' => Boolean::class],
        'select' => ['backend_type' => 'int', 'backend_model' => null, 'source_model' => Table::class],
        'multiselect' => [
            'backend_type' => 'text',
            'backend_model' => ArrayBackend::class,
            'source_model' => Table::class,
        ],
    ];

    /**
     * Inputs whose options are managed in the attribute form.
     */
    public const OPTION_INPUTS = ['select', 'multiselect'];

    /**
     * Inputs that accept text validation rules (length, input validation).
     */
    public const TEXT_INPUTS = ['text', 'textarea'];

    /**
     * Whether the input type can be used for a new attribute.
     *
     * @param string $input
     * @return bool
     */
    public function isAllowed(string $input): bool
    {
        return isset(self::TYPES[$input]);
    }

    /**
     * Storage of an input type.
     *
     * @param string $input
     * @return array{backend_type: string, backend_model: ?string, source_model: ?string}
     */
    public function getStorage(string $input): array
    {
        return self::TYPES[$input];
    }

    /**
     * Input types as select options.
     *
     * @return array<int,array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'text', 'label' => __('Text Field')],
            ['value' => 'textarea', 'label' => __('Text Area')],
            ['value' => 'date', 'label' => __('Date')],
            ['value' => 'boolean', 'label' => __('Yes/No')],
            ['value' => 'select', 'label' => __('Dropdown')],
            ['value' => 'multiselect', 'label' => __('Multiple Select')],
        ];
    }

    /**
     * Label of any frontend input, system inputs included.
     *
     * @param string $input
     * @return string
     */
    public function getLabel(string $input): string
    {
        foreach ($this->toOptionArray() as $option) {
            if ($option['value'] === $input) {
                return (string)$option['label'];
            }
        }

        return match ($input) {
            'multiline' => (string)__('Multiple Lines'),
            'hidden' => (string)__('Hidden'),
            default => $input,
        };
    }
}
