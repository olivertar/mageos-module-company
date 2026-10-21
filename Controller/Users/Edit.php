<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Users;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use MageOS\Company\Api\Data\CompanyInterface;

/**
 * Edit the role, job title and status of a company user.
 */
class Edit extends AbstractUsersAction implements HttpGetActionInterface
{
    /**
     * @inheritDoc
     */
    protected function executeFor(CompanyInterface $company, int $customerId): ResultInterface
    {
        try {
            $this->getCompanyUser($company);
        } catch (NoSuchEntityException) {
            $this->context->messageManager->addErrorMessage(__('This user does not belong to your company.'));

            return $this->redirect('company/users');
        }

        return $this->page((string)__('Edit Company User'));
    }
}
