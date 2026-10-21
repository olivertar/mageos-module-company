<?php

declare(strict_types=1);

namespace MageOS\Company\Api;

use Magento\Customer\Api\Data\CustomerInterface;
use MageOS\Company\Api\Data\CompanyAddressInterface;
use MageOS\Company\Api\Data\CompanyInterface;

/**
 * Public company application (anonymous web API). Creates a new customer, the company and its legal address.
 *
 * @api
 */
interface CompanyRegistrationInterface
{
    /**
     * Apply for a company account.
     *
     * Only the attributes of the company_register and company_address_register forms are taken from the payload;
     * the customer is always a new account of the current website.
     *
     * @param \MageOS\Company\Api\Data\CompanyInterface $company
     * @param \Magento\Customer\Api\Data\CustomerInterface $customer
     * @param string $password
     * @param \MageOS\Company\Api\Data\CompanyAddressInterface $legalAddress
     * @return \MageOS\Company\Api\Data\CompanyInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function apply(
        CompanyInterface $company,
        CustomerInterface $customer,
        string $password,
        CompanyAddressInterface $legalAddress
    ): CompanyInterface;
}
