<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Users;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use MageOS\Company\Api\CompanyManagementInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Api\MyCompanyManagementInterface;
use MageOS\Company\Controller\CompanyActionContext;

/**
 * Remove a user from the company. The customer account is kept.
 */
class Remove extends AbstractUsersAction implements HttpPostActionInterface
{
    /**
     * @param CompanyActionContext $context
     * @param CompanyManagementInterface $companyManagement
     * @param MyCompanyManagementInterface $myCompanyManagement
     */
    public function __construct(
        CompanyActionContext $context,
        CompanyManagementInterface $companyManagement,
        private readonly MyCompanyManagementInterface $myCompanyManagement
    ) {
        parent::__construct($context, $companyManagement);
    }

    /**
     * @inheritDoc
     */
    protected function executeFor(CompanyInterface $company, int $customerId): ResultInterface
    {
        $this->runOperation(
            fn () => $this->myCompanyManagement->removeUser(
                $customerId,
                (int)$this->context->request->getParam('id')
            ),
            (string)__('The user has been removed from the company.')
        );

        return $this->redirect('company/users');
    }
}
