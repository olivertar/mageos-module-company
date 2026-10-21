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
use MageOS\Company\Api\CompanyRepositoryInterface;
use MageOS\Company\Api\Data\CompanyCustomerInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Api\Data\CompanySearchResultsInterface;
use MageOS\Company\Api\Data\CompanySearchResultsInterfaceFactory;
use MageOS\Company\Model\CompanyCustomer\Assigner;
use MageOS\Company\Model\ResourceModel\Company as CompanyResource;
use MageOS\Company\Model\ResourceModel\Company\CollectionFactory;
use MageOS\Company\Model\Validator\CompanyValidator;
use Psr\Log\LoggerInterface;

/**
 * Validates and persists companies; assigns the super user as company admin in the same transaction.
 */
class CompanyRepository implements CompanyRepositoryInterface
{
    /**
     * @param CompanyFactory $companyFactory
     * @param CompanyResource $companyResource
     * @param CompanyRegistry $companyRegistry
     * @param CollectionFactory $collectionFactory
     * @param CompanySearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param JoinProcessorInterface $extensionAttributesJoinProcessor
     * @param CompanyValidator $validator
     * @param Assigner $customerAssigner
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CompanyFactory $companyFactory,
        private readonly CompanyResource $companyResource,
        private readonly CompanyRegistry $companyRegistry,
        private readonly CollectionFactory $collectionFactory,
        private readonly CompanySearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly JoinProcessorInterface $extensionAttributesJoinProcessor,
        private readonly CompanyValidator $validator,
        private readonly Assigner $customerAssigner,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(CompanyInterface $company): CompanyInterface
    {
        $model = $this->prepareModel($company);
        $isNew = $model->getId() === null;
        // never serve an instance with unsaved (possibly invalid) changes from the cache
        $this->companyRegistry->remove($model->getId());

        $errors = $this->validator->validate($model);
        if ($errors) {
            $exception = new InputException();
            foreach ($errors as $error) {
                $exception->addError($error);
            }
            throw $exception;
        }

        $connection = $this->companyResource->getConnection();
        $connection->beginTransaction();
        try {
            $this->companyResource->save($model);
            $superUserId = $model->getSuperUserId();
            if ($superUserId !== null) {
                $this->customerAssigner->assign($model, $superUserId, CompanyCustomerInterface::ROLE_ADMIN);
            }
            $connection->commit();
        } catch (LocalizedException $e) {
            $connection->rollBack();
            $this->resetIdOnFailure($model, $isNew);
            throw $e;
        } catch (\Exception $e) {
            $connection->rollBack();
            $this->resetIdOnFailure($model, $isNew);
            $this->logger->critical($e);
            throw new CouldNotSaveException(__('Could not save the company.'), $e);
        } catch (\Throwable $e) {
            $connection->rollBack();
            $this->resetIdOnFailure($model, $isNew);
            throw $e;
        }

        return $this->getById((int)$model->getId());
    }

    /**
     * @inheritDoc
     */
    public function getById(int $companyId): CompanyInterface
    {
        $company = $this->companyRegistry->get($companyId);
        if ($company === null) {
            $company = $this->companyFactory->create();
            $this->companyResource->load($company, $companyId);
            if (!$company->getId()) {
                throw NoSuchEntityException::singleField(CompanyInterface::ENTITY_ID, $companyId);
            }
            $company->setOrigData();
            $this->companyRegistry->push($company);
        }

        return $company;
    }

    /**
     * @inheritDoc
     */
    public function getByErpCode(string $erpCode): CompanyInterface
    {
        $companyId = $this->companyResource->getIdByErpCode($erpCode);
        if ($companyId === null) {
            throw NoSuchEntityException::singleField(CompanyInterface::COMPANY_ID, $erpCode);
        }

        return $this->getById($companyId);
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): CompanySearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect('*');
        $this->extensionAttributesJoinProcessor->process($collection, CompanyInterface::class);
        $this->collectionProcessor->process($searchCriteria, $collection);

        $items = [];
        /** @var Company $company */
        foreach ($collection->getItems() as $company) {
            $company->setOrigData();
            $items[] = $company;
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
    public function delete(CompanyInterface $company): bool
    {
        $companyId = (int)$company->getId();
        /** @var Company $model */
        $model = $company instanceof Company ? $company : $this->getById($companyId);
        try {
            $this->companyResource->delete($model);
        } catch (\Exception $e) {
            $this->logger->critical($e);
            throw new CouldNotDeleteException(__('Could not delete the company "%1".', $companyId), $e);
        }
        $this->companyRegistry->remove($companyId);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $companyId): bool
    {
        return $this->delete($this->getById($companyId));
    }

    /**
     * Return the model to persist.
     *
     * A company that was not loaded through this repository (e.g. hydrated from a web API payload) is merged
     * onto the stored one, so attributes missing from the payload keep their current value.
     *
     * @param CompanyInterface $company
     * @return Company
     * @throws LocalizedException
     */
    private function prepareModel(CompanyInterface $company): Company
    {
        if (!$company instanceof Company) {
            throw new LocalizedException(__('Unsupported company implementation "%1".', $company::class));
        }
        if ($company->getId() === null || $company->getOrigData() !== null) {
            return $company;
        }

        $stored = $this->companyFactory->create();
        $this->companyResource->load($stored, $company->getId());
        if (!$stored->getId()) {
            throw NoSuchEntityException::singleField(CompanyInterface::ENTITY_ID, $company->getId());
        }
        $stored->setOrigData();
        foreach ($company->getData() as $key => $value) {
            if ($value !== null) {
                $stored->setData($key, $value);
            }
        }

        return $stored;
    }

    /**
     * Drop the ID assigned by a rolled back insert.
     *
     * @param Company $model
     * @param bool $isNew
     * @return void
     */
    private function resetIdOnFailure(Company $model, bool $isNew): void
    {
        if ($isNew) {
            $model->setId(null);
        }
    }
}
