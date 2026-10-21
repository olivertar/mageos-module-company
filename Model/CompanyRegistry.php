<?php

declare(strict_types=1);

namespace MageOS\Company\Model;

/**
 * Request-level cache of loaded companies, shared by the services that can make a cached company stale
 * (e.g. deleting an address clears the company default address columns in the database).
 */
class CompanyRegistry
{
    /**
     * @var Company[]
     */
    private array $companies = [];

    /**
     * Get a cached company.
     *
     * @param int $companyId
     * @return Company|null
     */
    public function get(int $companyId): ?Company
    {
        return $this->companies[$companyId] ?? null;
    }

    /**
     * Cache a company.
     *
     * @param Company $company
     * @return void
     */
    public function push(Company $company): void
    {
        $this->companies[(int)$company->getId()] = $company;
    }

    /**
     * Drop a company from the cache.
     *
     * @param int|null $companyId
     * @return void
     */
    public function remove(?int $companyId): void
    {
        unset($this->companies[(int)$companyId]);
    }
}
