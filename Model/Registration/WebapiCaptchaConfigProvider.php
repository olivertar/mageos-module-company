<?php

declare(strict_types=1);

namespace MageOS\Company\Model\Registration;

use Magento\ReCaptchaUi\Model\IsCaptchaEnabledInterface;
use Magento\ReCaptchaUi\Model\ValidationConfigResolverInterface;
use Magento\ReCaptchaValidationApi\Api\Data\ValidationConfigInterface;
use Magento\ReCaptchaWebapiApi\Api\Data\EndpointInterface;
use Magento\ReCaptchaWebapiApi\Api\WebapiValidationConfigProviderInterface;
use MageOS\Company\Api\CompanyRegistrationInterface;
use MageOS\Company\Observer\ValidateRegistrationCaptcha;

/**
 * reCAPTCHA of the company application web APIs (REST and GraphQL), with the same setting as the storefront form.
 */
class WebapiCaptchaConfigProvider implements WebapiValidationConfigProviderInterface
{
    /**
     * @param IsCaptchaEnabledInterface $isCaptchaEnabled
     * @param ValidationConfigResolverInterface $configResolver
     */
    public function __construct(
        private readonly IsCaptchaEnabledInterface $isCaptchaEnabled,
        private readonly ValidationConfigResolverInterface $configResolver
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getConfigFor(EndpointInterface $endpoint): ?ValidationConfigInterface
    {
        $isRegistration = ($endpoint->getServiceClass() === CompanyRegistrationInterface::class
                && $endpoint->getServiceMethod() === 'apply')
            // GraphQL endpoints are identified by the resolver class (MageOS_CompanyGraphQl is optional)
            //phpcs:ignore Magento2.PHP.LiteralNamespaces
            || $endpoint->getServiceClass() === 'MageOS\\CompanyGraphQl\\Model\\Resolver\\CreateCompanyApplication';
        if (!$isRegistration
            || !$this->isCaptchaEnabled->isCaptchaEnabledFor(ValidateRegistrationCaptcha::CAPTCHA_KEY)
        ) {
            return null;
        }

        return $this->configResolver->get(ValidateRegistrationCaptcha::CAPTCHA_KEY);
    }
}
