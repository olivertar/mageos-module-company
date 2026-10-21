<?php

declare(strict_types=1);

namespace MageOS\Company\Test\Unit\Model\Status;

use Magento\Framework\Exception\LocalizedException;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Model\Status\TransitionPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TransitionPolicyTest extends TestCase
{
    private const PENDING = CompanyInterface::STATUS_PENDING;
    private const APPROVED = CompanyInterface::STATUS_APPROVED;
    private const REJECTED = CompanyInterface::STATUS_REJECTED;
    private const BLOCKED = CompanyInterface::STATUS_BLOCKED;

    /**
     * @var TransitionPolicy
     */
    private TransitionPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new TransitionPolicy();
    }

    /**
     * @return array<string,array{0: int, 1: int, 2: bool}>
     */
    public static function transitionProvider(): array
    {
        return [
            'pending → approved' => [self::PENDING, self::APPROVED, true],
            'pending → rejected' => [self::PENDING, self::REJECTED, true],
            'pending → blocked' => [self::PENDING, self::BLOCKED, false],
            'approved → blocked' => [self::APPROVED, self::BLOCKED, true],
            'approved → pending' => [self::APPROVED, self::PENDING, false],
            'approved → rejected' => [self::APPROVED, self::REJECTED, false],
            'blocked → approved' => [self::BLOCKED, self::APPROVED, true],
            'blocked → pending' => [self::BLOCKED, self::PENDING, false],
            'rejected → pending' => [self::REJECTED, self::PENDING, true],
            'rejected → approved' => [self::REJECTED, self::APPROVED, false],
            'same status' => [self::APPROVED, self::APPROVED, false],
            'unknown status' => [99, self::APPROVED, false],
        ];
    }

    #[DataProvider('transitionProvider')]
    public function testIsAllowed(int $from, int $to, bool $expected): void
    {
        $this->assertSame($expected, $this->policy->isAllowed($from, $to));
    }

    public function testRejectAndBlockNeedAReason(): void
    {
        $this->assertTrue($this->policy->isReasonRequired(self::REJECTED));
        $this->assertTrue($this->policy->isReasonRequired(self::BLOCKED));
        $this->assertFalse($this->policy->isReasonRequired(self::APPROVED));
        $this->assertFalse($this->policy->isReasonRequired(self::PENDING));
    }

    public function testAssertRejectsInvalidTransition(): void
    {
        $this->expectException(LocalizedException::class);
        $this->policy->assert(self::PENDING, self::BLOCKED, 'reason');
    }

    public function testAssertRejectsBlankReason(): void
    {
        $this->expectException(LocalizedException::class);
        $this->policy->assert(self::APPROVED, self::BLOCKED, '   ');
    }

    public function testAssertAcceptsValidTransition(): void
    {
        $this->policy->assert(self::PENDING, self::APPROVED, null);
        $this->policy->assert(self::PENDING, self::REJECTED, 'Incomplete data');
        $this->addToAssertionCount(2);
    }
}
