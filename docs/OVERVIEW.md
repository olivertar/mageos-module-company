# MageOS Company: Overview

`MageOS_Company` and `MageOS_CompanyGraphQl` add **company accounts** to Mage-OS: a business applies for an account, the store reviews it, and once approved several people can act on behalf of the same company, sharing its profile, addresses and a message thread with the store.

| Module | Purpose |
|---|---|
| `MageOS_Company` (`app/code/MageOS/Company`) | Domain, persistence, admin panel, Luma storefront, REST API, emails, message queue, grid indexer. |
| `MageOS_CompanyGraphQl` (`app/code/MageOS/CompanyGraphQl`) | GraphQL schema for headless storefronts. No business logic of its own: every resolver calls the service contracts of `MageOS_Company`. |

Related documents:

- [User Guide](USER_GUIDE.md): every storefront and admin feature, step by step.
- [Technical Documentation](TECHNICAL.md): architecture, database schema, flows, extension points.
- [REST API](openapi.yaml): OpenAPI 3.0 specification (with [REST_API.md](REST_API.md) as a quick guide).
- [GraphQL API](GRAPHQL_API.md): schema reference with examples.

---

## 1. The problem it solves

Magento's customer model is one person, one account. A business customer is not: it is an organisation with a legal identity (legal name, VAT number), its own addresses, several buyers, and a relationship with the store that often starts with a review ("are you really a business?").

Without a company layer, stores fake it: shared logins, customer groups used as companies, notes in the customer record, or emails outside the platform. Each of these loses something: who did what, who may do what, and a clean place to keep business data.

`MageOS_Company` gives that layer a proper home:

- **A company entity** with its own attributes and addresses, managed like customers are.
- **A lifecycle**: an application is *pending* until the store approves or rejects it; an approved company can be *blocked* and *unblocked*. Every change is recorded with who made it and why.
- **People inside the company**: each customer belongs to at most one company, with a role (`admin` or `user`), a job title and an active/inactive status. Company admins invite colleagues themselves.
- **A conversation**: one message thread per company between the store and the company, with internal notes for the store team.
- **Access control**: users of companies that are not approved cannot sign in, and what each user can do inside the company is decided by a single, replaceable authorization service.

## 2. What it is

- **A foundation for B2B on Mage-OS.** It covers the part every B2B project needs first: who the business customer is, who belongs to it, and whether the store has accepted it.
- **Native Magento.** Companies and company addresses are EAV entities built exactly like `customer` and `customer_address`: attribute metadata, form codes, validation rules, website scope, UI components, a flat grid indexer modelled on the customer grid. Developers who know the customer module already know how this one works.
- **Service contracts first.** The storefront controllers, the REST API, the GraphQL API and the admin all call the same interfaces in `Api/`. A rule enforced once (for example "a company user cannot change the company status") is enforced everywhere.
- **Configurable without code.** Store owners add company and address attributes from `Stores > Attributes > Company`; they appear in the registration form, the account area, the admin, REST and GraphQL with no code changes.
- **Secure by default.** Company data is always derived from the logged-in customer, never from the request. Protected fields (status, ERP code, super user, website) cannot be set by customers. Cross-company access answers "not found" without revealing what exists. All storefront writes require a form key.
- **Integration-ready.** An optional ERP code (`company_id`, globally unique), admin REST endpoints for integrations, and events at every important step (`company_register_after`, `company_status_change_after`, `company_message_send_after`).

## 3. What it is not

The module deliberately stops where most stores start to differ. These are **out of scope**:

| Not included | Why / where it belongs |
|---|---|
| Shared catalogs, company-specific prices | Pricing modules; customer groups still work as usual. |
| Quotes, negotiable prices, purchase orders, approval rules | Separate B2B workflow modules. |
| Credit limits, payment on account | Payment modules. |
| Requisition lists, quick order | Catalog/checkout modules. |
| Company hierarchies, teams, divisions | Not modelled: a company is flat. |
| Granular per-user permissions | Planned as a separate module that replaces `CompanyAuthorizationInterface` (see Technical Documentation, section 10). |
| Using company addresses in checkout | Company addresses are separate from customer addresses. Their attribute codes mirror `customer_address` to make a future checkout integration easy. |
| Hyvä storefront | Luma only for now. Logic sits in ViewModels and thin templates, ready for a future `MageOS_CompanyHyva`. |
| A concrete ERP connector | The module exposes the ERP code, the REST API and events; the connector is project-specific. |
| Compatibility with Adobe Commerce B2B | Both use `company_*` tables, so they cannot be installed on the same instance. This is an accepted trade-off. |

It is also **not** a CRM: the message thread is for the store–company relationship, not for sales pipelines or ticketing.

## 4. The spirit behind it

Four ideas shaped every decision.

1. **Do the core well, keep it small.** A company account, its people, its lifecycle and its conversation with the store. Everything else is left to modules that can be added, swapped or skipped. A small core is easier to trust, audit and upgrade.

2. **Follow the platform, not around it.** When Magento already has a pattern (EAV with form codes, UI component grids, mview indexers, message queues, ACL, service contracts, web API ACL), the module uses it rather than inventing a new one. The result feels like part of Mage-OS, and the knowledge transfers in both directions.

3. **One rule, one place.** Business rules live in the domain services, never in controllers or resolvers. That is why the storefront, REST and GraphQL behave identically, and why the end-to-end tests can check all three against the same expectations.

4. **Respect for the people involved.** Applicants get one clear email, not a pile of them. Nobody can add someone else's account to their company without that person's consent (every invitation must be accepted). Applicants can follow their application and reply through a signed personal link without needing an account that works yet. Error messages never reveal other companies' data.

It is open source in the Mage-OS spirit: built for the community, readable, and meant to be extended.

## 5. At a glance

```
Applicant                         Store (admin)                      Company users
---------                         -------------                      -------------
Registers company  ─────────────► Reviews application
(pending, cannot sign in)         Approve / Reject (reason)
Status link: follow, reply,       Messages, internal notes
resubmit if rejected
                                  Approved ──────────────────────►  Sign in, My Company
                                                                     Addresses, users, invitations
                                  Block / Unblock (sessions ended)   Messages with the store
```

**Statuses**: `pending` → `approved` or `rejected`; `rejected` → `pending` (resubmit); `approved` ↔ `blocked`.

**Roles**: `admin` (everything) and `user` (view company and addresses, read and send messages).

**Interfaces**: Luma storefront, admin panel, REST (admin/integration and "mine" customer routes), GraphQL (customer and public operations only).

## 6. Requirements

- Mage-OS 3 or later (PHP 8.1+ syntax).
- Message queue consumers running (cron `consumers_runner` or `bin/magento queue:consumers:start company.message.notify`) so message notifications are sent.
- Cron running, for the `company_grid` indexer when it is set to "Update by Schedule".
