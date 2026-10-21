<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Validator;

use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Directory\Model\CountryFactory;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\Phrase;
use MageOS\Company\Api\Data\CompanyAddressInterface;

/**
 * Business validation of a company address before it is persisted.
 */
class AddressValidator
{
    /**
     * @param DirectoryHelper $directoryHelper
     * @param CountryFactory $countryFactory
     * @param RegionFactory $regionFactory
     */
    public function __construct(
        private readonly DirectoryHelper $directoryHelper,
        private readonly CountryFactory $countryFactory,
        private readonly RegionFactory $regionFactory
    ) {
    }

    /**
     * Validate a company address.
     *
     * @param CompanyAddressInterface $address
     * @return Phrase[] Empty when valid.
     */
    public function validate(CompanyAddressInterface $address): array
    {
        $errors = [];

        if (!$address->getParentId()) {
            $errors[] = __('"%1" is required.', CompanyAddressInterface::PARENT_ID);
        }

        $required = [
            CompanyAddressInterface::FIRSTNAME => $address->getFirstname(),
            CompanyAddressInterface::LASTNAME => $address->getLastname(),
            CompanyAddressInterface::CITY => $address->getCity(),
            CompanyAddressInterface::TELEPHONE => $address->getTelephone(),
        ];
        foreach ($required as $field => $value) {
            if (trim((string)$value) === '') {
                $errors[] = __('"%1" is required.', $field);
            }
        }
        if ($address->getStreet() === []) {
            $errors[] = __('"%1" is required.', CompanyAddressInterface::STREET);
        }

        $countryId = trim((string)$address->getCountryId());
        if ($countryId === '') {
            $errors[] = __('"%1" is required.', CompanyAddressInterface::COUNTRY_ID);

            return $errors;
        }
        if (!$this->countryFactory->create()->loadByCode($countryId)->getId()) {
            $errors[] = __('Country "%1" does not exist.', $countryId);

            return $errors;
        }

        return array_merge($errors, $this->validateRegion($address, $countryId), $this->validatePostcode(
            $address,
            $countryId
        ));
    }

    /**
     * Validate region against the country.
     *
     * @param CompanyAddressInterface $address
     * @param string $countryId
     * @return Phrase[]
     */
    private function validateRegion(CompanyAddressInterface $address, string $countryId): array
    {
        $regionId = $address->getRegionId();
        if ($regionId !== null) {
            $region = $this->regionFactory->create()->load($regionId);
            if (!$region->getId() || $region->getCountryId() !== $countryId) {
                return [__('Region "%1" is not valid for country "%2".', $regionId, $countryId)];
            }

            return [];
        }
        if ($this->directoryHelper->isRegionRequired($countryId) && trim((string)$address->getRegion()) === '') {
            return [__('"%1" is required.', CompanyAddressInterface::REGION_ID)];
        }

        return [];
    }

    /**
     * Validate postcode presence for countries that need it.
     *
     * @param CompanyAddressInterface $address
     * @param string $countryId
     * @return Phrase[]
     */
    private function validatePostcode(CompanyAddressInterface $address, string $countryId): array
    {
        if (trim((string)$address->getPostcode()) === '' && !$this->directoryHelper->isZipCodeOptional($countryId)) {
            return [__('"%1" is required.', CompanyAddressInterface::POSTCODE)];
        }

        return [];
    }
}
