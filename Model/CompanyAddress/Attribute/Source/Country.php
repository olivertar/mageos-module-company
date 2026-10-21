<?php

declare(strict_types=1);

namespace MageOS\Company\Model\CompanyAddress\Attribute\Source;

use Magento\Directory\Model\ResourceModel\Country\CollectionFactory;
use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Allowed countries of the current store.
 *
 * Unlike the customer address country source (based on the option table source), getOptionText() works with
 * country codes, which the EAV form validation relies on.
 */
class Country extends AbstractSource
{
    /**
     * @param CollectionFactory $countryCollectionFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly CollectionFactory $countryCollectionFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritDoc
     *
     * @return array<int,array<string,mixed>>
     */
    public function getAllOptions()
    {
        if ($this->_options === null) {
            $this->_options = $this->countryCollectionFactory->create()
                ->loadByStore($this->storeManager->getStore()->getId())
                ->toOptionArray();
        }

        return $this->_options;
    }
}
