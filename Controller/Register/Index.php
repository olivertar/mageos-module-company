<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Register;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Company\Api\CompanyManagementInterface;
use MageOS\Company\Model\Config;

/**
 * Company registration form.
 */
class Index extends AbstractRegister implements HttpGetActionInterface
{
    /**
     * @param RequestInterface $request
     * @param CustomerSession $customerSession
     * @param CompanyManagementInterface $companyManagement
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param RedirectFactory $redirectFactory
     * @param ForwardFactory $forwardFactory
     * @param MessageManager $messageManager
     * @param PageFactory $pageFactory
     */
    public function __construct(
        RequestInterface $request,
        CustomerSession $customerSession,
        CompanyManagementInterface $companyManagement,
        Config $config,
        StoreManagerInterface $storeManager,
        RedirectFactory $redirectFactory,
        ForwardFactory $forwardFactory,
        MessageManager $messageManager,
        private readonly PageFactory $pageFactory
    ) {
        parent::__construct(
            $request,
            $customerSession,
            $companyManagement,
            $config,
            $storeManager,
            $redirectFactory,
            $forwardFactory,
            $messageManager
        );
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $denied = $this->getDeniedResult();
        if ($denied !== null) {
            return $denied;
        }
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set((string)__('Register Your Company'));

        return $page;
    }
}
