<?php

declare(strict_types=1);

namespace MageOS\Company\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Api\Data\CompanySearchResultsInterface;

/**
 * @api
 */
interface CompanyRepositoryInterface
{
    /**
     * Create or update a company.
     *
     * @param \MageOS\Company\Api\Data\CompanyInterface $company
     * @return \MageOS\Company\Api\Data\CompanyInterface
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(CompanyInterface $company): CompanyInterface;

    /**
     * Get company by entity ID.
     *
     * @param int $companyId
     * @return \MageOS\Company\Api\Data\CompanyInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $companyId): CompanyInterface;

    /**
     * Get company by external (ERP) code.
     *
     * @param string $erpCode
     * @return \MageOS\Company\Api\Data\CompanyInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getByErpCode(string $erpCode): CompanyInterface;

    /**
     * Get companies matching the search criteria.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \MageOS\Company\Api\Data\CompanySearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): CompanySearchResultsInterface;

    /**
     * Delete a company.
     *
     * @param \MageOS\Company\Api\Data\CompanyInterface $company
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(CompanyInterface $company): bool;

    /**
     * Delete a company by entity ID.
     *
     * @param int $companyId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $companyId): bool;
}
