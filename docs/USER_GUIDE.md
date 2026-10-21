# MageOS Company: User Guide

This guide describes every feature of the company module, first on the storefront (what business customers see) and then in the admin panel (what the store team uses). For the concepts behind it, see the [Overview](OVERVIEW.md).

Labels and paths match the English (`en_US`) interface. Menu paths are written as `Menu > Submenu`.

---

## Contents

1. [Key concepts](#1-key-concepts)
2. [Storefront](#2-storefront)
   1. [Registering a company](#21-registering-a-company)
   2. [Following an application (status link)](#22-following-an-application-status-link)
   3. [Signing in](#23-signing-in)
   4. [My Company](#24-my-company)
   5. [Company Addresses](#25-company-addresses)
   6. [Company Users and invitations](#26-company-users-and-invitations)
   7. [Accepting an invitation](#27-accepting-an-invitation)
   8. [Company Messages](#28-company-messages)
3. [Admin panel](#3-admin-panel)
   1. [Companies grid](#31-companies-grid)
   2. [Creating and editing a company](#32-creating-and-editing-a-company)
   3. [Reviewing applications and changing status](#33-reviewing-applications-and-changing-status)
   4. [Company addresses (admin)](#34-company-addresses-admin)
   5. [Company users (admin)](#35-company-users-admin)
   6. [Messages and internal notes](#36-messages-and-internal-notes)
   7. [Company tab on the customer page](#37-company-tab-on-the-customer-page)
   8. [Company attributes](#38-company-attributes)
   9. [Configuration](#39-configuration)
   10. [Email templates](#310-email-templates)
   11. [Admin permissions (ACL)](#311-admin-permissions-acl)
   12. [Notifications in the admin inbox](#312-notifications-in-the-admin-inbox)
   13. [Operations: cron, queue and indexer](#313-operations-cron-queue-and-indexer)
4. [Emails sent by the module](#4-emails-sent-by-the-module)
5. [Frequently asked questions](#5-frequently-asked-questions)

---

## 1. Key concepts

**Company.** A business account with a name, a company email, a website, a status, profile attributes (legal name, VAT number, phone and others) and addresses. A company belongs to one website.

**Company status.**

| Status | Meaning | Can its users sign in? |
|---|---|---|
| Pending Approval | Application received, waiting for review. | No |
| Approved | Active company account. | Yes (active users) |
| Rejected | Application declined, with a reason. Can be resubmitted. | No |
| Blocked | Account suspended by the store, with a reason. | No |

Allowed changes:

```
Pending Approval ──Approve──► Approved ──Block──► Blocked
       │                          ▲                  │
       └──Reject──► Rejected      └─────Unblock──────┘
                       │
                       └──Resubmit / Reopen Application──► Pending Approval
```

**Company user.** A customer account linked to a company. A customer belongs to at most one company. Each user has:

- a **role**: `Admin` or `User`;
- an optional **job title**;
- a **status**: `Active` or `Inactive` (inactive users cannot sign in).

**Super user (main contact).** One user of the company, always an `Admin` and always active. Company emails are sent to this person. The applicant who registers the company becomes its super user.

**What each role can do:**

| Action | Admin | User |
|---|:---:|:---:|
| View the company profile | ✔ | ✔ |
| Edit the company profile | ✔ | |
| View addresses | ✔ | ✔ |
| Add, edit, delete addresses and set defaults | ✔ | |
| Manage users and invitations | ✔ | |
| Read messages | ✔ | ✔ |
| Send messages | ✔ | ✔ |

Rules that always hold:

- A company always keeps at least one active admin.
- The super user cannot be demoted, deactivated or removed. Choose another super user first (admin panel).

---

## 2. Storefront

The storefront features are available when **Enable Companies on Storefront** is set to Yes (see [Configuration](#39-configuration)).

### 2.1 Registering a company

**Where:** the **Register Your Company** link in the header, a block on the sign-in page, or directly at `/company/register`. The link is hidden when the public registration form is disabled.

**Who can register:**

- **A visitor** (not signed in): fills in the company, the legal address and a new customer account (first name, last name, email, password). The account is created together with the company.
- **A signed-in customer without a company**: fills in only the company and the legal address. The address is prefilled with the customer's name, and the customer becomes the super user.

A customer who already belongs to a company cannot register another one.

**The form** has three parts:

1. **Company information**: Company Name, Company Email, Legal Name, VAT Number, Phone Number, and optional fields such as Fax, Website URL, Industry, Number of Employees, Description, Registration Comment and Preferred Language. The exact list depends on the attributes the store enabled for the registration form (see [Company attributes](#38-company-attributes)).
2. **Legal address**: Address Label, First Name, Last Name, Company, Street Address (two lines), City, Country, State/Province, Zip/Postal Code, Phone Number, VAT Number. The State/Province field changes with the country.
3. **Your account** (visitors only): First Name, Last Name, Email, Password, Confirm Password.

Depending on the configuration, the form can also show:

- a **terms** checkbox with the store's text, which must be ticked;
- a **reCAPTCHA**.

**Validation.** Required fields and format rules (email, URL, VAT number format, lengths) are checked in the browser and again by the server. On an error the form comes back with the values already entered, except the passwords.

**After submitting:**

- The legal address is also set as the default billing and shipping address of the company.
- With manual review (the default), the company is **Pending Approval**. The success page explains that the account will be activated after review. A signed-in customer is signed out, because users of pending companies cannot sign in.
- With **Approve New Companies Automatically**, the company is **Approved** at once.

**Emails:**

- The applicant receives **one** email: "We received your company application". It includes a personal **status link** (see 2.2). The company email does not receive a copy.
- The store's admin recipients receive "New company application" with a link to the company in the admin panel. An entry also appears in the admin inbox.
- Magento's usual "welcome" email is not sent. It is replaced by the "company approved" email. If the store requires email confirmation for new accounts, the confirmation email is held back and sent when the company is approved.

### 2.2 Following an application (status link)

Applicants of a **pending** or **rejected** company cannot sign in yet. Instead, the emails "application received", "application rejected" and "new message" contain a personal link that opens the **application status page**.

On that page the applicant can:

- see the current status;
- read the **reason** if the application was rejected;
- read the messages from the store (internal notes of the store team are never shown) and **reply**;
- **Resubmit Application**, with an optional comment, when it was rejected. The company goes back to Pending Approval and the applicant receives "application received" again, with a new link.

Things to know about the link:

- It is personal: do not forward it. Anyone with the link can see the application and reply.
- It expires after the configured lifetime (30 days by default). Every new email brings a fresh link.
- It stops working if the company email or the super user changes, or if the store disables links (lifetime 0).
- It only works on the store's website where the company was registered.
- Once the company is **approved**, the link takes the applicant to the sign-in page. If the company is **blocked**, the page says the link is no longer available.
- An invalid or expired link shows "This link is not valid or has expired. Use the link in the most recent email you received from us."

### 2.3 Signing in

Company users sign in with their normal customer account. Sign-in is refused, with the correct password, in these cases:

| Situation | Message |
|---|---|
| Company pending | Explains that the company account is waiting for approval. |
| Company rejected | Explains that the company application was rejected. |
| Company blocked | "Your company account is blocked. Please contact us for more information." |
| User inactive | "Your company user account is inactive. Please contact your company administrator." |

With a wrong password the usual generic message is shown, so these messages never reveal anything to someone without the credentials.

The same rules apply to API tokens (REST and GraphQL `generateCustomerToken`). When a company is blocked or rejected, or a user is deactivated, existing sessions and API tokens of the affected users are ended immediately.

### 2.4 My Company

**Where:** `My Account > My Company` (`/company/account`). The links of the company area only appear for active users of an approved company, and each link only if the user's role allows it.

The page shows:

- the company profile (the fields of the account edit form);
- the **ERP code** (customer number in the store's systems), read-only, when the store has set one;
- the company status;
- your own role and job title.

**Editing** (`Edit`, admins only): the form contains the company fields enabled for "account edit". Status, ERP code, website and super user cannot be changed here. Clearing an optional field and saving removes its value.

### 2.5 Company Addresses

**Where:** `My Account > Company Addresses` (`/company/address`).

Every user can see the address book. Admins can also:

- **Add New Address** and **Edit** an address (same fields as the legal address, plus a label such as "Headquarters" or "Warehouse 2");
- tick **Default billing**, **Default shipping** or **Legal address** in the form;
- use **Set as default billing / shipping / legal** from the list;
- **Delete** an address (with confirmation). Deleting a default address clears that default.

Company addresses are separate from the customer's personal address book and are not offered at checkout.

### 2.6 Company Users and invitations

**Where:** `My Account > Company Users` (`/company/users`, admins only).

The page has three parts:

1. **Users**: name, email, role, job title, status, and a mark on the main contact. **Edit** opens the user form where an admin changes the **role**, **job title** and **status** (Active/Inactive). **Remove** takes the user out of the company; their customer account is kept.
2. **Open invitations**: email, role, date, and whether it has expired. **Resend** sends a new link (the old one stops working). **Cancel** deletes it.
3. **Invite a user**: email, role (User or Admin) and optional job title.

How invitations work:

- Every invitation must be **accepted** by the invited person through the emailed link, even if they already have an account. Nobody is added to a company without their consent.
- The link is valid for the configured lifetime (7 days by default) and can be used once.
- Inviting the same email again replaces the open invitation.
- You cannot invite someone who is already in your company. Someone who belongs to another company is refused with a generic message that does not name the other company.

Deactivating a user ends their sessions immediately and prevents sign-in until they are activated again. The super user and the last active admin cannot be deactivated, demoted or removed.

### 2.7 Accepting an invitation

The invitation email contains a link to `/company/invitation/accept`. The company must be approved, and the link only works on the company's website.

| Situation of the invited person | What happens |
|---|---|
| No account yet | Fills in first name, last name and password (the email is fixed). The account is created, joins the company and is signed in, unless the store requires email confirmation. |
| Has an account, not signed in | Is asked to sign in, then comes back to accept. |
| Signed in with that account | Accepts with one click. |
| Signed in with a different account | Is asked to sign out first. |

Only the owner of the invited email address can accept.

### 2.8 Company Messages

**Where:** `My Account > Company Messages` (`/company/message`). The link shows the number of unread messages, for example "Company Messages (2)".

- One conversation per company, shared by all its users. Newest messages are shown at the end.
- Store messages appear as **Customer Service**, automatic status notices as **Notification**, and your own as **You**. Messages from colleagues show their name.
- Opening the page marks the store messages as read. New messages are marked **New**.
- Write a reply in the form at the bottom. Messages are plain text. Limits: maximum length (2000 characters by default) and maximum messages per user per hour (20 by default). If sending fails, your text is kept in the form.
- When someone sends a message, the store is notified by email and in the admin inbox.

---

## 3. Admin panel

### 3.1 Companies grid

**Where:** `Customers > Companies`.

**Columns:** ID, Company Name, Company Email, Status, ERP Code, Website, Super User, Super User Email, Users (count), Created, Updated, Unread Messages, plus the company attributes marked "Add to Column Options" (by default Legal Name, VAT Number, Phone Number and Industry). Use **Columns** to show or hide them.

**Filters:** by any filterable column (text, select or date range). The keyword **Search** looks for partial matches in the searchable columns (name, email, ERP code, and searchable attributes). Super user, user count and unread messages are calculated live, so they cannot be filtered or sorted.

**Mass actions** (select rows, then **Actions**):

| Action | Notes |
|---|---|
| Approve | Asks for an optional comment. |
| Reject | Asks for a required reason. |
| Block | Asks for a required reason. |
| Unblock | Asks for an optional comment. |
| Delete | Asks for confirmation. Deletes the companies, their addresses, users' links, history and messages. Customer accounts are kept. |

Companies whose status does not allow the action are skipped, and the result message lists them. Actions you have no permission for are hidden.

**Row action:** **Edit**. **Add New Company** creates one from scratch.

> The grid reads an index (`company_grid`). If the indexer is set to "Update by Schedule", changes appear after cron runs. See [Operations](#313-operations-cron-queue-and-indexer).

### 3.2 Creating and editing a company

The company page has these sections:

**Buttons:** Back, Delete, the status buttons that apply to the current status (see 3.3), Save and Continue Edit, Save Company.

**Company Information:**

- Company Name, Company Email, website, and all company attributes enabled for the admin form.
- **ERP Code**: only editable with the "Manage ERP Code" permission. It must be unique across all companies.
- **Website**: chosen when creating; it cannot be changed afterwards.
- **Status**: chosen when creating; afterwards it changes only through the status buttons, so every change is recorded.
- **Super User**: when editing, choose among the company's users. The new super user becomes an admin.

**Addresses**, **Users**, **Messages** and **Status History** are described below. A company created in the admin starts with a history entry; it receives no applicant emails.

### 3.3 Reviewing applications and changing status

Status buttons appear on the company page only when the change is allowed and you have the "Change Company Status" permission:

| Button | Available when | Reason | Effect |
|---|---|---|---|
| Approve | Pending Approval | optional comment | Users can sign in. "Company approved" email (and the held-back confirmation email, if any). |
| Reject | Pending Approval | **required** | Sessions and tokens of its users are ended. "Application rejected" email with the reason and a status link. |
| Block | Approved | **required** | Sessions and tokens ended. "Company blocked" email with the reason. |
| Unblock | Blocked | optional comment | Users can sign in again. "Company unblocked" email. |
| Reopen Application | Rejected | optional comment | Back to Pending Approval. "Application received" email with a new status link. |

Clicking a button opens a small window asking for the comment or reason; **OK** applies the change. Every change:

- is saved in **Status History** (date, from, to, who did it, comment);
- adds an automatic **Notification** message to the conversation;
- sends the corresponding email to the super user.

The applicant can also resubmit a rejected application from the status link; the history then shows the customer as the actor.

### 3.4 Company addresses (admin)

In the **Addresses** section of the company page:

- **Add New Address** opens a form in a modal window. Tick **Default Billing Address**, **Default Shipping Address** or **Legal Address** to set the defaults.
- Each row has **Edit**, **Set as default billing**, **Set as default shipping**, **Set as legal address** and **Delete**.

### 3.5 Company users (admin)

In the **Users** section:

- **Assign Customer**: enter the email of an existing customer of the company's website, a role and a job title. Unlike the storefront, the admin assigns directly, without an invitation.
- **Edit** a user to change role, job title and status (Active/Inactive).
- **Remove** takes the user out of the company. The customer account is kept.

The same protections apply: the super user stays an active admin, and there is always at least one active admin. A customer who belongs to another company cannot be assigned.

### 3.6 Messages and internal notes

**On the company page**, the **Messages** section shows the whole conversation, with new messages marked and **internal notes** highlighted. Write a reply below it:

- Leave **Internal note (not visible to the company)** unticked to send a message to the company. All active users of the company are emailed (or the company email, if it has no users). While the company is pending or rejected, the applicant receives it by email with the status link.
- Tick **Internal note (not visible to the company)** to leave a note only the store team can see. It is never shown or sent to the company.

Viewing the conversation marks the company's messages as read.

**`Customers > Company Messages`** lists one row per company with messages: Company Name, Company Email, Status, Last Message, Last Message At, Messages (count) and Unread. Filter by "Unread" (yes/no), search by name or email, and use **Open Conversation** to go to the company page.

The companies grid also shows the **Unread Messages** column.

### 3.7 Company tab on the customer page

`Customers > All Customers > Edit` shows a **Company** tab: company name (with a link), status, ERP code, role, job title and the company's super user. Customers without a company show "This customer does not belong to any company." The tab requires the "View Companies" permission.

### 3.8 Company attributes

**Where:** `Stores > Attributes > Company`.

The grid lists company and company address attributes: Attribute Code, Default Label, Entity, Input Type, Required, Origin (System or Custom), In Company Grid and Sort Order. Use **Add Company Attribute** or **Add Company Address Attribute** to create one.

**Attribute form:**

| Section | Fields |
|---|---|
| Attribute Properties | Default Label, Attribute Code (2–30 characters, lowercase letters, digits and `_`, starting with a letter; cannot be changed later), Input Type (cannot be changed later), Default Value, Sort Order, Values Required, Enabled |
| Forms | Use in Forms: the forms where the attribute appears (see below) |
| Validation | Input Validation (for example email, URL, letters, numbers), Minimum Text Length and Maximum Text Length, depending on the input type |
| Options | For Dropdown and Multiple Select: rows of options (Label, Default, Actions) you can reorder, mark as default or delete |
| Company Grid | Add to Column Options, Visible by Default, Use in Filter Options, Use in Keyword Search (company attributes only) |
| Labels per Store View | A label per store view |

**Input types:** Text Field, Text Area, Date, Yes/No, Dropdown, Multiple Select.

**Forms:**

| Entity | Form | Where |
|---|---|---|
| Company | Registration | Public registration form |
| Company | Account Edit | My Company > Edit |
| Company | Admin | Admin company page |
| Address | Registration | Legal address in the registration form |
| Address | Address Edit | Company Addresses on the storefront |
| Address | Admin | Admin address modal |

A new attribute appears automatically in the chosen forms, in the REST API and in GraphQL (`custom_attributes` and `companyAttributesMetadata`). Grid settings take effect after the `company_grid` index is rebuilt (cron does it automatically).

**System attributes** (created by the module) are protected:

- You can change the label, store labels, sort order and grid settings.
- For Industry and Number of Employees you can also edit the options.
- For optional system attributes (Fax, Website URL, Industry, …) you can choose the forms.
- You cannot change the code, input type, "required" or validation, and you cannot delete them.

> The default value of a Dropdown is stored, but the storefront forms do not preselect it.

### 3.9 Configuration

**Where:** `Stores > Configuration > Customers > Companies`. All settings have website scope.

**General**

| Setting | Default | Description |
|---|---|---|
| Enable Companies on Storefront | Yes | Account area, registration form and customer APIs. |
| Approve New Companies Automatically | No | When No, new applications stay pending until an admin approves them. |
| VAT Number Validation | Format | `None` or `Format` (basic format check of the VAT number). |

**Registration**

| Setting | Default | Description |
|---|---|---|
| Enable Public Registration Form | Yes | Shows the form and the "Register Your Company" link. |
| Require Terms Acceptance | No | Adds a required checkbox. |
| Terms Text | (empty) | Text next to the checkbox. HTML links are allowed. |
| Application Status Link Lifetime (Days) | 30 | Validity of the applicant's status link. 0 disables the links (and invalidates those already sent). |

**Email Notifications**

| Setting | Default |
|---|---|
| Email Sender | General Contact |
| Admin Recipients | (empty). Comma-separated emails notified of new applications and messages. |
| Application Received Template, New Application (Admin) Template, Company Approved Template, Company Rejected Template, Company Blocked Template, Company Unblocked Template, New Message (Customer) Template, New Message (Admin) Template, User Invitation Template | The module's templates. |

**Company Users**

| Setting | Default | Description |
|---|---|---|
| Invitation Lifetime (Days) | 7 | Validity of invitation links. |

**Messages**

| Setting | Default | Description |
|---|---|---|
| Maximum Message Length | 2000 | Characters. 0 for no limit. |
| Messages per Customer per Hour | 20 | 0 for no limit. |

**reCAPTCHA:** `Stores > Configuration > Security > Google reCAPTCHA Storefront > Storefront > Enable for Company Registration`. It protects the registration form, the REST registration endpoint and the GraphQL `createCompanyApplication` mutation.

### 3.10 Email templates

The module's templates can be customised in `Marketing > Email Templates > Add New Template > Load Template`:

| Template | Sent to | When |
|---|---|---|
| Company Application Received | Applicant (super user) | Registration, resubmission |
| Company Application (Admin) | Admin Recipients | New application |
| Company Approved | Super user | Approve |
| Company Rejected | Super user | Reject (includes the reason) |
| Company Blocked | Super user | Block (includes the reason) |
| Company Unblocked | Super user | Unblock |
| Company New Message (Customer) | Active company users | Store message |
| Company New Message (Admin) | Admin Recipients | Company message |
| Company User Invitation | Invited email | Invitation sent or resent |

Then select your template in the configuration (3.9). Emails are sent in the language of the default store view of the company's website. If the company has no super user, the company email is used.

### 3.11 Admin permissions (ACL)

In `System > Permissions > User Roles > Role Resources`:

| Resource | Allows |
|---|---|
| Customers > Companies | Access to the companies menu. |
| View Companies | Grid, company page, customer tab, read APIs. |
| Add/Edit Companies | Create and edit companies, addresses and users. |
| Delete Companies | Delete companies. |
| Change Company Status | Approve, reject, block, unblock, reopen. |
| Manage ERP Code | Edit the ERP code; ERP API endpoints. |
| Company Messages | Messages menu and conversation on the company page. |
| Stores > Attributes > Company Attributes | Manage company attributes. |
| Stores > Configuration > Company Section | Module configuration. |

The same resources protect the admin REST API, which makes them usable for integrations (see the [REST API](openapi.yaml)).

### 3.12 Notifications in the admin inbox

New applications and new messages from companies also create an entry in the admin notification inbox (the bell icon), with a link to the company.

### 3.13 Operations: cron, queue and indexer

- **Message notifications** are sent by the queue consumer `company.message.notify`. It is started by Magento's cron (`consumers_runner`) or manually with `bin/magento queue:consumers:start company.message.notify`. Without it, message emails stay in the queue.
- **Companies grid index** (`company_grid`, `System > Index Management`): in "Update on Save" mode a company is reindexed when it is saved; in "Update by Schedule" mode changes are applied by cron. Changing the grid settings of an attribute marks the index as invalid; cron rebuilds it, or run `bin/magento indexer:reindex company_grid`.

---

## 4. Emails sent by the module

| Event | Recipient | Template | Status link |
|---|---|---|:---:|
| Company registered | Applicant | Application Received | ✔ |
| Company registered | Admin Recipients (+ inbox) | New Application (Admin) | |
| Approved | Super user | Company Approved | |
| Rejected | Super user | Company Rejected | ✔ |
| Blocked | Super user | Company Blocked | |
| Unblocked | Super user | Company Unblocked | |
| Resubmitted / reopened | Applicant | Application Received | ✔ |
| Store message | Active users (pending/rejected: applicant) | New Message (Customer) | ✔ (pending/rejected only) |
| Company message | Admin Recipients (+ inbox) | New Message (Admin) | |
| Invitation | Invited email | User Invitation | |

Internal notes and automatic status notices are never emailed as messages.

---

## 5. Frequently asked questions

**An applicant says they cannot sign in.** That is expected until the company is approved. They can follow the application with the status link in their email.

**The applicant lost the status link.** Any new email brings a fresh link: send them a message from the company page, or reject/reopen if appropriate.

**How do I move a user to another company?** Remove them from the current company first; then assign them in the new one (admin) or invite them (storefront).

**How do I change the main contact?** In the admin, edit the company and choose another **Super User** among its users.

**Can a company have more than one admin?** Yes. Any number of users can have the Admin role.

**A change does not appear in the companies grid.** The index is probably in "Update by Schedule" mode; wait for cron or reindex `company_grid`.

**Message emails are not arriving.** Check that cron or the `company.message.notify` consumer is running, and that **Admin Recipients** is filled in for store notifications.

**Can I delete a company without deleting its people?** Yes. Deleting a company keeps the customer accounts; they simply stop belonging to a company.
