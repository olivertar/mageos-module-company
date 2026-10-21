<?php

declare(strict_types=1);

namespace MageOS\Company\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use MageOS\Company\Api\Data\CompanyAddressInterface;
use MageOS\Company\Api\Data\CompanyAddressSearchResultsInterface;

/**
 * @api
 */
interface CompanyAddressRepositoryInterface
{
    /**
     * Create or update a company address.
     *
     * @param \MageOS\Company\Api\Data\CompanyAddressInterface $address
     * @return \MageOS\Company\Api\Data\CompanyAddressInterface
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(CompanyAddressInterface $address): CompanyAddressInterface;

    /**
     * Get company address by ID.
     *
     * @param int $addressId
     * @return \MageOS\Company\Api\Data\CompanyAddressInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $addressId): CompanyAddressInterface;

    /**
     * Get company addresses matching the search criteria.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \MageOS\Company\Api\Data\CompanyAddressSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): CompanyAddressSearchResultsInterface;

    /**
     * Delete a company address.
     *
     * @param \MageOS\Company\Api\Data\CompanyAddressInterface $address
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(CompanyAddressInterface $address): bool;

    /**
     * Delete a company address by ID.
     *
     * @param int $addressId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $addressId): bool;
}
