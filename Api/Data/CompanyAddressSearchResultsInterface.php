<?php

declare(strict_types=1);

namespace MageOS\Company\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * @api
 */
interface CompanyAddressSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get company addresses.
     *
     * @return \MageOS\Company\Api\Data\CompanyAddressInterface[]
     */
    public function getItems();

    /**
     * Set company addresses.
     *
     * @param \MageOS\Company\Api\Data\CompanyAddressInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
