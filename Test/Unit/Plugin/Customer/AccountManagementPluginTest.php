<?php

declare(strict_types=1);

namespace MageOS\Company\Test\Unit\Plugin\Customer;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use MageOS\Company\Api\CompanyManagementInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Model\Authorization\CompanyContext;
use MageOS\Company\Plugin\Customer\AccountManagementPlugin;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class AccountManagementPluginTest extends TestCase
{
    /**
     * @var CompanyManagementInterface|MockObject
     */
    private $companyManagement;

    /**
     * @var CompanyContext|MockObject
     */
    private $companyContext;

    /**
     * @var CustomerInterface|MockObject
     */
    private $customer;

    /**
     * @var AccountManagementPlugin
     */
    private AccountManagementPlugin $plugin;

    protected function setUp(): void
    {
        $this->companyManagement = $this->createMock(CompanyManagementInterface::class);
        $this->customer = $this->createMock(CustomerInterface::class);
        $this->customer->method('getId')->willReturn('5');
        $this->companyContext = $this->createMock(CompanyContext::class);
        $this->plugin = new AccountManagementPlugin($this->companyManagement, $this->companyContext);
    }

    public function testCustomerWithoutCompanyCanLogIn(): void
    {
        $this->companyManagement->method('getByCustomerId')
            ->with(5)
            ->willThrowException(new NoSuchEntityException());

        $this->assertSame($this->customer, $this->authenticate());
    }

    public function testUserOfApprovedCompanyCanLogIn(): void
    {
        $this->givenCompanyStatus(CompanyInterface::STATUS_APPROVED);

        $this->assertSame($this->customer, $this->authenticate());
    }

    /**
     * @return array<string,array{0: int, 1: string}>
     */
    public static function deniedStatusProvider(): array
    {
        return [
            'pending' => [CompanyInterface::STATUS_PENDING, 'pending approval'],
            'rejected' => [CompanyInterface::STATUS_REJECTED, 'not approved'],
            'blocked' => [CompanyInterface::STATUS_BLOCKED, 'blocked'],
        ];
    }

    #[DataProvider('deniedStatusProvider')]
    public function testUserOfNotApprovedCompanyCannotLogIn(int $status, string $message): void
    {
        $this->givenCompanyStatus($status);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage($message);
        $this->authenticate();
    }

    public function testInactiveUserOfApprovedCompanyCannotLogIn(): void
    {
        $this->givenCompanyStatus(CompanyInterface::STATUS_APPROVED);
        $this->companyContext->method('isInactiveUser')->with(5)->willReturn(true);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('inactive');
        $this->authenticate();
    }

    private function givenCompanyStatus(int $status): void
    {
        $company = $this->createMock(CompanyInterface::class);
        $company->method('getStatus')->willReturn($status);
        $this->companyManagement->method('getByCustomerId')->with(5)->willReturn($company);
    }

    private function authenticate(): CustomerInterface
    {
        return $this->plugin->afterAuthenticate($this->createMock(AccountManagementInterface::class), $this->customer);
    }
}
