<?php

declare(strict_types=1);

namespace MageOS\Company\Block\Adminhtml\Customer\Edit\Tab;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Ui\Component\Layout\Tabs\TabInterface;
use MageOS\Company\Api\CompanyManagementInterface;
use MageOS\Company\Api\Data\CompanyCustomerInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Model\Company\Attribute\Source\Status as StatusSource;
use MageOS\Company\Model\CompanyCustomer\Source\Role as RoleSource;
use MageOS\Company\Model\ResourceModel\CompanyCustomer as CompanyCustomerResource;
use MageOS\Company\Model\CompanyCustomerFactory;

/**
 * "Company" tab of the admin customer form.
 */
class Company extends Template implements TabInterface
{
    /**
     * @var CompanyInterface|null
     */
    private ?CompanyInterface $company = null;

    /**
     * @var bool
     */
    private bool $companyLoaded = false;

    /**
     * @param Context $context
     * @param CompanyManagementInterface $companyManagement
     * @param CompanyCustomerFactory $relationFactory
     * @param CompanyCustomerResource $relationResource
     * @param StatusSource $statusSource
     * @param RoleSource $roleSource
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly CompanyManagementInterface $companyManagement,
        private readonly CompanyCustomerFactory $relationFactory,
        private readonly CompanyCustomerResource $relationResource,
        private readonly StatusSource $statusSource,
        private readonly RoleSource $roleSource,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Edited customer ID.
     *
     * @return int
     */
    public function getCustomerId(): int
    {
        return (int)$this->getRequest()->getParam('id');
    }

    /**
     * Company of the customer, if any.
     *
     * @return CompanyInterface|null
     */
    public function getCompany(): ?CompanyInterface
    {
        if (!$this->companyLoaded) {
            $this->companyLoaded = true;
            try {
                $this->company = $this->companyManagement->getByCustomerId($this->getCustomerId());
            } catch (NoSuchEntityException) {
                $this->company = null;
            }
        }

        return $this->company;
    }

    /**
     * Customer ↔ company relation.
     *
     * @return CompanyCustomerInterface|null
     */
    public function getRelation(): ?CompanyCustomerInterface
    {
        $relation = $this->relationFactory->create();
        $this->relationResource->load($relation, $this->getCustomerId());

        return $relation->getCustomerId() ? $relation : null;
    }

    /**
     * Status label of the company.
     *
     * @param CompanyInterface $company
     * @return string
     */
    public function getStatusLabel(CompanyInterface $company): string
    {
        return (string)$this->statusSource->getOptionText($company->getStatus());
    }

    /**
     * Role label.
     *
     * @param string|null $role
     * @return string
     */
    public function getRoleLabel(?string $role): string
    {
        foreach ($this->roleSource->toOptionArray() as $option) {
            if ($option['value'] === $role) {
                return (string)$option['label'];
            }
        }

        return (string)$role;
    }

    /**
     * Admin URL of the company form.
     *
     * @param CompanyInterface $company
     * @return string
     */
    public function getCompanyUrl(CompanyInterface $company): string
    {
        return $this->getUrl('mageos_company/company/edit', ['id' => $company->getId()]);
    }

    /**
     * @inheritDoc
     */
    public function getTabLabel()
    {
        return (string)__('Company');
    }

    /**
     * @inheritDoc
     */
    public function getTabTitle()
    {
        return (string)__('Company');
    }

    /**
     * @inheritDoc
     */
    public function canShowTab()
    {
        return (bool)$this->getCustomerId();
    }

    /**
     * @inheritDoc
     */
    public function isHidden()
    {
        return !$this->getCustomerId();
    }

    /**
     * @inheritDoc
     */
    public function getTabClass()
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function getTabUrl()
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function isAjaxLoaded()
    {
        return false;
    }
}
