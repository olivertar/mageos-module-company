<?php

declare(strict_types=1);

namespace MageOS\Company\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use MageOS\Company\Api\Data\StatusHistoryInterface;

/**
 * Company status history resource model.
 */
class StatusHistory extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('company_status_history', StatusHistoryInterface::HISTORY_ID);
    }
}
