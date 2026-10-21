# MageOS Company: REST API Guide

The complete specification is **[openapi.yaml](openapi.yaml)** (OpenAPI 3.0.3, validated with Redocly CLI). Open it with any OpenAPI tool, for example:

```bash
npx @redocly/cli preview-docs app/code/MageOS/Company/docs/openapi.yaml   # local HTML preview
npx @redocly/cli build-docs  app/code/MageOS/Company/docs/openapi.yaml   # static redoc-static.html
```

or import it into Postman, Insomnia or Swagger UI. Custom extensions in the specification:

| Extension | Meaning |
|---|---|
| `x-acl-resource` | Magento web API resource of the route (`etc/webapi.xml`): an admin ACL resource, `self` or `anonymous` |
| `x-company-resource` | Company permission checked by `CompanyAuthorizationInterface` for "mine" routes |
| `x-service` | Service contract method behind the route |
| `x-magento-route` | Original Magento route when the placeholder name differs from the documented path |

This guide summarises the protocol and shows complete examples.

---

## 1. Protocol

| Item | Value |
|---|---|
| Transport | HTTPS |
| Base URL | `https://{host}/rest/{store_code}/V1` (`/rest/V1` uses the default store) |
| Format | JSON (`Content-Type: application/json`); XML is also accepted by Magento |
| Authentication | `Authorization: Bearer <token>` |
| Body parameters | Named after the service method parameters (`company`, `address`, `reason`, `comment`, `customerId`, …) |
| Path parameters | `:companyId`, `:customerId`, `:addressId`, `:userId`, `:erpCode`, `:type` |
| Errors | `{ "message": "...", "parameters": ..., "errors": [...] }`, HTTP 400 / 401 / 404 / 500 |

The store code in the URL matters for customer and registration routes: the website of that store is the one used for registration and for checking that a company belongs to the current website.

## 2. Authentication

**Admin user** (for testing; integrations should use OAuth access tokens):

```bash
curl -X POST https://example.com/rest/V1/integration/admin/token \
  -H 'Content-Type: application/json' \
  -d '{"username":"admin","password":"secret"}'
```

**Integration**: `System > Extensions > Integrations`, grant the minimal resources, for example `MageOS_Company::company_view` and `MageOS_Company::company_erp` for an ERP connector, and use the access token.

**Company user (customer token)**:

```bash
curl -X POST https://example.com/rest/default/V1/integration/customer/token \
  -H 'Content-Type: application/json' \
  -d '{"username":"ana@acme.example","password":"S3cure-Passw0rd!"}'
```

The token is refused for users of companies that are pending, rejected or blocked, and for inactive users. It is revoked when the company is blocked or rejected or the user is deactivated.

## 3. Endpoints

### 3.1 Admin and integrations

| Method | Path | ACL resource | Description |
|---|---|---|---|
| GET | `/V1/companies` | `company_view` | Search (search criteria) |
| POST | `/V1/companies` | `company_edit` | Create (starts `pending`) |
| GET | `/V1/companies/:companyId` | `company_view` | Get |
| PUT | `/V1/companies/:companyId` | `company_edit` | Partial update |
| DELETE | `/V1/companies/:companyId` | `company_delete` | Delete |
| GET | `/V1/companies/erp/:erpCode` | `company_erp` | Get by ERP code |
| PUT | `/V1/companies/:companyId/erp` | `company_erp` | Set/clear ERP code |
| POST | `/V1/companies/:companyId/approve` | `company_approve` | pending → approved |
| POST | `/V1/companies/:companyId/reject` | `company_approve` | pending → rejected (reason) |
| POST | `/V1/companies/:companyId/block` | `company_approve` | approved → blocked (reason) |
| POST | `/V1/companies/:companyId/unblock` | `company_approve` | blocked → approved |
| POST | `/V1/companies/:companyId/resubmit` | `company_approve` | rejected → pending |
| GET | `/V1/companies/:companyId/history` | `company_view` | Status history |
| GET | `/V1/companies/:companyId/customers` | `company_view` | Users |
| POST | `/V1/companies/:companyId/customers` | `company_edit` | Assign a customer |
| PUT | `/V1/companies/:companyId/customers/:customerId/status` | `company_edit` | Activate/deactivate |
| DELETE | `/V1/companies/:companyId/customers/:customerId` | `company_edit` | Remove a user |
| PUT | `/V1/companies/:companyId/super-user/:customerId` | `company_edit` | Set super user |
| GET | `/V1/companies/:companyId/addresses` | `company_view` | Addresses |
| GET | `/V1/companies/:companyId/addresses/:addressId` | `company_view` | Address |
| POST | `/V1/companies/:companyId/addresses` | `company_edit` | Add address |
| PUT | `/V1/companies/:companyId/addresses/:id` | `company_edit` | Update address |
| DELETE | `/V1/companies/:companyId/addresses/:addressId` | `company_edit` | Delete address |
| GET | `/V1/companies/:companyId/messages` | `messages` | Thread (`?includeInternal=true`) |
| POST | `/V1/companies/:companyId/messages` | `messages` | Send message or internal note |

ACL resources are prefixed with `MageOS_Company::`.

### 3.2 Company users ("mine", customer token)

| Method | Path | Company permission | Description |
|---|---|---|---|
| GET | `/V1/companies/mine` | `company.view` | My company |
| PUT | `/V1/companies/mine` | `company.edit` | Update profile |
| GET | `/V1/companies/mine/addresses` | `company.address.view` | Addresses |
| GET | `/V1/companies/mine/addresses/:addressId` | `company.address.view` | Address |
| POST | `/V1/companies/mine/addresses` | `company.address.manage` | Add address |
| PUT | `/V1/companies/mine/addresses/:id` | `company.address.manage` | Update address |
| DELETE | `/V1/companies/mine/addresses/:addressId` | `company.address.manage` | Delete address |
| PUT | `/V1/companies/mine/addresses/:addressId/default/:type` | `company.address.manage` | Set `default_billing`, `default_shipping` or `legal_address` |
| GET | `/V1/companies/mine/customers` | `company.users.manage` | Users |
| POST | `/V1/companies/mine/customers` | `company.users.manage` | Invite |
| PUT | `/V1/companies/mine/customers/:userId` | `company.users.manage` | Role, job title, status |
| DELETE | `/V1/companies/mine/customers/:userId` | `company.users.manage` | Remove |
| GET | `/V1/companies/mine/messages` | `company.messages.view` | Thread (no internal notes) |
| POST | `/V1/companies/mine/messages` | `company.messages.send` | Send |
| POST | `/V1/companies/mine/messages/read` | `company.messages.view` | Mark store messages read |

### 3.3 Public

| Method | Path | Description |
|---|---|---|
| POST | `/V1/companies/register` | Apply for a company account (new customer + company + legal address) |

## 4. Examples

### Register a company (anonymous)

```bash
curl -X POST https://example.com/rest/default/V1/companies/register \
  -H 'Content-Type: application/json' \
  -d '{
    "company": {
      "name": "ACME",
      "email": "info@acme.example",
      "custom_attributes": [
        {"attribute_code": "legal_name", "value": "ACME Industries SL"},
        {"attribute_code": "vat_id", "value": "B12345678"},
        {"attribute_code": "phone", "value": "+34 600 000 000"}
      ]
    },
    "customer": {"firstname": "Ana", "lastname": "García", "email": "ana@acme.example"},
    "password": "S3cure-Passw0rd!",
    "legalAddress": {
      "firstname": "Ana", "lastname": "García", "street": ["Calle Mayor 1"],
      "city": "Madrid", "country_id": "ES", "region_id": 161, "postcode": "28001",
      "telephone": "+34 600 000 001"
    }
  }'
```

Response (`status` 0 = pending):

```json
{
  "id": 53,
  "name": "ACME",
  "status": 0,
  "email": "info@acme.example",
  "website_id": 1,
  "super_user_id": 56,
  "default_billing": 44,
  "default_shipping": 44,
  "legal_address": 44,
  "created_at": "2026-10-21 09:42:10",
  "updated_at": "2026-10-21 09:42:10",
  "custom_attributes": [
    {"attribute_code": "legal_name", "value": "ACME Industries SL"},
    {"attribute_code": "vat_id", "value": "B12345678"},
    {"attribute_code": "phone", "value": "+34 600 000 000"}
  ]
}
```

When reCAPTCHA is enabled for company registration, add `-H 'X-ReCaptcha: <token>'`.

### Find pending applications and approve one

```bash
curl -G https://example.com/rest/V1/companies \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  --data-urlencode 'searchCriteria[filterGroups][0][filters][0][field]=status' \
  --data-urlencode 'searchCriteria[filterGroups][0][filters][0][value]=0' \
  --data-urlencode 'searchCriteria[sortOrders][0][field]=created_at' \
  --data-urlencode 'searchCriteria[sortOrders][0][direction]=ASC'

curl -X POST https://example.com/rest/V1/companies/53/approve \
  -H "Authorization: Bearer $ADMIN_TOKEN" -H 'Content-Type: application/json' \
  -d '{"comment": "Welcome aboard"}'
```

An invalid transition answers 400:

```json
{
  "message": "This action needs the status \"%1\", but the company is \"%2\".",
  "parameters": ["Pending Approval", "Approved"]
}
```

### Link the company with the ERP

```bash
curl -X PUT https://example.com/rest/V1/companies/53/erp \
  -H "Authorization: Bearer $INTEGRATION_TOKEN" -H 'Content-Type: application/json' \
  -d '{"erpCode": "C-10045"}'

curl https://example.com/rest/V1/companies/erp/C-10045 -H "Authorization: Bearer $INTEGRATION_TOKEN"
```

### Company user: add an address and make it the default shipping address

```bash
curl -X POST https://example.com/rest/default/V1/companies/mine/addresses \
  -H "Authorization: Bearer $CUSTOMER_TOKEN" -H 'Content-Type: application/json' \
  -d '{"address": {"firstname": "Ana", "lastname": "García", "street": ["Gran Vía 2"],
       "city": "Madrid", "country_id": "ES", "region_id": 161, "postcode": "28013",
       "telephone": "+34 600 000 002",
       "custom_attributes": [{"attribute_code": "label", "value": "Warehouse 2"}]}}'

curl -X PUT https://example.com/rest/default/V1/companies/mine/addresses/45/default/default_shipping \
  -H "Authorization: Bearer $CUSTOMER_TOKEN"
```

A `user` (non-admin) trying the same receives 401:

```json
{"message": "You are not allowed to do that."}
```

### Company user: invite a colleague and send a message

```bash
curl -X POST https://example.com/rest/default/V1/companies/mine/customers \
  -H "Authorization: Bearer $CUSTOMER_TOKEN" -H 'Content-Type: application/json' \
  -d '{"email": "jane@acme.example", "role": "user", "jobTitle": "Buyer"}'
# → true

curl -X POST https://example.com/rest/default/V1/companies/mine/messages \
  -H "Authorization: Bearer $CUSTOMER_TOKEN" -H 'Content-Type: application/json' \
  -d '{"body": "Could you send us the updated price list?"}'
```

```json
{
  "message_id": 81,
  "company_entity_id": 53,
  "author_type": "customer",
  "author_id": 56,
  "body": "Could you send us the updated price list?",
  "is_internal": false,
  "read_at_customer": "2026-10-21 12:47:08",
  "created_at": "2026-10-21 12:47:08"
}
```

## 5. Behaviour notes

- **Partial updates.** `PUT` on companies merges onto the stored data: omitted or `null` fields are not changed, `""` clears a value.
- **Protected fields.** `status` (status routes), `company_id` (ERP route), `website_id` (creation only) are ignored by `PUT /V1/companies/:companyId`. Customers can never set them.
- **Custom attributes.** Any attribute created in `Stores > Attributes > Company` is accepted and returned in `custom_attributes`, subject to the form it is assigned to.
- **Not found vs. forbidden.** An address or user of another company answers 404 exactly like a missing one.
- **Side effects.** Status routes record history, add a system message, send emails and, for reject/block, revoke the company users' sessions and tokens. Store messages are emailed asynchronously by the `company.message.notify` consumer.
