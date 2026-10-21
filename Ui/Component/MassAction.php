<?php

declare(strict_types=1);

namespace MageOS\Company\Ui\Component;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentInterface;
use Magento\Ui\Component\MassAction as UiMassAction;

/**
 * Mass actions toolbar that hides the actions whose "aclResource" the admin is not allowed to use.
 */
class MassAction extends UiMassAction
{
    /**
     * @param ContextInterface $context
     * @param AuthorizationInterface $authorization
     * @param UiComponentInterface[] $components
     * @param array<string,mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        private readonly AuthorizationInterface $authorization,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $components, $data);
    }

    /**
     * @inheritDoc
     */
    public function prepare()
    {
        foreach ($this->getChildComponents() as $actionComponent) {
            $config = $actionComponent->getConfiguration();
            $resource = $config['aclResource'] ?? null;
            if ($resource && !$this->authorization->isAllowed($resource)) {
                $actionComponent->setData('config', ['actionDisable' => true] + $config);
            }
        }

        parent::prepare();
    }
}
