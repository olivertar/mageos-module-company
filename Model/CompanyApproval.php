<?php

declare(strict_types=1);

namespace MageOS\Company\Model;

use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use MageOS\Company\Api\CompanyApprovalInterface;
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Api\Data\MessageInterface;
use MageOS\Company\Api\MessageManagementInterface;
use MageOS\Company\Model\Notification\EmailSender;
use MageOS\Company\Model\Registration\WelcomeEmailDeferral;
use MageOS\Company\Model\ResourceModel\Company as CompanyResource;
use MageOS\Company\Model\Status\HistoryRecorder;
use MageOS\Company\Model\Status\SessionTerminator;
use MageOS\Company\Model\Status\TransitionPolicy;
use Psr\Log\LoggerInterface;

/**
 * Company status workflow: validates the transition, saves it with its history record, then notifies.
 */
class CompanyApproval implements CompanyApprovalInterface
{
    /**
     * Email sent for each target status.
     */
    private const EMAILS = [
        CompanyInterface::STATUS_PENDING => Config::EMAIL_APPLICATION_RECEIVED,
        CompanyInterface::STATUS_REJECTED => Config::EMAIL_REJECTED,
        CompanyInterface::STATUS_BLOCKED => Config::EMAIL_BLOCKED,
    ];

    /**
     * @param CompanyRepositoryInterface $companyRepository
     * @param CompanyResource $companyResource
     * @param TransitionPolicy $transitionPolicy
     * @param HistoryRecorder $historyRecorder
     * @param SessionTerminator $sessionTerminator
     * @param EmailSender $emailSender
     * @param EventManager $eventManager
     * @param WelcomeEmailDeferral $welcomeEmailDeferral
     * @param MessageManagementInterface $messageManagement
     * @param LoggerInterface $logger
     * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
     */
    public function __construct(
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly CompanyResource $companyResource,
        private readonly TransitionPolicy $transitionPolicy,
        private readonly HistoryRecorder $historyRecorder,
        private readonly SessionTerminator $sessionTerminator,
        private readonly EmailSender $emailSender,
        private readonly EventManager $eventManager,
        private readonly WelcomeEmailDeferral $welcomeEmailDeferral,
        private readonly MessageManagementInterface $messageManagement,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function approve(int $companyId, ?string $comment = null): CompanyInterface
    {
        $this->assertCurrentStatus($companyId, CompanyInterface::STATUS_PENDING);

        return $this->changeStatus($companyId, CompanyInterface::STATUS_APPROVED, $comment);
    }

    /**
     * @inheritDoc
     */
    public function reject(int $companyId, string $reason): CompanyInterface
    {
        return $this->changeStatus($companyId, CompanyInterface::STATUS_REJECTED, $reason);
    }

    /**
     * @inheritDoc
     */
    public function block(int $companyId, string $reason): CompanyInterface
    {
        return $this->changeStatus($companyId, CompanyInterface::STATUS_BLOCKED, $reason);
    }

    /**
     * @inheritDoc
     */
    public function unblock(int $companyId, ?string $comment = null): CompanyInterface
    {
        $this->assertCurrentStatus($companyId, CompanyInterface::STATUS_BLOCKED);

        return $this->changeStatus($companyId, CompanyInterface::STATUS_APPROVED, $comment);
    }

    /**
     * @inheritDoc
     */
    public function resubmit(int $companyId, ?string $comment = null): CompanyInterface
    {
        return $this->changeStatus($companyId, CompanyInterface::STATUS_PENDING, $comment);
    }

    /**
     * @inheritDoc
     */
    public function getHistory(int $companyId): array
    {
        $this->companyRepository->getById($companyId);

        return $this->historyRecorder->getHistory($companyId);
    }

    /**
     * Approve and unblock both lead to "approved": make sure the caller used the right one.
     *
     * @param int $companyId
     * @param int $expected
     * @return void
     * @throws LocalizedException
     */
    private function assertCurrentStatus(int $companyId, int $expected): void
    {
        $current = $this->companyRepository->getById($companyId)->getStatus();
        if ($current !== $expected) {
            throw new LocalizedException(__(
                'This action needs the status "%1", but the company is "%2".',
                $this->transitionPolicy->getLabel($expected),
                $this->transitionPolicy->getLabel($current)
            ));
        }
    }

    /**
     * Move a company to a new status.
     *
     * @param int $companyId
     * @param int $to
     * @param string|null $comment
     * @return CompanyInterface
     * @throws LocalizedException
     */
    private function changeStatus(int $companyId, int $to, ?string $comment): CompanyInterface
    {
        $company = $this->companyRepository->getById($companyId);
        $from = $company->getStatus();
        $this->transitionPolicy->assert($from, $to, $comment);

        $connection = $this->companyResource->getConnection();
        $connection->beginTransaction();
        try {
            $company->setStatus($to);
            $company = $this->companyRepository->save($company);
            $this->historyRecorder->record($companyId, $from, $to, $comment);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        $this->eventManager->dispatch('company_status_change_after', [
            'company' => $company,
            'from_status' => $from,
            'to_status' => $to,
            'comment' => $comment,
        ]);
        $this->addSystemMessage($companyId, $to, $comment);
        if (in_array($to, [CompanyInterface::STATUS_REJECTED, CompanyInterface::STATUS_BLOCKED], true)) {
            $this->sessionTerminator->terminate($companyId);
        }
        $this->emailSender->notifyCompany($company, $this->getEmailType($from, $to), $comment);
        if ($to === CompanyInterface::STATUS_APPROVED) {
            $this->welcomeEmailDeferral->release($companyId);
        }

        return $company;
    }

    /**
     * Leave a trace of the change in the company message thread.
     *
     * @param int $companyId
     * @param int $to
     * @param string|null $comment
     * @return void
     */
    private function addSystemMessage(int $companyId, int $to, ?string $comment): void
    {
        $body = (string)__('The company status changed to "%1".', $this->transitionPolicy->getLabel($to));
        $comment = trim((string)$comment);
        if ($comment !== '') {
            $body .= "\n" . $comment;
        }
        try {
            $this->messageManagement->send($companyId, $body, MessageInterface::AUTHOR_SYSTEM);
        } catch (\Throwable $e) {
            $this->logger->error('Company status message not saved: ' . $e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * Email notification of a transition.
     *
     * @param int $from
     * @param int $to
     * @return string
     */
    private function getEmailType(int $from, int $to): string
    {
        if ($to === CompanyInterface::STATUS_APPROVED) {
            return $from === CompanyInterface::STATUS_BLOCKED ? Config::EMAIL_UNBLOCKED : Config::EMAIL_APPROVED;
        }

        return self::EMAILS[$to];
    }
}
