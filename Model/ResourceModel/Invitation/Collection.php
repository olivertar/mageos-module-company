<?php

declare(strict_types=1);

namespace MageOS\Company\Model\ResourceModel\Invitation;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use MageOS\Company\Model\Invitation;
use MageOS\Company\Model\ResourceModel\Invitation as InvitationResource;

/**
 * Company invitation collection.
 */
class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(Invitation::class, InvitationResource::class);
    }
}
