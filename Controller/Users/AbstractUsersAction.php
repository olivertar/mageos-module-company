<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Users;

use Magento\Framework\Exception\NoSuchEntityException;
use MageOS\Company\Api\CompanyAuthorizationInterface;
use MageOS\Company\Api\CompanyManagementInterface;
use MageOS\Company\Api\Data\CompanyCustomerInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Controller\AbstractCompanyAction;
use MageOS\Company\Controller\CompanyActionContext;

/**
 * Company user management action: resolves users of the customer's company only.
 */
abstract class AbstractUsersAction extends AbstractCompanyAction
{
    protected const RESOURCE = CompanyAuthorizationInterface::RESOURCE_USERS_MANAGE;

    /**
     * @param CompanyActionContext $context
     * @param CompanyManagementInterface $companyManagement
     */
    public function __construct(
        CompanyActionContext $context,
        protected readonly CompanyManagementInterface $companyManagement
    ) {
        parent::__construct($context);
    }

    /**
     * Company user by the "id" request parameter.
     *
     * @param CompanyInterface $company
     * @return CompanyCustomerInterface
     * @throws NoSuchEntityException When the customer is not a user of the company.
     */
    protected function getCompanyUser(CompanyInterface $company): CompanyCustomerInterface
    {
        $customerId = (int)$this->context->request->getParam('id');
        foreach ($this->companyManagement->getCustomers((int)$company->getId()) as $relation) {
            if ($relation->getCustomerId() === $customerId) {
                return $relation;
            }
        }
        throw NoSuchEntityException::singleField('id', $customerId);
    }
}
