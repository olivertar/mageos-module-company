<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Adminhtml\Address;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use MageOS\Company\Api\CompanyAddressRepositoryInterface;
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Api\Data\CompanyAddressInterface;
use MageOS\Company\Api\Data\CompanyAddressInterfaceFactory;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Model\Company;
use MageOS\Company\Model\CompanyAddress;
use MageOS\Company\Setup\CompanySetup;
use MageOS\Company\Ui\DataProvider\Address\FormDataProvider;
use MageOS\Company\Ui\DataProvider\Form\AttributeMetaProvider;
use Psr\Log\LoggerInterface;

/**
 * Save a company address from the modal form (AJAX).
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_Company::company_edit';

    /**
     * @param Context $context
     * @param CompanyAddressRepositoryInterface $addressRepository
     * @param CompanyAddressInterfaceFactory $addressFactory
     * @param CompanyRepositoryInterface $companyRepository
     * @param AttributeMetaProvider $attributeMetaProvider
     * @param JsonFactory $jsonFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly CompanyAddressRepositoryInterface $addressRepository,
        private readonly CompanyAddressInterfaceFactory $addressFactory,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly AttributeMetaProvider $attributeMetaProvider,
        private readonly JsonFactory $jsonFactory,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute(): Json
    {
        $request = $this->getRequest();
        $companyId = (int)$request->getParam(CompanyAddressInterface::PARENT_ID);
        $addressId = (int)$request->getParam(CompanyAddressInterface::ENTITY_ID);
        $error = true;

        try {
            $company = $this->companyRepository->getById($companyId);
            /** @var CompanyAddress $address */
            $address = $addressId ? $this->addressRepository->getById($addressId) : $this->addressFactory->create();
            if ($addressId && $address->getParentId() !== $companyId) {
                throw new LocalizedException(__('The address does not belong to this company.'));
            }
            $this->applyData($address);
            $address->setParentId($companyId);
            $address = $this->addressRepository->save($address);
            $message = $addressId ? __('The address has been updated.') : __('The address has been added.');
            $addressId = (int)$address->getId();
            $this->updateDefaults($company, $addressId);
            $error = false;
        } catch (InputException $e) {
            $message = $this->formatInputException($e);
        } catch (LocalizedException $e) {
            $message = __($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $message = __('We can\'t save the address right now.');
        }

        return $this->jsonFactory->create()->setData([
            'error' => $error,
            'messages' => (string)$message,
            'data' => ['entity_id' => $addressId ?: null],
        ]);
    }

    /**
     * Copy the posted form values onto the address.
     *
     * @param CompanyAddress $address
     * @return void
     */
    private function applyData(CompanyAddress $address): void
    {
        $codes = array_keys(
            $this->attributeMetaProvider->getAttributes(CompanyAddress::ENTITY, CompanySetup::FORM_ADMIN_ADDRESS)
        );
        foreach ($codes as $code) {
            $value = $this->getRequest()->getParam($code);
            if ($value === null) {
                continue;
            }
            if ($code === CompanyAddressInterface::STREET) {
                $address->setStreet(array_map('strval', (array)$value));
                continue;
            }
            $value = is_string($value) ? trim($value) : $value;
            $address->setData($code, $value === '' ? null : $value);
        }
        if ($address->getRegionId()) {
            $address->setRegion(null);
        }
    }

    /**
     * Set or clear the company default addresses from the form toggles.
     *
     * @param CompanyInterface $company
     * @param int $addressId
     * @return void
     */
    private function updateDefaults(CompanyInterface $company, int $addressId): void
    {
        /** @var Company $company */
        $changed = false;
        foreach (FormDataProvider::DEFAULT_FLAGS as $field) {
            $flag = $this->getRequest()->getParam($field);
            if ($flag === null) {
                continue;
            }
            $current = (int)$company->getData($field);
            if ($flag) {
                $changed = $changed || $current !== $addressId;
                $company->setData($field, $addressId);
            } elseif ($current === $addressId) {
                $changed = true;
                $company->setData($field, null);
            }
        }
        if ($changed) {
            $this->companyRepository->save($company);
        }
    }

    /**
     * One message with all validation errors.
     *
     * @param InputException $exception
     * @return Phrase
     */
    private function formatInputException(InputException $exception): Phrase
    {
        $messages = [$exception->getMessage()];
        foreach ($exception->getErrors() as $error) {
            $messages[] = $error->getMessage();
        }

        return __(implode(' ', array_unique($messages)));
    }
}
