<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Address;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use MageOS\Company\Api\CompanyAddressRepositoryInterface;
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Api\Data\CompanyAddressInterfaceFactory;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Controller\CompanyActionContext;
use MageOS\Company\Model\CompanyAddress;
use MageOS\Company\Model\Form\RequestApplier;
use MageOS\Company\Model\MyCompanyManagement;
use MageOS\Company\Setup\CompanySetup;

/**
 * Save a company address, and the default flags checked in the form.
 */
class FormPost extends AbstractAddressAction implements HttpPostActionInterface
{
    public const DATA_PERSISTOR_KEY = 'mageos_company_address_edit';

    /**
     * @param CompanyActionContext $context
     * @param CompanyAddressRepositoryInterface $addressRepository
     * @param CompanyAddressInterfaceFactory $addressFactory
     * @param CompanyRepositoryInterface $companyRepository
     * @param RequestApplier $requestApplier
     */
    public function __construct(
        CompanyActionContext $context,
        CompanyAddressRepositoryInterface $addressRepository,
        private readonly CompanyAddressInterfaceFactory $addressFactory,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly RequestApplier $requestApplier
    ) {
        parent::__construct($context, $addressRepository);
    }

    /**
     * @inheritDoc
     */
    protected function executeFor(CompanyInterface $company, int $customerId): ResultInterface
    {
        $addressId = (int)$this->context->request->getParam('id');
        try {
            $address = $addressId ? $this->getCompanyAddress($company) : $this->addressFactory->create();
        } catch (NoSuchEntityException) {
            $this->context->messageManager->addErrorMessage(__('This address does not exist.'));

            return $this->redirect('company/address');
        }
        /** @var CompanyAddress $address */
        $errors = $this->requestApplier->apply(
            $address,
            CompanyAddress::ENTITY,
            CompanySetup::FORM_ADDRESS_EDIT,
            $this->context->request,
            'address'
        );
        $address->setParentId((int)$company->getId());
        foreach ($errors as $error) {
            $this->context->messageManager->addErrorMessage($error);
        }
        if (!$errors && $this->save($company, $address)) {
            return $this->redirect('company/address');
        }
        $this->context->dataPersistor->set(self::DATA_PERSISTOR_KEY, $this->context->request->getParams());

        return $this->redirect('company/address/edit', $addressId ? ['id' => $addressId] : []);
    }

    /**
     * Save the address and the checked defaults.
     *
     * @param CompanyInterface $company
     * @param CompanyAddress $address
     * @return bool
     */
    private function save(CompanyInterface $company, CompanyAddress $address): bool
    {
        try {
            $addressId = (int)$this->addressRepository->save($address)->getId();
            $flags = (array)$this->context->request->getParam('defaults', []);
            $changed = false;
            foreach (MyCompanyManagement::DEFAULT_ADDRESS_TYPES as $flag => $setter) {
                if (!empty($flags[$flag])) {
                    $company->$setter($addressId);
                    $changed = true;
                }
            }
            if ($changed) {
                $this->companyRepository->save($company);
            }
            $this->context->messageManager->addSuccessMessage(__('The address has been saved.'));

            return true;
        } catch (InputException $e) {
            $this->context->messageManager->addErrorMessage($e->getMessage());
            foreach ($e->getErrors() as $error) {
                $this->context->messageManager->addErrorMessage($error->getMessage());
            }
        } catch (LocalizedException $e) {
            $this->context->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->context->logger->critical($e);
            $this->context->messageManager->addErrorMessage(__('Something went wrong. Please try again later.'));
        }

        return false;
    }
}
