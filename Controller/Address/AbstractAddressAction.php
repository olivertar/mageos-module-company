<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Address;

use Magento\Framework\Exception\NoSuchEntityException;
use MageOS\Company\Api\CompanyAddressRepositoryInterface;
use MageOS\Company\Api\CompanyAuthorizationInterface;
use MageOS\Company\Api\Data\CompanyAddressInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Controller\AbstractCompanyAction;
use MageOS\Company\Controller\CompanyActionContext;

/**
 * Address management action: resolves addresses of the customer's company only.
 */
abstract class AbstractAddressAction extends AbstractCompanyAction
{
    protected const RESOURCE = CompanyAuthorizationInterface::RESOURCE_ADDRESS_MANAGE;

    /**
     * @param CompanyActionContext $context
     * @param CompanyAddressRepositoryInterface $addressRepository
     */
    public function __construct(
        CompanyActionContext $context,
        protected readonly CompanyAddressRepositoryInterface $addressRepository
    ) {
        parent::__construct($context);
    }

    /**
     * Address of the company, by the "id" request parameter.
     *
     * @param CompanyInterface $company
     * @return CompanyAddressInterface
     * @throws NoSuchEntityException When it does not exist or belongs to another company.
     */
    protected function getCompanyAddress(CompanyInterface $company): CompanyAddressInterface
    {
        $addressId = (int)$this->context->request->getParam('id');
        $address = $this->addressRepository->getById($addressId);
        if ((int)$address->getParentId() !== (int)$company->getId()) {
            throw NoSuchEntityException::singleField('id', $addressId);
        }

        return $address;
    }
}
