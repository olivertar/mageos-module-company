<?php

declare(strict_types=1);

namespace MageOS\Company\Model\ResourceModel;

use Magento\Eav\Model\Entity\VersionControl\AbstractEntity;
use MageOS\Company\Model\Company as CompanyModel;

/**
 * Company EAV resource model.
 */
class Company extends AbstractEntity
{
    /**
     * @inheritDoc
     */
    public function getEntityType()
    {
        if (empty($this->_type)) {
            $this->setType(CompanyModel::ENTITY);
        }

        return parent::getEntityType();
    }

    /**
     * Find a company ID by its external (ERP) code.
     *
     * @param string $erpCode
     * @return int|null
     */
    public function getIdByErpCode(string $erpCode): ?int
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getEntityTable(), [$this->getEntityIdField()])
            ->where('company_id = ?', $erpCode);
        $id = (int)$connection->fetchOne($select);

        return $id ?: null;
    }

    /**
     * Check whether an external (ERP) code is already used by another company.
     *
     * @param string $erpCode
     * @param int|null $excludeCompanyId
     * @return bool
     */
    public function isErpCodeUsed(string $erpCode, ?int $excludeCompanyId = null): bool
    {
        $id = $this->getIdByErpCode($erpCode);

        return $id !== null && $id !== $excludeCompanyId;
    }

    /**
     * Check whether an email is already used by another company of the same website.
     *
     * @param string $email
     * @param int $websiteId
     * @param int|null $excludeCompanyId
     * @return bool
     */
    public function isEmailUsed(string $email, int $websiteId, ?int $excludeCompanyId = null): bool
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getEntityTable(), [$this->getEntityIdField()])
            ->where('email = ?', $email)
            ->where('website_id = ?', $websiteId);
        if ($excludeCompanyId !== null) {
            $select->where($this->getEntityIdField() . ' != ?', $excludeCompanyId);
        }

        return (bool)$connection->fetchOne($select);
    }
}
