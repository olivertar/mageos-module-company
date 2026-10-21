<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Account;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use MageOS\Company\Api\CompanyAuthorizationInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Controller\AbstractCompanyAction;

/**
 * Company profile edit form.
 */
class Edit extends AbstractCompanyAction implements HttpGetActionInterface
{
    protected const RESOURCE = CompanyAuthorizationInterface::RESOURCE_EDIT;

    /**
     * @inheritDoc
     */
    protected function executeFor(CompanyInterface $company, int $customerId): ResultInterface
    {
        return $this->page((string)__('Edit Company Information'));
    }
}
