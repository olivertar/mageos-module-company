<?php

declare(strict_types=1);

namespace MageOS\Company\Block\Adminhtml\Company\Edit;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;

/**
 * Shared helpers of the company form buttons.
 */
class GenericButton
{
    /**
     * @param UrlInterface $urlBuilder
     * @param RequestInterface $request
     * @param AuthorizationInterface $authorization
     * @param Escaper $escaper
     */
    public function __construct(
        protected readonly UrlInterface $urlBuilder,
        protected readonly RequestInterface $request,
        protected readonly AuthorizationInterface $authorization,
        protected readonly Escaper $escaper
    ) {
    }

    /**
     * Edited company ID, 0 for a new company.
     *
     * @return int
     */
    public function getCompanyId(): int
    {
        return (int)$this->request->getParam('id');
    }

    /**
     * Build an admin URL.
     *
     * @param string $route
     * @param array<string,mixed> $params
     * @return string
     */
    public function getUrl(string $route = '', array $params = []): string
    {
        return $this->urlBuilder->getUrl($route, $params);
    }
}
