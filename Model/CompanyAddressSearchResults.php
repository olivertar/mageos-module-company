<?php

declare(strict_types=1);

namespace MageOS\Company\Model;

use Magento\Framework\Api\SearchResults;
use MageOS\Company\Api\Data\CompanyAddressSearchResultsInterface;

/**
 * Typed search results for company addresses.
 */
class CompanyAddressSearchResults extends SearchResults implements CompanyAddressSearchResultsInterface
{
}
