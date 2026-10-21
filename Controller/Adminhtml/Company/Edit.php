<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Adminhtml\Company;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use MageOS\Company\Api\CompanyRepositoryInterface;

/**
 * Company edit form.
 */
class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_Company::company_view';

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
        $companyId = (int)$this->getRequest()->getParam('id');
        $title = (string)__('New Company');
        if ($companyId) {
            try {
                $title = (string)$this->companyRepository->getById($companyId)->getName();
            } catch (NoSuchEntityException) {
                $this->messageManager->addErrorMessage(__('This company no longer exists.'));

                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        } elseif (!$this->_authorization->isAllowed('MageOS_Company::company_edit')) {
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }

        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('MageOS_Company::company_manage');
        $page->getConfig()->getTitle()->prepend((string)__('Companies'));
        $page->getConfig()->getTitle()->prepend($title);

        return $page;
    }
}
