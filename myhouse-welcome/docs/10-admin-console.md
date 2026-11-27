# 10 — Admin console

Same design system, higher density. Staff live here for hours; hosts visit twice a
month. The tokens are identical; the spacing scale is one step tighter and tables
replace cards.

---

## Home — `/admin`

```
┌───────────────────────────────────────────────────────────────────────┐
│  Dashboard                                    Last 30 days ▾          │
│                                                                       │
│  Customers      Active guides    Drafts        New this month         │
│  341            287              41            23                     │
│  +23 ↑          +19 ↑            -4 ↓          €2,140                 │
│                                                                       │
│  ┌── Revenue ────────────────────┐ ┌── Packages ────────────────────┐ │
│  │  ▁▂▃▅▄▆▇▆█▇▆█                 │ │ Essential  ████████░░  142     │ │
│  │  €2,140 this month            │ │ Plus       ██████░░░░  118     │ │
│  │  €18,490 this year            │ │ Pro        ████░░░░░░   81     │ │
│  └───────────────────────────────┘ └────────────────────────────────┘ │
│                                                                       │
│  Needs attention                                                      │
│  ⚠  4 accounts paid but never activated (> 7 days)          View ▸    │
│  ⚠  2 Stripe webhooks failed                                View ▸    │
│  ⚠  7 guides stuck below 30% completion (> 14 days)         View ▸    │
│  ⚠  1 media file failed virus scan                          View ▸    │
│                                                                       │
│  Recent customers                                                     │
│  Customer          Property         Package  Status  Compl.  Activity │
│  Marco Bianchi     Casa San Fran…   Plus     ● Live    85%   2h ago   │
│  Elena Rossi       Villa Aurora     Pro      ● Live   100%   5h ago   │
│  Studio Mare       —                Plus     ○ Draft   12%   1d ago   │
│  Luca Ferrari      Il Glicine       Essential ● Live   72%   2d ago   │
└───────────────────────────────────────────────────────────────────────┘
```

**"Needs attention" is the point of this page.** KPIs are reassurance; the alert
list is work. Each row is a saved filter with a defined next action, so the
dashboard is a queue rather than a report.

---

## Customers — `/admin/customers`

Table with server-side search, filter and sort.

Columns: customer, email, property count, package + version, account status,
guide status, completion, last login, created, revenue.

Filters: status, package, guide status, completion band, created range, has
discount, never activated, never published, support-assisted.

Bulk actions: export CSV, resend welcome email, assign to a staff member, tag.

Search matches customer name, email, company, VAT number, property name and
slug — staff get calls that start with any of those.

---

## Customer detail — `/admin/customers/:id`

One page, no tabs. Layout in [03](03-information-architecture.md#customer-detail-page).

### Actions rail

| Action | Effect | Guard |
| --- | --- | --- |
| **Assist customer** | Starts support mode | Reason required, 60-min session |
| **Change package** | Assign a different package version | Diff preview, reason, logged |
| **Grant feature** | Entitlement override | `entitlement.override`, reason and end date required |
| **Resend welcome email** | New activation token, invalidates the previous one | Rate-limited 3/hour |
| **Reset onboarding** | Clears `onboarding_progress`, keeps all content | Typed confirmation |
| **Preview guide** | Opens the draft renderer in a new tab | — |
| **Regenerate QR** | New token ⚠ invalidates printed codes | Typed confirmation, warns explicitly |
| **Change slug** | Old slug becomes a permanent redirect | — |
| **Suspend** | Unpublishes, keeps everything | Reason |
| **Reactivate** | Republishes the last version | — |
| **Archive** | Soft delete, hidden from lists, 30-day recovery | Typed confirmation + reason |
| **Delete data (GDPR)** | Hard erasure pipeline | Two-person: requested by one super admin, confirmed by another |

Nothing on this rail is silent. Every one writes `activity_logs` with actor,
subject, before/after and reason.

---

## Support mode

> The single most important admin capability. Most support calls end with
> "can you just do it for me?"

### Starting

```
┌─ Assist Marco Bianchi ────────────────────────────┐
│                                                   │
│  Why are you assisting this customer?             │
│  [ Customer called — can't upload the check-in  ] │
│  [ photos from their iPhone                     ] │
│                                                   │
│  Session length   [ 60 minutes ▾ ]                │
│                                                   │
│  The customer will see that MyHouse support made  │
│  these changes. All actions are recorded.         │
│                                                   │
│                   [ Cancel ]  [ Start assisting ] │
└───────────────────────────────────────────────────┘
```

### During

The app shell — not individual pages — renders a persistent banner that cannot be
dismissed:

```
┌──────────────────────────────────────────────────────────────┐
│ ⓘ You are assisting Casa San Francesco as administrator.     │
│   Giulia · started 14:02 · ends 15:02          [End session] │
└──────────────────────────────────────────────────────────────┘
```

The banner is `position: sticky; top: 0` above everything, in a distinct
semantic colour used nowhere else in the product, and it pushes page content
down rather than overlapping it — so no screenshot from a support session can be
mistaken for a customer's own screen.

### Guarantees

- The session is a distinct principal: `actor = admin`, `acting_for = account`
  ([02](02-roles-and-permissions.md#impersonation-is-a-distinct-principal)).
- Permissions are the **intersection** of the admin's platform rights and the
  account's entitlements. Support mode cannot conjure a Pro feature.
- Billing pages, password change and account deletion are blocked inside support
  mode, regardless of role. An admin cannot change a customer's password or buy
  something on their behalf from inside their session.
- Every write is logged with the impersonation session ID.
- Analytics generated in support mode are tagged and excluded from the customer's
  counts.
- Auto-expiry at the chosen duration; a soft warning at −5 minutes.
- The customer sees "Edited by MyHouse support" with a timestamp in their own
  activity feed, and receives a summary email if content was published.

### After

The session record stores: admin, account, reason, start, end, IP, user agent,
action count, and the list of subjects touched. `/admin/system/audit` filters by
impersonation session.

---

## Orders — `/admin/orders`

Table: date, customer, package + version, amount, discount, status, Stripe ID
(links to the Stripe dashboard), invoice.

Detail page adds: full line items, tax, the payment method's brand and last four
digits, refund history, the raw webhook events for that order with a replay
button, and a timeline (`created → checkout opened → paid → provisioned →
activated → published`) that makes "where did this get stuck?" answerable at a
glance.

Refund control: full or partial, reason required, confirmation naming the
consequences ("Casa San Francesco's guide will go offline").

---

## Packages — `/admin/packages`

```
  Packages                                        [ + New package ]

  ⠿  Essential   v3 current   €49    ● Public                142 customers  ▸
  ⠿  Plus        v2 current   €99    ● Public  ★ Recommended 118 customers  ▸
  ⠿  Pro         v4 current   €179   ● Public                 81 customers  ▸
  ⠿  Legacy Base v1 archived  €39    ○ Hidden                  9 customers  ▸
```

Drag to reorder (affects the marketing page), star to mark recommended, toggle
public to stop selling without affecting existing customers.

### Package detail

```
  Plus                                                      [ Edit ]

  Versions
  v2  €99.00  price_1Cd…  current   from 01 Jul 2026    118 customers  [view]
  v1  €89.00  price_1Ab…  retired   01 Jan – 30 Jun     41 customers   [view]

  Features — v2                                    🔒 in use, read-only
  welcome.properties.max                1
  welcome.languages.max                 5
  welcome.sections.services             ✓
  welcome.media.pdf                     ✓
  welcome.media.storage_mb              400
  …

  [ Create new version from v2 ]
```

Editing a version that has orders is impossible by design; the only path is a new
version ([05](05-packages-and-entitlements.md#package-versions)). The Stripe price
ID field is validated against the Stripe API on save — a typo here silently
breaks checkout, so it is checked at write time, not at purchase time.

---

## Content — `/admin/content/templates`

The global section templates that seed every new guide: key, name, icon,
category, default order, required feature, field schema, default copy per locale.

Changing a template affects **new guides only**. Existing guides keep their
sections; a separate, explicit "propagate to existing guides" action exists, shows
how many guides it would touch, and never overwrites content a host has edited —
it only adds sections that are missing.

Also here: recommendation categories, and the default copy pack per locale.

---

## Translations — `/admin/translations`

Platform-wide view of the machine-translation queue: pending jobs, failures, cost
per engine, and a review queue for accounts that asked for help. Staff can run a
translation pass on behalf of a customer in support mode.

---

## System

| Page | Contents |
| --- | --- |
| `/admin/system/settings` | Global settings: default locale, supported locales, file size limits, from-addresses, feature kill switches, maintenance mode |
| `/admin/system/audit` | Full `activity_logs` with filters by actor, account, action, date, impersonation session; CSV export |
| `/admin/system/webhooks` | `webhook_events` with status, attempts, last error, payload, replay |
| `/admin/system/jobs` | Queue depth, failure rate, dead-letter inspection and requeue |
| `/admin/system/health` | DB, Redis, storage, Stripe reachability, cache hit rate |

---

## Admin UX rules

1. **Every destructive action states its consequence in the confirmation**, in the
   customer's terms: not "Are you sure?" but "Casa San Francesco's guide will go
   offline and printed QR codes will stop working."
2. **Every table row leads to a detail page.** No modal-only records.
3. **Every filter is a URL.** Support staff paste links to each other.
4. **Every number links to its list.** "4 accounts paid but never activated" is a
   link, not a fact.
5. **Reasons are required, not optional**, on anything a customer might later
   query.
6. **Nothing is deleted.** Archive, suspend, redirect — never DELETE, except in
   the GDPR erasure pipeline, which is deliberate, two-person and audited.
