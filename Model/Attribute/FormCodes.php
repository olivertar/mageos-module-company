<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Attribute;

use MageOS\Company\Model\Company;
use MageOS\Company\Model\CompanyAddress;
use MageOS\Company\Setup\CompanySetup;

/**
 * Entity types managed in Stores > Attributes > Company and the forms their attributes can be used in.
 */
class FormCodes
{
    /**
     * Managed entity types with their label.
     *
     * @return array<string,string>
     */
    public function getEntityTypes(): array
    {
        return [
            Company::ENTITY => (string)__('Company'),
            CompanyAddress::ENTITY => (string)__('Company Address'),
        ];
    }

    /**
     * Forms of an entity type, keyed by form code.
     *
     * @param string $entityTypeCode
     * @return array<string,string>
     */
    public function getForms(string $entityTypeCode): array
    {
        return match ($entityTypeCode) {
            Company::ENTITY => [
                CompanySetup::FORM_ADMIN_COMPANY => (string)__('Admin: Company Form'),
                CompanySetup::FORM_COMPANY_REGISTER => (string)__('Storefront: Company Registration'),
                CompanySetup::FORM_COMPANY_ACCOUNT_EDIT => (string)__('Storefront: Edit My Company'),
            ],
            CompanyAddress::ENTITY => [
                CompanySetup::FORM_ADMIN_ADDRESS => (string)__('Admin: Company Address Form'),
                CompanySetup::FORM_ADDRESS_REGISTER => (string)__('Storefront: Company Registration (Legal Address)'),
                CompanySetup::FORM_ADDRESS_EDIT => (string)__('Storefront: Company Address Book'),
            ],
            default => [],
        };
    }
}
