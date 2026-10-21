<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Form;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Model\AbstractModel;
use MageOS\Company\Model\Company;
use MageOS\Company\Model\FormFactory;

/**
 * Applies the posted values of a storefront form to a company or company address, validated with the rules of
 * the attributes of the form code.
 */
class RequestApplier
{
    /**
     * @param FormFactory $formFactory
     */
    public function __construct(
        private readonly FormFactory $formFactory
    ) {
    }

    /**
     * Extract, validate and set the values of the form attributes posted under a request scope.
     *
     * Values not posted are left untouched; posted empty values clear the field.
     *
     * @param AbstractModel $entity
     * @param string $entityType Company::ENTITY or CompanyAddress::ENTITY.
     * @param string $formCode
     * @param RequestInterface $request
     * @param string $scope
     * @return string[] Validation errors.
     */
    public function apply(
        AbstractModel $entity,
        string $entityType,
        string $formCode,
        RequestInterface $request,
        string $scope
    ): array {
        $form = $this->formFactory->create();
        if ($entityType !== Company::ENTITY) {
            $form->setEntityType($entityType);
        }
        $form->setFormCode($formCode)->setEntity($entity);
        $data = $form->extractData($request, $scope);
        $result = $form->validateData($data);
        // set the raw values: compactData() goes through the typed setters with strings (e.g. setRegionId("12"))
        foreach ($data as $code => $value) {
            if ($value !== false) {
                $entity->setData($code, $value === '' ? null : $value);
            }
        }

        return $result === true ? [] : array_map('strval', $result);
    }
}
