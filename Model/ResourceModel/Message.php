<?php

declare(strict_types=1);

namespace MageOS\Company\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use MageOS\Company\Api\Data\MessageInterface;

/**
 * Company message resource model.
 */
class Message extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('company_message', MessageInterface::MESSAGE_ID);
    }

    /**
     * Mark as read the messages a reader has not read yet (the other party's messages).
     *
     * @param int $companyId
     * @param string $readerType
     * @return int
     */
    public function markRead(int $companyId, string $readerType): int
    {
        $column = $this->getReadColumn($readerType);

        return $this->getConnection()->update(
            $this->getMainTable(),
            [$column => new \Zend_Db_Expr('CURRENT_TIMESTAMP')],
            [
                MessageInterface::COMPANY_ENTITY_ID . ' = ?' => $companyId,
                $column . ' IS NULL',
            ]
        );
    }

    /**
     * Number of messages a reader has not read.
     *
     * @param int $companyId
     * @param string $readerType
     * @return int
     */
    public function countUnread(int $companyId, string $readerType): int
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['COUNT(*)'])
            ->where(MessageInterface::COMPANY_ENTITY_ID . ' = ?', $companyId)
            ->where($this->getReadColumn($readerType) . ' IS NULL');

        return (int)$connection->fetchOne($select);
    }

    /**
     * Number of messages a customer wrote in the last hour.
     *
     * @param int $customerId
     * @return int
     */
    public function countRecentByCustomer(int $customerId): int
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['COUNT(*)'])
            ->where(MessageInterface::AUTHOR_TYPE . ' = ?', MessageInterface::AUTHOR_CUSTOMER)
            ->where(MessageInterface::AUTHOR_ID . ' = ?', $customerId)
            ->where(MessageInterface::CREATED_AT . ' >= NOW() - INTERVAL 1 HOUR');

        return (int)$connection->fetchOne($select);
    }

    /**
     * Read date column of a reader.
     *
     * @param string $readerType
     * @return string
     */
    private function getReadColumn(string $readerType): string
    {
        return $readerType === MessageInterface::AUTHOR_ADMIN
            ? MessageInterface::READ_AT_ADMIN
            : MessageInterface::READ_AT_CUSTOMER;
    }
}
