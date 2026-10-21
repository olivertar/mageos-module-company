<?php

declare(strict_types=1);

namespace MageOS\Company\Observer;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\HttpInterface as HttpResponse;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\UrlInterface;
use Magento\ReCaptchaUi\Model\IsCaptchaEnabledInterface;
use Magento\ReCaptchaUi\Model\RequestHandlerInterface;

/**
 * reCAPTCHA check of the company registration form.
 */
class ValidateRegistrationCaptcha implements ObserverInterface
{
    public const CAPTCHA_KEY = 'company_register';

    /**
     * @param UrlInterface $url
     * @param IsCaptchaEnabledInterface $isCaptchaEnabled
     * @param RequestHandlerInterface $requestHandler
     * @param ResponseInterface $response
     */
    public function __construct(
        private readonly UrlInterface $url,
        private readonly IsCaptchaEnabledInterface $isCaptchaEnabled,
        private readonly RequestHandlerInterface $requestHandler,
        private readonly ResponseInterface $response
    ) {
    }

    /**
     * Validate the captcha before the application is processed.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $request = $observer->getData('request');
        if (!$this->isCaptchaEnabled->isCaptchaEnabledFor(self::CAPTCHA_KEY)
            || !$request instanceof RequestInterface
            || !$this->response instanceof HttpResponse
        ) {
            return;
        }
        // the registration controllers do not extend Action: use the shared response the front controller returns
        $this->requestHandler->execute(
            self::CAPTCHA_KEY,
            $request,
            $this->response,
            $this->url->getUrl('company/register', ['_secure' => true])
        );
    }
}
