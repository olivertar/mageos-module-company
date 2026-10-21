<?php

declare(strict_types=1);

namespace MageOS\Company\Model\ResourceModel\StatusHistory;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use MageOS\Company\Model\ResourceModel\StatusHistory as StatusHistoryResource;
use MageOS\Company\Model\StatusHistory;

/**
 * Company status history collection.
 */
class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(StatusHistory::class, StatusHistoryResource::class);
    }
}
