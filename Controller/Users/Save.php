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
 * Save the role, job title and status of a company user.
 */
class Save extends AbstractUsersAction implements HttpPostActionInterface
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
        $request = $this->context->request;
        $userId = (int)$request->getParam('id');
        $status = $request->getParam('status');
        $saved = $this->runOperation(
            fn () => $this->myCompanyManagement->updateUser(
                $customerId,
                $userId,
                (string)$request->getParam('role'),
                trim((string)$request->getParam('job_title')),
                $status === null ? null : (int)$status
            ),
            (string)__('The user has been saved.')
        );

        return $saved ? $this->redirect('company/users') : $this->redirect('company/users/edit', ['id' => $userId]);
    }
}
