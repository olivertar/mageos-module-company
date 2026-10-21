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
 * Invite someone to join the company by email.
 */
class Invite extends AbstractUsersAction implements HttpPostActionInterface
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
        $request = $this->context->request;
        $email = trim((string)$request->getParam('email'));
        $this->runOperation(
            fn () => $this->invitationManager->invite(
                $company,
                $email,
                (string)$request->getParam('role'),
                (string)$request->getParam('job_title'),
                $customerId
            ),
            (string)__('An invitation has been sent to %1.', $email)
        );

        return $this->redirect('company/users');
    }
}
