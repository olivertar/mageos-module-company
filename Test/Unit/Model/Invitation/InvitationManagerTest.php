<?php

declare(strict_types=1);

namespace MageOS\Company\Test\Unit\Model\Invitation;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Math\Random;
use MageOS\Company\Api\CompanyManagementInterface;
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Model\Config;
use MageOS\Company\Model\Invitation;
use MageOS\Company\Model\Invitation\InvitationManager;
use MageOS\Company\Model\InvitationFactory;
use MageOS\Company\Model\Notification\EmailSender;
use MageOS\Company\Model\ResourceModel\CompanyCustomer as CompanyCustomerResource;
use MageOS\Company\Model\ResourceModel\Invitation as InvitationResource;
use MageOS\Company\Model\ResourceModel\Invitation\CollectionFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class InvitationManagerTest extends TestCase
{
    private const COMPANY_ID = 5;

    /**
     * @var CustomerRepositoryInterface|MockObject
     */
    private $customerRepository;

    /**
     * @var CompanyCustomerResource|MockObject
     */
    private $relationResource;

    /**
     * @var InvitationResource|MockObject
     */
    private $invitationResource;

    /**
     * @var EmailSender|MockObject
     */
    private $emailSender;

    /**
     * @var CompanyInterface|MockObject
     */
    private $company;

    /**
     * @var InvitationManager
     */
    private InvitationManager $manager;

    protected function setUp(): void
    {
        $invitationFactory = $this->createMock(InvitationFactory::class);
        $invitationFactory->method('create')->willReturnCallback(
            fn (): Invitation => $this->getMockBuilder(Invitation::class)
                ->disableOriginalConstructor()
                ->onlyMethods([])
                ->getMock()
        );
        $this->invitationResource = $this->createMock(InvitationResource::class);
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->relationResource = $this->createMock(CompanyCustomerResource::class);
        $this->emailSender = $this->createMock(EmailSender::class);
        $config = $this->createMock(Config::class);
        $config->method('getInvitationLifetime')->willReturn(7);
        $random = $this->createMock(Random::class);
        $random->method('getRandomString')->willReturn('secret-token');
        $this->company = $this->createMock(CompanyInterface::class);
        $this->company->method('getId')->willReturn(self::COMPANY_ID);
        $this->company->method('getWebsiteId')->willReturn(1);
        $this->company->method('getStatus')->willReturn(CompanyInterface::STATUS_APPROVED);

        $this->manager = new InvitationManager(
            $invitationFactory,
            $this->invitationResource,
            $this->createMock(CollectionFactory::class),
            $this->createMock(CompanyRepositoryInterface::class),
            $this->createMock(CompanyManagementInterface::class),
            $this->relationResource,
            $this->customerRepository,
            $this->emailSender,
            $config,
            $random
        );
    }

    public function testInviteStoresOnlyTheTokenHashAndEmailsTheToken(): void
    {
        $this->customerRepository->method('get')->willThrowException(new NoSuchEntityException());
        $saved = null;
        $this->invitationResource->expects($this->once())->method('deleteOpen')
            ->with(self::COMPANY_ID, 'new@example.com');
        $this->invitationResource->expects($this->once())->method('save')->willReturnCallback(
            function (Invitation $invitation) use (&$saved) {
                $saved = $invitation;

                return $this->invitationResource;
            }
        );
        $this->emailSender->expects($this->once())->method('sendInvitation')
            ->with($this->company, 'new@example.com', 'secret-token', '');

        $this->manager->invite($this->company, ' New@Example.com ', 'user', 'Buyer', null);

        $this->assertSame(hash('sha256', 'secret-token'), $saved->getData(Invitation::TOKEN_HASH));
        $this->assertSame('Buyer', $saved->getJobTitle());
    }

    public function testInvalidEmail(): void
    {
        $this->expectExceptionMessage('not a valid email');
        $this->manager->invite($this->company, 'nope', 'user', null, null);
    }

    public function testInvalidRole(): void
    {
        $this->expectExceptionMessage('not valid');
        $this->manager->invite($this->company, 'a@example.com', 'owner', null, null);
    }

    public function testCurrentUserCannotBeInvited(): void
    {
        $this->givenCustomerInCompany(self::COMPANY_ID);
        $this->expectExceptionMessage('already a user of your company');
        $this->manager->invite($this->company, 'a@example.com', 'user', null, null);
    }

    public function testUserOfAnotherCompanyIsRejectedWithoutDetails(): void
    {
        $this->givenCustomerInCompany(77);
        $this->emailSender->expects($this->never())->method('sendInvitation');
        $this->expectExceptionMessage('cannot be invited to your company');
        $this->manager->invite($this->company, 'a@example.com', 'user', null, null);
    }

    public function testAcceptRequiresTheInvitedEmail(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getEmail')->willReturn('other@example.com');
        $this->customerRepository->method('getById')->willReturn($customer);
        $invitation = $this->getMockBuilder(Invitation::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $invitation->setData(Invitation::EMAIL, 'invited@example.com');

        $this->expectExceptionMessage('This invitation was sent to invited@example.com');
        $this->manager->accept($invitation, 3);
    }

    private function givenCustomerInCompany(int $companyId): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(3);
        $this->customerRepository->method('get')->willReturn($customer);
        $this->relationResource->method('getCompanyIdByCustomerId')->with(3)->willReturn($companyId);
    }
}
