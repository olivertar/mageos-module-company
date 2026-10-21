<?php

declare(strict_types=1);

namespace MageOS\Company\Controller;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Framework\View\Result\PageFactory;
use MageOS\Company\Api\CompanyAuthorizationInterface;
use MageOS\Company\Model\Authorization\CompanyContext;
use Psr\Log\LoggerInterface;

/**
 * Services shared by the storefront company area controllers.
 */
class CompanyActionContext
{
    /**
     * @param RequestInterface $request
     * @param CustomerSession $customerSession
     * @param CompanyContext $companyContext
     * @param CompanyAuthorizationInterface $authorization
     * @param PageFactory $pageFactory
     * @param RedirectFactory $redirectFactory
     * @param MessageManager $messageManager
     * @param DataPersistorInterface $dataPersistor
     * @param LoggerInterface $logger
     */
    public function __construct(
        public readonly RequestInterface $request,
        public readonly CustomerSession $customerSession,
        public readonly CompanyContext $companyContext,
        public readonly CompanyAuthorizationInterface $authorization,
        public readonly PageFactory $pageFactory,
        public readonly RedirectFactory $redirectFactory,
        public readonly MessageManager $messageManager,
        public readonly DataPersistorInterface $dataPersistor,
        public readonly LoggerInterface $logger
    ) {
    }
}
