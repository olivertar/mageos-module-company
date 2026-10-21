<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Adminhtml\User;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use MageOS\Company\Api\CompanyManagementInterface;
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Api\Data\CompanyCustomerInterface;
use Psr\Log\LoggerInterface;

/**
 * Assign a customer to a company, or change its role and job title (AJAX).
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_Company::company_edit';

    /**
     * @param Context $context
     * @param CompanyRepositoryInterface $companyRepository
     * @param CompanyManagementInterface $companyManagement
     * @param CustomerRepositoryInterface $customerRepository
     * @param JsonFactory $jsonFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly CompanyManagementInterface $companyManagement,
        private readonly CustomerRepositoryInterface $customerRepository,
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
        $request = $this->getRequest();
        $companyId = (int)$request->getParam('company_id');
        $customerId = (int)$request->getParam('customer_id');
        $error = true;
        try {
            $company = $this->companyRepository->getById($companyId);
            if (!$customerId) {
                $email = trim((string)$request->getParam('email'));
                try {
                    $customerId = (int)$this->customerRepository->get($email, $company->getWebsiteId())->getId();
                } catch (NoSuchEntityException) {
                    throw new LocalizedException(
                        __('There is no customer with the email "%1" in the company website.', $email)
                    );
                }
            }
            $status = $request->getParam('status');
            // activate before and deactivate after the role change, so the admin invariants see the final state
            if ($status !== null && (int)$status === CompanyCustomerInterface::STATUS_ACTIVE) {
                $this->setStatus($companyId, $customerId, (int)$status);
            }
            $this->companyManagement->assignCustomer(
                $companyId,
                $customerId,
                (string)$request->getParam('role'),
                trim((string)$request->getParam('job_title'))
            );
            if ($status !== null && (int)$status !== CompanyCustomerInterface::STATUS_ACTIVE) {
                $this->setStatus($companyId, $customerId, (int)$status);
            }
            $error = false;
            $message = __('The company user has been saved.');
        } catch (LocalizedException $e) {
            $message = __($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $message = __('We can\'t save the company user right now.');
        }

        return $this->jsonFactory->create()->setData([
            'error' => $error,
            'messages' => (string)$message,
            'data' => ['customer_id' => $customerId ?: null],
        ]);
    }

    /**
     * Change the status of an assigned user (new users are created active).
     *
     * @param int $companyId
     * @param int $customerId
     * @param int $status
     * @return void
     * @throws LocalizedException
     */
    private function setStatus(int $companyId, int $customerId, int $status): void
    {
        try {
            $this->companyManagement->setCustomerStatus($companyId, $customerId, $status);
        } catch (NoSuchEntityException) {
            // not assigned yet: assignCustomer() creates it active
            return;
        }
    }
}
