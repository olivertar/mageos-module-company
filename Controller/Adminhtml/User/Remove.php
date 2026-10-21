<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Adminhtml\User;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use MageOS\Company\Api\CompanyManagementInterface;
use Psr\Log\LoggerInterface;

/**
 * Remove a customer from a company (AJAX).
 */
class Remove extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_Company::company_edit';

    /**
     * @param Context $context
     * @param CompanyManagementInterface $companyManagement
     * @param JsonFactory $jsonFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly CompanyManagementInterface $companyManagement,
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
        $error = true;
        try {
            $this->companyManagement->unassignCustomer(
                (int)$this->getRequest()->getParam('company_id'),
                (int)$this->getRequest()->getParam('id')
            );
            $error = false;
            $message = __('The customer has been removed from the company.');
        } catch (LocalizedException $e) {
            $message = __($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $message = __('We can\'t remove the customer right now.');
        }

        return $this->jsonFactory->create()->setData(['error' => $error, 'message' => (string)$message]);
    }
}
