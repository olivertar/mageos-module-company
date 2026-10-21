<?php

declare(strict_types=1);

namespace MageOS\Company\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * @api
 */
interface CompanySearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get companies.
     *
     * @return \MageOS\Company\Api\Data\CompanyInterface[]
     */
    public function getItems();

    /**
     * Set companies.
     *
     * @param \MageOS\Company\Api\Data\CompanyInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
