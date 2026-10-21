<?php

declare(strict_types=1);

namespace MageOS\Company\Setup\Patch\Data;

use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use MageOS\Company\Model\Indexer\GridIndexer;

/**
 * Build the company_grid_flat table, which the admin company grid reads.
 */
class ReindexCompanyGrid implements DataPatchInterface
{
    /**
     * @param IndexerRegistry $indexerRegistry
     */
    public function __construct(
        private readonly IndexerRegistry $indexerRegistry
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(): self
    {
        $this->indexerRegistry->get(GridIndexer::INDEXER_ID)->reindexAll();

        return $this;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [InstallCompanyEntities::class, UseCompanyCountrySource::class];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
