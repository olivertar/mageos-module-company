<?php

declare(strict_types=1);

namespace MageOS\Company\Model\ResourceModel\Company\Indexer;

use MageOS\Company\Model\ResourceModel\Company\Collection as CompanyCollection;

/**
 * Company collection read by the company_grid indexer: items are not registered in the entity snapshot.
 */
class Collection extends CompanyCollection
{
    /**
     * @inheritDoc
     */
    protected function beforeAddLoadedItem(\Magento\Framework\DataObject $item)
    {
        return $item;
    }
}
