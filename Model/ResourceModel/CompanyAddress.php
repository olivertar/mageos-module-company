<?php

declare(strict_types=1);

namespace MageOS\Company\Model\ResourceModel;

use Magento\Eav\Model\Entity\VersionControl\AbstractEntity;
use Magento\Framework\DataObject;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Model\CompanyAddress as CompanyAddressModel;

/**
 * Company address EAV resource model.
 */
class CompanyAddress extends AbstractEntity
{
    /**
     * @inheritDoc
     */
    public function getEntityType()
    {
        if (empty($this->_type)) {
            $this->setType(CompanyAddressModel::ENTITY);
        }

        return parent::getEntityType();
    }

    /**
     * Check whether an address belongs to a company.
     *
     * @param int $addressId
     * @param int $companyId
     * @return bool
     */
    public function belongsToCompany(int $addressId, int $companyId): bool
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getEntityTable(), [$this->getEntityIdField()])
            ->where($this->getEntityIdField() . ' = ?', $addressId)
            ->where('parent_id = ?', $companyId);

        return (bool)$connection->fetchOne($select);
    }

    /**
     * Clear company default/legal address references pointing to the deleted address.
     *
     * Those columns have no foreign key (as in customer_entity) to avoid circular constraints.
     *
     * @param DataObject $object
     * @return $this
     */
    protected function _afterDelete(DataObject $object)
    {
        $connection = $this->getConnection();
        foreach ([
            CompanyInterface::DEFAULT_BILLING,
            CompanyInterface::DEFAULT_SHIPPING,
            CompanyInterface::LEGAL_ADDRESS,
        ] as $column) {
            $connection->update(
                $this->getTable('company_entity'),
                [$column => null],
                [$column . ' = ?' => (int)$object->getId()]
            );
        }

        return parent::_afterDelete($object);
    }
}
