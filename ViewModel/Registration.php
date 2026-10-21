<?php

declare(strict_types=1);

namespace MageOS\Company\ViewModel;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Company\Api\Data\CompanyAddressInterface;
use MageOS\Company\Controller\Register\AbstractRegister;
use MageOS\Company\Model\Company;
use MageOS\Company\Model\CompanyAddress;
use MageOS\Company\Model\Config;
use MageOS\Company\Setup\CompanySetup;

/**
 * Company registration form: fields from the company_register and company_address_register forms.
 */
class Registration implements ArgumentInterface
{
    /**
     * Address attributes rendered by dedicated widgets instead of the generic field renderer.
     */
    private const ADDRESS_SPECIAL_FIELDS = [
        CompanyAddressInterface::COUNTRY_ID,
        CompanyAddressInterface::REGION,
        CompanyAddressInterface::REGION_ID,
    ];

    /**
     * @var array<string,mixed>|null
     */
    private ?array $formData = null;

    /**
     * @param AttributeFields $attributeFields
     * @param CustomerSession $customerSession
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param UrlInterface $urlBuilder
     * @param DataPersistorInterface $dataPersistor
     */
    public function __construct(
        private readonly AttributeFields $attributeFields,
        private readonly CustomerSession $customerSession,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly UrlInterface $urlBuilder,
        private readonly DataPersistorInterface $dataPersistor
    ) {
    }

    /**
     * Whether the applicant is a logged in customer (no account fields needed).
     *
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        return $this->customerSession->isLoggedIn();
    }

    /**
     * Company fields.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getCompanyFields(): array
    {
        $values = $this->getFormData()['company'] ?? [];

        return $this->attributeFields->getFields(
            Company::ENTITY,
            CompanySetup::FORM_COMPANY_REGISTER,
            'company',
            is_array($values) ? $values : []
        );
    }

    /**
     * Legal address fields, except country and region.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getAddressFields(): array
    {
        $values = $this->getFormData()['address'] ?? [];
        $values = is_array($values) ? $values : [];
        foreach (['firstname', 'lastname'] as $code) {
            $values[$code] = $values[$code] ?? $this->getCustomerValue($code);
        }

        return $this->attributeFields->getFields(
            CompanyAddress::ENTITY,
            CompanySetup::FORM_ADDRESS_REGISTER,
            'address',
            $values,
            self::ADDRESS_SPECIAL_FIELDS
        );
    }

    /**
     * Shared field helper (country/region widget).
     *
     * @return AttributeFields
     */
    public function getAttributeFields(): AttributeFields
    {
        return $this->attributeFields;
    }

    /**
     * Posted value of a field, kept after a failed submit.
     *
     * @param string|null $scope
     * @param string $code
     * @return mixed
     */
    public function getValue(?string $scope, string $code): mixed
    {
        $data = $this->getFormData();
        if ($scope !== null) {
            $data = is_array($data[$scope] ?? null) ? $data[$scope] : [];
        }

        return $data[$code] ?? null;
    }

    /**
     * Default first/last name of the address contact.
     *
     * @param string $code
     * @return string
     */
    public function getCustomerValue(string $code): string
    {
        if (!$this->isLoggedIn()) {
            return '';
        }
        $customer = $this->customerSession->getCustomer();

        return (string)$customer->getData($code);
    }

    /**
     * Whether terms must be accepted.
     *
     * @return bool
     */
    public function isTermsEnabled(): bool
    {
        return $this->config->isTermsEnabled($this->getWebsiteId());
    }

    /**
     * Terms text (admin-defined HTML).
     *
     * @return string
     */
    public function getTermsText(): string
    {
        return $this->config->getTermsText($this->getWebsiteId());
    }

    /**
     * Minimum customer password length.
     *
     * @return int
     */
    public function getMinimumPasswordLength(): int
    {
        return (int)$this->scopeConfig->getValue('customer/password/minimum_password_length');
    }

    /**
     * Number of character classes a customer password needs.
     *
     * @return int
     */
    public function getRequiredCharacterClassesNumber(): int
    {
        return (int)$this->scopeConfig->getValue('customer/password/required_character_classes_number');
    }

    /**
     * Form action.
     *
     * @return string
     */
    public function getPostUrl(): string
    {
        return $this->urlBuilder->getUrl('company/register/post', ['_secure' => true]);
    }

    /**
     * Login URL.
     *
     * @return string
     */
    public function getLoginUrl(): string
    {
        return $this->urlBuilder->getUrl('customer/account/login', ['_secure' => true]);
    }

    /**
     * Posted data kept in the session after a failed submit (read once).
     *
     * @return array<string,mixed>
     */
    private function getFormData(): array
    {
        if ($this->formData === null) {
            $data = $this->dataPersistor->get(AbstractRegister::SESSION_FORM_DATA);
            $this->dataPersistor->clear(AbstractRegister::SESSION_FORM_DATA);
            $this->formData = is_array($data) ? $data : [];
        }

        return $this->formData;
    }

    /**
     * Current website ID.
     *
     * @return int
     */
    private function getWebsiteId(): int
    {
        return (int)$this->storeManager->getWebsite()->getId();
    }
}
