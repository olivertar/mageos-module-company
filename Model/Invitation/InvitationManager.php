<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Invitation;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Math\Random;
use MageOS\Company\Api\CompanyManagementInterface;
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Api\Data\CompanyCustomerInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Model\Config;
use MageOS\Company\Model\Invitation;
use MageOS\Company\Model\InvitationFactory;
use MageOS\Company\Model\Notification\EmailSender;
use MageOS\Company\Model\ResourceModel\CompanyCustomer as CompanyCustomerResource;
use MageOS\Company\Model\ResourceModel\Invitation as InvitationResource;
use MageOS\Company\Model\ResourceModel\Invitation\CollectionFactory;

/**
 * Invitations to join a company.
 *
 * Joining always needs the invited person to accept with the emailed link (and to own the invited email): a
 * company admin cannot attach an existing customer account to its company without consent.
 */
class InvitationManager
{
    /**
     * @param InvitationFactory $invitationFactory
     * @param InvitationResource $invitationResource
     * @param CollectionFactory $collectionFactory
     * @param CompanyRepositoryInterface $companyRepository
     * @param CompanyManagementInterface $companyManagement
     * @param CompanyCustomerResource $relationResource
     * @param CustomerRepositoryInterface $customerRepository
     * @param EmailSender $emailSender
     * @param Config $config
     * @param Random $random
     * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
     */
    public function __construct(
        private readonly InvitationFactory $invitationFactory,
        private readonly InvitationResource $invitationResource,
        private readonly CollectionFactory $collectionFactory,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly CompanyManagementInterface $companyManagement,
        private readonly CompanyCustomerResource $relationResource,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly EmailSender $emailSender,
        private readonly Config $config,
        private readonly Random $random
    ) {
    }

    /**
     * Invite an email to a company and send the invitation email. Replaces an open invitation of the same email.
     *
     * @param CompanyInterface $company
     * @param string $email
     * @param string $role
     * @param string|null $jobTitle
     * @param int|null $invitedBy Customer ID of the inviting company admin.
     * @return Invitation
     * @throws LocalizedException
     */
    public function invite(
        CompanyInterface $company,
        string $email,
        string $role,
        ?string $jobTitle,
        ?int $invitedBy
    ): Invitation {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new LocalizedException(__('"%1" is not a valid email address.', $email));
        }
        if (!in_array($role, [CompanyCustomerInterface::ROLE_ADMIN, CompanyCustomerInterface::ROLE_USER], true)) {
            throw new LocalizedException(__('Role "%1" is not valid.', $role));
        }
        if ($company->getStatus() !== CompanyInterface::STATUS_APPROVED) {
            throw new LocalizedException(__('Only approved companies can invite users.'));
        }
        $customer = $this->findCustomer($email, (int)$company->getWebsiteId());
        if ($customer !== null) {
            $currentCompanyId = $this->relationResource->getCompanyIdByCustomerId((int)$customer->getId());
            if ($currentCompanyId === (int)$company->getId()) {
                throw new LocalizedException(__('%1 is already a user of your company.', $email));
            }
            if ($currentCompanyId !== null) {
                // do not tell the inviting company which company the person belongs to
                throw new LocalizedException(__('%1 cannot be invited to your company.', $email));
            }
        }

        $token = $this->random->getRandomString(48);
        $this->invitationResource->deleteOpen((int)$company->getId(), $email);
        $invitation = $this->invitationFactory->create();
        $invitation->setData([
            Invitation::COMPANY_ENTITY_ID => (int)$company->getId(),
            Invitation::EMAIL => $email,
            Invitation::ROLE => $role,
            Invitation::JOB_TITLE => $jobTitle === null || trim($jobTitle) === '' ? null : trim($jobTitle),
            Invitation::TOKEN_HASH => hash('sha256', $token),
            Invitation::INVITED_BY => $invitedBy,
            Invitation::EXPIRES_AT => new \Zend_Db_Expr(
                sprintf('NOW() + INTERVAL %d DAY', $this->config->getInvitationLifetime((int)$company->getWebsiteId()))
            ),
        ]);
        $this->invitationResource->save($invitation);
        $this->emailSender->sendInvitation($company, $email, $token, $this->getInviterName($invitedBy));

        return $invitation;
    }

    /**
     * Open invitation of a token: not accepted, not expired, of an approved company.
     *
     * @param string $token
     * @return Invitation|null
     */
    public function getOpenByToken(string $token): ?Invitation
    {
        if ($token === '') {
            return null;
        }
        $invitation = $this->invitationFactory->create();
        $this->invitationResource->loadByTokenHash($invitation, hash('sha256', $token));
        if (!$invitation->getId() || $invitation->isAccepted() || $invitation->isExpired()) {
            return null;
        }
        try {
            $company = $this->companyRepository->getById($invitation->getCompanyEntityId());
        } catch (NoSuchEntityException) {
            return null;
        }

        return $company->getStatus() === CompanyInterface::STATUS_APPROVED ? $invitation : null;
    }

    /**
     * Join the company of an invitation. The customer must own the invited email.
     *
     * @param Invitation $invitation
     * @param int $customerId
     * @return void
     * @throws LocalizedException
     */
    public function accept(Invitation $invitation, int $customerId): void
    {
        $customer = $this->customerRepository->getById($customerId);
        if (mb_strtolower((string)$customer->getEmail()) !== $invitation->getEmail()) {
            throw new LocalizedException(
                __('This invitation was sent to %1. Sign in with that account to accept it.', $invitation->getEmail())
            );
        }
        if ($this->relationResource->getCompanyIdByCustomerId($customerId) !== null) {
            throw new LocalizedException(__('Your account already belongs to a company.'));
        }
        $connection = $this->invitationResource->getConnection();
        $connection->beginTransaction();
        try {
            $this->companyManagement->assignCustomer(
                $invitation->getCompanyEntityId(),
                $customerId,
                $invitation->getRole(),
                $invitation->getJobTitle()
            );
            $connection->update(
                $this->invitationResource->getMainTable(),
                [Invitation::ACCEPTED_AT => new \Zend_Db_Expr('NOW()')],
                [Invitation::INVITATION_ID . ' = ?' => (int)$invitation->getId()]
            );
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * Open (not accepted) invitations of a company, newest first, with an "is_expired" flag.
     *
     * @param int $companyId
     * @return Invitation[]
     */
    public function getOpen(int $companyId): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(Invitation::COMPANY_ENTITY_ID, ['eq' => $companyId])
            ->addFieldToFilter(Invitation::ACCEPTED_AT, ['null' => true])
            ->setOrder(Invitation::CREATED_AT, 'DESC');
        $collection->getSelect()->columns([
            'is_expired' => new \Zend_Db_Expr('(main_table.' . Invitation::EXPIRES_AT . ' < NOW())'),
        ]);
        /** @var Invitation[] $items */
        $items = array_values($collection->getItems());

        return $items;
    }

    /**
     * Cancel an open invitation of a company.
     *
     * @param int $companyId
     * @param int $invitationId
     * @return void
     * @throws NoSuchEntityException
     */
    public function cancel(int $companyId, int $invitationId): void
    {
        $invitation = $this->invitationFactory->create();
        $this->invitationResource->load($invitation, $invitationId);
        if (!$invitation->getId() || $invitation->getCompanyEntityId() !== $companyId || $invitation->isAccepted()) {
            throw NoSuchEntityException::singleField(Invitation::INVITATION_ID, $invitationId);
        }
        $this->invitationResource->delete($invitation);
    }

    /**
     * Customer with an email in a website.
     *
     * @param string $email
     * @param int $websiteId
     * @return CustomerInterface|null
     */
    public function findCustomer(string $email, int $websiteId): ?CustomerInterface
    {
        try {
            return $this->customerRepository->get($email, $websiteId);
        } catch (NoSuchEntityException) {
            return null;
        }
    }

    /**
     * Name of the inviting user.
     *
     * @param int|null $customerId
     * @return string
     */
    private function getInviterName(?int $customerId): string
    {
        if ($customerId === null) {
            return '';
        }
        try {
            $customer = $this->customerRepository->getById($customerId);

            return trim($customer->getFirstname() . ' ' . $customer->getLastname());
        } catch (NoSuchEntityException) {
            return '';
        }
    }
}
