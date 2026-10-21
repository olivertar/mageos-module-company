<?php

declare(strict_types=1);

namespace MageOS\Company\Model;

use Magento\Framework\Api\SearchResults;
use MageOS\Company\Api\Data\MessageSearchResultsInterface;

/**
 * Typed search results for company messages.
 */
class MessageSearchResults extends SearchResults implements MessageSearchResultsInterface
{
}
