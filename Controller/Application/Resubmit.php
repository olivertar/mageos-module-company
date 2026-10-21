<?php

declare(strict_types=1);

namespace MageOS\Company\Controller\Application;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Company\Api\CompanyApprovalInterface;
use MageOS\Company\Api\Data\CompanyInterface;
use MageOS\Company\Api\Data\StatusHistoryInterface;
use MageOS\Company\Model\Registration\StatusLink;
use MageOS\Company\Model\Status\ActorResolver;

/**
 * The applicant resubmits a rejected application from the status page.
 */
class Resubmit extends AbstractApplicationAction
{
    private const MAX_COMMENT_LENGTH = 2000;

    /**
     * @param RequestInterface $request
     * @param RedirectFactory $redirectFactory
     * @param ManagerInterface $messageManager
     * @param StatusLink $statusLink
     * @param StoreManagerInterface $storeManager
     * @param CompanyApprovalInterface $companyApproval
     * @param ActorResolver $actorResolver
     */
    public function __construct(
        RequestInterface $request,
        RedirectFactory $redirectFactory,
        ManagerInterface $messageManager,
        StatusLink $statusLink,
        StoreManagerInterface $storeManager,
        private readonly CompanyApprovalInterface $companyApproval,
        private readonly ActorResolver $actorResolver
    ) {
        parent::__construct($request, $redirectFactory, $messageManager, $statusLink, $storeManager);
    }

    /**
     * @inheritDoc
     */
    protected function executeFor(CompanyInterface $company): void
    {
        if ($company->getStatus() !== CompanyInterface::STATUS_REJECTED) {
            throw new LocalizedException(__('Only rejected applications can be resubmitted.'));
        }
        $comment = trim((string)$this->request->getParam('comment'));
        if (mb_strlen($comment) > self::MAX_COMMENT_LENGTH) {
            throw new LocalizedException(
                __('The comment cannot be longer than %1 characters.', self::MAX_COMMENT_LENGTH)
            );
        }
        // the link authenticates the applicant: record the change as made by the super user
        $this->actorResolver->actAs(
            StatusHistoryInterface::ACTOR_CUSTOMER,
            $company->getSuperUserId(),
            fn () => $this->companyApproval->resubmit((int)$company->getId(), $comment === '' ? null : $comment)
        );
        $this->messageManager->addSuccessMessage(
            __('Your application has been resubmitted. We will review it and let you know by email.')
        );
    }
}
