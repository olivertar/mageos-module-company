<?php

declare(strict_types=1);

namespace MageOS\Company\Api;

use MageOS\Company\Api\Data\CompanyInterface;

/**
 * Company status workflow.
 *
 * pending → approved | rejected; approved → blocked; blocked → approved; rejected → pending.
 * Every transition is recorded in the status history, dispatches company_status_change_after and notifies the
 * company by email.
 *
 * @api
 */
interface CompanyApprovalInterface
{
    /**
     * Approve a pending company.
     *
     * @param int $companyId
     * @param string|null $comment
     * @return \MageOS\Company\Api\Data\CompanyInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function approve(int $companyId, ?string $comment = null): CompanyInterface;

    /**
     * Reject a pending company.
     *
     * @param int $companyId
     * @param string $reason
     * @return \MageOS\Company\Api\Data\CompanyInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function reject(int $companyId, string $reason): CompanyInterface;

    /**
     * Block an approved company: its users can no longer log in.
     *
     * @param int $companyId
     * @param string $reason
     * @return \MageOS\Company\Api\Data\CompanyInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function block(int $companyId, string $reason): CompanyInterface;

    /**
     * Unblock a blocked company.
     *
     * @param int $companyId
     * @param string|null $comment
     * @return \MageOS\Company\Api\Data\CompanyInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function unblock(int $companyId, ?string $comment = null): CompanyInterface;

    /**
     * Send a rejected application back to review.
     *
     * @param int $companyId
     * @param string|null $comment
     * @return \MageOS\Company\Api\Data\CompanyInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function resubmit(int $companyId, ?string $comment = null): CompanyInterface;

    /**
     * Get the status history of a company, newest first.
     *
     * @param int $companyId
     * @return \MageOS\Company\Api\Data\StatusHistoryInterface[]
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getHistory(int $companyId): array;
}
