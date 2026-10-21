<?php

declare(strict_types=1);

namespace MageOS\Company\Api;

use MageOS\Company\Api\Data\CompanyAddressInterface;

/**
 * Addresses of a given company (admin/integration). Every operation checks the address belongs to the company.
 *
 * @api
 */
interface CompanyAddressManagementInterface
{
    /**
     * Addresses of a company.
     *
     * @param int $companyId
     * @return \MageOS\Company\Api\Data\CompanyAddressInterface[]
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getList(int $companyId): array;

    /**
     * Address of a company.
     *
     * @param int $companyId
     * @param int $addressId
     * @return \MageOS\Company\Api\Data\CompanyAddressInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function get(int $companyId, int $addressId): CompanyAddressInterface;

    /**
     * Create (no ID) or update an address of a company.
     *
     * @param int $companyId
     * @param \MageOS\Company\Api\Data\CompanyAddressInterface $address
     * @return \MageOS\Company\Api\Data\CompanyAddressInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(int $companyId, CompanyAddressInterface $address): CompanyAddressInterface;

    /**
     * Delete an address of a company.
     *
     * @param int $companyId
     * @param int $addressId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(int $companyId, int $addressId): bool;
}
