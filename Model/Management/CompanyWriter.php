<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Management;

use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Model\Company;
use MageOS\Company\Model\Status\HistoryRecorder;

/**
 * Admin and integration writes through the web API, keeping the fields that have their own flow out of reach:
 * the status changes through CompanyApprovalInterface (with history), the ERP code through setErpCode()
 * (its own ACL), and the website is fixed once the company exists.
 */
class CompanyWriter
{
    /**
     * Fields never taken from an update payload.
     */
    private const PROTECTED_ON_UPDATE = [
        CompanyInterface::ENTITY_ID,
        CompanyInterface::STATUS,
        CompanyInterface::COMPANY_ID,
        CompanyInterface::WEBSITE_ID,
        CompanyInterface::CREATED_AT,
        CompanyInterface::UPDATED_AT,
        'custom_attributes',
        'extension_attributes',
    ];

    /**
     * @param CompanyRepositoryInterface $companyRepository
     * @param HistoryRecorder $historyRecorder
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly HistoryRecorder $historyRecorder,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Create a pending company.
     *
     * @param CompanyInterface $company
     * @return CompanyInterface
     * @throws LocalizedException
     */
    public function create(CompanyInterface $company): CompanyInterface
    {
        if ($company->getId()) {
            throw new LocalizedException(__('Use the update operation to change an existing company.'));
        }
        /** @var Company $company */
        $company->setStatus(CompanyInterface::STATUS_PENDING)->setCompanyId(null);
        if (!$company->getWebsiteId()) {
            $company->setWebsiteId((int)$this->storeManager->getDefaultStoreView()?->getWebsiteId());
        }
        $saved = $this->companyRepository->save($company);
        $this->historyRecorder->record((int)$saved->getId(), null, $saved->getStatus());

        return $saved;
    }

    /**
     * Update a company with the values the payload sets. Null means "not sent"; '' clears an attribute.
     *
     * @param int $companyId
     * @param CompanyInterface $company
     * @return CompanyInterface
     * @throws LocalizedException
     */
    public function update(int $companyId, CompanyInterface $company): CompanyInterface
    {
        /** @var Company $stored */
        $stored = $this->companyRepository->getById($companyId);
        $payload = $company instanceof Company ? $company->getData() : [];
        foreach ($payload as $key => $value) {
            if ($value === null || in_array($key, self::PROTECTED_ON_UPDATE, true)) {
                continue;
            }
            $stored->setData($key, $value === '' ? null : $value);
        }

        return $this->companyRepository->save($stored);
    }

    /**
     * Set or clear (null or '') the ERP code.
     *
     * @param int $companyId
     * @param string|null $erpCode
     * @return CompanyInterface
     * @throws LocalizedException
     */
    public function setErpCode(int $companyId, ?string $erpCode): CompanyInterface
    {
        $company = $this->companyRepository->getById($companyId);
        $erpCode = $erpCode === null ? null : trim($erpCode);
        $company->setCompanyId($erpCode === '' ? null : $erpCode);

        return $this->companyRepository->save($company);
    }
}
