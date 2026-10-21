<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Adminhtml\Message;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

/**
 * Company conversations grid.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_Company::messages';

    /**
     * @inheritDoc
     */
    public function execute()
    {
        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('MageOS_Company::messages_manage');
        $page->getConfig()->getTitle()->prepend((string)__('Company Messages'));

        return $page;
    }
}
