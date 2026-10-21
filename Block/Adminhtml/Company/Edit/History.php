<?php

declare(strict_types=1);

namespace MageOS\Company\Block\Adminhtml\Company\Edit;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\User\Model\ResourceModel\User as UserResource;
use Magento\User\Model\UserFactory;
use MageOS\Company\Api\CompanyApprovalInterface;
use MageOS\Company\Api\Data\StatusHistoryInterface;
use MageOS\Company\Model\Status\TransitionPolicy;

/**
 * Status history of the edited company.
 */
class History extends Template
{
    /**
     * @var array<string,string>
     */
    private array $actorNames = [];

    /**
     * @param Context $context
     * @param CompanyApprovalInterface $companyApproval
     * @param TransitionPolicy $transitionPolicy
     * @param CustomerRepositoryInterface $customerRepository
     * @param UserFactory $userFactory
     * @param UserResource $userResource
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly CompanyApprovalInterface $companyApproval,
        private readonly TransitionPolicy $transitionPolicy,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly UserFactory $userFactory,
        private readonly UserResource $userResource,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * History records, newest first.
     *
     * @return StatusHistoryInterface[]
     */
    public function getHistory(): array
    {
        $companyId = (int)$this->getRequest()->getParam('id');
        if (!$companyId) {
            return [];
        }
        try {
            return $this->companyApproval->getHistory($companyId);
        } catch (NoSuchEntityException) {
            return [];
        }
    }

    /**
     * Status label.
     *
     * @param int|null $status
     * @return string
     */
    public function getStatusLabel(?int $status): string
    {
        return $status === null ? '' : $this->transitionPolicy->getLabel($status);
    }

    /**
     * Human readable actor.
     *
     * @param StatusHistoryInterface $record
     * @return string
     */
    public function getActorLabel(StatusHistoryInterface $record): string
    {
        $type = $record->getActorType();
        $actorId = $record->getActorId();
        $key = $type . ':' . $actorId;
        if (!isset($this->actorNames[$key])) {
            $this->actorNames[$key] = $this->resolveActorName($type, $actorId);
        }

        return $this->actorNames[$key];
    }

    /**
     * Build the actor label.
     *
     * @param string $type
     * @param int|null $actorId
     * @return string
     */
    private function resolveActorName(string $type, ?int $actorId): string
    {
        switch ($type) {
            case StatusHistoryInterface::ACTOR_ADMIN:
                $user = $this->userFactory->create();
                if ($actorId !== null) {
                    $this->userResource->load($user, $actorId);
                }
                $name = $user->getId() ? (string)$user->getUserName() : '#' . $actorId;

                return (string)__('Admin: %1', $name);
            case StatusHistoryInterface::ACTOR_CUSTOMER:
                $name = '#' . $actorId;
                if ($actorId !== null) {
                    try {
                        $customer = $this->customerRepository->getById($actorId);
                        $name = trim($customer->getFirstname() . ' ' . $customer->getLastname())
                            . ' <' . $customer->getEmail() . '>';
                    } catch (NoSuchEntityException) {
                        $name = (string)__('Deleted customer #%1', $actorId);
                    }
                }

                return (string)__('Customer: %1', $name);
            case StatusHistoryInterface::ACTOR_INTEGRATION:
                return (string)__('Integration #%1', $actorId);
            default:
                return (string)__('System');
        }
    }
}
