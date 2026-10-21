<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Notification;

use Magento\Backend\App\Area\FrontNameResolver;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Admin URLs for notifications built in any area, CLI consumers included (no secret key: the admin logs in first).
 */
class AdminUrl
{
    /**
     * @param StoreManagerInterface $storeManager
     * @param FrontNameResolver $frontNameResolver
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly FrontNameResolver $frontNameResolver
    ) {
    }

    /**
     * URL of the company edit page.
     *
     * @param int $companyId
     * @return string
     */
    public function getCompanyUrl(int $companyId): string
    {
        /** @var Store $adminStore */
        $adminStore = $this->storeManager->getStore(Store::ADMIN_CODE);

        return sprintf(
            '%s%s/mageos_company/company/edit/id/%d/',
            $adminStore->getBaseUrl(),
            $this->frontNameResolver->getFrontName(),
            $companyId
        );
    }
}
