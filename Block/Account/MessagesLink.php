<?php

declare(strict_types=1);

namespace MageOS\Company\Block\Account;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\DefaultPathInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template\Context;
use MageOS\Company\Api\CompanyAuthorizationInterface;
use MageOS\Company\Api\Data\MessageInterface;
use MageOS\Company\Api\MessageManagementInterface;
use MageOS\Company\Model\Authorization\CompanyContext;

/**
 * "Company Messages" account navigation link with the unread count.
 */
class MessagesLink extends CompanyLink
{
    /**
     * @param Context $context
     * @param DefaultPathInterface $defaultPath
     * @param CustomerSession $customerSession
     * @param CompanyAuthorizationInterface $authorization
     * @param CompanyContext $companyContext
     * @param MessageManagementInterface $messageManagement
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        DefaultPathInterface $defaultPath,
        CustomerSession $customerSession,
        CompanyAuthorizationInterface $authorization,
        private readonly CompanyContext $companyContext,
        private readonly MessageManagementInterface $messageManagement,
        array $data = []
    ) {
        parent::__construct($context, $defaultPath, $customerSession, $authorization, $data);
    }

    /**
     * @inheritDoc
     *
     * @return string
     */
    public function getLabel()
    {
        $label = (string)__('Company Messages');
        try {
            $companyId = (int)$this->companyContext->getCompany((int)$this->customerSession->getCustomerId())->getId();
        } catch (LocalizedException) {
            return $label;
        }
        $unread = $this->messageManagement->getUnreadCount($companyId, MessageInterface::AUTHOR_CUSTOMER);

        return $unread ? sprintf('%s (%d)', $label, $unread) : $label;
    }
}
