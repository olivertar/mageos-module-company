<?php

declare(strict_types=1);

namespace MageOS\Company\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use MageOS\Company\Api\CompanyAddressManagementInterface;
use MageOS\Company\Api\CompanyAddressRepositoryInterface;
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Api\Data\CompanyAddressInterface;

/**
 * Addresses scoped to one company.
 */
class CompanyAddressManagement implements CompanyAddressManagementInterface
{
    /**
     * @param CompanyAddressRepositoryInterface $addressRepository
     * @param CompanyRepositoryInterface $companyRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        private readonly CompanyAddressRepositoryInterface $addressRepository,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getList(int $companyId): array
    {
        $this->companyRepository->getById($companyId);
        $criteria = $this->searchCriteriaBuilder
            ->addFilter(CompanyAddressInterface::PARENT_ID, $companyId)
            ->create();

        return array_values($this->addressRepository->getList($criteria)->getItems());
    }

    /**
     * @inheritDoc
     */
    public function get(int $companyId, int $addressId): CompanyAddressInterface
    {
        // same error for missing and foreign addresses: do not reveal other companies' address IDs
        try {
            $address = $this->addressRepository->getById($addressId);
        } catch (NoSuchEntityException) {
            $address = null;
        }
        if ($address === null || (int)$address->getParentId() !== $companyId) {
            throw NoSuchEntityException::doubleField('addressId', $addressId, 'companyId', $companyId);
        }

        return $address;
    }

    /**
     * @inheritDoc
     */
    public function save(int $companyId, CompanyAddressInterface $address): CompanyAddressInterface
    {
        $this->companyRepository->getById($companyId);
        if ($address->getId()) {
            $this->get($companyId, (int)$address->getId());
        }
        $address->setParentId($companyId);

        return $this->addressRepository->save($address);
    }

    /**
     * @inheritDoc
     */
    public function delete(int $companyId, int $addressId): bool
    {
        return $this->addressRepository->delete($this->get($companyId, $addressId));
    }
}
