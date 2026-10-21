<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Adminhtml\Attribute;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\ResultFactory;

/**
 * New attribute form; the entity type comes in the "entity_type" parameter.
 */
class NewAction extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_Company::attributes';

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
