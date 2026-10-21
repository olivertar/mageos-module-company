<?php

declare(strict_types=1);

namespace MageOS\Company\Test\Unit\Model\Validator;

use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Directory\Model\Country;
use Magento\Directory\Model\CountryFactory;
use Magento\Directory\Model\Region;
use Magento\Directory\Model\RegionFactory;
use MageOS\Company\Api\Data\CompanyAddressInterface;
use MageOS\Company\Model\Validator\AddressValidator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class AddressValidatorTest extends TestCase
{
    private const KNOWN_COUNTRIES = ['ES', 'US'];

    /**
     * @var DirectoryHelper|MockObject
     */
    private $directoryHelper;

    /**
     * @var Region|MockObject
     */
    private $region;

    /**
     * @var ?string
     */
    private ?string $loadedCountry = null;

    /**
     * @var AddressValidator
     */
    private AddressValidator $validator;

    protected function setUp(): void
    {
        $this->directoryHelper = $this->createMock(DirectoryHelper::class);

        $country = $this->createMock(Country::class);
        $country->method('loadByCode')->willReturnCallback(
            function (string $code) use ($country) {
                $this->loadedCountry = in_array($code, self::KNOWN_COUNTRIES, true) ? $code : null;

                return $country;
            }
        );
        $country->method('getId')->willReturnCallback(fn() => $this->loadedCountry);
        $countryFactory = $this->createMock(CountryFactory::class);
        $countryFactory->method('create')->willReturn($country);

        $this->region = $this->createPartialMock(Region::class, ['load', 'getId']);
        $this->region->method('load')->willReturnSelf();
        $regionFactory = $this->createMock(RegionFactory::class);
        $regionFactory->method('create')->willReturn($this->region);

        $this->validator = new AddressValidator($this->directoryHelper, $countryFactory, $regionFactory);
    }

    public function testValidAddressHasNoErrors(): void
    {
        $this->directoryHelper->method('isZipCodeOptional')->willReturn(false);
        $this->region->method('getId')->willReturn('130');
        $this->region->setData('country_id', 'ES');

        $this->assertSame([], $this->messages($this->address(['region_id' => 130])));
    }

    public function testRequiredFieldsAreReported(): void
    {
        $address = $this->address([
            'parent_id' => null,
            'firstname' => '',
            'lastname' => ' ',
            'street' => [],
            'city' => '',
            'telephone' => '',
            'country_id' => '',
        ]);

        $this->assertEqualsCanonicalizing(
            [
                '"parent_id" is required.',
                '"firstname" is required.',
                '"lastname" is required.',
                '"city" is required.',
                '"telephone" is required.',
                '"street" is required.',
                '"country_id" is required.',
            ],
            $this->messages($address)
        );
    }

    public function testUnknownCountryIsReported(): void
    {
        $this->assertSame(['Country "XX" does not exist.'], $this->messages($this->address(['country_id' => 'XX'])));
    }

    public function testRegionAndPostcodeRequiredByCountry(): void
    {
        $this->directoryHelper->method('isRegionRequired')->with('ES')->willReturn(true);
        $this->directoryHelper->method('isZipCodeOptional')->with('ES')->willReturn(false);

        $this->assertEqualsCanonicalizing(
            ['"region_id" is required.', '"postcode" is required.'],
            $this->messages($this->address(['postcode' => '']))
        );
    }

    public function testRegionOfAnotherCountryIsReported(): void
    {
        $this->directoryHelper->method('isZipCodeOptional')->willReturn(false);
        $this->region->method('getId')->willReturn('12');
        $this->region->setData('country_id', 'US');

        $this->assertSame(
            ['Region "12" is not valid for country "ES".'],
            $this->messages($this->address(['region_id' => 12]))
        );
    }

    /**
     * @param array<string,mixed> $fields
     * @return CompanyAddressInterface&MockObject
     */
    private function address(array $fields): CompanyAddressInterface&MockObject
    {
        $fields += [
            'parent_id' => 5,
            'firstname' => 'Ana',
            'lastname' => 'García',
            'street' => ['Calle Mayor 1'],
            'city' => 'Madrid',
            'telephone' => '+34 600 000 000',
            'country_id' => 'ES',
            'region_id' => null,
            'region' => null,
            'postcode' => '28001',
        ];

        $address = $this->createMock(CompanyAddressInterface::class);
        $address->method('getParentId')->willReturn($fields['parent_id']);
        $address->method('getFirstname')->willReturn($fields['firstname']);
        $address->method('getLastname')->willReturn($fields['lastname']);
        $address->method('getStreet')->willReturn($fields['street']);
        $address->method('getCity')->willReturn($fields['city']);
        $address->method('getTelephone')->willReturn($fields['telephone']);
        $address->method('getCountryId')->willReturn($fields['country_id']);
        $address->method('getRegionId')->willReturn($fields['region_id']);
        $address->method('getRegion')->willReturn($fields['region']);
        $address->method('getPostcode')->willReturn($fields['postcode']);

        return $address;
    }

    /**
     * @param CompanyAddressInterface $address
     * @return string[]
     */
    private function messages(CompanyAddressInterface $address): array
    {
        return array_map('strval', $this->validator->validate($address));
    }
}
