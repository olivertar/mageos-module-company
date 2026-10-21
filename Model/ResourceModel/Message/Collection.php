<?php

declare(strict_types=1);

namespace MageOS\Company\Model\ResourceModel\Message;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use MageOS\Company\Model\Message;
use MageOS\Company\Model\ResourceModel\Message as MessageResource;

/**
 * Company message collection.
 */
class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(Message::class, MessageResource::class);
    }
}
