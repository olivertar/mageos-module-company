<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Account;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Controller\AbstractCompanyAction;

/**
 * Company profile.
 */
class Index extends AbstractCompanyAction implements HttpGetActionInterface
{
    /**
     * @inheritDoc
     */
    protected function executeFor(CompanyInterface $company, int $customerId): ResultInterface
    {
        return $this->page((string)__('My Company'));
    }
}
