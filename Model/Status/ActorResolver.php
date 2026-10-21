<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Status;

use Magento\Authorization\Model\UserContextInterface;
use MageOS\Company\Api\Data\StatusHistoryInterface;

/**
 * Who is performing the current request, as a status history actor.
 */
class ActorResolver
{
    /**
     * @param UserContextInterface $userContext
     */
    public function __construct(
        private readonly UserContextInterface $userContext
    ) {
    }

    /**
     * Actor forced by actAs(), if any.
     *
     * @var array{0: string, 1: int|null}|null
     */
    private ?array $override = null;

    /**
     * Run a callback with another actor: for requests authenticated without a user context (application status link).
     *
     * @param string $actorType
     * @param int|null $actorId
     * @param callable $callback
     * @return mixed What the callback returns.
     */
    public function actAs(string $actorType, ?int $actorId, callable $callback): mixed
    {
        $previous = $this->override;
        $this->override = [$actorType, $actorId];
        try {
            return $callback();
        } finally {
            $this->override = $previous;
        }
    }

    /**
     * Actor type and ID of the current user context.
     *
     * @return array{0: string, 1: int|null}
     */
    public function resolve(): array
    {
        if ($this->override !== null) {
            return $this->override;
        }
        $userId = $this->userContext->getUserId();
        $userId = $userId ? (int)$userId : null;

        return match ((int)$this->userContext->getUserType()) {
            UserContextInterface::USER_TYPE_ADMIN => [StatusHistoryInterface::ACTOR_ADMIN, $userId],
            UserContextInterface::USER_TYPE_INTEGRATION => [StatusHistoryInterface::ACTOR_INTEGRATION, $userId],
            UserContextInterface::USER_TYPE_CUSTOMER => [StatusHistoryInterface::ACTOR_CUSTOMER, $userId],
            default => [StatusHistoryInterface::ACTOR_SYSTEM, null],
        };
    }
}
