<?php

declare(strict_types=1);

namespace MageOS\Company\Block\Account;

use Magento\Customer\Block\Account\SortLink;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\DefaultPathInterface;
use Magento\Framework\View\Element\Template\Context;
use MageOS\Company\Api\CompanyAuthorizationInterface;

/**
 * Account navigation link of the company area, shown only when the customer may use its "resource".
 */
class CompanyLink extends SortLink
{
    /**
     * @param Context $context
     * @param DefaultPathInterface $defaultPath
     * @param CustomerSession $customerSession
     * @param CompanyAuthorizationInterface $authorization
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        DefaultPathInterface $defaultPath,
        protected readonly CustomerSession $customerSession,
        protected readonly CompanyAuthorizationInterface $authorization,
        array $data = []
    ) {
        parent::__construct($context, $defaultPath, $data);
    }

    /**
     * @inheritDoc
     */
    protected function _toHtml()
    {
        $resource = (string)($this->getData('resource') ?: CompanyAuthorizationInterface::RESOURCE_VIEW);
        if (!$this->authorization->isAllowed((int)$this->customerSession->getCustomerId(), $resource)) {
            return '';
        }

        return parent::_toHtml();
    }
}
