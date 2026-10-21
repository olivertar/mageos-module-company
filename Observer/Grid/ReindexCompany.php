<?php

declare(strict_types=1);

namespace MageOS\Company\Observer\Grid;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use MageOS\Company\Model\Company;
use MageOS\Company\Model\Indexer\GridIndexer;
use Psr\Log\LoggerInterface;

/**
 * Update the company grid row after a company is saved or deleted (company_save/delete_commit_after).
 */
class ReindexCompany implements ObserverInterface
{
    /**
     * @param GridIndexer $gridIndexer
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly GridIndexer $gridIndexer,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $company = $observer->getEvent()->getData('company');
        if (!$company instanceof Company || !$company->getId()) {
            return;
        }
        try {
            $this->gridIndexer->reindexRow((int)$company->getId());
        } catch (\Exception $e) {
            // The company is already saved: a grid failure must not surface as a save error.
            $this->logger->error('Company grid reindex failed: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
}
