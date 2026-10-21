<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Adminhtml\Company;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Model\ResourceModel\Company\CollectionFactory;

/**
 * Delete the companies selected in the grid.
 */
class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_Company::company_delete';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param CompanyRepositoryInterface $companyRepository
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
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
        try {
            $ids = $this->filter->getCollection($this->collectionFactory->create())->getAllIds();
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $redirect;
        }
        $deleted = 0;
        foreach ($ids as $companyId) {
            try {
                $this->companyRepository->deleteById((int)$companyId);
                $deleted++;
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }
        if ($deleted) {
            $this->messageManager->addSuccessMessage(__('A total of %1 record(s) have been deleted.', $deleted));
        }

        return $redirect;
    }
}
