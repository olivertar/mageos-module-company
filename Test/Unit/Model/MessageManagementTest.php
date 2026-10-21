<?php

declare(strict_types=1);

namespace MageOS\Company\Test\Unit\Model;

use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\MessageQueue\PublisherInterface;
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Api\Data\CompanyCustomerInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Api\Data\MessageInterface;
use MageOS\Company\Api\MessageRepositoryInterface;
use MageOS\Company\Model\CompanyCustomer;
use MageOS\Company\Model\CompanyCustomerFactory;
use MageOS\Company\Model\Config;
use MageOS\Company\Model\Message;
use MageOS\Company\Model\MessageFactory;
use MageOS\Company\Model\MessageManagement;
use MageOS\Company\Model\ResourceModel\CompanyCustomer as CompanyCustomerResource;
use MageOS\Company\Model\ResourceModel\Message as MessageResource;
use MageOS\Company\Model\ResourceModel\Message\CollectionFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class MessageManagementTest extends TestCase
{
    private const COMPANY_ID = 3;
    private const CUSTOMER_ID = 11;

    /**
     * @var int
     */
    private int $companyStatus = CompanyInterface::STATUS_APPROVED;

    /**
     * @var array<string,mixed>
     */
    private array $relation = [
        'company_entity_id' => self::COMPANY_ID,
        'status' => CompanyCustomerInterface::STATUS_ACTIVE,
    ];

    /**
     * @var int
     */
    private int $recentMessages = 0;

    /**
     * @var int|null
     */
    private ?int $superUserId = self::CUSTOMER_ID;

    /**
     * @var Message|null
     */
    private ?Message $saved = null;

    /**
     * @var PublisherInterface|MockObject
     */
    private $publisher;

    /**
     * @var MessageManagement
     */
    private MessageManagement $management;

    protected function setUp(): void
    {
        $company = $this->createMock(CompanyInterface::class);
        $company->method('getId')->willReturn(self::COMPANY_ID);
        $company->method('getWebsiteId')->willReturn(1);
        $company->method('getStatus')->willReturnCallback(fn (): int => $this->companyStatus);
        $company->method('getSuperUserId')->willReturnCallback(fn (): ?int => $this->superUserId);
        $companyRepository = $this->createMock(CompanyRepositoryInterface::class);
        $companyRepository->method('getById')->willReturn($company);

        $messageRepository = $this->createMock(MessageRepositoryInterface::class);
        $messageRepository->method('save')->willReturnCallback(function (Message $message): Message {
            $message->setData(MessageInterface::MESSAGE_ID, 99);
            $this->saved = $message;

            return $message;
        });
        $messageFactory = $this->createMock(MessageFactory::class);
        $messageFactory->method('create')->willReturnCallback(
            fn (): Message => $this->getMockBuilder(Message::class)
                ->disableOriginalConstructor()
                ->onlyMethods([])
                ->getMock()
        );
        $messageResource = $this->createMock(MessageResource::class);
        $messageResource->method('countRecentByCustomer')->willReturnCallback(fn (): int => $this->recentMessages);

        $relationFactory = $this->createMock(CompanyCustomerFactory::class);
        $relationFactory->method('create')->willReturnCallback(
            fn (): CompanyCustomer => $this->getMockBuilder(CompanyCustomer::class)
                ->disableOriginalConstructor()
                ->onlyMethods([])
                ->getMock()
        );
        $relationResource = $this->createMock(CompanyCustomerResource::class);
        $relationResource->method('load')->willReturnCallback(
            function (CompanyCustomer $relation, $customerId) use ($relationResource) {
                if ((int)$customerId === self::CUSTOMER_ID) {
                    $relation->setData($this->relation + ['customer_id' => $customerId]);
                }

                return $relationResource;
            }
        );

        $config = $this->createMock(Config::class);
        $config->method('getMessageMaxLength')->willReturn(20);
        $config->method('getMessageRateLimit')->willReturn(5);
        $this->publisher = $this->createMock(PublisherInterface::class);

        $this->management = new MessageManagement(
            $companyRepository,
            $messageRepository,
            $messageFactory,
            $messageResource,
            $this->createMock(CollectionFactory::class),
            $relationResource,
            $relationFactory,
            $config,
            $this->publisher,
            $this->createMock(EventManager::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(\Magento\Authorization\Model\UserContextInterface::class)
        );
    }

    public function testCustomerMessageIsQueuedAndReadByItsSide(): void
    {
        $this->publisher->expects($this->once())->method('publish')
            ->with(MessageManagement::NOTIFY_TOPIC, '99');

        $this->management->send(
            self::COMPANY_ID,
            "  Hello\r\nthere ",
            MessageInterface::AUTHOR_CUSTOMER,
            self::CUSTOMER_ID
        );

        $this->assertSame("Hello\nthere", $this->saved->getBody());
        $this->assertNull($this->saved->getData(MessageInterface::READ_AT_ADMIN));
        $this->assertNotNull($this->saved->getData(MessageInterface::READ_AT_CUSTOMER));
    }

    public function testInternalNoteIsNotNotifiedAndHiddenFromCompany(): void
    {
        $this->publisher->expects($this->never())->method('publish');

        $this->management->send(self::COMPANY_ID, 'Note', MessageInterface::AUTHOR_ADMIN, 1, true);

        $this->assertTrue($this->saved->getIsInternal());
        $this->assertNotNull($this->saved->getData(MessageInterface::READ_AT_ADMIN));
        $this->assertNotNull($this->saved->getData(MessageInterface::READ_AT_CUSTOMER));
    }

    public function testSystemMessageIsUnreadForCompanyOnlyAndNotNotified(): void
    {
        $this->publisher->expects($this->never())->method('publish');

        $this->management->send(self::COMPANY_ID, 'A status message longer than the limit', 'system', 5);

        $this->assertNull($this->saved->getAuthorId());
        $this->assertNotNull($this->saved->getData(MessageInterface::READ_AT_ADMIN));
        $this->assertNull($this->saved->getData(MessageInterface::READ_AT_CUSTOMER));
    }

    public function testEmptyMessageIsRejected(): void
    {
        $this->expectExceptionMessage('cannot be empty');
        $this->management->send(self::COMPANY_ID, "  \n ", MessageInterface::AUTHOR_ADMIN, 1);
    }

    public function testTooLongMessageIsRejected(): void
    {
        $this->expectExceptionMessage('longer than 20');
        $this->management->send(self::COMPANY_ID, str_repeat('ñ', 21), MessageInterface::AUTHOR_ADMIN, 1);
    }

    public function testCustomerCannotWriteInternalNotes(): void
    {
        $this->expectExceptionMessage('Only admins');
        $this->management->send(self::COMPANY_ID, 'x', MessageInterface::AUTHOR_CUSTOMER, self::CUSTOMER_ID, true);
    }

    public function testCustomerOfAnotherCompanyIsRejected(): void
    {
        $this->relation['company_entity_id'] = 999;
        $this->expectExceptionMessage('cannot send messages to this company');
        $this->management->send(self::COMPANY_ID, 'x', MessageInterface::AUTHOR_CUSTOMER, self::CUSTOMER_ID);
    }

    public function testInactiveUserIsRejected(): void
    {
        $this->relation['status'] = CompanyCustomerInterface::STATUS_INACTIVE;
        $this->expectException(LocalizedException::class);
        $this->management->send(self::COMPANY_ID, 'x', MessageInterface::AUTHOR_CUSTOMER, self::CUSTOMER_ID);
    }

    public function testPendingCompanyCannotWrite(): void
    {
        $this->companyStatus = CompanyInterface::STATUS_PENDING;
        $this->expectExceptionMessage('once your company is approved');
        $this->management->send(self::COMPANY_ID, 'x', MessageInterface::AUTHOR_CUSTOMER, self::CUSTOMER_ID);
    }

    public function testRateLimit(): void
    {
        $this->recentMessages = 5;
        $this->expectExceptionMessage('too many messages');
        $this->management->send(self::COMPANY_ID, 'x', MessageInterface::AUTHOR_CUSTOMER, self::CUSTOMER_ID);
    }

    public function testInvalidAuthorType(): void
    {
        $this->expectExceptionMessage('not valid');
        $this->management->send(self::COMPANY_ID, 'x', 'integration', 1);
    }

    public function testApplicantWritesWhilePendingOrRejected(): void
    {
        foreach ([CompanyInterface::STATUS_PENDING, CompanyInterface::STATUS_REJECTED] as $status) {
            $this->companyStatus = $status;
            $message = $this->management->sendAsApplicant(self::COMPANY_ID, ' Here it is ');
            $this->assertSame(MessageInterface::AUTHOR_CUSTOMER, $message->getAuthorType());
            $this->assertSame(self::CUSTOMER_ID, $message->getAuthorId());
            $this->assertSame('Here it is', $message->getBody());
        }
    }

    public function testApplicantCannotWriteOnceApproved(): void
    {
        $this->expectExceptionMessage('cannot send messages');
        $this->management->sendAsApplicant(self::COMPANY_ID, 'x');
    }

    public function testApplicantNeedsSuperUser(): void
    {
        $this->companyStatus = CompanyInterface::STATUS_PENDING;
        $this->superUserId = null;
        $this->expectExceptionMessage('cannot send messages');
        $this->management->sendAsApplicant(self::COMPANY_ID, 'x');
    }

    public function testApplicantRateLimit(): void
    {
        $this->companyStatus = CompanyInterface::STATUS_PENDING;
        $this->recentMessages = 5;
        $this->expectExceptionMessage('too many messages');
        $this->management->sendAsApplicant(self::COMPANY_ID, 'x');
    }
}
