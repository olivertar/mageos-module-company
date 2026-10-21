<?php

declare(strict_types=1);

namespace MageOS\Company\Model\CompanyCustomer;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use MageOS\Company\Api\Data\CompanyCustomerInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Model\CompanyCustomer;
use MageOS\Company\Model\CompanyCustomerFactory;
use MageOS\Company\Model\ResourceModel\CompanyCustomer as CompanyCustomerResource;

/**
 * Creates or updates the relation between a customer and a company, enforcing the role invariants:
 * the super user is always an admin and a company always keeps at least one active admin.
 */
class Assigner
{
    /**
     * @param CompanyCustomerFactory $relationFactory
     * @param CompanyCustomerResource $relationResource
     * @param AssignmentValidator $assignmentValidator
     */
    public function __construct(
        private readonly CompanyCustomerFactory $relationFactory,
        private readonly CompanyCustomerResource $relationResource,
        private readonly AssignmentValidator $assignmentValidator
    ) {
    }

    /**
     * Assign a customer to a company.
     *
     * @param CompanyInterface $company Must be persisted.
     * @param int $customerId
     * @param string $role
     * @param string|null $jobTitle Null keeps the current job title.
     * @return CompanyCustomerInterface
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function assign(
        CompanyInterface $company,
        int $customerId,
        string $role,
        ?string $jobTitle = null
    ): CompanyCustomerInterface {
        if (!in_array($role, [CompanyCustomerInterface::ROLE_ADMIN, CompanyCustomerInterface::ROLE_USER], true)) {
            throw new LocalizedException(__('Role "%1" is not valid.', $role));
        }
        $companyId = (int)$company->getId();
        if (!$companyId) {
            throw new LocalizedException(__('The company must be saved before assigning customers.'));
        }
        $this->assignmentValidator->validate($company, $customerId);

        /** @var CompanyCustomer $relation */
        $relation = $this->relationFactory->create();
        $this->relationResource->load($relation, $customerId);
        $isNew = !$relation->getCustomerId();

        if ($role !== CompanyCustomerInterface::ROLE_ADMIN) {
            if ($company->getSuperUserId() === $customerId) {
                throw new LocalizedException(__('The company super user must have the admin role.'));
            }
            if (!$isNew
                && $relation->getRole() === CompanyCustomerInterface::ROLE_ADMIN
                && $this->relationResource->countActiveAdmins($companyId, $customerId) === 0
            ) {
                throw new LocalizedException(__('A company must keep at least one active admin.'));
            }
        }

        if ($isNew) {
            $relation->setCustomerId($customerId);
            $relation->setStatus(CompanyCustomerInterface::STATUS_ACTIVE);
        }
        if ($company->getSuperUserId() === $customerId) {
            $relation->setStatus(CompanyCustomerInterface::STATUS_ACTIVE);
        }
        $relation->setCompanyEntityId($companyId);
        $relation->setRole($role);
        if ($jobTitle !== null) {
            $relation->setJobTitle($jobTitle);
        }
        $this->relationResource->save($relation);

        return $relation;
    }

    /**
     * Activate or deactivate a company user.
     *
     * @param CompanyInterface $company
     * @param int $customerId
     * @param int $status
     * @return CompanyCustomerInterface
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function setStatus(CompanyInterface $company, int $customerId, int $status): CompanyCustomerInterface
    {
        if (!in_array(
            $status,
            [CompanyCustomerInterface::STATUS_ACTIVE, CompanyCustomerInterface::STATUS_INACTIVE],
            true
        )) {
            throw new LocalizedException(__('Status "%1" is not valid.', $status));
        }
        $companyId = (int)$company->getId();
        /** @var CompanyCustomer $relation */
        $relation = $this->relationFactory->create();
        $this->relationResource->load($relation, $customerId);
        if (!$relation->getCustomerId() || $relation->getCompanyEntityId() !== $companyId) {
            throw new NoSuchEntityException(
                __('The customer "%1" is not assigned to the company "%2".', $customerId, $companyId)
            );
        }
        if ($status === CompanyCustomerInterface::STATUS_INACTIVE) {
            if ($company->getSuperUserId() === $customerId) {
                throw new LocalizedException(__('The company super user cannot be deactivated.'));
            }
            if ($relation->getRole() === CompanyCustomerInterface::ROLE_ADMIN
                && $this->relationResource->countActiveAdmins($companyId, $customerId) === 0
            ) {
                throw new LocalizedException(__('A company must keep at least one active admin.'));
            }
        }
        $relation->setStatus($status);
        $this->relationResource->save($relation);

        return $relation;
    }
}
