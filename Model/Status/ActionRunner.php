<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Status;

use Magento\Framework\Exception\LocalizedException;
use MageOS\Company\Api\CompanyApprovalInterface;
use MageOS\Company\Api\Data\CompanyInterface;

/**
 * Runs a named status action (as posted by admin buttons and mass actions) through the approval service.
 */
class ActionRunner
{
    public const APPROVE = 'approve';
    public const REJECT = 'reject';
    public const BLOCK = 'block';
    public const UNBLOCK = 'unblock';
    public const RESUBMIT = 'resubmit';

    /**
     * Status a company must have for each action.
     */
    private const SOURCE_STATUS = [
        self::APPROVE => CompanyInterface::STATUS_PENDING,
        self::REJECT => CompanyInterface::STATUS_PENDING,
        self::BLOCK => CompanyInterface::STATUS_APPROVED,
        self::UNBLOCK => CompanyInterface::STATUS_BLOCKED,
        self::RESUBMIT => CompanyInterface::STATUS_REJECTED,
    ];

    /**
     * @param CompanyApprovalInterface $companyApproval
     */
    public function __construct(
        private readonly CompanyApprovalInterface $companyApproval
    ) {
    }

    /**
     * Whether an action name exists.
     *
     * @param string $action
     * @return bool
     */
    public function isValid(string $action): bool
    {
        return isset(self::SOURCE_STATUS[$action]);
    }

    /**
     * Whether an action applies to a company in the given status.
     *
     * @param string $action
     * @param int $status
     * @return bool
     */
    public function isAvailable(string $action, int $status): bool
    {
        return (self::SOURCE_STATUS[$action] ?? null) === $status;
    }

    /**
     * Whether an action needs a reason.
     *
     * @param string $action
     * @return bool
     */
    public function isReasonRequired(string $action): bool
    {
        return in_array($action, [self::REJECT, self::BLOCK], true);
    }

    /**
     * Run an action.
     *
     * @param string $action
     * @param int $companyId
     * @param string|null $comment
     * @return CompanyInterface
     * @throws LocalizedException
     */
    public function run(string $action, int $companyId, ?string $comment): CompanyInterface
    {
        return match ($action) {
            self::APPROVE => $this->companyApproval->approve($companyId, $comment),
            self::REJECT => $this->companyApproval->reject($companyId, (string)$comment),
            self::BLOCK => $this->companyApproval->block($companyId, (string)$comment),
            self::UNBLOCK => $this->companyApproval->unblock($companyId, $comment),
            self::RESUBMIT => $this->companyApproval->resubmit($companyId, $comment),
            default => throw new LocalizedException(__('The action "%1" is not valid.', $action)),
        };
    }
}
