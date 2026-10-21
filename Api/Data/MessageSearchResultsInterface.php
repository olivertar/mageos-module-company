<?php

declare(strict_types=1);

namespace MageOS\Company\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Company message search results.
 *
 * @api
 */
interface MessageSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get messages.
     *
     * @return \MageOS\Company\Api\Data\MessageInterface[]
     */
    public function getItems();

    /**
     * Set messages.
     *
     * @param \MageOS\Company\Api\Data\MessageInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
