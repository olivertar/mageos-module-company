<?php

declare(strict_types=1);

namespace MageOS\Company\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Module configuration reader (website scope).
 */
class Config
{
    public const VAT_VALIDATION_NONE = 'none';
    public const VAT_VALIDATION_FORMAT = 'format';

    public const EMAIL_APPLICATION_RECEIVED = 'application_received';
    public const EMAIL_ADMIN_NEW_APPLICATION = 'admin_new_application';
    public const EMAIL_APPROVED = 'approved';
    public const EMAIL_REJECTED = 'rejected';
    public const EMAIL_BLOCKED = 'blocked';
    public const EMAIL_UNBLOCKED = 'unblocked';
    public const EMAIL_NEW_MESSAGE_CUSTOMER = 'new_message_customer';
    public const EMAIL_NEW_MESSAGE_ADMIN = 'new_message_admin';
    public const EMAIL_INVITATION = 'invitation';

    private const XML_PATH_ACTIVE = 'company/general/active';
    private const XML_PATH_AUTO_APPROVE = 'company/general/auto_approve';
    private const XML_PATH_VAT_VALIDATION = 'company/general/vat_validation';
    private const XML_PATH_REGISTRATION_ENABLED = 'company/registration/enabled';
    private const XML_PATH_TERMS_ENABLED = 'company/registration/terms_enabled';
    private const XML_PATH_TERMS_TEXT = 'company/registration/terms_text';
    private const XML_PATH_STATUS_LINK_LIFETIME = 'company/registration/status_link_lifetime';
    private const XML_PATH_EMAIL_IDENTITY = 'company/email/identity';
    private const XML_PATH_ADMIN_RECIPIENTS = 'company/email/admin_recipients';
    private const XML_PATH_EMAIL_TEMPLATE = 'company/email/%s_template';
    private const XML_PATH_MESSAGE_MAX_LENGTH = 'company/messages/max_length';
    private const XML_PATH_MESSAGE_RATE_LIMIT = 'company/messages/rate_limit';
    private const XML_PATH_INVITATION_LIFETIME = 'company/users/invitation_lifetime';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Whether companies are enabled on the storefront.
     *
     * @param int|null $websiteId
     * @return bool
     */
    public function isActive(?int $websiteId = null): bool
    {
        return $this->isSetFlag(self::XML_PATH_ACTIVE, $websiteId);
    }

    /**
     * Whether new companies are approved without admin review.
     *
     * @param int|null $websiteId
     * @return bool
     */
    public function isAutoApprove(?int $websiteId = null): bool
    {
        return $this->isSetFlag(self::XML_PATH_AUTO_APPROVE, $websiteId);
    }

    /**
     * VAT number validation mode.
     *
     * @param int|null $websiteId
     * @return string
     */
    public function getVatValidation(?int $websiteId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_VAT_VALIDATION, $websiteId) ?: self::VAT_VALIDATION_NONE;
    }

    /**
     * Whether the public registration form is enabled.
     *
     * @param int|null $websiteId
     * @return bool
     */
    public function isRegistrationEnabled(?int $websiteId = null): bool
    {
        return $this->isActive($websiteId) && $this->isSetFlag(self::XML_PATH_REGISTRATION_ENABLED, $websiteId);
    }

    /**
     * Whether the registration form requires accepting terms.
     *
     * @param int|null $websiteId
     * @return bool
     */
    public function isTermsEnabled(?int $websiteId = null): bool
    {
        return $this->isSetFlag(self::XML_PATH_TERMS_ENABLED, $websiteId);
    }

    /**
     * Terms text shown in the registration form.
     *
     * @param int|null $websiteId
     * @return string
     */
    public function getTermsText(?int $websiteId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_TERMS_TEXT, $websiteId);
    }

    /**
     * Email sender identity.
     *
     * @param int|null $websiteId
     * @return string
     */
    public function getEmailIdentity(?int $websiteId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_EMAIL_IDENTITY, $websiteId);
    }

    /**
     * Admin notification recipients.
     *
     * @param int|null $websiteId
     * @return string[]
     */
    public function getAdminRecipients(?int $websiteId = null): array
    {
        $value = (string)$this->getValue(self::XML_PATH_ADMIN_RECIPIENTS, $websiteId);

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    /**
     * Email template ID of a notification (see EMAIL_* constants).
     *
     * @param string $type
     * @param int|null $websiteId
     * @return string
     */
    public function getEmailTemplate(string $type, ?int $websiteId = null): string
    {
        return (string)$this->getValue(sprintf(self::XML_PATH_EMAIL_TEMPLATE, $type), $websiteId);
    }

    /**
     * Maximum length of a message body (0 = unlimited).
     *
     * @param int|null $websiteId
     * @return int
     */
    public function getMessageMaxLength(?int $websiteId = null): int
    {
        return max(0, (int)$this->getValue(self::XML_PATH_MESSAGE_MAX_LENGTH, $websiteId));
    }

    /**
     * Maximum messages a customer can send per hour (0 = unlimited).
     *
     * @param int|null $websiteId
     * @return int
     */
    public function getMessageRateLimit(?int $websiteId = null): int
    {
        return max(0, (int)$this->getValue(self::XML_PATH_MESSAGE_RATE_LIMIT, $websiteId));
    }

    /**
     * Days an invitation link stays valid.
     *
     * @param int|null $websiteId
     * @return int
     */
    public function getInvitationLifetime(?int $websiteId = null): int
    {
        return max(1, (int)$this->getValue(self::XML_PATH_INVITATION_LIFETIME, $websiteId));
    }

    /**
     * Read a flag in website scope.
     *
     * @param string $path
     * @param int|null $websiteId
     * @return bool
     */
    private function isSetFlag(string $path, ?int $websiteId): bool
    {
        return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_WEBSITE, $websiteId);
    }

    /**
     * Read a value in website scope.
     *
     * @param string $path
     * @param int|null $websiteId
     * @return mixed
     */
    private function getValue(string $path, ?int $websiteId): mixed
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_WEBSITE, $websiteId);
    }

    /**
     * Validity of the application status links, in days; 0 when the links are disabled.
     *
     * @param int|null $websiteId
     * @return int
     */
    public function getStatusLinkLifetime(?int $websiteId = null): int
    {
        return max(0, (int)$this->getValue(self::XML_PATH_STATUS_LINK_LIFETIME, $websiteId));
    }
}
