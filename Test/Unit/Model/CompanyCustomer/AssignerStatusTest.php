<?php

declare(strict_types=1);

namespace MageOS\Company\Test\Unit\Model\CompanyCustomer;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use MageOS\Company\Api\Data\CompanyCustomerInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Model\CompanyCustomer;
use MageOS\Company\Model\CompanyCustomer\Assigner;
use MageOS\Company\Model\CompanyCustomer\AssignmentValidator;
use MageOS\Company\Model\CompanyCustomerFactory;
use MageOS\Company\Model\ResourceModel\CompanyCustomer as CompanyCustomerResource;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class AssignerStatusTest extends TestCase
{
    private const COMPANY_ID = 4;
    private const CUSTOMER_ID = 9;

    /**
     * @var array<string,mixed>
     */
    private array $relation = [
        'customer_id' => self::CUSTOMER_ID,
        'company_entity_id' => self::COMPANY_ID,
        'role' => CompanyCustomerInterface::ROLE_USER,
        'status' => CompanyCustomerInterface::STATUS_ACTIVE,
    ];

    /**
     * @var int
     */
    private int $otherActiveAdmins = 1;

    /**
     * @var CompanyCustomerResource|MockObject
     */
    private $resource;

    /**
     * @var CompanyInterface|MockObject
     */
    private $company;

    /**
     * @var Assigner
     */
    private Assigner $assigner;

    protected function setUp(): void
    {
        $factory = $this->createMock(CompanyCustomerFactory::class);
        $factory->method('create')->willReturnCallback(
            fn (): CompanyCustomer => $this->getMockBuilder(CompanyCustomer::class)
                ->disableOriginalConstructor()
                ->onlyMethods([])
                ->getMock()
        );
        $this->resource = $this->createMock(CompanyCustomerResource::class);
        $this->resource->method('load')->willReturnCallback(function (CompanyCustomer $relation) {
            $relation->setData($this->relation);

            return $this->resource;
        });
        $this->resource->method('countActiveAdmins')->willReturnCallback(fn (): int => $this->otherActiveAdmins);
        $this->company = $this->createMock(CompanyInterface::class);
        $this->company->method('getId')->willReturn(self::COMPANY_ID);
        $this->assigner = new Assigner($factory, $this->resource, $this->createMock(AssignmentValidator::class));
    }

    public function testDeactivateUser(): void
    {
        $this->resource->expects($this->once())->method('save');

        $relation = $this->assigner->setStatus($this->company, self::CUSTOMER_ID, 0);

        $this->assertSame(CompanyCustomerInterface::STATUS_INACTIVE, $relation->getStatus());
    }

    public function testSuperUserCannotBeDeactivated(): void
    {
        $this->company->method('getSuperUserId')->willReturn(self::CUSTOMER_ID);
        $this->expectExceptionMessage('super user cannot be deactivated');
        $this->assigner->setStatus($this->company, self::CUSTOMER_ID, 0);
    }

    public function testLastActiveAdminCannotBeDeactivated(): void
    {
        $this->relation['role'] = CompanyCustomerInterface::ROLE_ADMIN;
        $this->otherActiveAdmins = 0;
        $this->expectExceptionMessage('at least one active admin');
        $this->assigner->setStatus($this->company, self::CUSTOMER_ID, 0);
    }

    public function testUserOfAnotherCompanyIsNotFound(): void
    {
        $this->relation['company_entity_id'] = 99;
        $this->expectException(NoSuchEntityException::class);
        $this->assigner->setStatus($this->company, self::CUSTOMER_ID, 1);
    }

    public function testInvalidStatus(): void
    {
        $this->expectException(LocalizedException::class);
        $this->assigner->setStatus($this->company, self::CUSTOMER_ID, 5);
    }
}
