<?php

declare(strict_types=1);

namespace MageOS\Company\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use MageOS\Company\Model\Invitation as InvitationModel;

/**
 * Company invitation resource model.
 */
class Invitation extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('company_invitation', InvitationModel::INVITATION_ID);
    }

    /**
     * Load an invitation by token hash, with an "is_expired" flag computed by the database clock.
     *
     * @param InvitationModel $invitation
     * @param string $tokenHash
     * @return void
     */
    public function loadByTokenHash(InvitationModel $invitation, string $tokenHash): void
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->columns(['is_expired' => new \Zend_Db_Expr('(' . InvitationModel::EXPIRES_AT . ' < NOW())')])
            ->where(InvitationModel::TOKEN_HASH . ' = ?', $tokenHash);
        $row = $connection->fetchRow($select);
        if ($row) {
            $invitation->setData($row);
            $invitation->setOrigData();
        }
    }

    /**
     * Delete the open invitations of an email to a company.
     *
     * @param int $companyId
     * @param string $email
     * @return void
     */
    public function deleteOpen(int $companyId, string $email): void
    {
        $this->getConnection()->delete($this->getMainTable(), [
            InvitationModel::COMPANY_ENTITY_ID . ' = ?' => $companyId,
            InvitationModel::EMAIL . ' = ?' => $email,
            InvitationModel::ACCEPTED_AT . ' IS NULL',
        ]);
    }
}
