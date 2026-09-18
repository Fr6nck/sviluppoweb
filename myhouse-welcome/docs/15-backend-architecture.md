# 15 — Backend architecture

## Shape

A modular monolith with extracted workers. Not microservices: this is one product
with one database and a team small enough to fit in a room. Splitting it now
would buy nothing and cost a distributed transaction on every purchase.

```
                       ┌──────────────── edge / CDN ────────────────┐
                       │  static marketing · guide snapshots · media │
                       └───────────┬────────────────────────────────┘
                                   │ miss
              ┌────────────────────┼────────────────────────┐
              │                    │                        │
      ┌───────▼───────┐   ┌────────▼────────┐    ┌──────────▼─────────┐
      │  web (app)    │   │  web (guide)    │    │  api               │
      │  dashboard    │   │  public render  │    │  REST + webhooks   │
      │  admin        │   │  read-only      │    │                    │
      └───────┬───────┘   └────────┬────────┘    └──────────┬─────────┘
              └────────────────────┴────────────────────────┘
                                   │
                    ┌──────────────▼──────────────┐
                    │        domain services       │
                    │  auth · entitlements · guide │
                    │  publish · media · billing   │
                    │  translation · analytics     │
                    └──┬────────┬────────┬─────────┘
                       │        │        │
              ┌────────▼──┐ ┌───▼────┐ ┌─▼──────────┐
              │ PostgreSQL│ │ Redis  │ │ Object     │
              │ (truth)   │ │ cache  │ │ store (S3) │
              └───────────┘ │ queue  │ └────────────┘
                            │ session│
                            └───┬────┘
                                │
                     ┌──────────▼──────────┐
                     │      workers        │
                     │ media · publish     │
                     │ translate · email   │
                     │ rollup · webhook    │
                     └─────────────────────┘
```

## Reference stack

| Concern | Choice | Why |
| --- | --- | --- |
| Language | TypeScript, Node 22 | One language across three surfaces; the team is a web team |
| Framework | Next.js 15 (App Router) | Static marketing, static guide snapshots and an authenticated SPA in one deployable |
| API | REST route handlers + Zod | Explicit, cacheable, easy to consume from anywhere. See [16](16-api-reference.md) |
| ORM | Prisma | Typed access. Raw SQL for the publish read and analytics rollups |
| Database | PostgreSQL 16 | Relational content model, JSONB where it earns it, partitioning, RLS |
| Cache / queue / session | Redis (+ BullMQ) | One dependency for three jobs at this scale |
| Object store | S3-compatible (Cloudflare R2) | No egress fees, CDN in front |
| Image processing | sharp, in a worker | AVIF/WebP/JPEG variants, HEIC decode |
| Email | Transactional provider + MJML | Templated, localised, logged in `email_log` |
| Payments | Stripe hosted Checkout | PCI scope stays SAQ-A |
| Errors / tracing | Sentry + OpenTelemetry | |
| Hosting | Containers behind a CDN | Nothing here needs a specific vendor |

The stack is a recommendation, not a requirement of the design. Everything in
[14](14-data-model.md) and [16](16-api-reference.md) holds if this is built in
Laravel or Django instead.

---

## Request pipeline

```
request
  → rate limit            (per IP, per user, per endpoint class)
  → body size limit
  → CORS
  → session resolve       (Redis; rotating token)
  → actor build           { user, roles, accountId, impersonation }
  → RLS context           SET LOCAL app.account_id = …
  → route match
  → permission guard      can(actor, 'guide.publish', { accountId })
  → entitlement guard     requireEntitlement('welcome.languages.max')
  → Zod input validation
  → service call          ← business logic lives here, not in the handler
  → audit write           (inside the service, same transaction)
  → serialise             (role-aware; strips Stripe IDs for account actors)
  → response
```

Two invariants:

1. **Handlers contain no business logic.** They validate, call one service
   method, and serialise. This is what makes the same operation reachable from
   the API, a worker and an admin action without drifting.
2. **The audit write is inside the service, in the same transaction as the
   change.** A new endpoint cannot forget to log, and a rolled-back change
   cannot leave a log entry claiming it happened.

---

## Domain services

| Service | Owns |
| --- | --- |
| `AuthService` | Registration, activation, login, sessions, password reset, MFA |
| `AccountService` | Account lifecycle, members, billing identity, archive, erasure |
| `EntitlementService` | Resolution, caching, overrides, package migration |
| `BillingService` | Checkout sessions, webhook handling, refunds, upgrades, invoices |
| `PropertyService` | Properties, slugs, property cloning |
| `GuideService` | Sections, blocks, ordering, completion, draft state |
| `PublishService` | Snapshot build, version write, cache purge, unpublish |
| `MediaService` | Presign, complete, process, variants, usages, quota |
| `TranslationService` | State machine, machine runs, completeness, glossary |
| `AnalyticsService` | Ingest, rollup, query |
| `AuditService` | Activity log, impersonation sessions |
| `NotificationService` | Email templates, localisation, delivery log |

Services call each other through interfaces, never through each other's tables.
`PublishService` asks `EntitlementService` what to include; it does not read
`entitlements`.

---

## The publish pipeline

The most important write path in the system.

```
POST /api/guide/publish
  │
  ├─ 1. authorise        permission + account active + payment completed
  ├─ 2. validate         every section against its schema
  ├─ 3. entitlement pass exclude what the package no longer allows,
  │                      collect a human-readable list of exclusions
  ├─ 4. resolve media    variant URLs, dimensions, blur placeholders
  ├─ 5. promote          reviewed translations → published
  ├─ 6. per locale       build a complete snapshot; drop locales under threshold
  ├─ 7. compute          distances, search index, completion percentage
  ├─ 8. transaction      INSERT guide_versions
  │                      UPDATE guides SET published_version_id, status,
  │                             has_unpublished_changes = false
  │                      INSERT activity_logs
  ├─ 9. after commit     purge edge cache for every (host, slug, locale)
  │                      warm the cache with a HEAD request
  └─10. respond          { url, version, excluded[] }
```

Steps 1–7 are pure: they take the current database state and return a snapshot.
That makes the whole pipeline testable without a database write, and makes
preview and publish the same code path with a different final step.

**Rollback** is `UPDATE guides SET published_version_id = <older version>` plus a
purge. Snapshots are immutable, so a bad publish is one click to undo — which is
what makes the publish button safe to press.

---

## Background jobs

| Queue | Job | Trigger | Retries |
| --- | --- | --- | --- |
| `media` | `media.process` | Upload completed | 3, exponential |
| `media` | `media.purge` | Nightly | 3 |
| `publish` | `publish.warm` | After publish | 2 |
| `translate` | `translation.run` | Host request | 3, per-field granularity |
| `email` | `email.send` | Various | 5, exponential |
| `billing` | `billing.webhook` | Stripe webhook received | 5, then dead letter |
| `billing` | `billing.reconcile` | Nightly | 1, alerts on drift |
| `analytics` | `analytics.rollup` | Hourly | 3 |
| `analytics` | `analytics.partition` | Monthly | 1, alerts |
| `lifecycle` | `lifecycle.nudge` | Daily | 1 |

Every job is idempotent and keyed so a retry cannot double-charge, double-send or
double-count. Failures after the final retry land in `jobs_dead_letter`, visible
and requeueable at `/admin/system/jobs`.

---

## Caching

| What | Where | TTL | Invalidation |
| --- | --- | --- | --- |
| Guide snapshot HTML | Edge | `s-maxage=300, swr=86400` | Purge on publish, slug change, unpublish |
| Media variants | Edge | 1 year, immutable | Never — a replace creates a new ID |
| Entitlement map | Redis | 5 min | Explicit on purchase, upgrade, override, migration |
| Package catalogue | Redis | 10 min | Explicit on package write |
| Session | Redis | 30 days sliding | On logout, password change, role change |
| Slug → guide | Redis | 1 hour | On slug change |

`stale-while-revalidate` is what keeps a published guide reachable when the
origin is down: a guest gets yesterday's door code rather than an error page.
For this product that is unambiguously the right trade.

---

## Configuration and secrets

All configuration is environment variables, validated at boot by a Zod schema.
The process **refuses to start** on a missing or malformed value, rather than
failing on the first request that needs it.

```
DATABASE_URL                 REDIS_URL
STRIPE_SECRET_KEY            STRIPE_WEBHOOK_SECRET
S3_ENDPOINT S3_BUCKET S3_ACCESS_KEY_ID S3_SECRET_ACCESS_KEY
DEEPL_API_KEY                EMAIL_API_KEY
SESSION_SECRET               ANALYTICS_SALT_SECRET
APP_URL  GUIDE_URL  MARKETING_URL  QR_SHORT_URL
```

Two guards against leaking a secret to a browser:

1. A boot assertion that no variable matching `/^(sk_|whsec_|rk_)/` appears in
   any client-exposed config object.
2. A CI step that greps the built client bundle for `sk_live`, `whsec_` and the
   database host. It fails the build, not the deploy.

Secrets are held in the platform's secret manager and rotated quarterly; the
Stripe webhook secret supports two active values during rotation.

---

## Observability

**Structured logs** — JSON, with `request_id`, `actor_id`, `account_id`,
`impersonation_session_id`. Never a password, token, door code, Wi-Fi password or
full card detail: the serialiser masks any field marked `sensitive` in the field
schema, so a new sensitive field is protected by declaring it, not by remembering.

**Metrics that matter here**, as opposed to generic ones:

- `guide.publish.duration` and failure rate
- `guide.render.cache_hit_ratio` — the number the guest experience depends on
- `checkout.webhook.lag` — seconds between Stripe event and provisioning
- `media.process.duration` by input type (HEIC is the slow one)
- `entitlement.cache.hit_ratio`
- `translation.run.characters` per account, for cost control

**Alerts**: webhook failure rate > 1%, provisioning lag > 120 s, publish failure
rate > 2%, cache hit ratio < 90%, dead-letter queue non-empty, reconciliation
drift non-zero.

---

## Resilience

| Dependency | If it is down |
| --- | --- |
| Stripe | Checkout is unavailable; a clear message and a "notify me" capture. Existing guides unaffected. |
| Object store | Uploads fail with a retry; **published guides keep serving** because variants are on the CDN. |
| Redis | Sessions fail closed (users are logged out); entitlements fall back to a direct database read; guest guides are unaffected. |
| PostgreSQL | The application is down. **Published guides keep serving from the edge** — the single most important property of the snapshot design. |
| Translation API | Machine runs queue and retry; manual translation is unaffected. |
| Email | Queued and retried; activation links are recoverable from the admin console. |

The pattern throughout: a guest holding a printed QR code should be the last
person affected by any outage.

## Backups

- PostgreSQL: continuous WAL archiving, point-in-time recovery to any second in
  the last 7 days, nightly full retained 30 days, weekly retained 12 months.
- Object store: versioning on, cross-region replication, 30-day soft delete.
- **Restores are rehearsed quarterly** into a scratch environment, and the runbook
  is only considered valid after a rehearsal that someone other than its author
  followed.
