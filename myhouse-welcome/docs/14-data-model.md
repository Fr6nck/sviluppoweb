# 14 — Data model

PostgreSQL. 46 tables. Full DDL in [`schema/schema.sql`](../schema/schema.sql),
Prisma equivalent in [`schema/schema.prisma`](../schema/schema.prisma).

---

## Relational or JSON: the rule we applied

> **Relational** if it is queried, filtered, joined, counted, permission-checked,
> reported on, or referenced by a foreign key.
> **JSON** if it is a leaf whose shape varies by type and is only ever read as a
> whole, by the thing that wrote it.

Applied honestly, that leaves exactly six JSON columns in the whole schema. Each
one is justified below. Everything else — including things that are tempting to
blob, like guide content structure — is relational.

### The six JSON columns

| Column | Why JSON is right |
| --- | --- |
| `content_blocks.data` | A block's payload shape depends on its type: a `wifi` block has `ssid`/`password`, a `gallery` has an ordered media list, a `faq` has Q&A pairs. Fourteen types would mean fourteen sparse-columned tables or one table with 60 mostly-null columns. Each type has a versioned JSON Schema, validated on write. The **structure** (which blocks, in which section, in which order, enabled or not) is relational — only the leaf payload is JSON. |
| `guide_versions.snapshot` | A published snapshot is a read-optimised, immutable artifact. It is written once and read as a whole. Normalising it would mean reassembling a guide from 40 rows to serve a guest, which is exactly the latency we are avoiding. |
| `package_features.value_json` | Feature values are heterogeneous by design: `true`, `5`, `-1`, `"editorial"`. A typed column per shape would be three sparse columns and a discriminator. |
| `entitlements.value_json` | Same reason, same shape. |
| `activity_logs.changes` | A before/after diff of an arbitrary subject. It is written once, read by a human in a log viewer, never queried by its internals. |
| `webhook_events.payload` | The provider's document, stored verbatim so we can replay it. Altering it would destroy its value. |

Plus three small, bounded config objects — `guides.theme_json`,
`guides.branding_json`, `qr_codes.style_json` — which are single-row settings
blobs with a fixed schema and no query requirement.

### What we refused to put in JSON, and why

| Tempting blob | Kept relational because |
| --- | --- |
| The whole guide as one document | "Which guides have no Wi-Fi section?" and "how many guides use PDFs?" are real admin questions. Publishing, translation state and per-section permissions all need row identity. |
| Translations as `{"it": …, "en": …}` | Per-locale **status**, reviewer, timestamp, staleness and locking are the entire feature ([11](11-multilingual.md)). |
| Recommendations as an array on the guide | They are filtered by category, sorted, counted against a limit, and each one owns a media file. |
| Media as an array of URLs | Usage tracking, deletion safety, quota accounting, variants and scan status all need rows. |
| Entitlements computed on the fly from the package | Overrides, promos, grandfathering and "why does this account have this?" need a record ([05](05-packages-and-entitlements.md)). |

---

## Entity map

```
                                    ┌──────────┐
                                    │  users   │
                                    └────┬─────┘
                       user_roles ───────┤
                                    ┌────▼──────┐        ┌───────────────┐
                                    │ accounts  │───────▶│ package_      │
                                    └────┬──────┘  pin   │ versions      │
            ┌────────────┬───────────────┼──────────┐    └───────┬───────┘
            │            │               │          │            │
     ┌──────▼─────┐ ┌────▼────┐   ┌──────▼─────┐ ┌──▼──────┐  ┌──▼──────────────┐
     │ properties │ │ orders  │   │entitlements│ │  media  │  │ package_features│
     └──────┬─────┘ └────┬────┘   └────────────┘ └─────────┘  └──┬──────────────┘
            │            │                                       │
       ┌────▼────┐  ┌────▼─────┐                            ┌────▼────┐
       │ guides  │  │ payments │                            │features │
       └────┬────┘  └────┬─────┘                            └─────────┘
            │            └──▶ refunds, invoices
   ┌────────┼────────┬──────────────┬──────────────┬────────────────┐
   │        │        │              │              │                │
┌──▼─────┐ ┌▼──────┐ ┌▼──────────┐ ┌▼───────────┐ ┌▼─────────────┐ ┌▼──────────────┐
│ guide_ │ │guide_ │ │public_urls│ │ qr_codes   │ │recommendations│ │guide_versions│
│sections│ │locales│ └───────────┘ └────────────┘ └───────────────┘ └──────────────┘
└──┬─────┘ └───────┘
   │
┌──▼────────────┐        ┌──────────────┐
│content_blocks │───────▶│ translations │ (polymorphic)
└───────────────┘        └──────────────┘
```

---

## Tables by domain

### Identity and tenancy (8)

| Table | Purpose |
| --- | --- |
| `users` | People who log in. Email unique (case-insensitive via `citext`). `password_hash` nullable until activation. |
| `accounts` | The customer/tenant. Billing identity, Stripe customer, status, pinned package version. |
| `account_members` | `users × accounts` with a role. Enables teams; MVP writes exactly one row per account. |
| `roles` | `super_admin`, `admin_support`, `account_owner`, `account_editor`. |
| `permissions` | The keys from [02](02-roles-and-permissions.md). |
| `role_permissions` | Join. |
| `user_roles` | `user × role × account_id (nullable)`. Null = platform scope. |
| `auth_tokens` | Email verification, password reset, activation. Hashed, single-use, expiring. |

Sessions live in Redis, not Postgres — see [17](17-auth-and-security.md).

### Catalog and entitlements (5)

`features`, `packages`, `package_versions`, `package_features`, `entitlements`.
Semantics in [05](05-packages-and-entitlements.md).

Key constraints:
- `package_versions (package_id, version)` unique.
- Partial unique index: one `is_current = true` per package.
- `package_features (package_version_id, feature_id)` unique.
- `entitlements` has no unique constraint on `(account, feature)` — multiple rows
  with different sources and windows are the point; resolution picks the winner.

### Commerce (8)

`orders`, `payments`, `refunds`, `invoices`, `subscriptions`, `discount_codes`,
`discount_redemptions`, `webhook_events`.

Key constraints:
- `webhook_events.provider_event_id` unique — this single index is the entire
  idempotency mechanism.
- All money is `BIGINT` minor units plus a `CHAR(3)` currency. No `NUMERIC`, no
  floats.
- `orders.package_version_id` is `RESTRICT` on delete: a priced version that has
  been sold can never be removed.

### Content (10)

| Table | Notes |
| --- | --- |
| `properties` | `account_id`, name, type, address, coordinates, contacts, host identity, timezone. |
| `guides` | One per property in MVP. Status, default locale, published version pointer, completion, theme/branding, `has_unpublished_changes`. |
| `guide_versions` | Immutable published snapshots. `(guide_id, version_number)` unique. |
| `section_templates` | Global catalogue seeding new guides. Field schema per section. |
| `guide_sections` | Per-guide instance: key, icon, order, enabled, visibility, PIN hash, date window. |
| `content_blocks` | Ordered blocks within a section. Type + JSON payload + optional media. |
| `recommendation_categories` | System categories plus per-account custom ones. |
| `recommendations` | Places, with category, coordinates, contacts, host-pick flag. |
| `guide_locales` | Per guide: locale, default flag, enabled flag, completeness. |
| `locales` | Supported languages, admin-managed. |

### Media (3)

`media`, `media_variants`, `media_usages`. Semantics in [12](12-media-pipeline.md).

`media_usages` is what makes deletion safe and quotas honest. It is written by the
same service call that sets a media reference, never by a background reconciler,
so it cannot drift.

### Publishing (2)

`public_urls` (slug history with redirects), `qr_codes` (permanent tokens).
Semantics in [13](13-urls-slugs-qr.md).

### Translation (1)

`translations` — polymorphic, per field path, per locale, with state. See
[11](11-multilingual.md).

### Analytics (2)

`analytics_events` (raw, partitioned monthly, 14-month retention),
`analytics_daily` (rollups, kept indefinitely). See [18](18-analytics.md).

### Operations (7)

`activity_logs`, `admin_notes`, `impersonation_sessions`, `settings`,
`onboarding_progress`, `email_log`, `jobs_dead_letter`.

---

## Decisions worth defending

### UUIDv7 primary keys

`UUID` generated as v7 (time-ordered). Not `bigserial`, because IDs appear in
URLs, Stripe metadata and support conversations across services, and sequential
integers leak customer counts. Not UUIDv4, because random keys destroy B-tree
locality on insert-heavy tables like `analytics_events`.

A short human-readable reference (`MH-2026-0341`) is generated separately for
orders, because nobody reads a UUID over the phone.

### Soft delete, everywhere, with one exception

`deleted_at` on user-owned entities. Hard `DELETE` exists only in the GDPR
erasure pipeline, which is a deliberate, two-person, audited operation
([22](22-privacy-gdpr.md)).

All application queries go through a repository layer that applies
`WHERE deleted_at IS NULL` by default. Partial indexes match:

```sql
CREATE INDEX idx_properties_account ON properties(account_id) WHERE deleted_at IS NULL;
```

### Timestamps

Every table has `created_at` and `updated_at` as `TIMESTAMPTZ NOT NULL DEFAULT
now()`. `updated_at` is maintained by a trigger, not by the application, so a raw
SQL fix during an incident cannot leave a stale timestamp.

All storage is UTC. `properties.timezone` (IANA) exists so that time-windowed
section visibility and check-in reminders work in the property's local time, not
the server's.

### Ordering

`sort_order INTEGER` with gaps of 100 on insert. Reordering rewrites only the
moved row in the common case. A compaction job renumbers a list when gaps run out.
Fractional ordering was considered and rejected — it accumulates float precision
problems over hundreds of drags.

### Enums as check constraints, not Postgres enums

```sql
status TEXT NOT NULL DEFAULT 'draft'
  CHECK (status IN ('draft','published','unpublished'))
```

Adding a value to a Postgres `ENUM` requires `ALTER TYPE`, which historically
could not run in a transaction alongside other DDL and complicates rollbacks. A
`CHECK` constraint is dropped and recreated in one migration.

### `citext` for email

Case-insensitive uniqueness at the database level. `Marco@…` and `marco@…` are one
person, and the constraint says so rather than relying on every insert path to
lowercase first.

### No `ON DELETE CASCADE` above the guide level

Cascades are used *within* an aggregate — deleting a `guide_section` removes its
`content_blocks` — because those have no independent existence. Above that level
(`account`, `property`, `guide`) deletion is `RESTRICT`, and removal is a
service-level operation that archives, unpublishes, detaches media and writes an
audit record in the right order. A stray `DELETE FROM accounts` should fail, not
silently destroy a customer's business.

---

## Indexing strategy

Beyond primary and foreign keys:

```sql
-- the guest hot path: one lookup, covering
CREATE UNIQUE INDEX idx_public_urls_lookup ON public_urls(host, slug);
CREATE INDEX idx_guides_published ON guides(id) INCLUDE (published_version_id)
  WHERE status = 'published';

-- QR resolution
CREATE UNIQUE INDEX idx_qr_token ON qr_codes(token) WHERE revoked_at IS NULL;

-- entitlement resolution
CREATE INDEX idx_entitlements_lookup ON entitlements(account_id, feature_key)
  WHERE revoked_at IS NULL;

-- translation completeness, the most-run aggregate in the app
CREATE INDEX idx_translations_lookup
  ON translations(translatable_type, translatable_id, locale)
  INCLUDE (field_path, status);
CREATE INDEX idx_translations_pending ON translations(locale, status)
  WHERE status IN ('missing','machine_translated');

-- admin customer table
CREATE INDEX idx_accounts_admin ON accounts(status, created_at DESC)
  WHERE deleted_at IS NULL;

-- idempotency
CREATE UNIQUE INDEX idx_webhook_event_id ON webhook_events(provider, provider_event_id);

-- analytics rollups
CREATE INDEX idx_events_guide_time ON analytics_events(guide_id, occurred_at DESC);

-- full-text search across the admin customer table
CREATE INDEX idx_accounts_search ON accounts
  USING gin(to_tsvector('simple', coalesce(name,'') || ' ' || coalesce(legal_name,'')
            || ' ' || coalesce(vat_number,'') || ' ' || coalesce(billing_email,'')));
```

---

## Multi-tenancy isolation

Every account-owned table carries `account_id` directly, even where it could be
derived through a join. Denormalised deliberately, for three reasons:

1. **Row-level security** can be expressed simply:
   `CREATE POLICY tenant ON guides USING (account_id = current_setting('app.account_id')::uuid)`.
   RLS is enabled in the reference deployment as defence in depth behind the
   application's own scoping.
2. **A missing join clause becomes a constraint violation** rather than a cross-
   tenant data leak.
3. Admin queries filter by tenant without four-table joins.

The redundancy is protected by a trigger that asserts the denormalised
`account_id` matches the parent's on insert and update.

---

## Retention

| Data | Retention |
| --- | --- |
| `analytics_events` | 14 months, then dropped by partition |
| `analytics_daily` | Indefinite (aggregate, no personal data) |
| `activity_logs` | 24 months, then archived to cold storage |
| `webhook_events` | 12 months |
| `email_log` | 12 months |
| `guide_versions` | Last 20 per guide, plus the current one, always |
| Soft-deleted rows | 30 days, then purged |
| Archived accounts | 30 days to restore, then GDPR erasure eligible |
| Orders, payments, invoices | 10 years — Italian statutory accounting retention. Survives account deletion in pseudonymised form. |
