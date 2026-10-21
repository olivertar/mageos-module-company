<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Invitation;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Model\Invitation;
use MageOS\Company\Model\Invitation\InvitationManager;
use Psr\Log\LoggerInterface;

/**
 * Accept an invitation: as the logged in customer, or by creating an account for the invited email.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class AcceptPost implements HttpPostActionInterface
{
    /**
     * @param RequestInterface $request
     * @param CustomerSession $customerSession
     * @param InvitationManager $invitationManager
     * @param CompanyRepositoryInterface $companyRepository
     * @param AccountManagementInterface $accountManagement
     * @param CustomerInterfaceFactory $customerFactory
     * @param StoreManagerInterface $storeManager
     * @param RedirectFactory $redirectFactory
     * @param MessageManager $messageManager
     * @param LoggerInterface $logger
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly CustomerSession $customerSession,
        private readonly InvitationManager $invitationManager,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly AccountManagementInterface $accountManagement,
        private readonly CustomerInterfaceFactory $customerFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly RedirectFactory $redirectFactory,
        private readonly MessageManager $messageManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $token = (string)$this->request->getParam('token');
        $invitation = $this->invitationManager->getOpenByToken($token);
        if ($invitation === null || !$this->isCurrentWebsite($invitation)) {
            $this->messageManager->addErrorMessage(__('This invitation is no longer valid.'));

            return $this->redirectFactory->create()->setPath('customer/account/login');
        }

        try {
            if ($this->customerSession->isLoggedIn()) {
                $this->invitationManager->accept($invitation, (int)$this->customerSession->getCustomerId());
            } else {
                $this->createAccountAndAccept($invitation);
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $this->back($token);
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $this->messageManager->addErrorMessage(__('Something went wrong. Please try again later.'));

            return $this->back($token);
        }

        $companyName = $this->companyRepository->getById($invitation->getCompanyEntityId())->getName();
        $this->messageManager->addSuccessMessage(__('You have joined %1.', $companyName));
        if (!$this->customerSession->isLoggedIn()) {
            $this->messageManager->addNoticeMessage(__('Please confirm your email address, then sign in.'));

            return $this->redirectFactory->create()->setPath('customer/account/login');
        }

        return $this->redirectFactory->create()->setPath('company/account');
    }

    /**
     * Create the account of the invited email, join the company and log in.
     *
     * @param Invitation $invitation
     * @return void
     * @throws LocalizedException
     */
    private function createAccountAndAccept(Invitation $invitation): void
    {
        $websiteId = (int)$this->storeManager->getWebsite()->getId();
        if ($this->invitationManager->findCustomer($invitation->getEmail(), $websiteId) !== null) {
            throw new LocalizedException(
                __(
                    'An account already exists for %1. Please sign in to accept the invitation.',
                    $invitation->getEmail()
                )
            );
        }
        $password = (string)$this->request->getParam('password');
        if ($password !== (string)$this->request->getParam('password_confirmation')) {
            throw new LocalizedException(__('Please make sure your passwords match.'));
        }
        $store = $this->storeManager->getStore();
        $customer = $this->customerFactory->create()
            ->setFirstname(trim((string)$this->request->getParam('firstname')))
            ->setLastname(trim((string)$this->request->getParam('lastname')))
            ->setEmail($invitation->getEmail())
            ->setStoreId((int)$store->getId())
            ->setWebsiteId($websiteId);
        $customer = $this->accountManagement->createAccount($customer, $password);
        $this->invitationManager->accept($invitation, (int)$customer->getId());

        $status = $this->accountManagement->getConfirmationStatus((int)$customer->getId());
        if ($status !== AccountManagementInterface::ACCOUNT_CONFIRMATION_REQUIRED) {
            $this->customerSession->setCustomerDataAsLoggedIn($customer);
        }
    }

    /**
     * Whether the invitation belongs to the website of the request.
     *
     * @param Invitation $invitation
     * @return bool
     */
    private function isCurrentWebsite(Invitation $invitation): bool
    {
        $company = $this->companyRepository->getById($invitation->getCompanyEntityId());

        return (int)$company->getWebsiteId() === (int)$this->storeManager->getWebsite()->getId();
    }

    /**
     * Back to the invitation page.
     *
     * @param string $token
     * @return Redirect
     */
    private function back(string $token): Redirect
    {
        return $this->redirectFactory->create()
            ->setPath('company/invitation/accept', ['_query' => ['token' => $token]]);
    }
}
