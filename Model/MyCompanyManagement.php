<?php

declare(strict_types=1);

namespace MageOS\Company\Model;

use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use MageOS\Company\Api\CompanyAddressManagementInterface;
use MageOS\Company\Api\CompanyAddressRepositoryInterface;
use MageOS\Company\Api\CompanyAuthorizationInterface as Auth;
use MageOS\Company\Api\CompanyManagementInterface;
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Api\Data\CompanyAddressInterface;
use MageOS\Company\Api\Data\CompanyAddressInterfaceFactory;
use MageOS\Company\Api\Data\CompanyCustomerInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Api\Data\MessageInterface;
use MageOS\Company\Api\MessageManagementInterface;
use MageOS\Company\Api\MyCompanyManagementInterface;
use MageOS\Company\Model\Authorization\CompanyContext;
use MageOS\Company\Model\Form\FormData;
use MageOS\Company\Model\Invitation\InvitationManager;
use MageOS\Company\Setup\CompanySetup;

/**
 * Company user operations on its own company.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class MyCompanyManagement implements MyCompanyManagementInterface
{
    /**
     * Default address types and the company setter of each.
     */
    public const DEFAULT_ADDRESS_TYPES = [
        'default_billing' => 'setDefaultBilling',
        'default_shipping' => 'setDefaultShipping',
        'legal_address' => 'setLegalAddress',
    ];

    /**
     * @param CompanyContext $companyContext
     * @param Auth $authorization
     * @param CompanyRepositoryInterface $companyRepository
     * @param CompanyManagementInterface $companyManagement
     * @param CompanyAddressManagementInterface $addressManagement
     * @param CompanyAddressRepositoryInterface $addressRepository
     * @param CompanyAddressInterfaceFactory $addressFactory
     * @param MessageManagementInterface $messageManagement
     * @param InvitationManager $invitationManager
     * @param FormData $formData
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        private readonly CompanyContext $companyContext,
        private readonly Auth $authorization,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly CompanyManagementInterface $companyManagement,
        private readonly CompanyAddressManagementInterface $addressManagement,
        private readonly CompanyAddressRepositoryInterface $addressRepository,
        private readonly CompanyAddressInterfaceFactory $addressFactory,
        private readonly MessageManagementInterface $messageManagement,
        private readonly InvitationManager $invitationManager,
        private readonly FormData $formData
    ) {
    }

    /**
     * @inheritDoc
     */
    public function get(int $customerId): CompanyInterface
    {
        return $this->authorize($customerId, Auth::RESOURCE_VIEW);
    }

    /**
     * @inheritDoc
     */
    public function update(int $customerId, CompanyInterface $company): CompanyInterface
    {
        /** @var Company $stored */
        $stored = $this->authorize($customerId, Auth::RESOURCE_EDIT);
        /** @var Company $company */
        $this->formData->copy($company, $stored, Company::ENTITY, CompanySetup::FORM_COMPANY_ACCOUNT_EDIT);
        $this->assertValid(
            $this->formData->validate($stored, Company::ENTITY, CompanySetup::FORM_COMPANY_ACCOUNT_EDIT)
        );

        return $this->companyRepository->save($stored);
    }

    /**
     * @inheritDoc
     */
    public function getAddresses(int $customerId): array
    {
        $company = $this->authorize($customerId, Auth::RESOURCE_ADDRESS_VIEW);

        return $this->addressManagement->getList((int)$company->getId());
    }

    /**
     * @inheritDoc
     */
    public function getAddress(int $customerId, int $addressId): CompanyAddressInterface
    {
        $company = $this->authorize($customerId, Auth::RESOURCE_ADDRESS_VIEW);

        return $this->addressManagement->get((int)$company->getId(), $addressId);
    }

    /**
     * @inheritDoc
     */
    public function saveAddress(int $customerId, CompanyAddressInterface $address): CompanyAddressInterface
    {
        $company = $this->authorize($customerId, Auth::RESOURCE_ADDRESS_MANAGE);
        $companyId = (int)$company->getId();
        /** @var CompanyAddress $target */
        $target = $address->getId()
            ? $this->addressManagement->get($companyId, (int)$address->getId())
            : $this->addressFactory->create();
        /** @var CompanyAddress $address */
        $this->formData->copy($address, $target, CompanyAddress::ENTITY, CompanySetup::FORM_ADDRESS_EDIT);
        $target->setParentId($companyId);
        $this->assertValid(
            $this->formData->validate($target, CompanyAddress::ENTITY, CompanySetup::FORM_ADDRESS_EDIT)
        );

        return $this->addressRepository->save($target);
    }

    /**
     * @inheritDoc
     */
    public function deleteAddress(int $customerId, int $addressId): bool
    {
        $company = $this->authorize($customerId, Auth::RESOURCE_ADDRESS_MANAGE);

        return $this->addressManagement->delete((int)$company->getId(), $addressId);
    }

    /**
     * @inheritDoc
     */
    public function setDefaultAddress(int $customerId, int $addressId, string $type): CompanyInterface
    {
        $company = $this->authorize($customerId, Auth::RESOURCE_ADDRESS_MANAGE);
        $setter = self::DEFAULT_ADDRESS_TYPES[$type] ?? null;
        if ($setter === null) {
            throw new LocalizedException(__('Unknown default address type.'));
        }
        $address = $this->addressManagement->get((int)$company->getId(), $addressId);
        $company->$setter((int)$address->getId());

        return $this->companyRepository->save($company);
    }

    /**
     * @inheritDoc
     */
    public function getUsers(int $customerId): array
    {
        $company = $this->authorize($customerId, Auth::RESOURCE_USERS_MANAGE);

        return $this->companyManagement->getCustomers((int)$company->getId());
    }

    /**
     * @inheritDoc
     */
    public function inviteUser(int $customerId, string $email, string $role = 'user', ?string $jobTitle = null): bool
    {
        $company = $this->authorize($customerId, Auth::RESOURCE_USERS_MANAGE);
        $this->invitationManager->invite($company, $email, $role, $jobTitle, $customerId);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function updateUser(
        int $customerId,
        int $userId,
        string $role,
        ?string $jobTitle = null,
        ?int $status = null
    ): CompanyCustomerInterface {
        $company = $this->authorize($customerId, Auth::RESOURCE_USERS_MANAGE);
        $companyId = (int)$company->getId();
        $user = $this->getUser($companyId, $userId);
        $changeStatus = $status !== null && $status !== $user->getStatus();
        // activate first and deactivate last, so the admin invariants are checked on the final state
        if ($changeStatus && $status === CompanyCustomerInterface::STATUS_ACTIVE) {
            $this->companyManagement->setCustomerStatus($companyId, $userId, $status);
        }
        $relation = $this->companyManagement->assignCustomer($companyId, $userId, $role, $jobTitle);
        if ($changeStatus && $status !== CompanyCustomerInterface::STATUS_ACTIVE) {
            $relation = $this->companyManagement->setCustomerStatus($companyId, $userId, (int)$status);
        }

        return $relation;
    }

    /**
     * @inheritDoc
     */
    public function removeUser(int $customerId, int $userId): bool
    {
        $company = $this->authorize($customerId, Auth::RESOURCE_USERS_MANAGE);
        $this->getUser((int)$company->getId(), $userId);

        return $this->companyManagement->unassignCustomer((int)$company->getId(), $userId);
    }

    /**
     * @inheritDoc
     */
    public function getMessages(int $customerId): array
    {
        $company = $this->authorize($customerId, Auth::RESOURCE_MESSAGES_VIEW);

        return $this->messageManagement->getThread((int)$company->getId());
    }

    /**
     * @inheritDoc
     */
    public function sendMessage(int $customerId, string $body): MessageInterface
    {
        $company = $this->authorize($customerId, Auth::RESOURCE_MESSAGES_SEND);

        return $this->messageManagement->send(
            (int)$company->getId(),
            $body,
            MessageInterface::AUTHOR_CUSTOMER,
            $customerId
        );
    }

    /**
     * @inheritDoc
     */
    public function markMessagesRead(int $customerId): int
    {
        $company = $this->authorize($customerId, Auth::RESOURCE_MESSAGES_VIEW);

        return $this->messageManagement->markRead((int)$company->getId(), MessageInterface::AUTHOR_CUSTOMER);
    }

    /**
     * Company of the customer, if it may use the resource.
     *
     * @param int $customerId
     * @param string $resource
     * @return CompanyInterface
     * @throws AuthorizationException
     */
    private function authorize(int $customerId, string $resource): CompanyInterface
    {
        try {
            $company = $this->companyContext->getCompany($customerId);
        } catch (LocalizedException $e) {
            throw new AuthorizationException(new Phrase($e->getMessage()));
        }
        if (!$this->authorization->isAllowed($customerId, $resource)) {
            throw new AuthorizationException(__('You are not allowed to do that.'));
        }

        return $company;
    }

    /**
     * User of a company.
     *
     * @param int $companyId
     * @param int $userId
     * @return CompanyCustomerInterface
     * @throws NoSuchEntityException
     */
    private function getUser(int $companyId, int $userId): CompanyCustomerInterface
    {
        foreach ($this->companyManagement->getCustomers($companyId) as $relation) {
            if ($relation->getCustomerId() === $userId) {
                return $relation;
            }
        }
        throw NoSuchEntityException::singleField('userId', $userId);
    }

    /**
     * Throw the form validation errors.
     *
     * @param string[] $errors
     * @return void
     * @throws InputException
     */
    private function assertValid(array $errors): void
    {
        if (!$errors) {
            return;
        }
        $exception = new InputException();
        foreach ($errors as $error) {
            $exception->addError(new Phrase($error));
        }
        throw $exception;
    }
}
