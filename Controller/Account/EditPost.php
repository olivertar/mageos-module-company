<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Account;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use MageOS\Company\Api\CompanyAuthorizationInterface;
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Controller\AbstractCompanyAction;
use MageOS\Company\Controller\CompanyActionContext;
use MageOS\Company\Model\Company;
use MageOS\Company\Model\Form\RequestApplier;
use MageOS\Company\Setup\CompanySetup;

/**
 * Save the company profile. Only the attributes of the company_account_edit form are taken from the request.
 */
class EditPost extends AbstractCompanyAction implements HttpPostActionInterface
{
    protected const RESOURCE = CompanyAuthorizationInterface::RESOURCE_EDIT;
    public const DATA_PERSISTOR_KEY = 'mageos_company_account_edit';

    /**
     * @param CompanyActionContext $context
     * @param CompanyRepositoryInterface $companyRepository
     * @param RequestApplier $requestApplier
     */
    public function __construct(
        CompanyActionContext $context,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly RequestApplier $requestApplier
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    protected function executeFor(CompanyInterface $company, int $customerId): ResultInterface
    {
        /** @var Company $company */
        $errors = $this->requestApplier->apply(
            $company,
            Company::ENTITY,
            CompanySetup::FORM_COMPANY_ACCOUNT_EDIT,
            $this->context->request,
            'company'
        );
        foreach ($errors as $error) {
            $this->context->messageManager->addErrorMessage($error);
        }
        if (!$errors) {
            try {
                $this->companyRepository->save($company);
                $this->context->messageManager->addSuccessMessage(__('The company information has been saved.'));

                return $this->redirect('company/account');
            } catch (InputException $e) {
                $this->context->messageManager->addErrorMessage($e->getMessage());
                foreach ($e->getErrors() as $error) {
                    $this->context->messageManager->addErrorMessage($error->getMessage());
                }
            } catch (LocalizedException $e) {
                $this->context->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->context->logger->critical($e);
                $this->context->messageManager->addErrorMessage(
                    __('Something went wrong. Please try again later.')
                );
            }
        }
        $this->context->dataPersistor->set(self::DATA_PERSISTOR_KEY, $this->context->request->getParam('company'));

        return $this->redirect('company/account/edit');
    }
}
