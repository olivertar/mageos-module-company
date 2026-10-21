<?php

declare(strict_types=1);

namespace MageOS\Company\Test\Unit\Model;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Api\Data\MessageInterface;
use MageOS\Company\Api\MessageManagementInterface;
use MageOS\Company\Model\CompanyApproval;
use MageOS\Company\Model\Config;
use MageOS\Company\Model\Notification\EmailSender;
use MageOS\Company\Model\Registration\WelcomeEmailDeferral;
use MageOS\Company\Model\ResourceModel\Company as CompanyResource;
use MageOS\Company\Model\Status\HistoryRecorder;
use MageOS\Company\Model\Status\SessionTerminator;
use MageOS\Company\Model\Status\TransitionPolicy;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class CompanyApprovalTest extends TestCase
{
    private const COMPANY_ID = 7;

    /**
     * @var CompanyInterface|MockObject
     */
    private $company;

    /**
     * @var int
     */
    private int $status = CompanyInterface::STATUS_PENDING;

    /**
     * @var CompanyRepositoryInterface|MockObject
     */
    private $companyRepository;

    /**
     * @var AdapterInterface|MockObject
     */
    private $connection;

    /**
     * @var HistoryRecorder|MockObject
     */
    private $historyRecorder;

    /**
     * @var SessionTerminator|MockObject
     */
    private $sessionTerminator;

    /**
     * @var EmailSender|MockObject
     */
    private $emailSender;

    /**
     * @var EventManager|MockObject
     */
    private $eventManager;

    /**
     * @var WelcomeEmailDeferral|MockObject
     */
    private $welcomeEmailDeferral;

    /**
     * @var MessageManagementInterface|MockObject
     */
    private $messageManagement;

    /**
     * @var CompanyApproval
     */
    private CompanyApproval $approval;

    protected function setUp(): void
    {
        $this->company = $this->createMock(CompanyInterface::class);
        $this->company->method('getStatus')->willReturnCallback(fn (): int => $this->status);
        $this->company->method('setStatus')->willReturnCallback(function (int $status) {
            $this->status = $status;

            return $this->company;
        });
        $this->companyRepository = $this->createMock(CompanyRepositoryInterface::class);
        $this->companyRepository->method('getById')->with(self::COMPANY_ID)->willReturn($this->company);
        $this->companyRepository->method('save')->willReturnArgument(0);
        $this->connection = $this->createMock(AdapterInterface::class);
        $companyResource = $this->createMock(CompanyResource::class);
        $companyResource->method('getConnection')->willReturn($this->connection);
        $this->historyRecorder = $this->createMock(HistoryRecorder::class);
        $this->sessionTerminator = $this->createMock(SessionTerminator::class);
        $this->emailSender = $this->createMock(EmailSender::class);
        $this->eventManager = $this->createMock(EventManager::class);
        $this->welcomeEmailDeferral = $this->createMock(WelcomeEmailDeferral::class);
        $this->messageManagement = $this->createMock(MessageManagementInterface::class);

        $this->approval = new CompanyApproval(
            $this->companyRepository,
            $companyResource,
            new TransitionPolicy(),
            $this->historyRecorder,
            $this->sessionTerminator,
            $this->emailSender,
            $this->eventManager,
            $this->welcomeEmailDeferral,
            $this->messageManagement,
            $this->createMock(LoggerInterface::class)
        );
    }

    public function testApproveRecordsHistoryAndNotifies(): void
    {
        $this->connection->expects($this->once())->method('commit');
        $this->historyRecorder->expects($this->once())->method('record')->with(
            self::COMPANY_ID,
            CompanyInterface::STATUS_PENDING,
            CompanyInterface::STATUS_APPROVED,
            'Welcome'
        );
        $this->eventManager->expects($this->once())->method('dispatch')->with(
            'company_status_change_after',
            $this->callback(fn (array $data): bool => $data['from_status'] === CompanyInterface::STATUS_PENDING
                && $data['to_status'] === CompanyInterface::STATUS_APPROVED)
        );
        $this->sessionTerminator->expects($this->never())->method('terminate');
        $this->emailSender->expects($this->once())->method('notifyCompany')
            ->with($this->company, Config::EMAIL_APPROVED, 'Welcome');
        $this->welcomeEmailDeferral->expects($this->once())->method('release')->with(self::COMPANY_ID);

        $this->approval->approve(self::COMPANY_ID, 'Welcome');

        $this->assertSame(CompanyInterface::STATUS_APPROVED, $this->status);
    }

    public function testBlockLogsUsersOut(): void
    {
        $this->status = CompanyInterface::STATUS_APPROVED;
        $this->sessionTerminator->expects($this->once())->method('terminate')->with(self::COMPANY_ID);
        $this->emailSender->expects($this->once())->method('notifyCompany')
            ->with($this->company, Config::EMAIL_BLOCKED, 'Unpaid invoices');
        $this->welcomeEmailDeferral->expects($this->never())->method('release');
        $this->messageManagement->expects($this->once())->method('send')->with(
            self::COMPANY_ID,
            $this->stringContains('Unpaid invoices'),
            MessageInterface::AUTHOR_SYSTEM
        );

        $this->approval->block(self::COMPANY_ID, 'Unpaid invoices');

        $this->assertSame(CompanyInterface::STATUS_BLOCKED, $this->status);
    }

    public function testUnblockSendsUnblockedEmail(): void
    {
        $this->status = CompanyInterface::STATUS_BLOCKED;
        $this->emailSender->expects($this->once())->method('notifyCompany')
            ->with($this->company, Config::EMAIL_UNBLOCKED, null);

        $this->approval->unblock(self::COMPANY_ID);

        $this->assertSame(CompanyInterface::STATUS_APPROVED, $this->status);
    }

    public function testApproveCannotUnblock(): void
    {
        $this->status = CompanyInterface::STATUS_BLOCKED;
        $this->companyRepository->expects($this->never())->method('save');

        $this->expectException(LocalizedException::class);
        $this->approval->approve(self::COMPANY_ID);
    }

    public function testInvalidTransitionChangesNothing(): void
    {
        $this->status = CompanyInterface::STATUS_REJECTED;
        $this->companyRepository->expects($this->never())->method('save');
        $this->historyRecorder->expects($this->never())->method('record');
        $this->emailSender->expects($this->never())->method('notifyCompany');

        $this->expectException(LocalizedException::class);
        $this->approval->block(self::COMPANY_ID, 'reason');
    }

    public function testRejectNeedsReason(): void
    {
        $this->companyRepository->expects($this->never())->method('save');

        $this->expectException(LocalizedException::class);
        $this->approval->reject(self::COMPANY_ID, ' ');
    }

    public function testFailedSaveRollsBackAndDoesNotNotify(): void
    {
        $this->companyRepository = $this->createMock(CompanyRepositoryInterface::class);
        $this->companyRepository->method('getById')->willReturn($this->company);
        $this->companyRepository->method('save')->willThrowException(new LocalizedException(__('Invalid')));
        $companyResource = $this->createMock(CompanyResource::class);
        $companyResource->method('getConnection')->willReturn($this->connection);
        $approval = new CompanyApproval(
            $this->companyRepository,
            $companyResource,
            new TransitionPolicy(),
            $this->historyRecorder,
            $this->sessionTerminator,
            $this->emailSender,
            $this->eventManager,
            $this->welcomeEmailDeferral,
            $this->messageManagement,
            $this->createMock(LoggerInterface::class)
        );
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');
        $this->emailSender->expects($this->never())->method('notifyCompany');
        $this->eventManager->expects($this->never())->method('dispatch');

        $this->expectException(LocalizedException::class);
        $approval->approve(self::COMPANY_ID);
    }
}
