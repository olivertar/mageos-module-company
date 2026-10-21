<?php

declare(strict_types=1);

namespace MageOS\Company\Model;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use MageOS\Company\Api\CompanyManagementInterface;
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Api\Data\CompanyAddressInterface;
use MageOS\Company\Api\Data\CompanyCustomerInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Model\CompanyCustomer\Assigner;
use MageOS\Company\Model\Management\CompanyWriter;
use MageOS\Company\Model\Registration\Registrar;
use MageOS\Company\Model\Status\SessionTerminator;
use MageOS\Company\Model\ResourceModel\CompanyCustomer as CompanyCustomerResource;
use MageOS\Company\Model\ResourceModel\CompanyCustomer\CollectionFactory as RelationCollectionFactory;

/**
 * Company users management.
 */
class CompanyManagement implements CompanyManagementInterface
{
    /**
     * @param CompanyRepositoryInterface $companyRepository
     * @param CompanyCustomerFactory $relationFactory
     * @param CompanyCustomerResource $relationResource
     * @param RelationCollectionFactory $relationCollectionFactory
     * @param Assigner $customerAssigner
     * @param Registrar $registrar
     * @param SessionTerminator $sessionTerminator
     * @param CompanyWriter $companyWriter
     */
    public function __construct(
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly CompanyCustomerFactory $relationFactory,
        private readonly CompanyCustomerResource $relationResource,
        private readonly RelationCollectionFactory $relationCollectionFactory,
        private readonly Assigner $customerAssigner,
        private readonly Registrar $registrar,
        private readonly SessionTerminator $sessionTerminator,
        private readonly CompanyWriter $companyWriter
    ) {
    }

    /**
     * @inheritDoc
     */
    public function create(CompanyInterface $company): CompanyInterface
    {
        return $this->companyWriter->create($company);
    }

    /**
     * @inheritDoc
     */
    public function update(int $companyId, CompanyInterface $company): CompanyInterface
    {
        return $this->companyWriter->update($companyId, $company);
    }

    /**
     * @inheritDoc
     */
    public function setErpCode(int $companyId, ?string $erpCode): CompanyInterface
    {
        return $this->companyWriter->setErpCode($companyId, $erpCode);
    }

    /**
     * @inheritDoc
     */
    public function register(
        CompanyInterface $company,
        CustomerInterface $customer,
        ?string $password = null,
        ?CompanyAddressInterface $legalAddress = null
    ): CompanyInterface {
        return $this->registrar->register($company, $customer, $password, $legalAddress);
    }

    /**
     * @inheritDoc
     */
    public function getByCustomerId(int $customerId): CompanyInterface
    {
        $companyId = $this->relationResource->getCompanyIdByCustomerId($customerId);
        if ($companyId === null) {
            throw new NoSuchEntityException(__('The customer "%1" does not belong to any company.', $customerId));
        }

        return $this->companyRepository->getById($companyId);
    }

    /**
     * @inheritDoc
     */
    public function assignCustomer(
        int $companyId,
        int $customerId,
        string $role = CompanyCustomerInterface::ROLE_USER,
        ?string $jobTitle = null
    ): CompanyCustomerInterface {
        return $this->customerAssigner->assign(
            $this->companyRepository->getById($companyId),
            $customerId,
            $role,
            $jobTitle
        );
    }

    /**
     * @inheritDoc
     */
    public function unassignCustomer(int $companyId, int $customerId): bool
    {
        $company = $this->companyRepository->getById($companyId);

        $relation = $this->relationFactory->create();
        $this->relationResource->load($relation, $customerId);
        if (!$relation->getCustomerId() || $relation->getCompanyEntityId() !== $companyId) {
            throw new NoSuchEntityException(
                __('The customer "%1" is not assigned to the company "%2".', $customerId, $companyId)
            );
        }
        if ($company->getSuperUserId() === $customerId) {
            throw new LocalizedException(
                __('The company super user cannot be removed. Assign another super user first.')
            );
        }
        if ($relation->getRole() === CompanyCustomerInterface::ROLE_ADMIN
            && $this->relationResource->countActiveAdmins($companyId, $customerId) === 0
        ) {
            throw new LocalizedException(__('A company must keep at least one active admin.'));
        }

        $this->relationResource->delete($relation);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function setCustomerStatus(int $companyId, int $customerId, int $status): CompanyCustomerInterface
    {
        $relation = $this->customerAssigner->setStatus(
            $this->companyRepository->getById($companyId),
            $customerId,
            $status
        );
        if ($status === CompanyCustomerInterface::STATUS_INACTIVE) {
            $this->sessionTerminator->terminateCustomer($customerId);
        }

        return $relation;
    }

    /**
     * @inheritDoc
     */
    public function getCustomers(int $companyId): array
    {
        $this->companyRepository->getById($companyId);
        $collection = $this->relationCollectionFactory->create();
        $collection->addFieldToFilter(CompanyCustomerInterface::COMPANY_ENTITY_ID, ['eq' => $companyId]);
        /** @var CompanyCustomerInterface[] $customers */
        $customers = array_values($collection->getItems());

        return $customers;
    }

    /**
     * @inheritDoc
     */
    public function setSuperUser(int $companyId, int $customerId): CompanyInterface
    {
        $company = $this->companyRepository->getById($companyId);
        $company->setSuperUserId($customerId);

        return $this->companyRepository->save($company);
    }
}
