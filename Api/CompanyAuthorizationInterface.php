<?php

declare(strict_types=1);

namespace MageOS\Company\Api;

/**
 * What a company user may do on its own company.
 *
 * Storefront controllers, "mine" APIs and GraphQL resolvers check these resources instead of the role, so a
 * permissions module can replace the default role-based implementation with a preference.
 *
 * @api
 */
interface CompanyAuthorizationInterface
{
    public const RESOURCE_VIEW = 'company.view';
    public const RESOURCE_EDIT = 'company.edit';
    public const RESOURCE_USERS_MANAGE = 'company.users.manage';
    public const RESOURCE_ADDRESS_VIEW = 'company.address.view';
    public const RESOURCE_ADDRESS_MANAGE = 'company.address.manage';
    public const RESOURCE_MESSAGES_VIEW = 'company.messages.view';
    public const RESOURCE_MESSAGES_SEND = 'company.messages.send';

    /**
     * Whether a customer may use a resource on its company. False when it has no active, approved company.
     *
     * @param int $customerId
     * @param string $resource
     * @return bool
     */
    public function isAllowed(int $customerId, string $resource): bool;
}
