<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Adminhtml\Attribute;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use MageOS\Company\Model\Attribute\AttributeRepository;
use MageOS\Company\Model\Attribute\FormCodes;

/**
 * Attribute edit form.
 */
class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_Company::attributes';

    /**
     * @param Context $context
     * @param AttributeRepository $attributeRepository
     * @param FormCodes $formCodes
     */
    public function __construct(
        Context $context,
        private readonly AttributeRepository $attributeRepository,
        private readonly FormCodes $formCodes
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $attributeId = (int)$this->getRequest()->getParam('id');
        if ($attributeId) {
            try {
                $attribute = $this->attributeRepository->getById($attributeId);
                $title = (string)($attribute->getFrontendLabel() ?: $attribute->getAttributeCode());
            } catch (NoSuchEntityException) {
                $this->messageManager->addErrorMessage(__('The attribute no longer exists.'));

                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        } else {
            $entityTypes = $this->formCodes->getEntityTypes();
            $entityType = (string)$this->getRequest()->getParam('entity_type');
            if (!isset($entityTypes[$entityType])) {
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
            $title = (string)__('New %1 Attribute', $entityTypes[$entityType]);
        }

        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('MageOS_Company::company_attributes');
        $page->getConfig()->getTitle()->prepend((string)__('Company Attributes'));
        $page->getConfig()->getTitle()->prepend($title);

        return $page;
    }
}
