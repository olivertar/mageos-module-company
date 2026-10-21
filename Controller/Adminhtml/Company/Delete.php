<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Adminhtml\Company;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use MageOS\Company\Api\CompanyRepositoryInterface;

/**
 * Delete one company.
 */
class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_Company::company_delete';

    /**
     * @param Context $context
     * @param CompanyRepositoryInterface $companyRepository
     */
    public function __construct(
        Context $context,
        private readonly CompanyRepositoryInterface $companyRepository
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create()->setPath('*/*/');
        $companyId = (int)$this->getRequest()->getParam('id');
        if (!$companyId) {
            return $redirect;
        }
        try {
            $this->companyRepository->deleteById($companyId);
            $this->messageManager->addSuccessMessage(__('The company has been deleted.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $redirect;
    }
}
