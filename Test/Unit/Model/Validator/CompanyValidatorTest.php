<?php

declare(strict_types=1);

namespace MageOS\Company\Test\Unit\Model\Validator;

use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute as EavAttribute;
use Magento\Framework\Api\AttributeValue;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Model\Config;
use MageOS\Company\Model\ResourceModel\Company as CompanyResource;
use MageOS\Company\Model\ResourceModel\CompanyAddress as CompanyAddressResource;
use MageOS\Company\Model\Validator\CompanyValidator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class CompanyValidatorTest extends TestCase
{
    /**
     * @var CompanyResource|MockObject
     */
    private $companyResource;

    /**
     * @var CompanyAddressResource|MockObject
     */
    private $addressResource;

    /**
     * @var StoreManagerInterface|MockObject
     */
    private $storeManager;

    /**
     * @var Config|MockObject
     */
    private $config;

    /**
     * @var string
     */
    private string $vatValidation = Config::VAT_VALIDATION_FORMAT;

    /**
     * @var CompanyValidator
     */
    private CompanyValidator $validator;

    protected function setUp(): void
    {
        $this->companyResource = $this->createMock(CompanyResource::class);
        $this->addressResource = $this->createMock(CompanyAddressResource::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->storeManager->method('getWebsite')->willReturnCallback(
            function (int $websiteId) {
                if ($websiteId !== 1) {
                    throw new NoSuchEntityException(__('no website'));
                }

                return $this->createMock(WebsiteInterface::class);
            }
        );

        $eavConfig = $this->createMock(EavConfig::class);
        $eavConfig->method('getEntityAttributes')->willReturn([
            'name' => $this->attribute(true, true),
            'legal_name' => $this->attribute(false, true),
            'vat_id' => $this->attribute(false, true),
            'website_url' => $this->attribute(false, false),
        ]);

        $this->config = $this->createMock(Config::class);
        $this->config->method('getVatValidation')->willReturnCallback(fn () => $this->vatValidation);

        $this->validator = new CompanyValidator(
            $this->companyResource,
            $this->addressResource,
            $eavConfig,
            $this->storeManager,
            $this->config
        );
    }

    public function testValidCompanyHasNoErrors(): void
    {
        $company = $this->company(
            ['legal_name' => 'ACME S.L.', 'vat_id' => 'ES-B12.345.678', 'website_url' => 'https://acme.test']
        );

        $this->assertSame([], $this->messages($company));
    }

    public function testRequiredFieldsAreReported(): void
    {
        $company = $this->company([], ['name' => '', 'email' => '', 'website_id' => null]);

        $this->assertEqualsCanonicalizing(
            [
                '"name" is required.',
                '"website_id" is required.',
                '"email" is required.',
                '"legal_name" is required.',
                '"vat_id" is required.',
            ],
            $this->messages($company)
        );
    }

    public function testVatFormatIsNotCheckedWhenValidationIsDisabled(): void
    {
        $this->vatValidation = Config::VAT_VALIDATION_NONE;
        $company = $this->company(['legal_name' => 'ACME', 'vat_id' => '12'], []);

        $this->assertSame([], $this->messages($company));
    }

    public function testInvalidFormatsAreReported(): void
    {
        $company = $this->company(
            ['legal_name' => 'ACME', 'vat_id' => '12', 'website_url' => 'javascript:alert(1)'],
            ['email' => 'not-an-email', 'website_id' => 99, 'status' => 9]
        );

        $this->assertEqualsCanonicalizing(
            [
                'Website with ID "99" does not exist.',
                '"not-an-email" is not a valid email address.',
                'Status "9" is not valid.',
                '"12" is not a valid VAT number.',
                '"javascript:alert(1)" is not a valid URL.',
            ],
            $this->messages($company)
        );
    }

    public function testDuplicateErpCodeAndEmailAreReported(): void
    {
        $this->companyResource->method('isErpCodeUsed')->with('ERP-1', 5)->willReturn(true);
        $this->companyResource->method('isEmailUsed')->with('info@acme.test', 1, 5)->willReturn(true);
        $company = $this->company(['legal_name' => 'ACME', 'vat_id' => 'B12345678'], ['id' => 5, 'erp' => 'ERP-1']);

        $this->assertEqualsCanonicalizing(
            [
                'A company with the email "info@acme.test" already exists in this website.',
                'The ERP code "ERP-1" is already assigned to another company.',
            ],
            $this->messages($company)
        );
    }

    public function testDefaultAddressMustBelongToCompany(): void
    {
        $this->addressResource->method('belongsToCompany')->willReturnMap([[10, 5, true], [11, 5, false]]);
        $company = $this->company(
            ['legal_name' => 'ACME', 'vat_id' => 'B12345678'],
            ['id' => 5, 'default_billing' => 10, 'default_shipping' => 11]
        );

        $this->assertSame(
            ['"default_shipping": address "11" does not belong to this company.'],
            $this->messages($company)
        );
    }

    /**
     * @param bool $isStatic
     * @param bool $isRequired
     * @return EavAttribute&MockObject
     */
    private function attribute(bool $isStatic, bool $isRequired): EavAttribute&MockObject
    {
        $attribute = $this->createPartialMock(EavAttribute::class, ['isStatic', 'getIsRequired']);
        $attribute->method('isStatic')->willReturn($isStatic);
        $attribute->method('getIsRequired')->willReturn($isRequired);

        return $attribute;
    }

    /**
     * @param array<string,mixed> $customAttributes
     * @param array<string,mixed> $fields
     * @return CompanyInterface&MockObject
     */
    private function company(array $customAttributes, array $fields = []): CompanyInterface&MockObject
    {
        $fields += [
            'id' => null,
            'name' => 'ACME',
            'email' => 'info@acme.test',
            'website_id' => 1,
            'status' => CompanyInterface::STATUS_PENDING,
            'erp' => null,
            'default_billing' => null,
            'default_shipping' => null,
        ];
        $company = $this->createMock(CompanyInterface::class);
        $company->method('getId')->willReturn($fields['id']);
        $company->method('getName')->willReturn($fields['name']);
        $company->method('getEmail')->willReturn($fields['email']);
        $company->method('getWebsiteId')->willReturn($fields['website_id']);
        $company->method('getStatus')->willReturn($fields['status']);
        $company->method('getCompanyId')->willReturn($fields['erp']);
        $company->method('getDefaultBilling')->willReturn($fields['default_billing']);
        $company->method('getDefaultShipping')->willReturn($fields['default_shipping']);
        $company->method('getLegalAddress')->willReturn(null);
        $company->method('getCustomAttribute')->willReturnCallback(
            static function (string $code) use ($customAttributes) {
                if (!array_key_exists($code, $customAttributes)) {
                    return null;
                }

                return (new AttributeValue())->setAttributeCode($code)->setValue($customAttributes[$code]);
            }
        );

        return $company;
    }

    /**
     * @param CompanyInterface $company
     * @return string[]
     */
    private function messages(CompanyInterface $company): array
    {
        return array_map('strval', $this->validator->validate($company));
    }
}
