<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Address;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use MageOS\Company\Api\Data\CompanyInterface;

/**
 * Delete a company address.
 */
class Delete extends AbstractAddressAction implements HttpPostActionInterface
{
    /**
     * @inheritDoc
     */
    protected function executeFor(CompanyInterface $company, int $customerId): ResultInterface
    {
        $this->runOperation(
            fn () => $this->addressRepository->delete($this->getCompanyAddress($company)),
            (string)__('The address has been deleted.')
        );

        return $this->redirect('company/address');
    }
}
