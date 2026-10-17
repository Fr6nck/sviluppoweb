# 02 — Roles and permissions

## Model

Permissions are **not** attached to users. They are attached to roles, and roles
are granted within a scope.

```
user ──< user_roles >── role ──< role_permissions >── permission
              │
              └── scope: platform | account:<id>
```

- A **platform-scoped** grant (`account_id IS NULL`) applies everywhere. Only
  MyHouse/Blackout staff hold these.
- An **account-scoped** grant applies to exactly one account and everything owned
  by it.

Every authorisation check is therefore the same shape:

```ts
can(actor, 'guide.publish', { accountId })
```

and never `if (user.isAdmin)`. There is one place in the codebase where role
names appear as literals: the seed file.

## Roles

### `super_admin` — MyHouse / Blackout

Platform scope. Full access to every account, every order, every setting.

Can do everything in the permission table below, plus the four things only a
platform role can do:

- **Support mode** — act inside a customer account without the customer's
  password ([10](10-admin-console.md#support-mode)).
- **Package authoring** — create package versions, set Stripe price IDs, toggle
  features ([05](05-packages-and-entitlements.md)).
- **Entitlement override** — grant or revoke a feature for one account, with a
  written reason, outside of what the package gives.
- **Global settings and templates** — section templates, locales, default copy.

Every super admin action writes to `activity_logs`. There is no super admin path
that skips the audit trail; the audit write is inside the service layer, not the
controller, so a new endpoint cannot forget it.

### `admin_support` — MyHouse staff, reduced

Platform scope, deliberately narrower. Added because "give the new support hire
super admin" is how audit trails die.

Can: read all accounts, enter support mode, edit customer content, resend emails,
regenerate QR codes, add admin notes.

Cannot: change package definitions, change prices, issue refunds, delete
accounts, grant entitlement overrides, change global settings.

### `account_owner` — the customer who bought

Account scope. The user created at activation. Can do everything within their own
account, including billing and inviting members.

### `account_editor` — a teammate

Account scope. Content and publishing, no billing, no account deletion, no member
management. Dormant in MVP (the UI does not expose invitations) but the role and
the `account_members` table exist so that Phase 2 is a UI change only.

### `guest` — not a user

Guests are not authenticated and have no row in `users`. "Guest" is an access
path, not a role: an unauthenticated request to a published public URL. The only
guest-side credential that exists is the optional per-section PIN
([17](17-auth-and-security.md#protected-sections)), which authorises a *section*,
not a person.

## Permission matrix

`●` full · `◐` own account only · `○` none

| Permission key | super_admin | admin_support | account_owner | account_editor |
| --- | :---: | :---: | :---: | :---: |
| `account.read` | ● | ● | ◐ | ◐ |
| `account.update` | ● | ● | ◐ | ○ |
| `account.archive` | ● | ○ | ○ | ○ |
| `account.delete_data` | ● | ○ | ◐ | ○ |
| `account.member.manage` | ● | ○ | ◐ | ○ |
| `account.impersonate` | ● | ● | ○ | ○ |
| `billing.read` | ● | ● | ◐ | ○ |
| `billing.refund` | ● | ○ | ○ | ○ |
| `billing.discount.manage` | ● | ○ | ○ | ○ |
| `billing.package.assign` | ● | ○ | ○ | ○ |
| `property.read` | ● | ● | ◐ | ◐ |
| `property.create` | ● | ● | ◐ | ○ |
| `property.update` | ● | ● | ◐ | ◐ |
| `property.delete` | ● | ○ | ◐ | ○ |
| `guide.read` | ● | ● | ◐ | ◐ |
| `guide.update` | ● | ● | ◐ | ◐ |
| `guide.publish` | ● | ● | ◐ | ◐ |
| `guide.unpublish` | ● | ● | ◐ | ○ |
| `guide.slug.change` | ● | ● | ◐ | ○ |
| `guide.qr.regenerate` | ● | ● | ◐ | ○ |
| `media.upload` | ● | ● | ◐ | ◐ |
| `media.delete` | ● | ● | ◐ | ◐ |
| `translation.edit` | ● | ● | ◐ | ◐ |
| `translation.machine_run` | ● | ● | ◐ | ◐ |
| `analytics.read` | ● | ● | ◐ | ◐ |
| `package.manage` | ● | ○ | ○ | ○ |
| `entitlement.override` | ● | ○ | ○ | ○ |
| `template.manage` | ● | ○ | ○ | ○ |
| `locale.manage` | ● | ○ | ○ | ○ |
| `settings.global` | ● | ○ | ○ | ○ |
| `audit.read` | ● | ◐ | ○ | ○ |

Three keys carry an extra condition beyond the role check:

- `guide.publish` also requires the account status to be `active` and at least
  one completed payment. A refunded or suspended account keeps its content and
  loses publication.
- `guide.slug.change` for an `account_owner` is rate-limited to 3 changes per 30
  days; a super admin is not limited. Slugs are permanent-ish public identity.
- `media.delete` refuses when `media_usages` is non-empty, and returns the list
  of places using the file. Admins get the same refusal with a force option that
  logs.

## What a client must never see

Enforced at the API layer, not by hiding UI:

- Anything under `/api/admin/**` returns `404` (not `403`) to a non-platform
  actor, so the admin surface is not discoverable.
- Stripe object IDs, webhook payloads, and internal cost fields are stripped by
  the response serialiser for account-scoped actors. The client sees
  `invoiceUrl` and `status`, never `pi_…`.
- Feature keys that the account does not hold are returned as
  `{ available: false, requiredPackage: 'plus' }` — enough to render the
  "Available with Plus" affordance, nothing more.
- `activity_logs` is never exposed on the client API. Customers get a friendly
  "Last updated" derived from `guides.updated_at`.

## Impersonation is a distinct principal

When a super admin enters support mode, the session does **not** become the
customer. The request carries both identities:

```
actor:        user_8f3a  (admin, Giulia)
acting_for:   account_27 (Casa San Francesco)
impersonation_session: imp_91c
```

Consequences:

- Authorisation evaluates the admin's platform permissions, intersected with
  what the account's package entitles. An admin cannot use support mode to give a
  customer a Pro feature — that requires `entitlement.override`, which is a
  separate, separately-logged action.
- Every write records `actor_user_id = the admin` and
  `impersonated_account_id = the account`. The customer's own activity feed shows
  "Edited by MyHouse support".
- Analytics events generated during impersonation are tagged and excluded from
  the customer's view counts.
- A persistent banner is rendered by the app shell, not by individual pages, so
  no screen can be built that forgets it.
- Sessions expire after 60 minutes and require a typed reason to start.

Full mechanics in [17](17-auth-and-security.md#support-mode-impersonation).
