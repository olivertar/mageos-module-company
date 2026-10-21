<?php

declare(strict_types=1);

namespace MageOS\Company\Block\Adminhtml\Attribute\Edit;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use MageOS\Company\Model\Attribute\AttributeRepository;

/**
 * "Delete Attribute" button, for custom attributes only.
 */
class DeleteButton implements ButtonProviderInterface
{
    /**
     * @param RequestInterface $request
     * @param UrlInterface $urlBuilder
     * @param Escaper $escaper
     * @param AttributeRepository $attributeRepository
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly UrlInterface $urlBuilder,
        private readonly Escaper $escaper,
        private readonly AttributeRepository $attributeRepository
    ) {
    }

    /**
     * @inheritDoc
     *
     * @return array<string,mixed>
     */
    public function getButtonData()
    {
        $attributeId = (int)$this->request->getParam('id');
        if (!$attributeId) {
            return [];
        }
        try {
            if (!$this->attributeRepository->getById($attributeId)->getIsUserDefined()) {
                return [];
            }
        } catch (NoSuchEntityException) {
            return [];
        }

        return [
            'label' => __('Delete Attribute'),
            'class' => 'delete',
            'on_click' => sprintf(
                "deleteConfirm('%s', '%s', {data: {}})",
                $this->escaper->escapeJs(
                    (string)__('Are you sure you want to delete this attribute? Its values will be deleted too.')
                ),
                $this->escaper->escapeJs(
                    $this->urlBuilder->getUrl('mageos_company/attribute/delete', ['id' => $attributeId])
                )
            ),
            'sort_order' => 20,
        ];
    }
}
