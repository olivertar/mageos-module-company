<?php

declare(strict_types=1);

namespace MageOS\Company\ViewModel;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Directory\Model\CountryFactory;
use Magento\Eav\Model\Attribute as EavAttribute;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use MageOS\Company\Api\CompanyAddressRepositoryInterface;
use MageOS\Company\Api\CompanyAuthorizationInterface;
use MageOS\Company\Api\CompanyManagementInterface;
use MageOS\Company\Api\Data\CompanyAddressInterface;
use MageOS\Company\Api\Data\CompanyCustomerInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Controller\Account\EditPost;
use MageOS\Company\Controller\Address\FormPost;
use MageOS\Company\Model\Authorization\CompanyContext;
use MageOS\Company\Model\Company;
use MageOS\Company\Model\CompanyAddress;
use MageOS\Company\Model\FormFactory;
use MageOS\Company\Model\Invitation;
use MageOS\Company\Model\Invitation\InvitationManager;
use MageOS\Company\Model\Status\TransitionPolicy;
use MageOS\Company\Setup\CompanySetup;

/**
 * Storefront company area: profile, addresses and users of the logged in customer's company.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class CompanyAccount implements ArgumentInterface
{

    /**
     * @var CompanyInterface|null|false
     */
    private $company = false;

    /**
     * @param CustomerSession $customerSession
     * @param CompanyContext $companyContext
     * @param CompanyAuthorizationInterface $authorization
     * @param CompanyManagementInterface $companyManagement
     * @param CompanyAddressRepositoryInterface $addressRepository
     * @param CustomerRepositoryInterface $customerRepository
     * @param InvitationManager $invitationManager
     * @param AttributeFields $attributeFields
     * @param FormFactory $formFactory
     * @param TransitionPolicy $transitionPolicy
     * @param CountryFactory $countryFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param DataPersistorInterface $dataPersistor
     * @param RequestInterface $request
     * @param UrlInterface $urlBuilder
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly CompanyContext $companyContext,
        private readonly CompanyAuthorizationInterface $authorization,
        private readonly CompanyManagementInterface $companyManagement,
        private readonly CompanyAddressRepositoryInterface $addressRepository,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly InvitationManager $invitationManager,
        private readonly AttributeFields $attributeFields,
        private readonly FormFactory $formFactory,
        private readonly TransitionPolicy $transitionPolicy,
        private readonly CountryFactory $countryFactory,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly RequestInterface $request,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * Company of the logged in customer.
     *
     * @return CompanyInterface|null
     */
    public function getCompany(): ?CompanyInterface
    {
        if ($this->company === false) {
            try {
                $this->company = $this->companyContext->getCompany($this->getCustomerId());
            } catch (LocalizedException) {
                $this->company = null;
            }
        }

        return $this->company;
    }

    /**
     * Logged in customer ID.
     *
     * @return int
     */
    public function getCustomerId(): int
    {
        return (int)$this->customerSession->getCustomerId();
    }

    /**
     * Whether the customer may use a resource (CompanyAuthorizationInterface::RESOURCE_*).
     *
     * @param string $resource
     * @return bool
     */
    public function isAllowed(string $resource): bool
    {
        return $this->authorization->isAllowed($this->getCustomerId(), $resource);
    }

    /**
     * Company status label.
     *
     * @return string
     */
    public function getStatusLabel(): string
    {
        $company = $this->getCompany();

        return $company ? $this->transitionPolicy->getLabel($company->getStatus()) : '';
    }

    /**
     * Role label.
     *
     * @param string|null $role
     * @return string
     */
    public function getRoleLabel(?string $role): string
    {
        return $role === CompanyCustomerInterface::ROLE_ADMIN ? (string)__('Company Admin') : (string)__('User');
    }

    /**
     * Role of the logged in customer.
     *
     * @return string
     */
    public function getMyRoleLabel(): string
    {
        return $this->getRoleLabel($this->companyContext->getRole($this->getCustomerId()));
    }

    /**
     * Profile values of the company_account_edit form, as label/value pairs (option values as text).
     *
     * @return array<int,array{label: string, value: string}>
     */
    public function getProfile(): array
    {
        /** @var Company|null $company */
        $company = $this->getCompany();
        if ($company === null) {
            return [];
        }
        $rows = [];
        if ($company->getCompanyId()) {
            $rows[] = ['label' => (string)__('Customer Code'), 'value' => (string)$company->getCompanyId()];
        }
        $form = $this->formFactory->create()->setFormCode(CompanySetup::FORM_COMPANY_ACCOUNT_EDIT);
        /** @var EavAttribute $attribute */
        foreach ($form->getAttributes() as $code => $attribute) {
            $value = $company->getData($code);
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            if ($attribute->usesSource()) {
                $texts = [];
                foreach ((array)(is_string($value) ? explode(',', $value) : $value) as $optionValue) {
                    $texts[] = (string)$attribute->getSource()->getOptionText($optionValue);
                }
                $value = implode(', ', array_filter($texts));
            }
            $rows[] = [
                'label' => (string)__($attribute->getStoreLabel() ?: $attribute->getFrontendLabel()),
                'value' => (string)$value,
            ];
        }

        return $rows;
    }

    /**
     * Fields of the company edit form, with the current or just posted values.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getCompanyFields(): array
    {
        /** @var Company|null $company */
        $company = $this->getCompany();
        $values = $company ? $company->getData() : [];
        $posted = $this->dataPersistor->get(EditPost::DATA_PERSISTOR_KEY);
        $this->dataPersistor->clear(EditPost::DATA_PERSISTOR_KEY);
        if (is_array($posted)) {
            $values = array_merge($values, $posted);
        }

        return $this->attributeFields->getFields(
            Company::ENTITY,
            CompanySetup::FORM_COMPANY_ACCOUNT_EDIT,
            'company',
            $values
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
     * Company addresses.
     *
     * @return CompanyAddressInterface[]
     */
    public function getAddresses(): array
    {
        $company = $this->getCompany();
        if ($company === null) {
            return [];
        }
        $criteria = $this->searchCriteriaBuilder
            ->addFilter(CompanyAddressInterface::PARENT_ID, (int)$company->getId())
            ->create();

        return $this->addressRepository->getList($criteria)->getItems();
    }

    /**
     * Default types (labels) of an address.
     *
     * @param CompanyAddressInterface $address
     * @return string[]
     */
    public function getDefaultLabels(CompanyAddressInterface $address): array
    {
        $labels = [];
        foreach ($this->getDefaultIds() as $type => $addressId) {
            if ($addressId === (int)$address->getId()) {
                $labels[$type] = $this->getDefaultTypes()[$type];
            }
        }

        return $labels;
    }

    /**
     * Default types an address is not yet, as type → label.
     *
     * @param CompanyAddressInterface $address
     * @return array<string,string>
     */
    public function getAvailableDefaults(CompanyAddressInterface $address): array
    {
        $types = [];
        foreach ($this->getDefaultIds() as $type => $addressId) {
            if ($addressId !== (int)$address->getId()) {
                $types[$type] = (string)__('Set as %1', $this->getDefaultTypes()[$type]);
            }
        }

        return $types;
    }

    /**
     * Address as text lines.
     *
     * @param CompanyAddressInterface $address
     * @return string[]
     */
    public function formatAddress(CompanyAddressInterface $address): array
    {
        /** @var CompanyAddress $address */
        $country = $this->countryFactory->create()->loadByCode((string)$address->getCountryId());
        $cityLine = implode(', ', array_filter([
            (string)$address->getCity(),
            (string)$address->getRegion(),
            (string)$address->getPostcode(),
        ]));

        return array_values(array_filter(array_merge(
            [
                (string)$address->getData('label'),
                trim($address->getFirstname() . ' ' . $address->getLastname()),
                (string)$address->getCompany(),
            ],
            $address->getStreet(),
            [$cityLine, (string)$country->getName(), (string)$address->getTelephone()]
        ), static fn (string $line): bool => trim($line) !== ''));
    }

    /**
     * Address being edited, if it belongs to the company.
     *
     * @return CompanyAddressInterface|null
     */
    public function getEditAddress(): ?CompanyAddressInterface
    {
        $company = $this->getCompany();
        $addressId = (int)$this->request->getParam('id');
        if ($company === null || !$addressId) {
            return null;
        }
        try {
            $address = $this->addressRepository->getById($addressId);
        } catch (NoSuchEntityException) {
            return null;
        }

        return (int)$address->getParentId() === (int)$company->getId() ? $address : null;
    }

    /**
     * Values of the address form: the edited address, or what was just posted.
     *
     * @return array<string,mixed>
     */
    public function getAddressValues(): array
    {
        /** @var CompanyAddress|null $address */
        $address = $this->getEditAddress();
        $values = $address ? $address->getData() : [];
        $posted = $this->dataPersistor->get(FormPost::DATA_PERSISTOR_KEY);
        $this->dataPersistor->clear(FormPost::DATA_PERSISTOR_KEY);
        if (is_array($posted) && is_array($posted['address'] ?? null)) {
            $values = array_merge($values, $posted['address']);
        }

        return $values;
    }

    /**
     * Address form fields, except country and region.
     *
     * @param array<string,mixed> $values
     * @return array<int,array<string,mixed>>
     */
    public function getAddressFields(array $values): array
    {
        return $this->attributeFields->getFields(
            CompanyAddress::ENTITY,
            CompanySetup::FORM_ADDRESS_EDIT,
            'address',
            $values,
            [CompanyAddressInterface::COUNTRY_ID, CompanyAddressInterface::REGION, CompanyAddressInterface::REGION_ID]
        );
    }

    /**
     * Default types the address form can set, as type → label.
     *
     * @return array<string,string>
     */
    public function getDefaultTypes(): array
    {
        return [
            'default_billing' => (string)__('Default Billing Address'),
            'default_shipping' => (string)__('Default Shipping Address'),
            'legal_address' => (string)__('Legal Address'),
        ];
    }

    /**
     * Company users with their customer data.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getUsers(): array
    {
        $company = $this->getCompany();
        if ($company === null) {
            return [];
        }
        $users = [];
        foreach ($this->companyManagement->getCustomers((int)$company->getId()) as $relation) {
            $users[] = $this->describeUser($company, $relation);
        }

        return $users;
    }

    /**
     * User being edited, if it belongs to the company.
     *
     * @return array<string,mixed>|null
     */
    public function getEditUser(): ?array
    {
        $customerId = (int)$this->request->getParam('id');
        foreach ($this->getUsers() as $user) {
            if ($user['customer_id'] === $customerId) {
                return $user;
            }
        }

        return null;
    }

    /**
     * Open invitations of the company.
     *
     * @return Invitation[]
     */
    public function getInvitations(): array
    {
        $company = $this->getCompany();

        return $company ? $this->invitationManager->getOpen((int)$company->getId()) : [];
    }

    /**
     * Role options.
     *
     * @return array<string,string>
     */
    public function getRoleOptions(): array
    {
        return [
            CompanyCustomerInterface::ROLE_USER => $this->getRoleLabel(CompanyCustomerInterface::ROLE_USER),
            CompanyCustomerInterface::ROLE_ADMIN => $this->getRoleLabel(CompanyCustomerInterface::ROLE_ADMIN),
        ];
    }

    /**
     * Storefront URL.
     *
     * @param string $route
     * @param array<string,mixed> $params
     * @return string
     */
    public function getUrl(string $route, array $params = []): string
    {
        return $this->urlBuilder->getUrl($route, $params + ['_secure' => true]);
    }

    /**
     * Default address IDs of the company by type.
     *
     * @return array<string,int|null>
     */
    private function getDefaultIds(): array
    {
        $company = $this->getCompany();

        return [
            'default_billing' => $company?->getDefaultBilling(),
            'default_shipping' => $company?->getDefaultShipping(),
            'legal_address' => $company?->getLegalAddress(),
        ];
    }

    /**
     * User row for templates.
     *
     * @param CompanyInterface $company
     * @param CompanyCustomerInterface $relation
     * @return array<string,mixed>
     */
    private function describeUser(CompanyInterface $company, CompanyCustomerInterface $relation): array
    {
        $customerId = (int)$relation->getCustomerId();
        try {
            $customer = $this->customerRepository->getById($customerId);
            $name = trim($customer->getFirstname() . ' ' . $customer->getLastname());
            $email = (string)$customer->getEmail();
        } catch (NoSuchEntityException) {
            $name = (string)__('Former user');
            $email = '';
        }

        return [
            'customer_id' => $customerId,
            'name' => $name,
            'email' => $email,
            'role' => $relation->getRole(),
            'job_title' => (string)$relation->getJobTitle(),
            'active' => $relation->getStatus() === CompanyCustomerInterface::STATUS_ACTIVE,
            'is_super_user' => $company->getSuperUserId() === $customerId,
            'is_self' => $customerId === $this->getCustomerId(),
        ];
    }
}
