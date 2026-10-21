<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Registration;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\Exception\State\InputMismatchException;
use MageOS\Company\Api\CompanyManagementInterface;
use MageOS\Company\Api\CompanyRegistrationInterface;
use MageOS\Company\Api\Data\CompanyAddressInterface;
use MageOS\Company\Api\Data\CompanyAddressInterfaceFactory;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Api\Data\CompanyInterfaceFactory;
use MageOS\Company\Model\Company;
use MageOS\Company\Model\CompanyAddress;
use MageOS\Company\Model\Form\FormData;
use MageOS\Company\Setup\CompanySetup;

/**
 * Anonymous company application: payloads are copied into fresh entities, limited to the registration forms.
 */
class CompanyRegistration implements CompanyRegistrationInterface
{
    /**
     * @param CompanyManagementInterface $companyManagement
     * @param CompanyInterfaceFactory $companyFactory
     * @param CompanyAddressInterfaceFactory $addressFactory
     * @param CustomerInterfaceFactory $customerFactory
     * @param FormData $formData
     */
    public function __construct(
        private readonly CompanyManagementInterface $companyManagement,
        private readonly CompanyInterfaceFactory $companyFactory,
        private readonly CompanyAddressInterfaceFactory $addressFactory,
        private readonly CustomerInterfaceFactory $customerFactory,
        private readonly FormData $formData
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(
        CompanyInterface $company,
        CustomerInterface $customer,
        string $password,
        CompanyAddressInterface $legalAddress
    ): CompanyInterface {
        /** @var Company $newCompany */
        $newCompany = $this->companyFactory->create();
        /** @var Company $company */
        $this->formData->copy($company, $newCompany, Company::ENTITY, CompanySetup::FORM_COMPANY_REGISTER);
        /** @var CompanyAddress $newAddress */
        $newAddress = $this->addressFactory->create();
        /** @var CompanyAddress $legalAddress */
        $this->formData->copy(
            $legalAddress,
            $newAddress,
            CompanyAddress::ENTITY,
            CompanySetup::FORM_ADDRESS_REGISTER
        );
        $errors = array_merge(
            $this->formData->validate($newCompany, Company::ENTITY, CompanySetup::FORM_COMPANY_REGISTER),
            $this->formData->validate($newAddress, CompanyAddress::ENTITY, CompanySetup::FORM_ADDRESS_REGISTER)
        );
        if ($errors) {
            $exception = new InputException();
            foreach ($errors as $error) {
                $exception->addError(new Phrase($error));
            }
            throw $exception;
        }

        // never an existing account: the ID, group and website of the payload are ignored
        $newCustomer = $this->customerFactory->create()
            ->setFirstname((string)$customer->getFirstname())
            ->setLastname((string)$customer->getLastname())
            ->setEmail(trim((string)$customer->getEmail()));
        try {
            return $this->companyManagement->register($newCompany, $newCustomer, $password, $newAddress);
        } catch (InputMismatchException) {
            // do not confirm to anonymous callers whether an email has an account
            throw new LocalizedException(
                __('We could not create the account with the data provided. If you already have an account, '
                    . 'sign in and apply from your account.')
            );
        }
    }
}
