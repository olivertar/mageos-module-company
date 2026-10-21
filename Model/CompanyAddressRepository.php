<?php

declare(strict_types=1);

namespace MageOS\Company\Model;

use Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use MageOS\Company\Api\CompanyAddressRepositoryInterface;
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Api\Data\CompanyAddressInterface;
use MageOS\Company\Api\Data\CompanyAddressSearchResultsInterface;
use MageOS\Company\Api\Data\CompanyAddressSearchResultsInterfaceFactory;
use MageOS\Company\Model\ResourceModel\CompanyAddress as CompanyAddressResource;
use MageOS\Company\Model\ResourceModel\CompanyAddress\CollectionFactory;
use MageOS\Company\Model\Validator\AddressValidator;
use Psr\Log\LoggerInterface;

/**
 * Validates and persists company addresses.
 */
class CompanyAddressRepository implements CompanyAddressRepositoryInterface
{
    /**
     * @var CompanyAddress[]
     */
    private array $instances = [];

    /**
     * @param CompanyAddressFactory $addressFactory
     * @param CompanyAddressResource $addressResource
     * @param CollectionFactory $collectionFactory
     * @param CompanyAddressSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param JoinProcessorInterface $extensionAttributesJoinProcessor
     * @param CompanyRepositoryInterface $companyRepository
     * @param CompanyRegistry $companyRegistry
     * @param AddressValidator $validator
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CompanyAddressFactory $addressFactory,
        private readonly CompanyAddressResource $addressResource,
        private readonly CollectionFactory $collectionFactory,
        private readonly CompanyAddressSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly JoinProcessorInterface $extensionAttributesJoinProcessor,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly CompanyRegistry $companyRegistry,
        private readonly AddressValidator $validator,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(CompanyAddressInterface $address): CompanyAddressInterface
    {
        $model = $this->prepareModel($address);
        // never serve an instance with unsaved (possibly invalid) changes from the cache
        unset($this->instances[$model->getId()]);

        $errors = $this->validator->validate($model);
        if ($errors) {
            $exception = new InputException();
            foreach ($errors as $error) {
                $exception->addError($error);
            }
            throw $exception;
        }
        $this->companyRepository->getById((int)$model->getParentId());

        try {
            $this->addressResource->save($model);
        } catch (LocalizedException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->critical($e);
            throw new CouldNotSaveException(__('Could not save the company address.'), $e);
        }

        return $this->getById((int)$model->getId());
    }

    /**
     * @inheritDoc
     */
    public function getById(int $addressId): CompanyAddressInterface
    {
        if (!isset($this->instances[$addressId])) {
            $address = $this->addressFactory->create();
            $this->addressResource->load($address, $addressId);
            if (!$address->getId()) {
                throw NoSuchEntityException::singleField(CompanyAddressInterface::ENTITY_ID, $addressId);
            }
            $address->setOrigData();
            $this->instances[$addressId] = $address;
        }

        return $this->instances[$addressId];
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): CompanyAddressSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect('*');
        $this->extensionAttributesJoinProcessor->process($collection, CompanyAddressInterface::class);
        $this->collectionProcessor->process($searchCriteria, $collection);

        $items = [];
        /** @var CompanyAddress $address */
        foreach ($collection->getItems() as $address) {
            $address->setOrigData();
            $items[] = $address;
        }

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($items);
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function delete(CompanyAddressInterface $address): bool
    {
        $addressId = (int)$address->getId();
        /** @var CompanyAddress $model */
        $model = $address instanceof CompanyAddress ? $address : $this->getById($addressId);
        $companyId = $model->getParentId();
        try {
            $this->addressResource->delete($model);
        } catch (\Exception $e) {
            $this->logger->critical($e);
            throw new CouldNotDeleteException(__('Could not delete the company address "%1".', $addressId), $e);
        }
        unset($this->instances[$addressId]);
        // the delete cleared the company default/legal address columns
        $this->companyRegistry->remove($companyId);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $addressId): bool
    {
        return $this->delete($this->getById($addressId));
    }

    /**
     * Return the model to persist, merging detached payloads onto the stored address.
     *
     * An existing address cannot be moved to another company.
     *
     * @param CompanyAddressInterface $address
     * @return CompanyAddress
     * @throws LocalizedException
     */
    private function prepareModel(CompanyAddressInterface $address): CompanyAddress
    {
        if (!$address instanceof CompanyAddress) {
            throw new LocalizedException(__('Unsupported company address implementation "%1".', $address::class));
        }
        if ($address->getId() === null) {
            return $address;
        }

        $stored = $this->addressFactory->create();
        $this->addressResource->load($stored, $address->getId());
        if (!$stored->getId()) {
            throw NoSuchEntityException::singleField(CompanyAddressInterface::ENTITY_ID, $address->getId());
        }
        if ($address->getParentId() !== null && $address->getParentId() !== $stored->getParentId()) {
            throw new LocalizedException(__('A company address cannot be moved to another company.'));
        }
        if ($address->getOrigData() !== null) {
            return $address;
        }

        $stored->setOrigData();
        foreach ($address->getData() as $key => $value) {
            if ($value !== null) {
                $stored->setData($key, $value);
            }
        }

        return $stored;
    }
}
