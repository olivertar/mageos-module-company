<?php

declare(strict_types=1);

namespace MageOS\Company\ViewModel;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Url\EncoderInterface as UrlEncoder;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Model\Invitation;
use MageOS\Company\Model\Invitation\InvitationManager;

/**
 * Invitation landing page: decides whether the visitor accepts as itself, signs in first or creates an account.
 */
class InvitationAccept implements ArgumentInterface
{
    public const MODE_INVALID = 'invalid';
    public const MODE_ACCEPT = 'accept';
    public const MODE_WRONG_ACCOUNT = 'wrong_account';
    public const MODE_SIGN_IN = 'sign_in';
    public const MODE_CREATE_ACCOUNT = 'create_account';

    /**
     * @var Invitation|null|false
     */
    private $invitation = false;

    /**
     * @param RequestInterface $request
     * @param InvitationManager $invitationManager
     * @param CompanyRepositoryInterface $companyRepository
     * @param CustomerSession $customerSession
     * @param CustomerUrl $customerUrl
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param UrlInterface $urlBuilder
     * @param UrlEncoder $urlEncoder
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly InvitationManager $invitationManager,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly CustomerSession $customerSession,
        private readonly CustomerUrl $customerUrl,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly UrlInterface $urlBuilder,
        private readonly UrlEncoder $urlEncoder
    ) {
    }

    /**
     * What the page should offer (see MODE_* constants).
     *
     * @return string
     */
    public function getMode(): string
    {
        $invitation = $this->getInvitation();
        if ($invitation === null) {
            return self::MODE_INVALID;
        }
        if ($this->customerSession->isLoggedIn()) {
            $email = mb_strtolower((string)$this->customerSession->getCustomer()->getEmail());

            return $email === $invitation->getEmail() ? self::MODE_ACCEPT : self::MODE_WRONG_ACCOUNT;
        }
        $websiteId = (int)$this->storeManager->getWebsite()->getId();

        return $this->invitationManager->findCustomer($invitation->getEmail(), $websiteId) !== null
            ? self::MODE_SIGN_IN
            : self::MODE_CREATE_ACCOUNT;
    }

    /**
     * Open invitation of the request token, for a company of the current website.
     *
     * @return Invitation|null
     */
    public function getInvitation(): ?Invitation
    {
        if ($this->invitation === false) {
            $invitation = $this->invitationManager->getOpenByToken($this->getToken());
            if ($invitation !== null && $this->getCompanyWebsiteId($invitation) !== (int)$this->storeManager
                    ->getWebsite()->getId()
            ) {
                $invitation = null;
            }
            $this->invitation = $invitation;
        }

        return $this->invitation;
    }

    /**
     * Name of the inviting company.
     *
     * @return string
     */
    public function getCompanyName(): string
    {
        $invitation = $this->getInvitation();
        if ($invitation === null) {
            return '';
        }
        try {
            return (string)$this->companyRepository->getById($invitation->getCompanyEntityId())->getName();
        } catch (NoSuchEntityException) {
            return '';
        }
    }

    /**
     * Token of the request.
     *
     * @return string
     */
    public function getToken(): string
    {
        return (string)$this->request->getParam('token');
    }

    /**
     * Form action.
     *
     * @return string
     */
    public function getPostUrl(): string
    {
        return $this->urlBuilder->getUrl('company/invitation/acceptPost', ['_secure' => true]);
    }

    /**
     * Login URL that comes back to this page.
     *
     * @return string
     */
    public function getLoginUrl(): string
    {
        return $this->urlBuilder->getUrl('customer/account/login', [
            'referer' => $this->urlEncoder->encode($this->urlBuilder->getCurrentUrl()),
            '_secure' => true,
        ]);
    }

    /**
     * Logout URL.
     *
     * @return string
     */
    public function getLogoutUrl(): string
    {
        return $this->customerUrl->getLogoutUrl();
    }

    /**
     * Minimum customer password length.
     *
     * @return int
     */
    public function getMinimumPasswordLength(): int
    {
        return (int)$this->scopeConfig->getValue('customer/password/minimum_password_length');
    }

    /**
     * Number of character classes a customer password needs.
     *
     * @return int
     */
    public function getRequiredCharacterClassesNumber(): int
    {
        return (int)$this->scopeConfig->getValue('customer/password/required_character_classes_number');
    }

    /**
     * Website of the company of an invitation.
     *
     * @param Invitation $invitation
     * @return int
     */
    private function getCompanyWebsiteId(Invitation $invitation): int
    {
        try {
            return (int)$this->companyRepository->getById($invitation->getCompanyEntityId())->getWebsiteId();
        } catch (NoSuchEntityException) {
            return 0;
        }
    }
}
