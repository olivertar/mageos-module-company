<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Application;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Model\Registration\StatusLink;

/**
 * POST action of the application status page, authenticated by the signed link token.
 */
abstract class AbstractApplicationAction implements HttpPostActionInterface
{
    /**
     * @param RequestInterface $request
     * @param RedirectFactory $redirectFactory
     * @param ManagerInterface $messageManager
     * @param StatusLink $statusLink
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        protected readonly RequestInterface $request,
        protected readonly RedirectFactory $redirectFactory,
        protected readonly ManagerInterface $messageManager,
        private readonly StatusLink $statusLink,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $token = (string)$this->request->getParam('token');
        try {
            $company = $this->statusLink->resolve($token, (int)$this->storeManager->getWebsite()->getId());
            if (!in_array($company->getStatus(), StatusLink::STATUSES, true)) {
                throw new LocalizedException(__('This link is no longer available.'));
            }
            $this->executeFor($company);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->redirectToStatus($token);
    }

    /**
     * Run the action for the company of the link.
     *
     * @param CompanyInterface $company
     * @return void
     * @throws LocalizedException
     */
    abstract protected function executeFor(CompanyInterface $company): void;

    /**
     * Back to the status page.
     *
     * @param string $token
     * @return Redirect
     */
    private function redirectToStatus(string $token): Redirect
    {
        return $this->redirectFactory->create()->setPath(
            StatusLink::ROUTE,
            ['_query' => ['token' => $token], '_secure' => true]
        );
    }
}
