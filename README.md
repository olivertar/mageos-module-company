# MageOS_Company

Company accounts for Mage-OS. A business applies for an account, the store reviews it, and once approved several people act on behalf of the same company, sharing its profile, addresses and a message thread with the store.

The module is a foundation for B2B, built the native Magento way: companies and company addresses are EAV entities modelled on `customer` and `customer_address`, and the storefront, admin, REST and GraphQL all go through the same service contracts.

## Features

**Storefront (Luma)**

- Public registration form: company, legal address and the applicant's customer account in one step, with optional terms checkbox and reCAPTCHA.
- Signed status link so applicants can follow their application without signing in.
- Sign-in restricted to users of approved companies, with a clear message for pending, rejected and blocked ones.
- *My Company* area: company profile, company addresses (default billing, default shipping, legal address), users and invitations, message thread with the store.
- Roles inside the company (`admin`, `user`), job titles, active/inactive users.

**Admin**

- Companies grid (flat indexer, filters, full-text search, mass actions) and company form.
- Status lifecycle: pending → approved / rejected, approved ↔ blocked, rejected → pending, each change recorded with actor and reason.
- Company addresses, users (assign existing customers, set the super user), messages and internal notes.
- Company tab on the customer page.
- Company and company address attributes managed from `Stores > Attributes > Company`; new attributes appear in forms, grid, REST and GraphQL with no code.
- Configuration, email templates, granular ACL and admin inbox notifications.

**Integration**

- REST API for admins/integrations, company users ("mine" routes) and public registration.
- GraphQL API in the companion module `MageOS_CompanyGraphQl`.
- Optional, unique ERP code per company.
- Events: `company_register_after`, `company_status_change_after`, `company_message_send_after`.
- Store messages are emailed asynchronously through the `company.message.notify` queue consumer.

Out of scope on purpose: company prices and shared catalogs, quotes, purchase orders, credit limits, company hierarchies, per-user permissions, company addresses in checkout, Hyvä. See [OVERVIEW.md](docs/OVERVIEW.md#3-what-it-is-not) for the reasons.

> The module uses `company_*` tables, so it cannot be installed on the same instance as Adobe Commerce B2B.

## Requirements

- Mage-OS 3.x (developed on `mage-os/framework` 3.6.0)
- PHP 8.1 – 8.5
- Cron running (`consumers_runner` and indexer jobs)

## Installation

With Composer, from this repository:

```bash
composer config repositories.mageos-company vcs git@github.com:olivertar/mageos-module-company.git
composer require mageos/module-company:dev-main
```

Or copy the module to `app/code/MageOS/Company`. Then:

```bash
bin/magento module:enable MageOS_Company
bin/magento setup:upgrade
bin/magento setup:di:compile        # production mode
bin/magento indexer:reindex company_grid
bin/magento cache:flush
```

Enable `MageOS_CompanyGraphQl` as well if you need the GraphQL API.

## Configuration

`Stores > Configuration > Customers > Companies` (website scope): storefront availability, automatic approval, VAT validation, registration form and terms, status link lifetime, email sender, admin recipients and templates, invitation lifetime, message length and rate limits.

reCAPTCHA for registration: `Stores > Configuration > Security > Google reCAPTCHA Storefront`.

## Documentation

| Document | Content |
|---|---|
| [Overview](docs/OVERVIEW.md) | What the module is and is not, goals and design principles |
| [User Guide](docs/USER_GUIDE.md) | Every storefront and admin feature, configuration, emails, FAQ |
| [Technical Documentation](docs/TECHNICAL.md) | Architecture, service contracts, database schema, flows, extension points, security |
| [REST API](docs/openapi.yaml) | OpenAPI 3.0.3 specification, with a [quick guide](docs/REST_API.md) |
| [GraphQL API](docs/GRAPHQL_API.md) | Queries, mutations, types and examples (`MageOS_CompanyGraphQl`) |

## Extending

- Replace `MageOS\Company\Api\CompanyAuthorizationInterface` to change what each company user may do.
- Observe the events above to connect an ERP, CRM or workflow.
- Add attributes from the admin, or with data patches using `MageOS\Company\Setup\CompanySetup`.

Details in [TECHNICAL.md](docs/TECHNICAL.md).

## Testing

```bash
# Unit tests
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/MageOS/Company

# Coding standard
vendor/bin/phpcs --standard=Magento2 --extensions=php,phtml,xml,graphqls app/code/MageOS/Company

# End-to-end (storefront, REST, GraphQL, emails through Mailpit); module installed in app/code
bash app/code/MageOS/Company/Test/E2E/run.sh
```

`run.sh` temporarily redirects outgoing mail to Mailpit (`mailbox:1025` by default) and restores the original SMTP settings afterwards.
