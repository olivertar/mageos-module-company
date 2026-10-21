<?php

declare(strict_types=1);

namespace MageOS\Company\Test\Unit\Model\Authorization;

use Magento\Framework\Exception\LocalizedException;
use MageOS\Company\Api\CompanyAuthorizationInterface as Auth;
use MageOS\Company\Api\Data\CompanyCustomerInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Model\Authorization\CompanyContext;
use MageOS\Company\Model\Authorization\RoleBasedAuthorization;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class RoleBasedAuthorizationTest extends TestCase
{
    /**
     * @return array<string,array{0: string|null, 1: string, 2: bool}>
     */
    public static function permissionProvider(): array
    {
        return [
            'admin edits' => [CompanyCustomerInterface::ROLE_ADMIN, Auth::RESOURCE_EDIT, true],
            'admin manages users' => [CompanyCustomerInterface::ROLE_ADMIN, Auth::RESOURCE_USERS_MANAGE, true],
            'admin custom resource' => [CompanyCustomerInterface::ROLE_ADMIN, 'company.orders.view', true],
            'user views' => [CompanyCustomerInterface::ROLE_USER, Auth::RESOURCE_VIEW, true],
            'user views addresses' => [CompanyCustomerInterface::ROLE_USER, Auth::RESOURCE_ADDRESS_VIEW, true],
            'user reads messages' => [CompanyCustomerInterface::ROLE_USER, Auth::RESOURCE_MESSAGES_VIEW, true],
            'user sends messages' => [CompanyCustomerInterface::ROLE_USER, Auth::RESOURCE_MESSAGES_SEND, true],
            'user cannot edit' => [CompanyCustomerInterface::ROLE_USER, Auth::RESOURCE_EDIT, false],
            'user cannot manage users' => [CompanyCustomerInterface::ROLE_USER, Auth::RESOURCE_USERS_MANAGE, false],
            'user cannot manage addresses' => [
                CompanyCustomerInterface::ROLE_USER,
                Auth::RESOURCE_ADDRESS_MANAGE,
                false,
            ],
        ];
    }

    #[DataProvider('permissionProvider')]
    public function testRolePermissions(string $role, string $resource, bool $expected): void
    {
        $context = $this->createMock(CompanyContext::class);
        $context->method('getCompany')->willReturn($this->createMock(CompanyInterface::class));
        $context->method('getRole')->willReturn($role);

        $this->assertSame($expected, (new RoleBasedAuthorization($context))->isAllowed(7, $resource));
    }

    public function testNothingIsAllowedWithoutUsableCompany(): void
    {
        $context = $this->createMock(CompanyContext::class);
        $context->method('getCompany')->willThrowException(new LocalizedException(__('No company')));
        $context->method('getRole')->willReturn(CompanyCustomerInterface::ROLE_ADMIN);

        $this->assertFalse((new RoleBasedAuthorization($context))->isAllowed(7, Auth::RESOURCE_VIEW));
    }
}
