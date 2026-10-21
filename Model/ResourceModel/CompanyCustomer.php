<?php

declare(strict_types=1);

namespace MageOS\Company\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use MageOS\Company\Api\Data\CompanyCustomerInterface;

/**
 * Customer ↔ company relation resource model. The customer ID is the primary key (one company per customer).
 */
class CompanyCustomer extends AbstractDb
{
    public const PENDING_WELCOME_EMAIL = 'pending_welcome_email';

    /**
     * @var bool
     */
    protected $_isPkAutoIncrement = false;

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('company_customer', CompanyCustomerInterface::CUSTOMER_ID);
    }

    /**
     * Get the company entity ID a customer belongs to.
     *
     * @param int $customerId
     * @return int|null
     */
    public function getCompanyIdByCustomerId(int $customerId): ?int
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), [CompanyCustomerInterface::COMPANY_ENTITY_ID])
            ->where(CompanyCustomerInterface::CUSTOMER_ID . ' = ?', $customerId);
        $companyId = (int)$connection->fetchOne($select);

        return $companyId ?: null;
    }

    /**
     * Count active admins of a company, optionally excluding one customer.
     *
     * @param int $companyId
     * @param int|null $excludeCustomerId
     * @return int
     */
    public function countActiveAdmins(int $companyId, ?int $excludeCustomerId = null): int
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['COUNT(*)'])
            ->where(CompanyCustomerInterface::COMPANY_ENTITY_ID . ' = ?', $companyId)
            ->where(CompanyCustomerInterface::ROLE . ' = ?', CompanyCustomerInterface::ROLE_ADMIN)
            ->where(CompanyCustomerInterface::STATUS . ' = ?', CompanyCustomerInterface::STATUS_ACTIVE);
        if ($excludeCustomerId !== null) {
            $select->where(CompanyCustomerInterface::CUSTOMER_ID . ' != ?', $excludeCustomerId);
        }

        return (int)$connection->fetchOne($select);
    }

    /**
     * Hold back (type) or release (null) the welcome email of a company user.
     *
     * @param int $customerId
     * @param string|null $emailType
     * @return void
     */
    public function setPendingWelcomeEmail(int $customerId, ?string $emailType): void
    {
        $this->getConnection()->update(
            $this->getMainTable(),
            [self::PENDING_WELCOME_EMAIL => $emailType],
            [CompanyCustomerInterface::CUSTOMER_ID . ' = ?' => $customerId]
        );
    }

    /**
     * Welcome emails held back for the users of a company.
     *
     * @param int $companyId
     * @return array<int,string> Email type by customer ID.
     */
    public function getPendingWelcomeEmails(int $companyId): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), [CompanyCustomerInterface::CUSTOMER_ID, self::PENDING_WELCOME_EMAIL])
            ->where(CompanyCustomerInterface::COMPANY_ENTITY_ID . ' = ?', $companyId)
            ->where(self::PENDING_WELCOME_EMAIL . ' IS NOT NULL');

        return array_map('strval', $connection->fetchPairs($select));
    }
}
