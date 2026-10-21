# MageOS Company: GraphQL API Reference

GraphQL schema of `MageOS_CompanyGraphQl`, for headless storefronts. The authoritative definition is the SDL file [`app/code/MageOS/CompanyGraphQl/etc/schema.graphqls`](../../CompanyGraphQl/etc/schema.graphqls); it is merged into the store schema, so standard introspection and tools (GraphiQL, Altair, Apollo, `graphql-codegen`) see it too.

Admin and ERP operations are **not** part of GraphQL: use the [REST API](openapi.yaml).

---

## Contents

1. [Protocol](#1-protocol)
2. [Authentication and authorization](#2-authentication-and-authorization)
3. [Errors](#3-errors)
4. [Queries](#4-queries)
5. [Mutations](#5-mutations)
6. [Types](#6-types)
7. [Inputs](#7-inputs)
8. [Enums](#8-enums)
9. [Recipes](#9-recipes)

---

## 1. Protocol

| Item | Value |
|---|---|
| Endpoint | `POST https://{host}/graphql` (`GET` for queries is also accepted by Magento) |
| Body | `{"query": "...", "variables": {...}, "operationName": "..."}` |
| Headers | `Content-Type: application/json`; `Authorization: Bearer <customer token>` for customer operations; `Store: <store_code>` to select the store view (defaults to the default store); `X-ReCaptcha: <token>` for `createCompanyApplication` when reCAPTCHA is enabled |
| IDs | Opaque `uid` strings (base64 of the numeric ID, `Magento\Framework\GraphQl\Query\Uid`). Pass them back unchanged |
| Limits | Core query depth and complexity limits (`graphql/validation`) |
| Caching | Company queries are private to the customer and not cached by FPC. `companyAttributesMetadata` is public |

The `Store` header matters: registration happens in the website of that store, and a company only answers on its own website.

## 2. Authentication and authorization

Obtain a customer token with the core mutation:

```graphql
mutation {
  generateCustomerToken(email: "ana@acme.example", password: "S3cure-Passw0rd!") { token }
}
```

It fails for users of companies that are pending, rejected or blocked, and for inactive company users. Tokens are revoked when the company is blocked or rejected, or the user is deactivated.

| Operation | Token | Company permission |
|---|---|---|
| `companyAttributesMetadata` | none | |
| `createCompanyApplication` | none | |
| `company`, `Customer.company` | customer | `company.view` (returns `null` otherwise) |
| `Company.addresses`, `legal_address`, `default_*_address` | customer | `company.address.view` |
| `Company.users` | customer | `company.users.manage` |
| `Company.unread_messages`, `companyMessages` | customer | `company.messages.view` |
| `updateCompany` | customer | `company.edit` |
| `createCompanyAddress`, `updateCompanyAddress`, `deleteCompanyAddress` | customer | `company.address.manage` |
| `inviteCompanyUser`, `updateCompanyUser`, `removeCompanyUser` | customer | `company.users.manage` |
| `sendCompanyMessage` | customer | `company.messages.send` |
| `markCompanyMessagesRead` | customer | `company.messages.view` |

Role defaults: `ADMIN` has every permission; `USER` has `company.view`, `company.address.view`, `company.messages.view` and `company.messages.send`. `Company.allowed_resources` returns the exact list for the logged-in user, so a frontend can show or hide actions.

The company is always the one of the customer in the token. IDs of addresses and users from another company behave exactly like non-existent IDs.

## 3. Errors

Errors follow the GraphQL specification, with Magento's `extensions.category`:

| Category | When |
|---|---|
| `graphql-authorization` | No customer token, or missing company permission / no active company |
| `graphql-no-such-entity` | Address or user not found (or not in the customer's company) |
| `graphql-input` | Validation errors (all messages joined), invalid `uid`, invalid paging, business rule violations |

```json
{
  "errors": [
    {
      "message": "The current customer isn't authorized.",
      "locations": [{"line": 1, "column": 3}],
      "path": ["company"],
      "extensions": {"category": "graphql-authorization"}
    }
  ],
  "data": {"company": null}
}
```

Field-level permissions fail only their field: a `USER` querying `company { name users { email } }` receives `name`, `users: null` and an error with `path: ["company", "users"]`.

---

## 4. Queries

### 4.1 `company`

```graphql
company: Company
```

The company of the logged-in customer. Requires a customer token (`graphql-authorization` without it). Returns `null` when the customer has no company, the company is not approved, the user is inactive, or the user lacks `company.view`.

```graphql
query MyCompany {
  company {
    uid
    name
    email
    status
    erp_code
    custom_attributes { attribute_code value label }
    legal_address { uid city country_code }
    default_shipping_address { uid label street city }
    super_user { firstname lastname email }
    current_user { uid role job_title is_super_user }
    allowed_resources
    unread_messages
    addresses { uid label firstname lastname street city postcode country_code region { region_code region } telephone default_billing default_shipping legal_address }
    users { uid firstname lastname email role job_title status is_super_user }
  }
}
```

```json
{
  "data": {
    "company": {
      "uid": "NTM=",
      "name": "ACME",
      "email": "info@acme.example",
      "status": "APPROVED",
      "erp_code": "C-10045",
      "custom_attributes": [
        {"attribute_code": "legal_name", "value": "ACME Industries SL", "label": "ACME Industries SL"},
        {"attribute_code": "vat_id", "value": "B12345678", "label": "B12345678"},
        {"attribute_code": "industry", "value": "214", "label": "Manufacturing"}
      ],
      "legal_address": {"uid": "NDQ=", "city": "Madrid", "country_code": "ES"},
      "default_shipping_address": {"uid": "NDQ=", "label": "Headquarters", "street": ["Calle Mayor 1"], "city": "Madrid"},
      "super_user": {"firstname": "Ana", "lastname": "García", "email": "ana@acme.example"},
      "current_user": {"uid": "NTY=", "role": "ADMIN", "job_title": null, "is_super_user": true},
      "allowed_resources": ["company.view", "company.edit", "company.users.manage", "company.address.view", "company.address.manage", "company.messages.view", "company.messages.send"],
      "unread_messages": 1,
      "addresses": [
        {"uid": "NDQ=", "label": "Headquarters", "firstname": "Ana", "lastname": "García", "street": ["Calle Mayor 1"], "city": "Madrid", "postcode": "28001", "country_code": "ES", "region": {"region_code": "M", "region": "Madrid"}, "telephone": "+34 600 000 001", "default_billing": true, "default_shipping": true, "legal_address": true}
      ],
      "users": [
        {"uid": "NTY=", "firstname": "Ana", "lastname": "García", "email": "ana@acme.example", "role": "ADMIN", "job_title": null, "status": "ACTIVE", "is_super_user": true}
      ]
    }
  }
}
```

### 4.2 `Customer.company`

```graphql
type Customer { company: Company }
```

Same as `company`, reachable from the core `customer` query:

```graphql
query { customer { firstname email company { name status current_user { role } } } }
```

### 4.3 `companyMessages`

```graphql
companyMessages(pageSize: Int = 20, currentPage: Int = 1): CompanyMessages
```

The company thread, **newest first**, without internal notes. `pageSize` must be 1–100 and `currentPage` ≥ 1 (`graphql-input` otherwise). Reading does not mark messages as read: call `markCompanyMessagesRead`.

| Argument | Type | Default | Description |
|---|---|---|---|
| `pageSize` | `Int` | 20 | Maximum messages to return (1–100) |
| `currentPage` | `Int` | 1 | Page, starting at 1 |

```graphql
query Thread($page: Int) {
  companyMessages(pageSize: 10, currentPage: $page) {
    total_count
    unread_count
    page_info { current_page page_size total_pages }
    items { uid author author_name body is_read created_at }
  }
}
```

```json
{
  "data": {
    "companyMessages": {
      "total_count": 3,
      "unread_count": 1,
      "page_info": {"current_page": 1, "page_size": 10, "total_pages": 1},
      "items": [
        {"uid": "ODI=", "author": "STORE", "author_name": null, "body": "Your price list is attached to your account.", "is_read": false, "created_at": "2026-10-21 13:02:11"},
        {"uid": "ODE=", "author": "COMPANY", "author_name": "Ana García", "body": "Could you send us the updated price list?", "is_read": true, "created_at": "2026-10-21 12:47:08"},
        {"uid": "ODA=", "author": "SYSTEM", "author_name": null, "body": "The company status changed to \"Approved\".\nWelcome aboard", "is_read": true, "created_at": "2026-10-21 12:36:56"}
      ]
    }
  }
}
```

### 4.4 `companyAttributesMetadata`

```graphql
companyAttributesMetadata(formCode: CompanyFormCodeEnum!): [CompanyAttributeMetadata!]!
```

Public. The attributes of a storefront form, in sort order, with labels of the current store view, validation rules and options, so a headless frontend can build the registration, profile and address forms dynamically. Custom attributes created in the admin are included automatically.

```graphql
query RegisterForm {
  company: companyAttributesMetadata(formCode: COMPANY_REGISTER) {
    attribute_code label frontend_input is_required sort_order multiline_count
    validate_rules { name value }
    options { value label }
  }
  address: companyAttributesMetadata(formCode: COMPANY_ADDRESS_REGISTER) {
    attribute_code label frontend_input is_required multiline_count
  }
}
```

```json
{
  "data": {
    "company": [
      {"attribute_code": "name", "label": "Company Name", "frontend_input": "text", "is_required": true, "sort_order": 10, "multiline_count": 0,
       "validate_rules": [{"name": "max_text_length", "value": "255"}, {"name": "min_text_length", "value": "1"}], "options": []},
      {"attribute_code": "email", "label": "Company Email", "frontend_input": "text", "is_required": true, "sort_order": 20, "multiline_count": 0,
       "validate_rules": [{"name": "input_validation", "value": "email"}], "options": []},
      {"attribute_code": "employees_range", "label": "Number of Employees", "frontend_input": "select", "is_required": false, "sort_order": 160, "multiline_count": 0,
       "validate_rules": [], "options": [{"value": "227", "label": "1-10"}, {"value": "228", "label": "11-50"}, {"value": "229", "label": "51-200"}, {"value": "230", "label": "201-500"}, {"value": "231", "label": "500+"}]}
    ],
    "address": [
      {"attribute_code": "street", "label": "Street Address", "frontend_input": "multiline", "is_required": true, "multiline_count": 2}
    ]
  }
}
```

`frontend_input` values: `text`, `textarea`, `date`, `boolean` (options `1` Yes / `0` No), `select`, `multiselect`, `multiline`, `hidden`. `name`/`email` are returned as fields of `CompanyProfileInput`; every other company attribute goes into `custom_attributes`. For addresses, `country_id` maps to `country_code`, `region`/`region_id` to `region`, and `label` is a field of `CompanyAddressInput`.

---

## 5. Mutations

### 5.1 `createCompanyApplication`

```graphql
createCompanyApplication(input: CompanyApplicationInput!): CompanyApplicationOutput
```

Public. Creates a new customer account, the company and its legal address in one transaction (in the website of the `Store` header). The company is `PENDING` until the store reviews it, or `APPROVED` with auto-approval. The applicant receives the "application received" email with a personal status link; while pending, `generateCustomerToken` fails for this customer.

- Only the attributes of the `COMPANY_REGISTER` and `COMPANY_ADDRESS_REGISTER` forms are accepted.
- An existing customer email gives a generic error; a duplicated company email is reported.
- `X-ReCaptcha` header required when reCAPTCHA is enabled for company registration.

```graphql
mutation Apply($input: CompanyApplicationInput!) {
  createCompanyApplication(input: $input) { status }
}
```

```json
{
  "input": {
    "company": {
      "name": "ACME",
      "email": "info@acme.example",
      "custom_attributes": [
        {"attribute_code": "legal_name", "value": "ACME Industries SL"},
        {"attribute_code": "vat_id", "value": "B12345678"},
        {"attribute_code": "phone", "value": "+34 600 000 000"},
        {"attribute_code": "employees_range", "value": "228"}
      ]
    },
    "legal_address": {
      "firstname": "Ana", "lastname": "García", "street": ["Calle Mayor 1"], "city": "Madrid",
      "country_code": "ES", "region": {"region_id": 161}, "postcode": "28001", "telephone": "+34 600 000 001"
    },
    "customer": {"firstname": "Ana", "lastname": "García", "email": "ana@acme.example", "password": "S3cure-Passw0rd!"}
  }
}
```

```json
{"data": {"createCompanyApplication": {"status": "PENDING"}}}
```

Validation error example:

```json
{
  "errors": [{"message": "\"Legal Name\" is a required value.", "extensions": {"category": "graphql-input"}, "path": ["createCompanyApplication"]}],
  "data": {"createCompanyApplication": null}
}
```

### 5.2 `updateCompany`

```graphql
updateCompany(input: CompanyUpdateInput!): Company
```

Updates the profile with the attributes of the `COMPANY_ACCOUNT_EDIT` form. Omitted fields are not changed; an empty string clears a value. Status, ERP code, website and super user cannot be changed.

```graphql
mutation {
  updateCompany(input: {
    name: "ACME Industries"
    custom_attributes: [
      {attribute_code: "website_url", value: "https://acme.example"}
      {attribute_code: "fax", value: ""}
    ]
  }) { name custom_attributes { attribute_code value } }
}
```

### 5.3 `createCompanyAddress`, `updateCompanyAddress`, `deleteCompanyAddress`

```graphql
createCompanyAddress(input: CompanyAddressInput!): CompanyAddress
updateCompanyAddress(uid: ID!, input: CompanyAddressInput!): CompanyAddress
deleteCompanyAddress(uid: ID!): Boolean
```

Attributes of the `COMPANY_ADDRESS_EDIT` form. `default_billing`, `default_shipping` and `legal_address` set the company defaults (`true` only; to move a default, set it on another address). Deleting a default address clears that default.

```graphql
mutation {
  createCompanyAddress(input: {
    label: "Warehouse 2"
    firstname: "Ana", lastname: "García"
    street: ["Polígono Sur, Nave 7"], city: "Getafe"
    country_code: ES, region: {region_id: 161}, postcode: "28906"
    telephone: "+34 600 000 003"
    default_shipping: true
  }) { uid label default_shipping }
}
```

```json
{"data": {"createCompanyAddress": {"uid": "NDU=", "label": "Warehouse 2", "default_shipping": true}}}
```

```graphql
mutation { deleteCompanyAddress(uid: "NDU=") }
```

An address of another company, or an invalid uid:

```json
{"errors": [{"message": "No such entity with addressId = 999, companyId = 53", "extensions": {"category": "graphql-no-such-entity"}}], "data": {"deleteCompanyAddress": null}}
```

### 5.4 `inviteCompanyUser`

```graphql
inviteCompanyUser(input: CompanyUserInviteInput!): Boolean
```

Emails an invitation link; the person joins when accepting it on the storefront (`/company/invitation/accept`). Inviting the same email again replaces the open invitation. Users of the own company cannot be invited; users of another company are refused with a generic message.

```graphql
mutation { inviteCompanyUser(input: {email: "jane@acme.example", role: USER, job_title: "Buyer"}) }
```

### 5.5 `updateCompanyUser`

```graphql
updateCompanyUser(uid: ID!, input: CompanyUserUpdateInput!): CompanyUser
```

Changes role, job title and status of a company user (`uid` from `Company.users`). Setting `INACTIVE` ends the user's sessions and tokens. The super user and the last active admin cannot be demoted or deactivated (`graphql-input`).

```graphql
mutation {
  updateCompanyUser(uid: "NTc=", input: {role: ADMIN, job_title: "Purchasing Manager", status: ACTIVE}) {
    uid role job_title status
  }
}
```

### 5.6 `removeCompanyUser`

```graphql
removeCompanyUser(uid: ID!): Boolean
```

Removes the user from the company; the customer account is kept. The super user and the last active admin cannot be removed.

### 5.7 `sendCompanyMessage`

```graphql
sendCompanyMessage(body: String!): CompanyMessage
```

Plain text, subject to the store's maximum length (default 2000) and hourly rate limit per customer (default 20). The store is notified by email and admin inbox.

```graphql
mutation { sendCompanyMessage(body: "Could you send us the updated price list?") { uid author author_name created_at } }
```

### 5.8 `markCompanyMessagesRead`

```graphql
markCompanyMessagesRead: Int
```

Marks the store and system messages as read for the company. Returns how many were marked.

```graphql
mutation { markCompanyMessagesRead }
```

```json
{"data": {"markCompanyMessagesRead": 1}}
```

---

## 6. Types

### `Company`

| Field | Type | Description |
|---|---|---|
| `uid` | `ID!` | Company ID |
| `name` | `String` | |
| `email` | `String` | Company email |
| `status` | `CompanyStatusEnum` | |
| `erp_code` | `String` | Code in the store's ERP, when linked |
| `custom_attributes` | `[CompanyAttributeValue!]!` | EAV profile values (legal name, VAT number, phone, custom attributes) |
| `legal_address` | `CompanyAddress` | Needs `company.address.view` (else `null`) |
| `default_billing_address` | `CompanyAddress` | Needs `company.address.view` (else `null`) |
| `default_shipping_address` | `CompanyAddress` | Needs `company.address.view` (else `null`) |
| `super_user` | `CompanyUser` | Main contact |
| `current_user` | `CompanyUser` | The logged-in customer as a company user |
| `allowed_resources` | `[String!]!` | Company permissions of the logged-in user |
| `addresses` | `[CompanyAddress!]` | Needs `company.address.view` (error otherwise) |
| `users` | `[CompanyUser!]` | Needs `company.users.manage` (error otherwise) |
| `unread_messages` | `Int` | Unread store messages; `null` without `company.messages.view` |

### `CompanyAttributeValue`

| Field | Type | Description |
|---|---|---|
| `attribute_code` | `String!` | |
| `value` | `String` | Stored value: option IDs for selects, comma-separated for multiselects |
| `label` | `String` | The value as text (option labels for selects) |

### `CompanyAddress`

| Field | Type | Description |
|---|---|---|
| `uid` | `ID!` | |
| `label` | `String` | "Headquarters", "Warehouse 2"… |
| `firstname`, `lastname`, `company` | `String` | |
| `street` | `[String]` | Up to 2 lines |
| `city` | `String` | |
| `region` | `CustomerAddressRegion` | `region_id`, `region`, `region_code` (core type) |
| `country_code` | `CountryCodeEnum` | Core enum |
| `postcode`, `telephone`, `vat_id` | `String` | |
| `default_billing`, `default_shipping`, `legal_address` | `Boolean` | Whether it is the company default |

### `CompanyUser`

| Field | Type | Description |
|---|---|---|
| `uid` | `ID!` | Customer ID |
| `firstname`, `lastname`, `email` | `String` | |
| `role` | `CompanyRoleEnum` | |
| `job_title` | `String` | |
| `status` | `CompanyUserStatusEnum` | |
| `is_super_user` | `Boolean` | Main contact |

### `CompanyMessage`

| Field | Type | Description |
|---|---|---|
| `uid` | `ID!` | |
| `author` | `CompanyMessageAuthorEnum` | |
| `author_name` | `String` | Name of the company user, for `COMPANY` messages ("Former user" if the account was deleted) |
| `body` | `String` | Plain text (escape when rendering) |
| `is_read` | `Boolean` | Whether the company read a store or system message (always `true` for company messages) |
| `created_at` | `String` | `YYYY-MM-DD HH:MM:SS` (UTC) |

### `CompanyMessages`

| Field | Type |
|---|---|
| `items` | `[CompanyMessage!]!` |
| `total_count` | `Int!` |
| `unread_count` | `Int!` |
| `page_info` | `SearchResultPageInfo` (`current_page`, `page_size`, `total_pages`) |

### `CompanyAttributeMetadata`

| Field | Type | Description |
|---|---|---|
| `attribute_code` | `String!` | |
| `label` | `String` | Store view label |
| `frontend_input` | `String` | See 4.4 |
| `is_required` | `Boolean` | |
| `sort_order` | `Int` | |
| `multiline_count` | `Int` | Lines for `multiline` (street) |
| `validate_rules` | `[CompanyAttributeValidateRule!]!` | `name`/`value` pairs: `min_text_length`, `max_text_length`, `input_validation` (`email`, `url`, `alpha`, `numeric`, `alphanumeric`, `date`…) |
| `options` | `[CompanyAttributeOption!]!` | `value`/`label` pairs for selects and booleans |

### `CompanyApplicationOutput`

| Field | Type | Description |
|---|---|---|
| `status` | `CompanyStatusEnum` | `PENDING`, or `APPROVED` with auto-approval |

---

## 7. Inputs

### `CompanyApplicationInput`

| Field | Type |
|---|---|
| `company` | `CompanyProfileInput!` |
| `legal_address` | `CompanyAddressInput!` |
| `customer` | `CompanyApplicantInput!` |

### `CompanyApplicantInput`

| Field | Type |
|---|---|
| `firstname` | `String!` |
| `lastname` | `String!` |
| `email` | `String!` |
| `password` | `String!` (store password rules apply) |

### `CompanyProfileInput` / `CompanyUpdateInput`

| Field | Type | Description |
|---|---|---|
| `name` | `String` | |
| `email` | `String` | |
| `custom_attributes` | `[CompanyAttributeValueInput!]` | Other attributes of the form |

### `CompanyAttributeValueInput`

| Field | Type | Description |
|---|---|---|
| `attribute_code` | `String!` | |
| `value` | `String` | Option IDs for selects (comma-separated for multiselect). `""` clears it |

### `CompanyAddressInput`

| Field | Type | Description |
|---|---|---|
| `label`, `firstname`, `lastname`, `company`, `city`, `postcode`, `telephone`, `vat_id` | `String` | |
| `street` | `[String]` | |
| `region` | `CustomerAddressRegionInput` | `region_id`, or `region` text for countries without regions |
| `country_code` | `CountryCodeEnum` | |
| `default_billing` | `Boolean` | Make it the default billing address |
| `default_shipping` | `Boolean` | Make it the default shipping address |
| `legal_address` | `Boolean` | Make it the legal address |

### `CompanyUserInviteInput`

| Field | Type | Default |
|---|---|---|
| `email` | `String!` | |
| `role` | `CompanyRoleEnum` | `USER` |
| `job_title` | `String` | |

### `CompanyUserUpdateInput`

| Field | Type | Description |
|---|---|---|
| `role` | `CompanyRoleEnum!` | |
| `job_title` | `String` | |
| `status` | `CompanyUserStatusEnum` | Omit to keep the current status |

---

## 8. Enums

| Enum | Values |
|---|---|
| `CompanyStatusEnum` | `PENDING`, `APPROVED`, `REJECTED`, `BLOCKED` |
| `CompanyRoleEnum` | `ADMIN`, `USER` |
| `CompanyUserStatusEnum` | `ACTIVE`, `INACTIVE` |
| `CompanyFormCodeEnum` | `COMPANY_REGISTER`, `COMPANY_ACCOUNT_EDIT`, `COMPANY_ADDRESS_REGISTER`, `COMPANY_ADDRESS_EDIT` |
| `CompanyMessageAuthorEnum` | `STORE` (store team or integration), `COMPANY` (a company user), `SYSTEM` (automatic status notice) |

---

## 9. Recipes

**Build the registration form dynamically.** Query `companyAttributesMetadata` for `COMPANY_REGISTER` and `COMPANY_ADDRESS_REGISTER`; render one field per attribute (`frontend_input`, `is_required`, `validate_rules`, `options`); send `name`/`email` as fields and the rest as `custom_attributes` in `createCompanyApplication`.

**Show the company area only to company users.** After login, query `customer { company { status allowed_resources } }`. `null` means the customer has no active company. Show "Edit profile" only if `allowed_resources` contains `company.edit`, "Users" only with `company.users.manage`, and so on.

**Unread badge.** Query `company { unread_messages }`; after showing the thread with `companyMessages`, call `markCompanyMessagesRead`.

**Handle a blocked company mid-session.** Blocking revokes tokens: subsequent requests fail with `graphql-authorization`. Send the user back to sign-in, where the store explains the reason.
