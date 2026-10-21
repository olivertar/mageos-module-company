<?php

declare(strict_types=1);

namespace MageOS\Company\Setup;

use Magento\Customer\Model\Attribute\Data\Postcode;
use Magento\Customer\Model\Customer\Attribute\Source\Website as WebsiteSource;
use Magento\Customer\Model\ResourceModel\Address\Attribute\Source\Region as RegionSource;
use Magento\Eav\Model\Entity\Attribute\Source\Table as TableSource;
use Magento\Eav\Setup\EavSetup;
use MageOS\Company\Model\Attribute;
use MageOS\Company\Model\Company;
use MageOS\Company\Model\Company\Attribute\Source\Locale as LocaleSource;
use MageOS\Company\Model\Company\Attribute\Source\Status as StatusSource;
use MageOS\Company\Model\CompanyAddress;
use MageOS\Company\Model\CompanyAddress\Attribute\Source\Country as CountrySource;
use MageOS\Company\Model\ResourceModel\Attribute\Collection as CompanyAttributeCollection;
use MageOS\Company\Model\ResourceModel\Company as CompanyResource;
use MageOS\Company\Model\ResourceModel\CompanyAddress as CompanyAddressResource;
use MageOS\Company\Model\ResourceModel\CompanyAddress\Attribute\Collection as AddressAttributeCollection;

/**
 * EAV setup for the company and company_address entity types.
 *
 * Each attribute may declare a "forms" key (not stored in eav_attribute) listing the form codes it is used in.
 *
 * @codeCoverageIgnore
 */
class CompanySetup extends EavSetup
{
    public const FORM_ADMIN_COMPANY = 'adminhtml_company';
    public const FORM_COMPANY_REGISTER = 'company_register';
    public const FORM_COMPANY_ACCOUNT_EDIT = 'company_account_edit';
    public const FORM_ADMIN_ADDRESS = 'adminhtml_company_address';
    public const FORM_ADDRESS_EDIT = 'company_address_edit';
    public const FORM_ADDRESS_REGISTER = 'company_address_register';

    private const NAME_RULES = '{"max_text_length":255,"min_text_length":1}';
    private const DEFAULT_ATTRIBUTE_SET = 'Default';

    /**
     * Register the entity types, their attributes and attribute forms.
     *
     * @return void
     */
    public function installCompanyEntities(): void
    {
        $entities = $this->getDefaultEntities();
        $this->installEntities($entities);

        foreach (array_keys($entities) as $entityTypeCode) {
            $this->updateEntityType(
                $entityTypeCode,
                'default_attribute_set_id',
                (string)$this->getAttributeSetId($entityTypeCode, self::DEFAULT_ATTRIBUTE_SET)
            );
        }

        $this->installForms($entities);
    }

    /**
     * Fill company_form_attribute from the "forms" key of each attribute.
     *
     * @param array<string,array<string,mixed>> $entities
     * @return void
     */
    private function installForms(array $entities): void
    {
        $connection = $this->getSetup()->getConnection();
        $data = [];
        foreach ($entities as $entityTypeCode => $entity) {
            foreach ($entity['attributes'] as $attributeCode => $attribute) {
                $attributeId = (int)$this->getAttributeId($entityTypeCode, $attributeCode);
                foreach ($attribute['forms'] ?? [] as $formCode) {
                    $data[] = ['form_code' => $formCode, 'attribute_id' => $attributeId];
                }
            }
        }
        if ($data) {
            $connection->insertOnDuplicate(
                $this->getSetup()->getTable('company_form_attribute'),
                $data,
                ['form_code']
            );
        }
    }

    /**
     * @inheritDoc
     *
     * @return array<string,array<string,mixed>>
     */
    public function getDefaultEntities()
    {
        return [
            Company::ENTITY => [
                'entity_model' => CompanyResource::class,
                'attribute_model' => Attribute::class,
                'table' => 'company_entity',
                'additional_attribute_table' => 'company_eav_attribute',
                'entity_attribute_collection' => CompanyAttributeCollection::class,
                'attributes' => $this->getCompanyAttributes(),
            ],
            CompanyAddress::ENTITY => [
                'entity_model' => CompanyAddressResource::class,
                'attribute_model' => Attribute::class,
                'table' => 'company_address_entity',
                'additional_attribute_table' => 'company_eav_attribute',
                'entity_attribute_collection' => AddressAttributeCollection::class,
                'attributes' => $this->getAddressAttributes(),
            ],
        ];
    }

    /**
     * Company attributes.
     *
     * @return array<string,array<string,mixed>>
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    private function getCompanyAttributes(): array
    {
        $adminOnly = [self::FORM_ADMIN_COMPANY];
        $allForms = [self::FORM_ADMIN_COMPANY, self::FORM_COMPANY_REGISTER, self::FORM_COMPANY_ACCOUNT_EDIT];
        $grid = [
            'is_used_in_grid' => true,
            'is_visible_in_grid' => true,
            'is_filterable_in_grid' => true,
            'is_searchable_in_grid' => true,
        ];

        return [
            'name' => [
                'type' => 'static',
                'label' => 'Company Name',
                'input' => 'text',
                'validate_rules' => self::NAME_RULES,
                'sort_order' => 10,
                'position' => 10,
                'forms' => $allForms,
            ] + $grid,
            'email' => [
                'type' => 'static',
                'label' => 'Company Email',
                'input' => 'text',
                'validate_rules' => '{"input_validation":"email"}',
                'sort_order' => 20,
                'position' => 20,
                'forms' => $allForms,
            ] + $grid,
            'company_id' => [
                'type' => 'static',
                'label' => 'ERP Code',
                'input' => 'text',
                'required' => false,
                'validate_rules' => '{"max_text_length":64}',
                'sort_order' => 30,
                'position' => 30,
                'forms' => $adminOnly,
            ] + $grid,
            'status' => [
                'type' => 'static',
                'label' => 'Status',
                'input' => 'select',
                'source' => StatusSource::class,
                'required' => false,
                'default' => 0,
                'sort_order' => 40,
                'position' => 40,
                'forms' => $adminOnly,
                'is_used_in_grid' => true,
                'is_visible_in_grid' => true,
                'is_filterable_in_grid' => true,
            ],
            'website_id' => [
                'type' => 'static',
                'label' => 'Website',
                'input' => 'select',
                'source' => WebsiteSource::class,
                'sort_order' => 50,
                'position' => 50,
                'forms' => $adminOnly,
                'is_used_in_grid' => true,
                'is_visible_in_grid' => true,
                'is_filterable_in_grid' => true,
            ],
            'super_user_id' => [
                'type' => 'static',
                'label' => 'Super User',
                'input' => 'text',
                'required' => false,
                'visible' => false,
                'sort_order' => 60,
                'position' => 60,
            ],
            'default_billing' => [
                'type' => 'static',
                'label' => 'Default Billing Address',
                'input' => 'text',
                'required' => false,
                'visible' => false,
                'sort_order' => 70,
                'position' => 70,
            ],
            'default_shipping' => [
                'type' => 'static',
                'label' => 'Default Shipping Address',
                'input' => 'text',
                'required' => false,
                'visible' => false,
                'sort_order' => 80,
                'position' => 80,
            ],
            'legal_address' => [
                'type' => 'static',
                'label' => 'Legal Address',
                'input' => 'text',
                'required' => false,
                'visible' => false,
                'sort_order' => 90,
                'position' => 90,
            ],
            'legal_name' => [
                'type' => 'varchar',
                'label' => 'Legal Name',
                'input' => 'text',
                'validate_rules' => self::NAME_RULES,
                'sort_order' => 100,
                'position' => 100,
                'forms' => $allForms,
            ] + $grid,
            'vat_id' => [
                'type' => 'varchar',
                'label' => 'VAT Number',
                'input' => 'text',
                'validate_rules' => '{"max_text_length":32,"min_text_length":4}',
                'sort_order' => 110,
                'position' => 110,
                'forms' => $allForms,
            ] + $grid,
            'phone' => [
                'type' => 'varchar',
                'label' => 'Phone Number',
                'input' => 'text',
                'validate_rules' => self::NAME_RULES,
                'sort_order' => 120,
                'position' => 120,
                'forms' => $allForms,
            ] + $grid,
            'fax' => [
                'type' => 'varchar',
                'label' => 'Fax',
                'input' => 'text',
                'required' => false,
                'validate_rules' => '{"max_text_length":255}',
                'sort_order' => 130,
                'position' => 130,
                'forms' => $allForms,
            ],
            'website_url' => [
                'type' => 'varchar',
                'label' => 'Website URL',
                'input' => 'text',
                'required' => false,
                'validate_rules' => '{"input_validation":"url","max_text_length":255}',
                'sort_order' => 140,
                'position' => 140,
                'forms' => $allForms,
            ],
            'industry' => [
                'type' => 'int',
                'label' => 'Industry',
                'input' => 'select',
                'source' => TableSource::class,
                'required' => false,
                'sort_order' => 150,
                'position' => 150,
                'forms' => $allForms,
                'option' => ['values' => [
                    'Agriculture',
                    'Construction',
                    'Education',
                    'Energy',
                    'Finance',
                    'Healthcare',
                    'Hospitality',
                    'Information Technology',
                    'Manufacturing',
                    'Professional Services',
                    'Public Sector',
                    'Retail',
                    'Transportation & Logistics',
                    'Wholesale',
                    'Other',
                ]],
                'is_used_in_grid' => true,
                'is_visible_in_grid' => false,
                'is_filterable_in_grid' => true,
            ],
            'employees_range' => [
                'type' => 'int',
                'label' => 'Number of Employees',
                'input' => 'select',
                'source' => TableSource::class,
                'required' => false,
                'sort_order' => 160,
                'position' => 160,
                'forms' => $allForms,
                'option' => ['values' => ['1-10', '11-50', '51-200', '201-500', '500+']],
            ],
            'description' => [
                'type' => 'text',
                'label' => 'Description',
                'input' => 'textarea',
                'required' => false,
                'sort_order' => 170,
                'position' => 170,
                'forms' => $allForms,
            ],
            'registration_comment' => [
                'type' => 'text',
                'label' => 'Registration Comment',
                'input' => 'textarea',
                'required' => false,
                'sort_order' => 180,
                'position' => 180,
                'forms' => [self::FORM_ADMIN_COMPANY, self::FORM_COMPANY_REGISTER],
            ],
            'preferred_language' => [
                'type' => 'varchar',
                'label' => 'Preferred Language',
                'input' => 'select',
                'source' => LocaleSource::class,
                'required' => false,
                'sort_order' => 190,
                'position' => 190,
                'forms' => $allForms,
            ],
        ];
    }

    /**
     * Company address attributes (codes mirror customer_address).
     *
     * @return array<string,array<string,mixed>>
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    private function getAddressAttributes(): array
    {
        $forms = [self::FORM_ADMIN_ADDRESS, self::FORM_ADDRESS_EDIT, self::FORM_ADDRESS_REGISTER];

        return [
            'label' => [
                'type' => 'varchar',
                'label' => 'Address Label',
                'input' => 'text',
                'required' => false,
                'validate_rules' => '{"max_text_length":255}',
                'sort_order' => 5,
                'position' => 5,
                'forms' => $forms,
            ],
            'firstname' => [
                'type' => 'static',
                'label' => 'First Name',
                'input' => 'text',
                'validate_rules' => self::NAME_RULES,
                'sort_order' => 10,
                'position' => 10,
                'forms' => $forms,
            ],
            'lastname' => [
                'type' => 'static',
                'label' => 'Last Name',
                'input' => 'text',
                'validate_rules' => self::NAME_RULES,
                'sort_order' => 20,
                'position' => 20,
                'forms' => $forms,
            ],
            'company' => [
                'type' => 'static',
                'label' => 'Company',
                'input' => 'text',
                'required' => false,
                'validate_rules' => '{"max_text_length":255}',
                'sort_order' => 30,
                'position' => 30,
                'forms' => $forms,
            ],
            'street' => [
                'type' => 'static',
                'label' => 'Street Address',
                'input' => 'multiline',
                'multiline_count' => 2,
                'validate_rules' => self::NAME_RULES,
                'sort_order' => 40,
                'position' => 40,
                'forms' => $forms,
            ],
            'city' => [
                'type' => 'static',
                'label' => 'City',
                'input' => 'text',
                'validate_rules' => self::NAME_RULES,
                'sort_order' => 50,
                'position' => 50,
                'forms' => $forms,
            ],
            'country_id' => [
                'type' => 'static',
                'label' => 'Country',
                'input' => 'select',
                'source' => CountrySource::class,
                'sort_order' => 60,
                'position' => 60,
                'forms' => $forms,
            ],
            'region' => [
                'type' => 'static',
                'label' => 'State/Province',
                'input' => 'text',
                'required' => false,
                'sort_order' => 70,
                'position' => 70,
                'forms' => $forms,
            ],
            'region_id' => [
                'type' => 'static',
                'label' => 'State/Province',
                'input' => 'hidden',
                'source' => RegionSource::class,
                'required' => false,
                'sort_order' => 70,
                'position' => 70,
                'forms' => $forms,
            ],
            'postcode' => [
                'type' => 'static',
                'label' => 'Zip/Postal Code',
                'input' => 'text',
                'required' => false,
                'validate_rules' => '[]',
                'data' => Postcode::class,
                'sort_order' => 80,
                'position' => 80,
                'forms' => $forms,
            ],
            'telephone' => [
                'type' => 'static',
                'label' => 'Phone Number',
                'input' => 'text',
                'validate_rules' => self::NAME_RULES,
                'sort_order' => 90,
                'position' => 90,
                'forms' => $forms,
            ],
            'vat_id' => [
                'type' => 'static',
                'label' => 'VAT Number',
                'input' => 'text',
                'required' => false,
                'validate_rules' => '{"max_text_length":255}',
                'sort_order' => 100,
                'position' => 100,
                'forms' => $forms,
            ],
        ];
    }
}
