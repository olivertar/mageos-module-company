<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Validator;

use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Model\Company;
use MageOS\Company\Model\Config;
use MageOS\Company\Model\ResourceModel\Company as CompanyResource;
use MageOS\Company\Model\ResourceModel\CompanyAddress as CompanyAddressResource;

/**
 * Business validation of a company before it is persisted.
 */
class CompanyValidator
{
    private const ERP_CODE_MAX_LENGTH = 64;
    private const TEXT_MAX_LENGTH = 255;

    /**
     * @param CompanyResource $companyResource
     * @param CompanyAddressResource $addressResource
     * @param EavConfig $eavConfig
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     */
    public function __construct(
        private readonly CompanyResource $companyResource,
        private readonly CompanyAddressResource $addressResource,
        private readonly EavConfig $eavConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config
    ) {
    }

    /**
     * Validate a company.
     *
     * @param CompanyInterface $company
     * @return Phrase[] Empty when valid.
     */
    public function validate(CompanyInterface $company): array
    {
        return array_merge(
            $this->validateStaticFields($company),
            $this->validateEavAttributes($company),
            $this->validateAddresses($company)
        );
    }

    /**
     * Validate static (company_entity) fields.
     *
     * @param CompanyInterface $company
     * @return Phrase[]
     */
    private function validateStaticFields(CompanyInterface $company): array
    {
        $errors = [];
        $companyId = $company->getId();

        $name = trim((string)$company->getName());
        if ($name === '') {
            $errors[] = __('"%1" is required.', 'name');
        } elseif (mb_strlen($name) > self::TEXT_MAX_LENGTH) {
            $errors[] = __('"%1" cannot be longer than %2 characters.', 'name', self::TEXT_MAX_LENGTH);
        }

        $websiteId = $company->getWebsiteId();
        if (!$websiteId) {
            $errors[] = __('"%1" is required.', 'website_id');
        } elseif (!$this->websiteExists($websiteId)) {
            $errors[] = __('Website with ID "%1" does not exist.', $websiteId);
        }

        $email = trim((string)$company->getEmail());
        if ($email === '') {
            $errors[] = __('"%1" is required.', 'email');
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = __('"%1" is not a valid email address.', $email);
        } elseif ($websiteId && $this->companyResource->isEmailUsed($email, $websiteId, $companyId)) {
            $errors[] = __('A company with the email "%1" already exists in this website.', $email);
        }

        if (!in_array($company->getStatus(), $this->getAllowedStatuses(), true)) {
            $errors[] = __('Status "%1" is not valid.', $company->getStatus());
        }

        $erpCode = trim((string)$company->getCompanyId());
        if ($erpCode !== '') {
            if (mb_strlen($erpCode) > self::ERP_CODE_MAX_LENGTH) {
                $errors[] = __(
                    '"%1" cannot be longer than %2 characters.',
                    'company_id',
                    self::ERP_CODE_MAX_LENGTH
                );
            } elseif ($this->companyResource->isErpCodeUsed($erpCode, $companyId)) {
                $errors[] = __('The ERP code "%1" is already assigned to another company.', $erpCode);
            }
        }

        return $errors;
    }

    /**
     * Validate required and formatted EAV attributes.
     *
     * @param CompanyInterface $company
     * @return Phrase[]
     */
    private function validateEavAttributes(CompanyInterface $company): array
    {
        $errors = [];
        $websiteId = $company->getWebsiteId();
        $validateVat = $this->config->getVatValidation($websiteId) === Config::VAT_VALIDATION_FORMAT;
        foreach ($this->eavConfig->getEntityAttributes(Company::ENTITY) as $code => $attribute) {
            if ($attribute->isStatic()) {
                continue;
            }
            $value = $company->getCustomAttribute((string)$code)?->getValue();
            $isEmpty = $value === null || (is_string($value) && trim($value) === '') || $value === [];
            if ($isEmpty) {
                if ($attribute->getIsRequired()) {
                    $errors[] = __('"%1" is required.', $code);
                }
                continue;
            }
            if ($code === 'vat_id' && $validateVat && !$this->isValidVat((string)$value)) {
                $errors[] = __('"%1" is not a valid VAT number.', $value);
            }
            if ($code === 'website_url' && !$this->isValidUrl((string)$value)) {
                $errors[] = __('"%1" is not a valid URL.', $value);
            }
        }

        return $errors;
    }

    /**
     * Default/legal addresses must belong to the company.
     *
     * @param CompanyInterface $company
     * @return Phrase[]
     */
    private function validateAddresses(CompanyInterface $company): array
    {
        $errors = [];
        $addresses = [
            CompanyInterface::DEFAULT_BILLING => $company->getDefaultBilling(),
            CompanyInterface::DEFAULT_SHIPPING => $company->getDefaultShipping(),
            CompanyInterface::LEGAL_ADDRESS => $company->getLegalAddress(),
        ];
        foreach ($addresses as $field => $addressId) {
            if ($addressId === null) {
                continue;
            }
            $companyId = $company->getId();
            if ($companyId === null || !$this->addressResource->belongsToCompany($addressId, $companyId)) {
                $errors[] = __('"%1": address "%2" does not belong to this company.', $field, $addressId);
            }
        }

        return $errors;
    }

    /**
     * Basic VAT format check: 4-20 alphanumeric characters once separators are removed.
     *
     * Country specific (or VIES) validation is planned as a configurable validator.
     *
     * @param string $vat
     * @return bool
     */
    private function isValidVat(string $vat): bool
    {
        $normalized = preg_replace('/[\s.\-]/', '', $vat) ?? '';

        return (bool)preg_match('/^[A-Za-z0-9]{4,20}$/', $normalized);
    }

    /**
     * Only absolute http(s) URLs are accepted.
     *
     * @param string $url
     * @return bool
     */
    private function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $url) === 1;
    }

    /**
     * Check website existence.
     *
     * @param int $websiteId
     * @return bool
     */
    private function websiteExists(int $websiteId): bool
    {
        try {
            $this->storeManager->getWebsite($websiteId);
        } catch (LocalizedException) {
            return false;
        }

        return true;
    }

    /**
     * Allowed company statuses.
     *
     * @return int[]
     */
    private function getAllowedStatuses(): array
    {
        return [
            CompanyInterface::STATUS_PENDING,
            CompanyInterface::STATUS_APPROVED,
            CompanyInterface::STATUS_REJECTED,
            CompanyInterface::STATUS_BLOCKED,
        ];
    }
}
