<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Invitation;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * Invitation landing page (the token is resolved by the Invitation view model).
 */
class Accept implements HttpGetActionInterface
{
    /**
     * @param PageFactory $pageFactory
     */
    public function __construct(
        private readonly PageFactory $pageFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set((string)__('Company Invitation'));

        return $page;
    }
}
