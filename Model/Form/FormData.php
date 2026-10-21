<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Form;

use Magento\Framework\Model\AbstractModel;
use MageOS\Company\Model\Company;
use MageOS\Company\Model\Form;
use MageOS\Company\Model\FormFactory;

/**
 * Restricts API payloads to the attributes of a form code and validates them with the attribute rules, like the
 * storefront forms do.
 */
class FormData
{
    /**
     * @param FormFactory $formFactory
     */
    public function __construct(
        private readonly FormFactory $formFactory
    ) {
    }

    /**
     * Copy the values the payload sets for the attributes of a form. Null means "not sent"; '' clears the value.
     *
     * @param AbstractModel $payload
     * @param AbstractModel $target
     * @param string $entityType
     * @param string $formCode
     * @return void
     */
    public function copy(AbstractModel $payload, AbstractModel $target, string $entityType, string $formCode): void
    {
        $data = $payload->getData();
        foreach (array_keys($this->getForm($entityType, $formCode)->getAttributes()) as $code) {
            if (array_key_exists($code, $data) && $data[$code] !== null) {
                $target->setData($code, $data[$code] === '' ? null : $data[$code]);
            }
        }
    }

    /**
     * Validate the entity values of the attributes of a form.
     *
     * @param AbstractModel $entity
     * @param string $entityType
     * @param string $formCode
     * @return string[]
     */
    public function validate(AbstractModel $entity, string $entityType, string $formCode): array
    {
        $form = $this->getForm($entityType, $formCode)->setEntity($entity);
        $data = [];
        foreach (array_keys($form->getAttributes()) as $code) {
            $data[$code] = $entity->getData($code);
        }
        $result = $form->validateData($data);

        return $result === true ? [] : array_map('strval', $result);
    }

    /**
     * Form of an entity type and form code.
     *
     * @param string $entityType
     * @param string $formCode
     * @return Form
     */
    private function getForm(string $entityType, string $formCode): Form
    {
        $form = $this->formFactory->create();
        if ($entityType !== Company::ENTITY) {
            $form->setEntityType($entityType);
        }

        return $form->setFormCode($formCode);
    }
}
