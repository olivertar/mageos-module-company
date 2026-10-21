<?php

declare(strict_types=1);

namespace MageOS\Company\Test\Unit\Model\Registration;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\UrlFactory;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Model\Config;
use MageOS\Company\Model\Registration\StatusLink;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class StatusLinkTest extends TestCase
{
    private const NOW = 1_800_000_000;

    /**
     * @var array<string,mixed>
     */
    private array $company = [
        'id' => 7,
        'status' => CompanyInterface::STATUS_PENDING,
        'website_id' => 1,
        'email' => 'co@example.com',
        'super_user_id' => 3,
    ];

    /**
     * @var int
     */
    private int $now = self::NOW;

    /**
     * @var int
     */
    private int $lifetime = 30;

    /**
     * @var StatusLink
     */
    private StatusLink $statusLink;

    protected function setUp(): void
    {
        $deploymentConfig = $this->createMock(DeploymentConfig::class);
        $deploymentConfig->method('get')->willReturn("oldkey\nnewkey");
        $repository = $this->createMock(CompanyRepositoryInterface::class);
        $repository->method('getById')->willReturnCallback(fn (int $id) => $this->makeCompany($id));
        $config = $this->createMock(Config::class);
        $config->method('getStatusLinkLifetime')->willReturnCallback(fn (): int => $this->lifetime);
        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtTimestamp')->willReturnCallback(fn (): int => $this->now);

        $this->statusLink = new StatusLink(
            $deploymentConfig,
            $repository,
            $this->createMock(StoreManagerInterface::class),
            $config,
            $dateTime,
            $this->createMock(UrlFactory::class)
        );
    }

    private function makeCompany(?int $id = null): CompanyInterface
    {
        $company = $this->createMock(CompanyInterface::class);
        $company->method('getId')->willReturn($id ?? $this->company['id']);
        $company->method('getStatus')->willReturn($this->company['status']);
        $company->method('getWebsiteId')->willReturn($this->company['website_id']);
        $company->method('getEmail')->willReturn($this->company['email']);
        $company->method('getSuperUserId')->willReturn($this->company['super_user_id']);

        return $company;
    }

    public function testValidTokenResolves(): void
    {
        $token = (string)$this->statusLink->createToken($this->makeCompany());
        $this->assertMatchesRegularExpression('/^7\.\d+\.[a-f0-9]{64}$/', $token);
        $this->assertSame(7, $this->statusLink->resolve($token, 1)->getId());
    }

    public function testNoTokenWhenDisabledOrApproved(): void
    {
        $this->company['status'] = CompanyInterface::STATUS_APPROVED;
        $this->assertNull($this->statusLink->createToken($this->makeCompany()));
        $this->company['status'] = CompanyInterface::STATUS_REJECTED;
        $this->assertNotNull($this->statusLink->createToken($this->makeCompany()));
        $this->lifetime = 0;
        $this->assertNull($this->statusLink->createToken($this->makeCompany()));
    }

    /**
     * @return array<string,array{0: callable(StatusLinkTest, string): string}>
     */
    public static function invalidCases(): array
    {
        return [
            'tampered signature' => [fn ($test, string $token) => substr($token, 0, -1) . 'x'],
            'other company' => [fn ($test, string $token) => '8' . substr($token, 1)],
            'longer expiry' => [fn ($test, string $token) => preg_replace('/\.(\d+)\./', '.9$1.', $token)],
            'garbage' => [fn ($test, string $token) => 'abc'],
        ];
    }

    /**
     * @dataProvider invalidCases
     * @param callable(StatusLinkTest, string): string $mutate
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidCases')]
    public function testInvalidTokensAreRejected(callable $mutate): void
    {
        $token = (string)$this->statusLink->createToken($this->makeCompany());
        $this->expectException(LocalizedException::class);
        $this->statusLink->resolve($mutate($this, $token), 1);
    }

    public function testExpiredToken(): void
    {
        $token = (string)$this->statusLink->createToken($this->makeCompany());
        $this->now += 31 * 86400;
        $this->expectException(LocalizedException::class);
        $this->statusLink->resolve($token, 1);
    }

    public function testOtherWebsite(): void
    {
        $token = (string)$this->statusLink->createToken($this->makeCompany());
        $this->expectException(LocalizedException::class);
        $this->statusLink->resolve($token, 2);
    }

    public function testChangedEmailOrSuperUserVoidsLinks(): void
    {
        $token = (string)$this->statusLink->createToken($this->makeCompany());
        $this->company['super_user_id'] = 4;
        try {
            $this->statusLink->resolve($token, 1);
            $this->fail('A new super user must void the link.');
        } catch (LocalizedException) {
            $this->addToAssertionCount(1);
        }
        $this->company['super_user_id'] = 3;
        $this->company['email'] = 'new@example.com';
        $this->expectException(LocalizedException::class);
        $this->statusLink->resolve($token, 1);
    }

    public function testDisabledLinksStopWorking(): void
    {
        $token = (string)$this->statusLink->createToken($this->makeCompany());
        $this->lifetime = 0;
        $this->expectException(LocalizedException::class);
        $this->statusLink->resolve($token, 1);
    }
}
