<?php

declare(strict_types=1);

namespace MageOS\Company\Observer\Grid;

use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use MageOS\Company\Model\Attribute;
use MageOS\Company\Model\Company;
use MageOS\Company\Model\Indexer\GridIndexer;

/**
 * Invalidate the company grid index when a company attribute enters, leaves or changes in the grid.
 *
 * Listens to company_entity_attribute_save_after and company_entity_attribute_delete_after.
 */
class InvalidateOnAttributeChange implements ObserverInterface
{
    private const GRID_FLAGS = ['is_used_in_grid', 'is_filterable_in_grid', 'is_searchable_in_grid'];

    /**
     * @param GridIndexer $gridIndexer
     * @param EavConfig $eavConfig
     */
    public function __construct(
        private readonly GridIndexer $gridIndexer,
        private readonly EavConfig $eavConfig
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $attribute = $observer->getEvent()->getData('attribute');
        if (!$attribute instanceof Attribute
            || (int)$attribute->getEntityTypeId() !== (int)$this->eavConfig->getEntityType(Company::ENTITY)->getId()
            || $attribute->getBackendType() === 'static'
        ) {
            return;
        }
        $deleted = $observer->getEvent()->getName() === 'company_entity_attribute_delete_after';
        if ($deleted || $attribute->getOrigData('attribute_id') === null) {
            $changed = (bool)$attribute->getData('is_used_in_grid');
        } else {
            $changed = false;
            foreach (self::GRID_FLAGS as $flag) {
                $changed = $changed || (bool)$attribute->getData($flag) !== (bool)$attribute->getOrigData($flag);
            }
        }
        if ($changed) {
            $this->gridIndexer->invalidate();
        }
    }
}
