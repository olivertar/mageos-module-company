<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Message;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use MageOS\Company\Api\CompanyAuthorizationInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Api\Data\MessageInterface;
use MageOS\Company\Api\MessageManagementInterface;
use MageOS\Company\Controller\AbstractCompanyAction;
use MageOS\Company\Controller\CompanyActionContext;

/**
 * Send a message from the customer account to the store.
 */
class Send extends AbstractCompanyAction implements HttpPostActionInterface
{
    protected const RESOURCE = CompanyAuthorizationInterface::RESOURCE_MESSAGES_SEND;
    public const DRAFT_KEY = 'mageos_company_message_draft';

    /**
     * @param CompanyActionContext $context
     * @param MessageManagementInterface $messageManagement
     */
    public function __construct(
        CompanyActionContext $context,
        private readonly MessageManagementInterface $messageManagement
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    protected function executeFor(CompanyInterface $company, int $customerId): ResultInterface
    {
        $body = (string)$this->context->request->getParam('body');
        $sent = $this->runOperation(
            fn () => $this->messageManagement->send(
                (int)$company->getId(),
                $body,
                MessageInterface::AUTHOR_CUSTOMER,
                $customerId
            ),
            (string)__('Your message has been sent.')
        );
        if (!$sent) {
            $this->context->dataPersistor->set(self::DRAFT_KEY, $body);
        }

        return $this->redirect('company/message');
    }
}
