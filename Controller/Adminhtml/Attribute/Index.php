<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Adminhtml\Attribute;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

/**
 * Company and company address attribute grid (Stores > Attributes > Company).
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_Company::attributes';

    /**
     * @inheritDoc
     */
    public function execute()
    {
        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('MageOS_Company::company_attributes');
        $page->getConfig()->getTitle()->prepend((string)__('Company Attributes'));

        return $page;
    }
}
