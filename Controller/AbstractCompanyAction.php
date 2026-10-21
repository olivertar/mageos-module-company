<?php

declare(strict_types=1);

namespace MageOS\Company\Controller;

use Magento\Customer\Controller\AccountInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\Page;
use MageOS\Company\Api\CompanyAuthorizationInterface;
use MageOS\Company\Api\Data\CompanyInterface;

/**
 * Storefront company area action: login is enforced by AccountInterface, the company comes from the customer
 * (never from the input) and the RESOURCE is checked with CompanyAuthorizationInterface.
 */
abstract class AbstractCompanyAction implements ActionInterface, AccountInterface
{
    /**
     * Authorization resource needed by the action.
     */
    protected const RESOURCE = CompanyAuthorizationInterface::RESOURCE_VIEW;

    /**
     * @param CompanyActionContext $context
     */
    public function __construct(
        protected readonly CompanyActionContext $context
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $customerId = (int)$this->context->customerSession->getCustomerId();
        try {
            $company = $this->context->companyContext->getCompany($customerId);
        } catch (LocalizedException $e) {
            $this->context->messageManager->addNoticeMessage($e->getMessage());

            return $this->redirect('customer/account');
        }
        if (!$this->context->authorization->isAllowed($customerId, static::RESOURCE)) {
            $this->context->messageManager->addErrorMessage(__('You are not allowed to do that.'));

            return $this->redirect('company/account');
        }

        return $this->executeFor($company, $customerId);
    }

    /**
     * Run the action for the company of the customer.
     *
     * @param CompanyInterface $company
     * @param int $customerId
     * @return ResultInterface
     */
    abstract protected function executeFor(CompanyInterface $company, int $customerId): ResultInterface;

    /**
     * Redirect result.
     *
     * @param string $path
     * @param array<string,mixed> $params
     * @return Redirect
     */
    protected function redirect(string $path, array $params = []): Redirect
    {
        return $this->context->redirectFactory->create()->setPath($path, $params);
    }

    /**
     * Page result with a title.
     *
     * @param string $title
     * @return Page
     */
    protected function page(string $title): Page
    {
        $page = $this->context->pageFactory->create();
        $page->getConfig()->getTitle()->set($title);

        return $page;
    }

    /**
     * Run a write operation, reporting its outcome as session messages.
     *
     * @param callable $operation
     * @param string $successMessage
     * @return bool Whether it succeeded.
     */
    protected function runOperation(callable $operation, string $successMessage): bool
    {
        try {
            $operation();
            $this->context->messageManager->addSuccessMessage($successMessage);

            return true;
        } catch (NoSuchEntityException) {
            // same answer for missing and foreign records: do not reveal other companies' data
            $this->context->messageManager->addErrorMessage(__('The requested item was not found in your company.'));
        } catch (LocalizedException $e) {
            $this->context->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->context->logger->critical($e);
            $this->context->messageManager->addErrorMessage(__('Something went wrong. Please try again later.'));
        }

        return false;
    }
}
