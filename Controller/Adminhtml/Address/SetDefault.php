<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Adminhtml\Address;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use MageOS\Company\Api\CompanyAddressRepositoryInterface;
use MageOS\Company\Api\CompanyRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Set an address as the company default billing, shipping or legal address (AJAX).
 */
class SetDefault extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_Company::company_edit';

    /**
     * @param Context $context
     * @param CompanyAddressRepositoryInterface $addressRepository
     * @param CompanyRepositoryInterface $companyRepository
     * @param JsonFactory $jsonFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly CompanyAddressRepositoryInterface $addressRepository,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly JsonFactory $jsonFactory,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute(): Json
    {
        $companyId = (int)$this->getRequest()->getParam('company_id');
        $addressId = (int)$this->getRequest()->getParam('id');
        $type = (string)$this->getRequest()->getParam('type');
        $error = true;
        try {
            $address = $this->addressRepository->getById($addressId);
            if ($address->getParentId() !== $companyId) {
                throw new LocalizedException(__('The address does not belong to this company.'));
            }
            $company = $this->companyRepository->getById($companyId);
            match ($type) {
                'billing' => $company->setDefaultBilling($addressId),
                'shipping' => $company->setDefaultShipping($addressId),
                'legal' => $company->setLegalAddress($addressId),
                default => throw new LocalizedException(__('Unknown default address type "%1".', $type)),
            };
            $this->companyRepository->save($company);
            $error = false;
            $message = __('The default address has been changed.');
        } catch (LocalizedException $e) {
            $message = __($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $message = __('We can\'t change the default address right now.');
        }

        return $this->jsonFactory->create()->setData(['error' => $error, 'message' => (string)$message]);
    }
}
