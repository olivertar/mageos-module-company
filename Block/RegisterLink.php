<?php

declare(strict_types=1);

namespace MageOS\Company\Block;

use Magento\Framework\View\Element\Html\Link;
use Magento\Framework\View\Element\Template\Context;
use MageOS\Company\Model\Config;

/**
 * Link to the company registration form, shown only when registration is enabled for the website.
 */
class RegisterLink extends Link
{
    /**
     * @param Context $context
     * @param Config $config
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @inheritDoc
     */
    public function getHref()
    {
        return $this->getUrl('company/register');
    }

    /**
     * @inheritDoc
     */
    protected function _toHtml()
    {
        $websiteId = (int)$this->_storeManager->getWebsite()->getId();
        if (!$this->config->isRegistrationEnabled($websiteId)) {
            return '';
        }

        return parent::_toHtml();
    }
}
