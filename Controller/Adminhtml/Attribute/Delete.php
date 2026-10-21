<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Adminhtml\Attribute;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use MageOS\Company\Model\Attribute\AttributeEditor;

/**
 * Delete a custom attribute and its values.
 */
class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_Company::attributes';

    /**
     * @param Context $context
     * @param AttributeEditor $attributeEditor
     */
    public function __construct(
        Context $context,
        private readonly AttributeEditor $attributeEditor
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create()->setPath('*/*/');
        $attributeId = (int)$this->getRequest()->getParam('id');
        if (!$attributeId) {
            return $redirect;
        }
        try {
            $this->attributeEditor->delete($attributeId);
            $this->messageManager->addSuccessMessage(__('The attribute has been deleted.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $redirect->setPath('*/*/edit', ['id' => $attributeId]);
        }

        return $redirect;
    }
}
