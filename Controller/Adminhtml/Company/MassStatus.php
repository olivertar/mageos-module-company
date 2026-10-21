<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Adminhtml\Company;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Model\ResourceModel\Company\CollectionFactory;
use MageOS\Company\Model\Status\ActionRunner;
use Psr\Log\LoggerInterface;

/**
 * Run a status action on the companies selected in the grid. Companies in another status are skipped.
 */
class MassStatus extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_Company::company_approve';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param ActionRunner $actionRunner
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly ActionRunner $actionRunner,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create()->setPath('*/*/');
        $action = (string)$this->getRequest()->getParam('status_action');
        $comment = $this->getRequest()->getParam('comment');
        $comment = is_string($comment) ? $comment : null;
        if (!$this->actionRunner->isValid($action)) {
            $this->messageManager->addErrorMessage(__('The action "%1" is not valid.', $action));

            return $redirect;
        }
        if ($this->actionRunner->isReasonRequired($action) && trim((string)$comment) === '') {
            $this->messageManager->addErrorMessage(__('Please enter a reason.'));

            return $redirect;
        }

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $redirect;
        }
        $updated = 0;
        $skipped = 0;
        /** @var CompanyInterface $company */
        foreach ($collection->getItems() as $company) {
            if (!$this->actionRunner->isAvailable($action, $company->getStatus())) {
                $skipped++;
                continue;
            }
            try {
                $this->actionRunner->run($action, (int)$company->getId(), $comment);
                $updated++;
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage(
                    __('%1: %2', $company->getName(), $e->getMessage())
                );
            } catch (\Exception $e) {
                $this->logger->critical($e);
                $this->messageManager->addErrorMessage(
                    __('%1: Something went wrong while updating the company status.', $company->getName())
                );
            }
        }
        if ($updated) {
            $this->messageManager->addSuccessMessage(__('A total of %1 record(s) have been updated.', $updated));
        }
        if ($skipped) {
            $this->messageManager->addNoticeMessage(
                __('%1 record(s) were skipped because their status does not allow this action.', $skipped)
            );
        }

        return $redirect;
    }
}
