<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Company\Attribute\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
use MageOS\Company\Api\Data\CompanyInterface;

/**
 * Company status options.
 */
class Status extends AbstractSource
{
    /**
     * @inheritDoc
     *
     * @return array<int, array{value: int, label: \Magento\Framework\Phrase}>
     */
    public function getAllOptions()
    {
        if ($this->_options === null) {
            $this->_options = [
                ['value' => CompanyInterface::STATUS_PENDING, 'label' => __('Pending Approval')],
                ['value' => CompanyInterface::STATUS_APPROVED, 'label' => __('Approved')],
                ['value' => CompanyInterface::STATUS_REJECTED, 'label' => __('Rejected')],
                ['value' => CompanyInterface::STATUS_BLOCKED, 'label' => __('Blocked')],
            ];
        }

        return $this->_options;
    }
}
