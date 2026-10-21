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
use Psr\Log\LoggerInterface;

/**
 * Delete a company address (AJAX).
 */
class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_Company::company_edit';

    /**
     * @param Context $context
     * @param CompanyAddressRepositoryInterface $addressRepository
     * @param JsonFactory $jsonFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly CompanyAddressRepositoryInterface $addressRepository,
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
        $error = true;
        try {
            $address = $this->addressRepository->getById($addressId);
            if ($address->getParentId() !== $companyId) {
                throw new LocalizedException(__('The address does not belong to this company.'));
            }
            $this->addressRepository->delete($address);
            $error = false;
            $message = __('The address has been deleted.');
        } catch (LocalizedException $e) {
            $message = __($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $message = __('We can\'t delete the address right now.');
        }

        return $this->jsonFactory->create()->setData(['error' => $error, 'message' => (string)$message]);
    }
}
