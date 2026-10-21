<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Users;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use MageOS\Company\Api\CompanyManagementInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Controller\CompanyActionContext;
use MageOS\Company\Model\Invitation\InvitationManager;

/**
 * Cancel an open invitation of the company.
 */
class CancelInvitation extends AbstractUsersAction implements HttpPostActionInterface
{
    /**
     * @param CompanyActionContext $context
     * @param CompanyManagementInterface $companyManagement
     * @param InvitationManager $invitationManager
     */
    public function __construct(
        CompanyActionContext $context,
        CompanyManagementInterface $companyManagement,
        private readonly InvitationManager $invitationManager
    ) {
        parent::__construct($context, $companyManagement);
    }

    /**
     * @inheritDoc
     */
    protected function executeFor(CompanyInterface $company, int $customerId): ResultInterface
    {
        $this->runOperation(
            fn () => $this->invitationManager->cancel(
                (int)$company->getId(),
                (int)$this->context->request->getParam('id')
            ),
            (string)__('The invitation has been cancelled.')
        );

        return $this->redirect('company/users');
    }
}
