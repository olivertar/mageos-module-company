<?php

declare(strict_types=1);

namespace MageOS\Company\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use MageOS\Company\Setup\CompanySetupFactory;

/**
 * Register the company and company_address EAV entity types with their attributes and forms.
 */
class InstallCompanyEntities implements DataPatchInterface
{
    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param CompanySetupFactory $companySetupFactory
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CompanySetupFactory $companySetupFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $this->companySetupFactory->create(['setup' => $this->moduleDataSetup])->installCompanyEntities();
        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
