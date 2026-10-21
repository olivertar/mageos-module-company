<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Users;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use MageOS\Company\Api\Data\CompanyInterface;

/**
 * Company users, open invitations and the invitation form.
 */
class Index extends AbstractUsersAction implements HttpGetActionInterface
{
    /**
     * @inheritDoc
     */
    protected function executeFor(CompanyInterface $company, int $customerId): ResultInterface
    {
        return $this->page((string)__('Company Users'));
    }
}
