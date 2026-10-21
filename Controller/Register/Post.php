<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Register;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Company\Api\CompanyManagementInterface;
use MageOS\Company\Api\Data\CompanyAddressInterfaceFactory;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Api\Data\CompanyInterfaceFactory;
use MageOS\Company\Model\CompanyAddress;
use MageOS\Company\Model\Config;
use MageOS\Company\Model\Company;
use MageOS\Company\Model\Form\RequestApplier;
use MageOS\Company\Setup\CompanySetup;
use Psr\Log\LoggerInterface;

/**
 * Submit a company application.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Post extends AbstractRegister implements HttpPostActionInterface
{
    /**
     * @param RequestInterface $request
     * @param CustomerSession $customerSession
     * @param CompanyManagementInterface $companyManagement
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param RedirectFactory $redirectFactory
     * @param ForwardFactory $forwardFactory
     * @param MessageManager $messageManager
     * @param RequestApplier $requestApplier
     * @param CompanyInterfaceFactory $companyFactory
     * @param CompanyAddressInterfaceFactory $addressFactory
     * @param CustomerInterfaceFactory $customerFactory
     * @param DataPersistorInterface $dataPersistor
     * @param LoggerInterface $logger
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        RequestInterface $request,
        CustomerSession $customerSession,
        CompanyManagementInterface $companyManagement,
        Config $config,
        StoreManagerInterface $storeManager,
        RedirectFactory $redirectFactory,
        ForwardFactory $forwardFactory,
        MessageManager $messageManager,
        private readonly RequestApplier $requestApplier,
        private readonly CompanyInterfaceFactory $companyFactory,
        private readonly CompanyAddressInterfaceFactory $addressFactory,
        private readonly CustomerInterfaceFactory $customerFactory,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct(
            $request,
            $customerSession,
            $companyManagement,
            $config,
            $storeManager,
            $redirectFactory,
            $forwardFactory,
            $messageManager
        );
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $denied = $this->getDeniedResult();
        if ($denied !== null) {
            return $denied;
        }
        $redirect = $this->redirectFactory->create();
        $isLoggedIn = $this->customerSession->isLoggedIn();

        try {
            /** @var Company $company */
            $company = $this->companyFactory->create();
            /** @var CompanyAddress $address */
            $address = $this->addressFactory->create();
            $errors = array_merge(
                $this->requestApplier->apply(
                    $company,
                    Company::ENTITY,
                    CompanySetup::FORM_COMPANY_REGISTER,
                    $this->request,
                    'company'
                ),
                $this->requestApplier->apply(
                    $address,
                    CompanyAddress::ENTITY,
                    CompanySetup::FORM_ADDRESS_REGISTER,
                    $this->request,
                    'address'
                )
            );
            $customer = $isLoggedIn ? $this->getLoggedInCustomer() : $this->getNewCustomer();
            $password = $isLoggedIn ? null : (string)$this->request->getParam('password');
            if (!$isLoggedIn && $password !== (string)$this->request->getParam('password_confirmation')) {
                $errors[] = __('Please make sure your passwords match.');
            }
            if ($this->config->isTermsEnabled((int)$this->storeManager->getWebsite()->getId())
                && !$this->request->getParam('terms')
            ) {
                $errors[] = __('Please accept the terms to continue.');
            }
            if ($errors) {
                foreach ($errors as $error) {
                    $this->messageManager->addErrorMessage((string)$error);
                }
                return $this->goBack($redirect);
            }

            $company = $this->companyManagement->register($company, $customer, $password, $address);
        } catch (InputException $e) {
            $this->addInputErrors($e);
            return $this->goBack($redirect);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $this->goBack($redirect);
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $this->messageManager->addErrorMessage(
                __('We could not submit your application. Please try again later.')
            );
            return $this->goBack($redirect);
        }

        $approved = $company->getStatus() === CompanyInterface::STATUS_APPROVED;
        // users of a company that is not approved cannot stay logged in
        if ($isLoggedIn && !$approved) {
            $this->customerSession->logout();
        }

        // the success page only shows an informative text, so the outcome can travel in the URL
        return $redirect->setPath('company/register/success', ['_query' => ['approved' => (int)$approved]]);
    }

    /**
     * The logged in customer, as the applicant.
     *
     * @return CustomerInterface
     */
    private function getLoggedInCustomer(): CustomerInterface
    {
        return $this->customerFactory->create()->setId((int)$this->customerSession->getCustomerId());
    }

    /**
     * New customer account from the posted applicant data.
     *
     * @return CustomerInterface
     */
    private function getNewCustomer(): CustomerInterface
    {
        $store = $this->storeManager->getStore();

        return $this->customerFactory->create()
            ->setFirstname(trim((string)$this->request->getParam('firstname')))
            ->setLastname(trim((string)$this->request->getParam('lastname')))
            ->setEmail(trim((string)$this->request->getParam('email')))
            ->setStoreId((int)$store->getId())
            ->setWebsiteId((int)$store->getWebsiteId());
    }

    /**
     * Show validation errors.
     *
     * @param InputException $e
     * @return void
     */
    private function addInputErrors(InputException $e): void
    {
        $errors = $e->getErrors();
        if (!$errors) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return;
        }
        foreach ($errors as $error) {
            $this->messageManager->addErrorMessage($error->getMessage());
        }
    }

    /**
     * Keep the posted values (except passwords) and go back to the form.
     *
     * @param \Magento\Framework\Controller\Result\Redirect $redirect
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    private function goBack(\Magento\Framework\Controller\Result\Redirect $redirect)
    {
        $data = $this->request->getParams();
        unset($data['password'], $data['password_confirmation'], $data['form_key']);
        $this->dataPersistor->set(self::SESSION_FORM_DATA, $data);

        return $redirect->setPath('company/register');
    }
}
