<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Registration;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Company\Api\CompanyAddressRepositoryInterface;
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Api\Data\CompanyAddressInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Api\Data\StatusHistoryInterface;
use MageOS\Company\Model\Config;
use MageOS\Company\Model\Notification\AdminInbox;
use MageOS\Company\Model\Notification\EmailSender;
use MageOS\Company\Model\ResourceModel\Company as CompanyResource;
use MageOS\Company\Model\ResourceModel\CompanyCustomer as CompanyCustomerResource;
use MageOS\Company\Model\Status\HistoryRecorder;

/**
 * Company application: creates the company, its legal address and (for guests) the customer in one transaction.
 */
class Registrar
{
    /**
     * @param CompanyRepositoryInterface $companyRepository
     * @param CompanyAddressRepositoryInterface $addressRepository
     * @param CompanyResource $companyResource
     * @param CompanyCustomerResource $relationResource
     * @param AccountManagementInterface $accountManagement
     * @param CustomerRepositoryInterface $customerRepository
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param HistoryRecorder $historyRecorder
     * @param EmailSender $emailSender
     * @param EventManager $eventManager
     * @param WelcomeEmailDeferral $welcomeEmailDeferral
     * @param AdminInbox $adminInbox
     * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
     */
    public function __construct(
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly CompanyAddressRepositoryInterface $addressRepository,
        private readonly CompanyResource $companyResource,
        private readonly CompanyCustomerResource $relationResource,
        private readonly AccountManagementInterface $accountManagement,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly HistoryRecorder $historyRecorder,
        private readonly EmailSender $emailSender,
        private readonly EventManager $eventManager,
        private readonly WelcomeEmailDeferral $welcomeEmailDeferral,
        private readonly AdminInbox $adminInbox
    ) {
    }

    /**
     * Register a company application.
     *
     * A customer with an ID is an existing customer (it must not belong to a company); otherwise the customer
     * is created with the given password. The customer becomes the company super user.
     *
     * @param CompanyInterface $company
     * @param CustomerInterface $customer
     * @param string|null $password
     * @param CompanyAddressInterface|null $legalAddress
     * @return CompanyInterface
     * @throws LocalizedException
     */
    public function register(
        CompanyInterface $company,
        CustomerInterface $customer,
        ?string $password = null,
        ?CompanyAddressInterface $legalAddress = null
    ): CompanyInterface {
        if ($company->getId()) {
            throw new LocalizedException(__('Only new companies can be registered.'));
        }
        $customerId = $customer->getId() ? (int)$customer->getId() : null;
        if ($customerId !== null) {
            $customer = $this->customerRepository->getById($customerId);
            if ($this->relationResource->getCompanyIdByCustomerId($customerId) !== null) {
                throw new LocalizedException(__('Your account already belongs to a company.'));
            }
            $websiteId = (int)$customer->getWebsiteId();
        } else {
            $websiteId = (int)$this->storeManager->getWebsite()->getId();
            $customer->setWebsiteId($websiteId);
        }
        if (!$this->config->isRegistrationEnabled($websiteId)) {
            throw new LocalizedException(__('Company registration is not available.'));
        }

        $status = $this->config->isAutoApprove($websiteId)
            ? CompanyInterface::STATUS_APPROVED
            : CompanyInterface::STATUS_PENDING;
        // fields the applicant does not control
        $company->setWebsiteId($websiteId)
            ->setStatus($status)
            ->setCompanyId(null)
            ->setSuperUserId(null)
            ->setDefaultBilling(null)
            ->setDefaultShipping(null)
            ->setLegalAddress(null);

        $connection = $this->companyResource->getConnection();
        $connection->beginTransaction();
        try {
            $company = $this->companyRepository->save($company);
            $companyId = (int)$company->getId();
            if ($legalAddress !== null) {
                $legalAddress->setParentId($companyId);
                $addressId = (int)$this->addressRepository->save($legalAddress)->getId();
                $company->setLegalAddress($addressId)
                    ->setDefaultBilling($addressId)
                    ->setDefaultShipping($addressId);
            }
            $isNewCustomer = $customerId === null;
            if ($isNewCustomer) {
                // the applicant gets the company emails only (see WelcomeEmailDeferral)
                $customerId = (int)$this->welcomeEmailDeferral->capture(
                    fn () => $this->accountManagement->createAccount($customer, $password)
                )->getId();
            }
            $company->setSuperUserId($customerId);
            $company = $this->companyRepository->save($company);
            if ($isNewCustomer) {
                $this->welcomeEmailDeferral->hold($customerId);
            }
            $this->historyRecorder->record(
                $companyId,
                null,
                $status,
                null,
                StatusHistoryInterface::ACTOR_CUSTOMER,
                $customerId
            );
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        $this->eventManager->dispatch('company_register_after', [
            'company' => $company,
            'customer_id' => $customerId,
        ]);
        $this->emailSender->notifyCompany(
            $company,
            $status === CompanyInterface::STATUS_APPROVED ? Config::EMAIL_APPROVED : Config::EMAIL_APPLICATION_RECEIVED
        );
        $this->emailSender->notifyAdminsOfApplication($company);
        $this->adminInbox->notifyApplication($company);
        if ($status === CompanyInterface::STATUS_APPROVED) {
            $this->welcomeEmailDeferral->release($companyId);
        }

        return $company;
    }
}
