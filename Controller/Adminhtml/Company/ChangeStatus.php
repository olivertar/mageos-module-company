<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Adminhtml\Company;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use MageOS\Company\Model\Status\ActionRunner;
use Psr\Log\LoggerInterface;

/**
 * Approve, reject, block, unblock or reopen a company from its edit page.
 */
class ChangeStatus extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_Company::company_approve';

    /**
     * @param Context $context
     * @param ActionRunner $actionRunner
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
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
        $companyId = (int)$this->getRequest()->getParam('id');
        $action = (string)$this->getRequest()->getParam('status_action');
        $comment = $this->getRequest()->getParam('comment');
        $comment = is_string($comment) ? $comment : null;
        $redirect = $this->resultRedirectFactory->create();

        try {
            $this->actionRunner->run($action, $companyId, $comment);
            $this->messageManager->addSuccessMessage(__('The company status has been updated.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $this->messageManager->addErrorMessage(__('Something went wrong while updating the company status.'));
        }

        return $companyId ? $redirect->setPath('*/*/edit', ['id' => $companyId]) : $redirect->setPath('*/*/');
    }
}
