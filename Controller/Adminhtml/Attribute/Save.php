<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Adminhtml\Attribute;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use MageOS\Company\Model\Attribute\AttributeEditor;
use Psr\Log\LoggerInterface;

/**
 * Save an attribute from the admin form.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_Company::attributes';
    public const DATA_PERSISTOR_KEY = 'mageos_company_attribute';

    /**
     * @param Context $context
     * @param AttributeEditor $attributeEditor
     * @param DataPersistorInterface $dataPersistor
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly AttributeEditor $attributeEditor,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        /** @var HttpRequest $request */
        $request = $this->getRequest();
        $data = $request->getPostValue('attribute');
        if (!is_array($data)) {
            return $redirect->setPath('*/*/');
        }
        $attributeId = (int)($data['attribute_id'] ?? 0);

        try {
            $attribute = $this->attributeEditor->save($data);
            $this->dataPersistor->clear(self::DATA_PERSISTOR_KEY);
            $this->messageManager->addSuccessMessage(__('The attribute has been saved.'));
            if ($request->getParam('back')) {
                return $redirect->setPath('*/*/edit', ['id' => $attribute->getId()]);
            }

            return $redirect->setPath('*/*/');
        } catch (InputException $e) {
            foreach ($e->getErrors() ?: [$e] as $error) {
                $this->messageManager->addErrorMessage($error->getMessage());
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $this->messageManager->addErrorMessage(__('Something went wrong while saving the attribute.'));
        }

        $this->dataPersistor->set(self::DATA_PERSISTOR_KEY, $data);

        return $attributeId
            ? $redirect->setPath('*/*/edit', ['id' => $attributeId])
            : $redirect->setPath('*/*/new', ['entity_type' => (string)($data['entity_type_code'] ?? '')]);
    }
}
