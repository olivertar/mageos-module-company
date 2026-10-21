<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Address;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use MageOS\Company\Api\Data\CompanyInterface;

/**
 * Add or edit a company address.
 */
class Edit extends AbstractAddressAction implements HttpGetActionInterface
{
    /**
     * @inheritDoc
     */
    protected function executeFor(CompanyInterface $company, int $customerId): ResultInterface
    {
        if ($this->context->request->getParam('id')) {
            try {
                $this->getCompanyAddress($company);
            } catch (NoSuchEntityException) {
                $this->context->messageManager->addErrorMessage(__('This address does not exist.'));

                return $this->redirect('company/address');
            }

            return $this->page((string)__('Edit Company Address'));
        }

        return $this->page((string)__('Add Company Address'));
    }
}
