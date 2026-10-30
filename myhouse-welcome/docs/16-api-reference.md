# 16 — API reference

Three consumers, three prefixes, three sets of rules.

| Prefix | Consumer | Auth | Notes |
| --- | --- | --- | --- |
| `/api/public/**` | Marketing, guest guide | none | Cacheable, rate-limited by IP |
| `/api/**` | Host dashboard | session | Scoped to the actor's account |
| `/api/admin/**` | Admin console | session + platform role | Returns `404` to everyone else |

## Conventions

- JSON in, JSON out. `Content-Type: application/json`.
- Money is always `{ "amountCents": 9900, "currency": "EUR" }`. Never a float,
  never a formatted string.
- Timestamps are RFC 3339 UTC: `2026-09-18T14:02:11Z`.
- IDs are UUIDs as strings.
- Collections are `{ "data": [...], "meta": { "total", "page", "perPage" } }`.
- Mutations accept an `Idempotency-Key` header; replays return the original
  response.
- `PATCH` bodies are partial. Omitted means unchanged; `null` means clear.

### Error shape

One shape, always, so the client never string-matches a message.

```json
{
  "error": {
    "code": "entitlement_limit_reached",
    "message": "Your package includes 1 language.",
    "field": "locale",
    "details": {
      "feature": "welcome.languages.max",
      "limit": 1,
      "current": 1,
      "requiredPackage": "plus"
    }
  }
}
```

`message` is already localised to the actor's locale and safe to display.
`code` is stable and safe to branch on.

| Code | HTTP | Meaning |
| --- | --- | --- |
| `validation_failed` | 422 | Field-level; `details.fields[]` carries each |
| `unauthenticated` | 401 | No session |
| `forbidden` | 403 | Authenticated, not permitted |
| `not_found` | 404 | Also returned for admin routes to non-staff |
| `entitlement_limit_reached` | 402 | Package limit hit |
| `entitlement_required` | 402 | Feature not in the package |
| `conflict` | 409 | Slug taken, concurrent edit |
| `rate_limited` | 429 | `Retry-After` set |
| `payment_required` | 402 | Account suspended or unpaid |
| `upstream_unavailable` | 503 | Stripe, storage or translation engine down |

---

## Public

```http
GET /api/public/packages
```
The pricing page's entire data source. Returns public packages with their current
version, price and visible features. No auth, cached 10 minutes.

```json
{ "data": [
  { "key": "plus", "name": "Plus", "tagline": "The whole house explained…",
    "isRecommended": true,
    "price": { "amountCents": 9900, "currency": "EUR", "interval": "one_time" },
    "features": [
      { "key": "welcome.languages.max", "name": "Languages", "value": 5, "type": "limit" },
      { "key": "welcome.media.pdf", "name": "PDF files", "value": true, "type": "boolean" }
    ],
    "bullets": ["Everything in Essential", "Services and amenities", "…"] } ] }
```

```http
POST /api/public/checkout
     { "packageKey": "plus", "locale": "it", "email": "marco@example.com" }
  → 200 { "checkoutUrl": "https://checkout.stripe.com/…", "orderId": "…" }
```

```http
GET  /api/public/guides/:host/:slug            → published snapshot (or 404)
GET  /api/public/guides/:host/:slug/:section   → one section
POST /api/public/guides/:id/sections/:key/unlock  { "pin": "4821" }
GET  /api/public/slug-available?slug=casa-san-francesco
POST /api/public/events                        → analytics beacon, fire and forget
GET  /q/:token                                 → 302 to the current primary URL
```

`GET /api/public/guides/*` returns only `published` content. There is no
parameter, header or role that makes it return a draft — preview uses a
different, authenticated endpoint. That separation is what guarantees a draft can
never leak.

---

## Host application

### Session

```http
POST   /api/auth/login              { email, password }        → sets cookie
POST   /api/auth/logout
POST   /api/auth/activate           { token, password }
POST   /api/auth/forgot-password    { email }                  → always 204
POST   /api/auth/reset-password     { token, password }
POST   /api/auth/verify-email       { token }
GET    /api/auth/session            → { user, account, roles, entitlements }
```

`GET /api/auth/session` returns the entitlement map with the session, so the app
never renders a gated affordance before it knows whether it is available. One
round trip, no flicker.

```json
{ "user":    { "id": "…", "fullName": "Marco Bianchi", "locale": "it-IT" },
  "account": { "id": "…", "name": "Casa San Francesco", "status": "active",
               "package": { "key": "plus", "name": "Plus", "version": 1 } },
  "roles": ["account_owner"],
  "entitlements": {
    "welcome.languages.max":   { "value": 5,     "source": "package" },
    "welcome.media.pdf":       { "value": true,  "source": "package" },
    "welcome.analytics.advanced": { "value": false, "requiredPackage": "pro" }
  },
  "onboarding": { "complete": false, "currentStep": "arrival", "percent": 18 } }
```

### Guide and content

```http
GET    /api/guide                                   → guide + sections summary
PATCH  /api/guide                                   { defaultLocale, themeJson, … }
POST   /api/guide/publish                           → { url, version, excluded[] }
POST   /api/guide/unpublish
POST   /api/guide/rollback                          { versionId }
GET    /api/guide/preview                           → draft snapshot (authenticated)

GET    /api/guide/sections
POST   /api/guide/sections                          { title, icon }      ← custom
PATCH  /api/guide/sections/:key                     { title, isEnabled, … }
DELETE /api/guide/sections/:key
POST   /api/guide/sections/reorder                  { order: ["welcome","wifi",…] }

GET    /api/guide/sections/:key/blocks
POST   /api/guide/sections/:key/blocks              { type, data }
PATCH  /api/guide/blocks/:id                        { data, isEnabled }
DELETE /api/guide/blocks/:id
POST   /api/guide/sections/:key/blocks/reorder      { order: [id, id, …] }
```

`PATCH /api/guide/blocks/:id` is the autosave endpoint and is therefore the
highest-traffic authenticated write. It accepts a partial `data` object, merges
at the top level, validates against the block type's JSON Schema, sets
`has_unpublished_changes`, and returns the saved representation plus the new
completion percentage — so the progress ring updates without a second request.

### Recommendations, media, languages

```http
GET    /api/guide/recommendations?category=restaurants
POST   /api/guide/recommendations                   { name, categoryId, … }
POST   /api/guide/recommendations/import            { mapsUrl }   ← extracts fields
PATCH  /api/guide/recommendations/:id
DELETE /api/guide/recommendations/:id

POST   /api/media/presign        { filename, mime, bytes, sha256 }
                                 → { mediaId, uploadUrl, headers } | { mediaId, deduped: true }
POST   /api/media/:id/complete   → { status: "processing" }
GET    /api/media/:id            → { status, variants[], usages[] }
GET    /api/media?kind=image&unused=true
PATCH  /api/media/:id            { altText, title, focalPoint }
POST   /api/media/:id/replace    { newMediaId }     ← every usage updates at once
DELETE /api/media/:id            → 409 with usages[] if in use

GET    /api/guide/locales
POST   /api/guide/locales                           { locale }
DELETE /api/guide/locales/:locale
PATCH  /api/guide/locales/:locale                   { isEnabled }
GET    /api/guide/translations?locale=de-DE&status=missing
PATCH  /api/guide/translations/:id                  { valueText, status }
POST   /api/guide/translations/accept               { ids: [...] }   ← "Looks good"
POST   /api/guide/translations/run                  { targetLocales, scope }
GET    /api/guide/translations/run/:jobId           → SSE progress
```

### QR, statistics, account

```http
GET    /api/guide/qr                    → { token, url, scanCount }
GET    /api/guide/qr/:format            → png | svg | pdf
POST   /api/guide/qr/regenerate         { confirm: true }   ← invalidates printed codes
PATCH  /api/guide/url                   { slug }            → old slug becomes a redirect

GET    /api/statistics?from=&to=        → basic; advanced fields require the entitlement
GET    /api/statistics/sections
GET    /api/statistics/outbound

GET    /api/account
PATCH  /api/account                     { name, vatNumber, billingAddress, … }
GET    /api/account/orders
GET    /api/account/invoices/:id        → signed URL
POST   /api/account/upgrade             { toPackageKey } → { checkoutUrl }
POST   /api/account/export              → queues a GDPR export, emails a link
POST   /api/account/delete              { password, confirm } → 30-day grace
```

### Onboarding

```http
GET    /api/onboarding                  → { wizardVersion, currentStep, completed[], schema }
PATCH  /api/onboarding/:step            { fields }   ← autosave, writes real content
POST   /api/onboarding/:step/skip
POST   /api/onboarding/complete
```

`GET /api/onboarding` returns the wizard **schema** as well as the progress, with
steps the account is not entitled to already removed. The client renders whatever
it is given; adding a question is a data change.

---

## Admin

Everything under `/api/admin/**` requires a platform role and returns `404`
otherwise. Every mutation writes `activity_logs`. Endpoints marked ⚠ require a
`reason` in the body and reject without one.

```http
GET    /api/admin/dashboard                     → KPIs + the "needs attention" queue
GET    /api/admin/customers?q=&status=&package=&completion=
GET    /api/admin/customers/:id
PATCH  /api/admin/customers/:id
POST   /api/admin/customers                     ⚠ manual account creation
POST   /api/admin/customers/:id/suspend         ⚠
POST   /api/admin/customers/:id/reactivate
POST   /api/admin/customers/:id/archive         ⚠
POST   /api/admin/customers/:id/resend-welcome
POST   /api/admin/customers/:id/reset-onboarding ⚠
POST   /api/admin/customers/:id/package         ⚠ { packageVersionId }
POST   /api/admin/customers/:id/entitlements    ⚠ { featureKey, value, endsAt }
GET    /api/admin/customers/:id/activity
POST   /api/admin/customers/:id/notes           { body, isPinned }

POST   /api/admin/impersonation                 ⚠ { accountId, durationMinutes }
DELETE /api/admin/impersonation/:id
GET    /api/admin/impersonation/active

GET    /api/admin/orders?status=&from=&to=
GET    /api/admin/orders/:id
POST   /api/admin/orders/:id/refund             ⚠ { amountCents? }
POST   /api/admin/orders/:id/resend-invoice

GET    /api/admin/packages
POST   /api/admin/packages
PATCH  /api/admin/packages/:key                 { name, sortOrder, isPublic, isRecommended }
POST   /api/admin/packages/:key/versions        ← the only way to change a sold version
PATCH  /api/admin/packages/:key/versions/:v     → 409 if frozen
POST   /api/admin/packages/:key/versions/:v/publish
POST   /api/admin/packages/migrate              ⚠ { fromVersionId, toVersionId, grandfather }
GET    /api/admin/packages/migrate/preview      → who gains what, who loses what

GET    /api/admin/discounts
POST   /api/admin/discounts                     ← writes to Stripe first, then locally
PATCH  /api/admin/discounts/:id

GET    /api/admin/templates
POST   /api/admin/templates
PATCH  /api/admin/templates/:key
POST   /api/admin/templates/:key/propagate      ⚠ adds missing sections; never overwrites

GET    /api/admin/locales
POST   /api/admin/locales
GET    /api/admin/translations/queue

GET    /api/admin/audit?actor=&account=&action=&from=&to=
GET    /api/admin/webhooks?status=
POST   /api/admin/webhooks/:id/replay
GET    /api/admin/jobs
POST   /api/admin/jobs/:id/requeue
GET    /api/admin/settings
PATCH  /api/admin/settings
```

### `POST /api/admin/packages/migrate` — the shape that matters

```json
// request
{ "fromVersionId": "pv_plus_1", "toVersionId": "pv_plus_2",
  "grandfather": true, "reason": "Plus v2 adds search; keeping v1 storage limits" }

// preview response
{ "affectedAccounts": 41,
  "gains": [{ "feature": "welcome.guide.search", "accounts": 41 }],
  "losses": [{ "feature": "welcome.media.storage_mb", "from": 400, "to": 250, "accounts": 41 }],
  "willGrandfather": [{ "feature": "welcome.media.storage_mb", "preservedValue": 400 }] }
```

With `grandfather: true` (the default) every loss becomes an `override`
entitlement preserving the old value. With `grandfather: false` the request is
rejected unless it also carries `"confirm": "MIGRATE"`, and affected customers
are emailed 14 days before the change takes effect.

---

## Webhooks

```http
POST /api/webhooks/stripe
```

Raw body, signature verified before parsing, deduplicated on
`(provider, provider_event_id)`, enqueued, `200` within ~50 ms. Full semantics in
[06](06-billing-stripe.md#webhooks).

---

## Rate limits

| Class | Limit |
| --- | --- |
| Guest guide render | 240 / min / IP |
| Analytics beacon | 60 / min / IP, silently dropped above |
| Login | 5 / 15 min / email **and** 20 / 15 min / IP |
| Password reset request | 3 / hour / email |
| Section PIN attempt | 5 / 15 min / IP / section |
| Autosave (`PATCH` blocks) | 600 / min / session |
| Media presign | 60 / hour / account |
| Translation run | 5 / hour / account |
| Slug change | 3 / 30 days / account |
| Admin read | 600 / min |

Login limits are per-email *and* per-IP so that neither a distributed attack on
one account nor a single host behind a shared NAT is handled wrongly.

---

## Versioning

No version in the path. The only external consumers are our own three clients,
deployed with the API. Changes are additive; a breaking change to a response
shape is gated behind an `Accept: application/vnd.myhouse.v2+json` header, and
the old shape is kept for one release cycle.

If a partner integration ever needs a stable contract, it gets `/api/v1/**` as a
thin, separately-versioned façade over the same services — not a fork of them.
