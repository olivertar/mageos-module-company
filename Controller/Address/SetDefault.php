<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Address;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use MageOS\Company\Api\CompanyAddressRepositoryInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Api\MyCompanyManagementInterface;
use MageOS\Company\Controller\CompanyActionContext;

/**
 * Make an address the default billing, shipping or legal address.
 */
class SetDefault extends AbstractAddressAction implements HttpPostActionInterface
{
    /**
     * @param CompanyActionContext $context
     * @param CompanyAddressRepositoryInterface $addressRepository
     * @param MyCompanyManagementInterface $myCompanyManagement
     */
    public function __construct(
        CompanyActionContext $context,
        CompanyAddressRepositoryInterface $addressRepository,
        private readonly MyCompanyManagementInterface $myCompanyManagement
    ) {
        parent::__construct($context, $addressRepository);
    }

    /**
     * @inheritDoc
     */
    protected function executeFor(CompanyInterface $company, int $customerId): ResultInterface
    {
        $request = $this->context->request;
        $this->runOperation(
            fn () => $this->myCompanyManagement->setDefaultAddress(
                $customerId,
                (int)$request->getParam('id'),
                (string)$request->getParam('type')
            ),
            (string)__('The default address has been updated.')
        );

        return $this->redirect('company/address');
    }
}
