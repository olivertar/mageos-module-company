<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Adminhtml\Message;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\LayoutFactory;
use MageOS\Company\Api\Data\MessageInterface;
use MageOS\Company\Api\MessageManagementInterface;
use MageOS\Company\Block\Adminhtml\Company\Edit\Messages;
use Psr\Log\LoggerInterface;

/**
 * Send a message or internal note from the company form (AJAX). Returns the refreshed thread.
 */
class Send extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_Company::messages';

    /**
     * @param Context $context
     * @param MessageManagementInterface $messageManagement
     * @param AuthSession $authSession
     * @param JsonFactory $jsonFactory
     * @param LayoutFactory $layoutFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly MessageManagementInterface $messageManagement,
        private readonly AuthSession $authSession,
        private readonly JsonFactory $jsonFactory,
        private readonly LayoutFactory $layoutFactory,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();
        $companyId = (int)$this->getRequest()->getParam('company_id');
        try {
            $this->messageManagement->send(
                $companyId,
                (string)$this->getRequest()->getParam('body'),
                MessageInterface::AUTHOR_ADMIN,
                (int)$this->authSession->getUser()?->getId(),
                (bool)$this->getRequest()->getParam('is_internal')
            );
        } catch (LocalizedException $e) {
            return $result->setData(['error' => true, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            $this->logger->critical($e);

            return $result->setData([
                'error' => true,
                'message' => (string)__('Something went wrong while sending the message.'),
            ]);
        }

        /** @var Messages $block */
        $block = $this->layoutFactory->create()->createBlock(Messages::class);
        $block->setData('company_id', $companyId)->setTemplate('MageOS_Company::company/messages/thread.phtml');

        return $result->setData(['error' => false, 'html' => $block->toHtml()]);
    }
}
