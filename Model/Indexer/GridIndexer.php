<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Indexer;

use Magento\Framework\Indexer\IndexerRegistry;

/**
 * Keeps the company_grid index in sync when a company or a grid attribute changes.
 */
class GridIndexer
{
    public const INDEXER_ID = 'company_grid';

    /**
     * @param IndexerRegistry $indexerRegistry
     */
    public function __construct(
        private readonly IndexerRegistry $indexerRegistry
    ) {
    }

    /**
     * Reindex one company, unless the indexer runs on schedule (then mview picks the change up).
     *
     * @param int $companyId
     * @return void
     */
    public function reindexRow(int $companyId): void
    {
        $indexer = $this->indexerRegistry->get(self::INDEXER_ID);
        if (!$indexer->isScheduled()) {
            $indexer->reindexRow($companyId);
        }
    }

    /**
     * Mark the index as invalid: the grid columns changed and need a full reindex.
     *
     * @return void
     */
    public function invalidate(): void
    {
        $this->indexerRegistry->get(self::INDEXER_ID)->invalidate();
    }
}
