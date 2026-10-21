<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Message;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use MageOS\Company\Api\CompanyAuthorizationInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Controller\AbstractCompanyAction;

/**
 * Company message thread in the customer account.
 */
class Index extends AbstractCompanyAction implements HttpGetActionInterface
{
    protected const RESOURCE = CompanyAuthorizationInterface::RESOURCE_MESSAGES_VIEW;

    /**
     * @inheritDoc
     */
    protected function executeFor(CompanyInterface $company, int $customerId): ResultInterface
    {
        return $this->page((string)__('Company Messages'));
    }
}
