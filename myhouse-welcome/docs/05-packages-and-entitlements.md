# 05 — Packages and entitlements

> One product. Three price points. Zero forks in the code.

## The rule

There is no `if (package === 'pro')` anywhere in the application. There is one
question, asked the same way from the API, the UI and the publish pipeline:

```ts
const max = await entitlements.value(accountId, 'welcome.languages.max');  // 1 | 5 | 20
const can = await entitlements.enabled(accountId, 'welcome.analytics');    // boolean
```

A super admin changing what Plus includes edits rows. No deploy, no migration, no
code change.

---

## Four tables, one resolution order

```
features            the registry of everything that can be gated
packages            Essential / Plus / Pro — identity and ordering
package_versions    an immutable priced configuration of a package
package_features    version × feature → value
entitlements        what one account actually holds, and why
```

### Resolution

```
value(account, key):
  1. entitlements where source='override'  and now within [starts_at, ends_at)   → win
  2. entitlements where source='promo'     and now within window  → highest value
  3. entitlements where source='package'   (written at purchase, pinned version) → value
  4. features.default_value
```

Resolved values are cached in Redis under `ent:{accountId}` with a 5-minute TTL
and are busted explicitly on purchase, upgrade, override and package migration.
The cache is a performance detail; correctness lives in the table.

### Why `entitlements` is materialised rather than computed

It would be possible to join `account → package_version → package_features` on
every check. We write resolved rows instead because:

- **Overrides and promos need somewhere to live.** "Give this customer French for
  free because we broke their guide" is a real operation with a reason and an end
  date. It is not expressible in the package tables.
- **Grandfathering needs a record, not an inference.** When Plus stops including
  PDFs, the accounts that bought Plus when it did must keep them. A row that says
  so is auditable; a rule that says so is a bug waiting to happen.
- **The grant is the receipt.** `entitlements.granted_by`, `reason`,
  `package_version_id` and `created_at` answer "why does this account have this?"
  in one query during a support call.

---

## The feature registry

Every gate is a typed key. Types are `boolean`, `limit` (integer, `-1` = unlimited)
and `enum`.

| Key | Type | Essential | Plus | Pro |
| --- | --- | --- | --- | --- |
| `welcome.properties.max` | limit | 1 | 1 | 5 |
| `welcome.languages.max` | limit | 1 | 5 | 20 |
| `welcome.languages.auto_translate` | boolean | ✗ | ✗ | ✓ |
| `welcome.sections.core` | boolean | ✓ | ✓ | ✓ |
| `welcome.sections.services` | boolean | ✗ | ✓ | ✓ |
| `welcome.sections.appliances` | boolean | ✗ | ✓ | ✓ |
| `welcome.sections.waste` | boolean | ✗ | ✓ | ✓ |
| `welcome.sections.transport` | boolean | ✗ | ✓ | ✓ |
| `welcome.sections.recommendations` | boolean | ✗ | ✓ | ✓ |
| `welcome.sections.experiences` | boolean | ✗ | ✗ | ✓ |
| `welcome.sections.extras` | boolean | ✗ | ✗ | ✓ |
| `welcome.sections.custom.max` | limit | 0 | 3 | 20 |
| `welcome.media.images.max` | limit | 15 | 80 | 400 |
| `welcome.media.storage_mb` | limit | 50 | 400 | 2000 |
| `welcome.media.pdf` | boolean | ✗ | ✓ | ✓ |
| `welcome.media.video_embed` | boolean | ✗ | ✗ | ✓ |
| `welcome.recommendations.max` | limit | 0 | 40 | 200 |
| `welcome.branding.colors` | boolean | ✗ | ✓ | ✓ |
| `welcome.branding.typography` | boolean | ✗ | ✗ | ✓ |
| `welcome.branding.remove_powered_by` | boolean | ✗ | ✗ | ✓ |
| `welcome.guide.search` | boolean | ✗ | ✓ | ✓ |
| `welcome.guide.protected_sections` | boolean | ✗ | ✗ | ✓ |
| `welcome.guide.scheduled_visibility` | boolean | ✗ | ✗ | ✓ |
| `welcome.analytics.basic` | boolean | ✓ | ✓ | ✓ |
| `welcome.analytics.advanced` | boolean | ✗ | ✗ | ✓ |
| `welcome.qr.branded_card` | boolean | ✗ | ✗ | ✓ |
| `welcome.domain.custom` | boolean | ✗ | ✗ | ✓ |
| `welcome.support.priority` | boolean | ✗ | ✗ | ✓ |

Machine-readable source of truth: [`schema/features.json`](../schema/features.json).
Package composition: [`schema/seed-packages.json`](../schema/seed-packages.json).

`welcome.analytics.basic` is `true` for everyone including Essential: a host must
be able to see that their guide is being read. Only the depth (sections, locales,
outbound clicks, trends) is Pro.

---

## Package versions

A `package` is an identity (`essential`) with ordering and visibility. A
`package_version` is a priced, immutable configuration of it.

```
packages
  key=plus, name="Plus", sort_order=2, is_public=true, is_recommended=true

package_versions
  id=pv_plus_1  version=1  price=8900 EUR  stripe_price_id=price_1Ab…  effective 2026-01-01 → 2026-06-30
  id=pv_plus_2  version=2  price=9900 EUR  stripe_price_id=price_1Cd…  effective 2026-07-01 → null   [current]
```

**Rules enforced by the service, not by convention:**

1. A version that has been referenced by at least one `order` is **frozen**. Its
   price, Stripe price ID and features can no longer be edited. The admin UI
   shows it read-only with "In use by 41 customers — create a new version to
   change it."
2. Editing a current, unused version edits it in place.
3. Editing a current, used version opens a **new draft version** prefilled from
   it. Publishing the draft sets `effective_to` on the old one and makes the new
   one current.
4. Accounts pin `package_version_id` at purchase. New customers get the current
   version; existing customers keep theirs forever unless migrated.

This is the whole answer to *"existing customers must not unexpectedly lose
purchased features"*. It is structural, not procedural.

### Migrating accounts between versions

Admin action with a mandatory preview:

```
Migrate 41 accounts from Plus v1 → Plus v2

  Gains:   welcome.guide.search            41 accounts
  Loses:   welcome.media.storage_mb  400 → 250    41 accounts   ⚠

  ○ Migrate and grandfather losses   (writes an override preserving 400 MB)   ← default
  ○ Migrate and apply losses         (requires typing MIGRATE to confirm)
  ○ Cancel
```

The default never takes something away. Taking something away is possible, loud,
logged, and notifies affected customers by email 14 days ahead.

### Never silently remove

Three places where the temptation exists, and what happens instead:

| Situation | Behaviour |
| --- | --- |
| Admin removes a feature from a package version | Only affects new purchases. Existing accounts are on frozen versions. |
| Admin migrates accounts to a version with fewer features | Grandfathering override written by default (above). |
| Refund / suspension | Guide is unpublished; entitlements and content are untouched. Reactivation restores everything. |

The one case where capability genuinely shrinks is a **downgrade purchased by the
customer** — not offered in MVP, and when it is, it will warn with a precise list
("your guide has 4 languages; Plus allows 1 — choose which to keep") before
taking payment.

---

## Enforcement points

An entitlement is checked in exactly three kinds of place. Anything else is a
leak.

### 1. API guards (authoritative)

```ts
router.post('/guide/languages',
  requirePermission('translation.edit'),
  requireEntitlement('welcome.languages.max', { as: 'limit', countFrom: countGuideLocales }),
  handler)
```

A limit guard receives the current count and refuses with a structured error the
UI can render without string-matching:

```json
{ "error": "entitlement_limit_reached",
  "feature": "welcome.languages.max",
  "limit": 1, "current": 1,
  "requiredPackage": "plus",
  "message": "Your package includes 1 language." }
```

### 2. UI affordances (cosmetic)

The app boots with an entitlement map. Gated items render muted with an
`Available with Plus` chip and a single explanatory screen. The UI never hides a
feature entirely — a host should know the product can do more, without being
nagged.

### 3. The publish pipeline (the safety net)

Publishing re-validates the whole guide against current entitlements. This
catches the case where a feature was revoked after the content was created: the
content stays in the database, is excluded from the published snapshot, and the
host is told precisely what was left out.

```
Published with 2 sections not included:
  • Experiences — available with Pro
  • Third language (Deutsch) — your package includes 1 language
```

---

## Adding a fourth package

Everything needed:

1. Insert a row into `packages`.
2. Insert a `package_version` with a Stripe price ID.
3. Set `package_features` values.
4. Set `is_public = true`.

The marketing pricing page, the comparison table, the checkout route, the upgrade
screen and the admin filters all read from the same source and pick it up. No
code is written to sell a new package.
