<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Registration;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Config\ConfigOptionsListConstants;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\UrlFactory;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Model\Config;

/**
 * Signed links that let the applicant of a pending or rejected company check the application without signing in
 * (D12): status, messages, reply and resubmit.
 *
 * Token: "<company id>.<expiry timestamp>.<HMAC-SHA256>". The signature covers the company ID, the expiry, the
 * website, the company email and the super user, so changing any of them voids the links already sent. No state is
 * stored: a link stays valid until it expires or the company leaves the pending/rejected status.
 */
class StatusLink
{
    public const ROUTE = 'company/application/status';

    /**
     * Statuses in which the links work.
     */
    public const STATUSES = [CompanyInterface::STATUS_PENDING, CompanyInterface::STATUS_REJECTED];

    private const SIGNATURE_CONTEXT = 'mageos-company-application-status';

    /**
     * @param DeploymentConfig $deploymentConfig
     * @param CompanyRepositoryInterface $companyRepository
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param DateTime $dateTime
     * @param UrlFactory $urlFactory
     */
    public function __construct(
        private readonly DeploymentConfig $deploymentConfig,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly DateTime $dateTime,
        private readonly UrlFactory $urlFactory
    ) {
    }

    /**
     * Status page URL for the applicant, or null when links are disabled or the status does not allow them.
     *
     * @param CompanyInterface $company
     * @return string|null
     */
    public function getUrl(CompanyInterface $company): ?string
    {
        $token = $this->createToken($company);
        if ($token === null) {
            return null;
        }
        $websiteId = (int)$company->getWebsiteId();
        $groupId = (string)$this->storeManager->getWebsite($websiteId)->getDefaultGroupId();
        $storeId = (int)$this->storeManager->getGroup($groupId)->getDefaultStoreId();
        // storefront URL builder: emails are also sent from the admin, where Store::getUrl() builds admin URLs
        $url = $this->urlFactory->create();
        $url->setScope($storeId);

        return $url->getUrl(self::ROUTE, ['_query' => ['token' => $token], '_nosid' => true, '_secure' => true]);
    }

    /**
     * New token for a company, or null when links are disabled or the status does not allow them.
     *
     * @param CompanyInterface $company
     * @return string|null
     */
    public function createToken(CompanyInterface $company): ?string
    {
        $lifetime = $this->config->getStatusLinkLifetime((int)$company->getWebsiteId());
        if (!$lifetime || !$company->getId() || !in_array($company->getStatus(), self::STATUSES, true)) {
            return null;
        }
        $expires = $this->dateTime->gmtTimestamp() + $lifetime * 86400;

        return sprintf('%d.%d.%s', (int)$company->getId(), $expires, $this->sign($company, $expires));
    }

    /**
     * Company of a valid token.
     *
     * @param string $token
     * @param int $websiteId Website of the current request: a link only works in the company's website.
     * @return CompanyInterface
     * @throws LocalizedException When the token is invalid, expired or no longer applies.
     */
    public function resolve(string $token, int $websiteId): CompanyInterface
    {
        $invalid = new LocalizedException(
            __('This link is not valid or has expired. Use the link in the most recent email you received from us.')
        );
        if (!preg_match('/^(\d{1,10})\.(\d{1,12})\.([a-f0-9]{64})$/', $token, $parts)) {
            throw $invalid;
        }
        [, $companyId, $expires, $signature] = $parts;
        if ((int)$expires < $this->dateTime->gmtTimestamp()) {
            throw $invalid;
        }
        try {
            $company = $this->companyRepository->getById((int)$companyId);
        } catch (NoSuchEntityException) {
            throw $invalid;
        }
        if (!hash_equals($this->sign($company, (int)$expires), $signature)
            || (int)$company->getWebsiteId() !== $websiteId
            || !$this->config->getStatusLinkLifetime($websiteId)
        ) {
            throw $invalid;
        }

        return $company;
    }

    /**
     * Signature of a company link.
     *
     * @param CompanyInterface $company
     * @param int $expires
     * @return string
     */
    private function sign(CompanyInterface $company, int $expires): string
    {
        $payload = implode('|', [
            self::SIGNATURE_CONTEXT,
            (int)$company->getId(),
            $expires,
            (int)$company->getWebsiteId(),
            mb_strtolower((string)$company->getEmail()),
            (int)$company->getSuperUserId(),
        ]);

        return hash_hmac('sha256', $payload, $this->getKey());
    }

    /**
     * Current deployment encryption key (the last one after a key rotation).
     *
     * @return string
     * @throws \RuntimeException When the deployment has no encryption key.
     */
    private function getKey(): string
    {
        $keys = (string)$this->deploymentConfig->get(ConfigOptionsListConstants::CONFIG_PATH_CRYPT_KEY);
        $keys = preg_split('/\s+/', trim($keys)) ?: [];
        $key = (string)end($keys);
        if ($key === '') {
            throw new \RuntimeException('The deployment has no encryption key.');
        }

        return $key;
    }
}
