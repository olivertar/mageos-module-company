<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Register;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Company\Api\CompanyManagementInterface;
use MageOS\Company\Model\Config;

/**
 * Shared checks of the registration pages.
 */
abstract class AbstractRegister implements ActionInterface
{
    public const SESSION_FORM_DATA = 'mageos_company_register_form';

    /**
     * @param RequestInterface $request
     * @param CustomerSession $customerSession
     * @param CompanyManagementInterface $companyManagement
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param RedirectFactory $redirectFactory
     * @param ForwardFactory $forwardFactory
     * @param MessageManager $messageManager
     */
    public function __construct(
        protected readonly RequestInterface $request,
        protected readonly CustomerSession $customerSession,
        protected readonly CompanyManagementInterface $companyManagement,
        protected readonly Config $config,
        protected readonly StoreManagerInterface $storeManager,
        protected readonly RedirectFactory $redirectFactory,
        protected readonly ForwardFactory $forwardFactory,
        protected readonly MessageManager $messageManager
    ) {
    }

    /**
     * Result to return instead of the page, when registration is not possible.
     *
     * @return ResultInterface|null
     */
    protected function getDeniedResult(): ?ResultInterface
    {
        $websiteId = (int)$this->storeManager->getWebsite()->getId();
        if (!$this->config->isRegistrationEnabled($websiteId)) {
            return $this->forwardFactory->create()->forward('noroute');
        }
        if ($this->customerSession->isLoggedIn() && $this->hasCompany((int)$this->customerSession->getCustomerId())) {
            $this->messageManager->addNoticeMessage(__('Your account already belongs to a company.'));

            return $this->redirectFactory->create()->setPath('customer/account');
        }

        return null;
    }

    /**
     * Whether a customer already belongs to a company.
     *
     * @param int $customerId
     * @return bool
     */
    private function hasCompany(int $customerId): bool
    {
        try {
            $this->companyManagement->getByCustomerId($customerId);

            return true;
        } catch (NoSuchEntityException) {
            return false;
        }
    }
}
