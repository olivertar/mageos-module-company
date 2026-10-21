<?php

declare(strict_types=1);

namespace MageOS\Company\Model;

use Magento\Framework\Api\SearchResults;
use MageOS\Company\Api\Data\CompanySearchResultsInterface;

/**
 * Typed search results for companies.
 */
class CompanySearchResults extends SearchResults implements CompanySearchResultsInterface
{
}
