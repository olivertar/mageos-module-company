<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Status;

use Magento\Framework\Exception\LocalizedException;
use MageOS\Company\Api\Data\CompanyInterface;

/**
 * Allowed company status transitions.
 */
class TransitionPolicy
{
    /**
     * Target statuses allowed from each status.
     */
    private const TRANSITIONS = [
        CompanyInterface::STATUS_PENDING => [CompanyInterface::STATUS_APPROVED, CompanyInterface::STATUS_REJECTED],
        CompanyInterface::STATUS_APPROVED => [CompanyInterface::STATUS_BLOCKED],
        CompanyInterface::STATUS_BLOCKED => [CompanyInterface::STATUS_APPROVED],
        CompanyInterface::STATUS_REJECTED => [CompanyInterface::STATUS_PENDING],
    ];

    /**
     * Transitions that must be justified with a reason.
     */
    private const REASON_REQUIRED = [CompanyInterface::STATUS_REJECTED, CompanyInterface::STATUS_BLOCKED];

    /**
     * Whether a company can move from one status to another.
     *
     * @param int $from
     * @param int $to
     * @return bool
     */
    public function isAllowed(int $from, int $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Statuses a company can move to from the given status.
     *
     * @param int $from
     * @return int[]
     */
    public function getAllowedTargets(int $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }

    /**
     * Whether moving to a status needs a reason.
     *
     * @param int $to
     * @return bool
     */
    public function isReasonRequired(int $to): bool
    {
        return in_array($to, self::REASON_REQUIRED, true);
    }

    /**
     * Validate a transition.
     *
     * @param int $from
     * @param int $to
     * @param string|null $comment
     * @return void
     * @throws LocalizedException
     */
    public function assert(int $from, int $to, ?string $comment): void
    {
        if (!$this->isAllowed($from, $to)) {
            throw new LocalizedException(
                __('The company status cannot change from "%1" to "%2".', $this->getLabel($from), $this->getLabel($to))
            );
        }
        if ($this->isReasonRequired($to) && trim((string)$comment) === '') {
            throw new LocalizedException(__('A reason is required to set the status "%1".', $this->getLabel($to)));
        }
    }

    /**
     * Status label.
     *
     * @param int $status
     * @return string
     */
    public function getLabel(int $status): string
    {
        return (string)match ($status) {
            CompanyInterface::STATUS_PENDING => __('Pending Approval'),
            CompanyInterface::STATUS_APPROVED => __('Approved'),
            CompanyInterface::STATUS_REJECTED => __('Rejected'),
            CompanyInterface::STATUS_BLOCKED => __('Blocked'),
            default => __('Unknown'),
        };
    }
}
