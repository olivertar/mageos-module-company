<?php

declare(strict_types=1);

namespace MageOS\Company\Test\Unit\Model\Management;

use Magento\Store\Model\StoreManagerInterface;
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Model\Company;
use MageOS\Company\Model\Management\CompanyWriter;
use MageOS\Company\Model\Status\HistoryRecorder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class CompanyWriterTest extends TestCase
{
    /**
     * @var CompanyRepositoryInterface|MockObject
     */
    private $repository;

    /**
     * @var HistoryRecorder|MockObject
     */
    private $historyRecorder;

    /**
     * @var CompanyWriter
     */
    private CompanyWriter $writer;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CompanyRepositoryInterface::class);
        $this->repository->method('save')->willReturnArgument(0);
        $this->historyRecorder = $this->createMock(HistoryRecorder::class);
        $this->writer = new CompanyWriter(
            $this->repository,
            $this->historyRecorder,
            $this->createMock(StoreManagerInterface::class)
        );
    }

    public function testCreateForcesPendingWithoutErpCodeAndRecordsHistory(): void
    {
        $company = $this->company(['name' => 'X', 'status' => 1, 'company_id' => 'ERP', 'website_id' => 1]);
        $company->setData('entity_id', null);
        $this->historyRecorder->expects($this->once())->method('record');

        $saved = $this->writer->create($company);

        $this->assertSame(CompanyInterface::STATUS_PENDING, $saved->getStatus());
        $this->assertNull($saved->getCompanyId());
    }

    public function testUpdateKeepsProtectedFieldsAndClearsEmptyValues(): void
    {
        $stored = $this->company([
            'entity_id' => 7, 'name' => 'Old', 'status' => 1, 'company_id' => 'ERP-1', 'website_id' => 1,
            'phone' => '100', 'fax' => '200',
        ]);
        $this->repository->method('getById')->with(7)->willReturn($stored);
        $payload = $this->company([
            'name' => 'New', 'status' => 3, 'company_id' => '7', 'website_id' => 2, 'fax' => '', 'phone' => null,
        ]);

        $this->writer->update(7, $payload);

        $this->assertSame('New', $stored->getData('name'));
        $this->assertSame(1, (int)$stored->getData('status'));
        $this->assertSame('ERP-1', $stored->getData('company_id'));
        $this->assertSame(1, (int)$stored->getData('website_id'));
        $this->assertSame('100', $stored->getData('phone'));
        $this->assertNull($stored->getData('fax'));
    }

    public function testSetErpCodeEmptyUnlinks(): void
    {
        $stored = $this->company(['entity_id' => 7, 'company_id' => 'ERP-1']);
        $this->repository->method('getById')->willReturn($stored);

        $this->writer->setErpCode(7, '  ');

        $this->assertNull($stored->getCompanyId());
    }

    /**
     * @param array<string,mixed> $data
     * @return Company
     */
    private function company(array $data): Company
    {
        $company = $this->getMockBuilder(Company::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCustomAttributesCodes'])
            ->getMock();
        $company->method('getCustomAttributesCodes')->willReturn([]);
        $company->setData($data);

        return $company;
    }
}
