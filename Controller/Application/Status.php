<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Application;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Model\Registration\StatusLink;

/**
 * Public application status page (signed link sent to applicants, D12).
 *
 * An approved company sends the applicant to the login page; other errors are shown on the page.
 */
class Status implements HttpGetActionInterface
{
    /**
     * @param RequestInterface $request
     * @param PageFactory $pageFactory
     * @param RedirectFactory $redirectFactory
     * @param ManagerInterface $messageManager
     * @param StatusLink $statusLink
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager,
        private readonly StatusLink $statusLink,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        try {
            $company = $this->statusLink->resolve(
                (string)$this->request->getParam('token'),
                (int)$this->storeManager->getWebsite()->getId()
            );
        } catch (LocalizedException) {
            $company = null; // the view model shows the error on the page
        }
        if ($company?->getStatus() === CompanyInterface::STATUS_APPROVED) {
            $this->messageManager->addSuccessMessage(
                __('Your company account has been approved. Sign in to continue.')
            );

            return $this->redirectFactory->create()->setPath('customer/account/login');
        }
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set((string)__('Company Application'));
        $page->getConfig()->setRobots('NOINDEX,NOFOLLOW');
        // the token is in the URL: do not leak it to other sites
        $page->setHeader('Referrer-Policy', 'no-referrer', true);

        return $page;
    }
}
