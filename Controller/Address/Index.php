<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Address;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use MageOS\Company\Api\CompanyAuthorizationInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Controller\AbstractCompanyAction;

/**
 * Company address book.
 */
class Index extends AbstractCompanyAction implements HttpGetActionInterface
{
    protected const RESOURCE = CompanyAuthorizationInterface::RESOURCE_ADDRESS_VIEW;

    /**
     * @inheritDoc
     */
    protected function executeFor(CompanyInterface $company, int $customerId): ResultInterface
    {
        return $this->page((string)__('Company Addresses'));
    }
}
