<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Adminhtml\Company;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Api\Data\CompanyInterfaceFactory;
use MageOS\Company\Model\Company;
use MageOS\Company\Model\Status\HistoryRecorder;
use MageOS\Company\Setup\CompanySetup;
use MageOS\Company\Ui\DataProvider\Form\AttributeMetaProvider;
use Psr\Log\LoggerInterface;

/**
 * Save a company from the admin form.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_Company::company_edit';
    public const DATA_PERSISTOR_KEY = 'mageos_company';

    /**
     * Fields that need an extra ACL resource to be changed.
     */
    private const PROTECTED_FIELDS = [
        CompanyInterface::COMPANY_ID => 'MageOS_Company::company_erp',
        CompanyInterface::STATUS => 'MageOS_Company::company_approve',
    ];

    /**
     * @param Context $context
     * @param CompanyRepositoryInterface $companyRepository
     * @param CompanyInterfaceFactory $companyFactory
     * @param AttributeMetaProvider $attributeMetaProvider
     * @param DataPersistorInterface $dataPersistor
     * @param HistoryRecorder $historyRecorder
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly CompanyInterfaceFactory $companyFactory,
        private readonly AttributeMetaProvider $attributeMetaProvider,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly HistoryRecorder $historyRecorder,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        /** @var HttpRequest $request */
        $request = $this->getRequest();
        $data = $request->getPostValue('company');
        if (!is_array($data)) {
            return $redirect->setPath('*/*/');
        }
        $companyId = (int)($data[CompanyInterface::ENTITY_ID] ?? 0);

        try {
            $company = $companyId ? $this->companyRepository->getById($companyId) : $this->companyFactory->create();
            $this->applyData($company, $data, $companyId === 0);
            $company = $this->companyRepository->save($company);
            if ($companyId === 0) {
                $this->historyRecorder->record((int)$company->getId(), null, $company->getStatus());
            }
            $this->dataPersistor->clear(self::DATA_PERSISTOR_KEY);
            $this->messageManager->addSuccessMessage(__('The company has been saved.'));

            if ($request->getParam('back')) {
                return $redirect->setPath('*/*/edit', ['id' => $company->getId()]);
            }

            return $redirect->setPath('*/*/');
        } catch (InputException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            foreach ($e->getErrors() as $error) {
                $this->messageManager->addErrorMessage($error->getMessage());
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $this->messageManager->addErrorMessage(__('Something went wrong while saving the company.'));
        }

        $this->dataPersistor->set(self::DATA_PERSISTOR_KEY, $data);

        return $companyId
            ? $redirect->setPath('*/*/edit', ['id' => $companyId])
            : $redirect->setPath('*/*/new');
    }

    /**
     * Copy the posted values the current admin may change onto the company.
     *
     * @param CompanyInterface $company
     * @param array<string,mixed> $data
     * @param bool $isNew
     * @return void
     */
    private function applyData(CompanyInterface $company, array $data, bool $isNew): void
    {
        /** @var Company $company */
        $codes = array_keys(
            $this->attributeMetaProvider->getAttributes(Company::ENTITY, CompanySetup::FORM_ADMIN_COMPANY)
        );
        $codes[] = CompanyInterface::SUPER_USER_ID;

        foreach ($codes as $code) {
            if (!array_key_exists($code, $data) || !$this->canChange($code, $isNew)) {
                continue;
            }
            $value = $data[$code];
            if (is_string($value)) {
                $value = trim($value);
            }
            $company->setData($code, $value === '' || $value === [] ? null : $value);
        }
    }

    /**
     * Whether the current admin may change a field.
     *
     * @param string $code
     * @param bool $isNew
     * @return bool
     */
    private function canChange(string $code, bool $isNew): bool
    {
        // The website cannot change once users are assigned; the super user must already be a company user;
        // the status of an existing company changes through the approval actions only (they keep the history).
        if ($code === CompanyInterface::WEBSITE_ID || ($code === CompanyInterface::STATUS && !$isNew)) {
            return $isNew;
        }
        if ($code === CompanyInterface::SUPER_USER_ID) {
            return !$isNew;
        }
        $resource = self::PROTECTED_FIELDS[$code] ?? null;

        return $resource === null || $this->_authorization->isAllowed($resource);
    }
}
