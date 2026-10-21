<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Adminhtml\Company;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\ResultFactory;

/**
 * New company form.
 */
class NewAction extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_Company::company_edit';

    /**
     * @inheritDoc
     */
    public function execute()
    {
        /** @var Forward $forward */
        $forward = $this->resultFactory->create(ResultFactory::TYPE_FORWARD);

        return $forward->forward('edit');
    }
}
