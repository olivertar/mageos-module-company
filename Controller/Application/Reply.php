<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Application;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Api\MessageManagementInterface;
use MageOS\Company\Model\Registration\StatusLink;

/**
 * The applicant replies from the application status page.
 */
class Reply extends AbstractApplicationAction
{
    public const DRAFT_KEY = 'mageos_company_application_draft';

    /**
     * @param RequestInterface $request
     * @param RedirectFactory $redirectFactory
     * @param ManagerInterface $messageManager
     * @param StatusLink $statusLink
     * @param StoreManagerInterface $storeManager
     * @param MessageManagementInterface $messageManagement
     * @param DataPersistorInterface $dataPersistor
     */
    public function __construct(
        RequestInterface $request,
        RedirectFactory $redirectFactory,
        ManagerInterface $messageManager,
        StatusLink $statusLink,
        StoreManagerInterface $storeManager,
        private readonly MessageManagementInterface $messageManagement,
        private readonly DataPersistorInterface $dataPersistor
    ) {
        parent::__construct($request, $redirectFactory, $messageManager, $statusLink, $storeManager);
    }

    /**
     * @inheritDoc
     */
    protected function executeFor(CompanyInterface $company): void
    {
        $body = (string)$this->request->getParam('body');
        try {
            $this->messageManagement->sendAsApplicant((int)$company->getId(), $body);
        } catch (LocalizedException $e) {
            $this->dataPersistor->set(self::DRAFT_KEY, $body);
            throw $e;
        }
        $this->messageManager->addSuccessMessage(__('Your message has been sent.'));
    }
}
