<?php

declare(strict_types=1);

namespace MageOS\Company\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use MageOS\Company\Api\Data\CompanyAddressInterface;
use MageOS\Company\Model\CompanyAddress;
use MageOS\Company\Model\CompanyAddress\Attribute\Source\Country;
use MageOS\Company\Setup\CompanySetupFactory;

/**
 * Switch the company address country to a source model that resolves country codes, needed by the EAV form
 * validation of the storefront forms.
 */
class UseCompanyCountrySource implements DataPatchInterface
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
        $this->companySetupFactory->create(['setup' => $this->moduleDataSetup])->updateAttribute(
            CompanyAddress::ENTITY,
            CompanyAddressInterface::COUNTRY_ID,
            'source_model',
            Country::class
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [InstallCompanyEntities::class];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
