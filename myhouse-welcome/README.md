# MyHouse Welcome

**Everything your guests need. In one place.**

MyHouse Welcome is the digital guest guide product of the MyHouse ecosystem. An
accommodation owner buys a package, answers a guided set of simple questions, and
gets a fast, mobile-first welcome guide for their guests — reachable by link and by
QR code, with no app to install.

This directory is the complete product definition: information architecture, user
flows, entitlement logic, data model, backend architecture, API surface, design
system, and a clickable prototype.

---

## What is in here

| Path | What it is |
| --- | --- |
| `app/` | **The working application** — PHP 8 + SQLite, no dependencies, deployable over FTP |
| `docs/` | The product and engineering specification (24 documents) |
| `schema/` | PostgreSQL DDL, Prisma schema, package seed data, wizard definition |
| `prototype/` | A clickable, framework-free HTML/CSS/JS prototype of every surface |

### The working application

`app/` is not a mock: it registers accounts, takes Stripe payments, resolves
entitlements against frozen package versions, publishes immutable guide
snapshots, generates QR codes from scratch, and lets an administrator enter a
customer's account without ever touching their password. It has no Composer, no
Node, no build step — `./costruisci-pacchetto.sh` produces a zip you upload into
a folder on shared hosting, and it works whether or not the server rewrites URLs.

Its surfaces follow the **Fauna** direction of the design canvas: full-bleed
photography, solid colour tiles, Gloock on headings and Onest on everything else,
and a night theme for the Wi-Fi page — the one guests look for in the dark. A
fine sand grain runs under every surface — but never under a control, so buttons
and calls to action stay smooth against it, and never over photographs, text or icons —
the two navigation bars are glass at 40% with a blurred backdrop, and there is a
light/dark switch that follows the system until someone chooses otherwise.

At install time it offers **three sample customers** (Lucia in Montepulciano,
Marco in Lecce, Agnese in Ortigia) with published guides, photographs,
translations and thirty days of analytics, so the product can be seen inhabited
rather than empty. They are real accounts with a documented password and the
admin dashboard nags until they are removed; one button deletes them, their
guides and their photographs.

Read [`app/LEGGIMI.md`](app/LEGGIMI.md) for installation and the address map.

### Documentation index

**Product**
1. [Product vision](docs/01-product-vision.md) — problem, principle, positioning, success metrics
2. [Roles and permissions](docs/02-roles-and-permissions.md) — Super Admin, Client/Host, Guest, permission matrix
3. [Information architecture](docs/03-information-architecture.md) — the four surfaces and every screen in them
4. [User journeys](docs/04-user-journeys.md) — end-to-end flows with failure branches
5. [Packages and entitlements](docs/05-packages-and-entitlements.md) — one product, feature flags, version pinning
6. [Billing and Stripe](docs/06-billing-stripe.md) — checkout, webhooks, refunds, upgrades, discount codes

**Surfaces**
7. [Onboarding wizard](docs/07-onboarding-wizard.md) — 17 steps, conditional logic, schema-driven
8. [Client dashboard and CMS](docs/08-client-dashboard.md) — home, section editor, live preview, autosave
9. [Guest guide](docs/09-guest-guide.md) — mobile-first public experience
10. [Admin console](docs/10-admin-console.md) — customers, orders, packages, support mode

**Systems**
11. [Multilingual architecture](docs/11-multilingual.md) — translation states, machine translation, never overwrite reviewed
12. [Media pipeline](docs/12-media-pipeline.md) — upload, HEIC, variants, safety, media library
13. [URLs, slugs and QR codes](docs/13-urls-slugs-qr.md) — permanent QR identity, slug history, redirects
14. [Data model](docs/14-data-model.md) — every entity, why relational vs. JSON
15. [Backend architecture](docs/15-backend-architecture.md) — services, jobs, caching, storage, observability
16. [API reference](docs/16-api-reference.md) — REST surface for all three consumers
17. [Authentication and security](docs/17-auth-and-security.md) — sessions, hashing, impersonation, threat model
18. [Analytics](docs/18-analytics.md) — cookieless events, rollups, Pro-tier depth

**Craft**
19. [Design system](docs/19-design-system.md) — tokens, components, motion, the MyHouse identity
20. [Content, empty and error states](docs/20-content-states-and-copy.md) — UX writing rules and the full copy deck
21. [Accessibility and performance](docs/21-accessibility-and-performance.md) — WCAG targets, budgets, rendering strategy
22. [Privacy and GDPR](docs/22-privacy-gdpr.md) — lawful bases, retention, export, deletion
23. [Roadmap](docs/23-roadmap.md) — MVP scope, Phase 2, explicit non-goals
24. [Decision log](docs/24-decision-log.md) — the twelve decisions that shape everything else

### Schema and definitions

| File | Purpose |
| --- | --- |
| `schema/schema.sql` | PostgreSQL DDL — 46 tables, indexes, constraints, triggers |
| `schema/schema.prisma` | The same model as a Prisma schema for the application layer |
| `schema/seed-packages.json` | Essential / Plus / Pro as data, not code |
| `schema/features.json` | The feature registry that drives entitlements |
| `schema/wizard-schema.json` | The onboarding wizard as a declarative definition |

### Prototype

Open `prototype/index.html` in a browser — no build, no install.

```bash
python3 -m http.server 8000   # then visit /myhouse-welcome/prototype/
```

| Screen | File |
| --- | --- |
| Prototype hub | `prototype/index.html` |
| Marketing site + pricing + FAQ | `prototype/marketing.html` |
| Checkout and account activation | `prototype/checkout.html` |
| Onboarding wizard | `prototype/onboarding.html` |
| Client dashboard, CMS, live preview, QR, languages | `prototype/dashboard.html` |
| Guest guide | `prototype/guide.html` |
| Admin console | `prototype/admin.html` |
| Design system reference | `prototype/design-system.html` |

The prototype persists state in `localStorage`, so what you type in onboarding
appears in the dashboard, in the live preview and in the guest guide.

### Verification

Nothing in this deliverable is asserted without being checked. Four suites run
against the real artifacts:

| Command | What it proves |
| --- | --- |
| `psql -d mhw -f schema/schema.sql && psql -d mhw -f schema/tests.sql` | 27 cases covering the guarantees the design depends on — a sold package version cannot change, a machine translation cannot overwrite human work, tenant isolation holds, a Stripe event processes once. Verified against PostgreSQL 16. |
| `python3 schema/validate.py` | The three configuration files agree: every feature a package prices exists, values match their declared types, tiers are monotonic, every wizard gate resolves. |
| `node prototype/walkthrough.mjs` | 45 browser assertions across the whole product: conditional wizard logic, entitlement gating, the publish bar, support mode requiring a reason, keyboard access, no horizontal scroll at 320px, dark mode. |
| `node prototype/contrast-check.mjs` | Every visible text node on every page measured against its effective background at the WCAG AA threshold. |
| `app/prove/esegui.sh` | 91 end-to-end checks over real HTTP: installation, the seeded sample data, the admin dashboard's numbers, impersonation, every guest surface in three languages, the night theme, the permanent QR redirect and its PNG, the deletion and recreation of the sample customers, and the grain, glass and theme machinery. The script deploys a copy of the app the way it lands on shared hosting, serves it, runs the checks and cleans up after itself. |

The browser suites need Playwright:
`PLAYWRIGHT_BROWSERS_PATH=/opt/pw-browsers NODE_PATH=$(npm root -g) node prototype/walkthrough.mjs`

They earned their keep. The contrast suite found a marketing CTA rendering at
**1.25:1** — a bare `.nav__links a` rule outranking `.btn--primary` — plus a
neutral token failing at 2.78:1 on sunken surfaces. The walkthrough found that a
**draft guide had no way to publish** from the dashboard, because the publish bar
only appeared once the guide was already published. None of those were visible in
review; all three are fixed, and the fixes are recorded in
[document 19](docs/19-design-system.md#colour).

---

## The shape of the system in one page

```
                    myhouse.it/welcome            app.myhouse.it            welcome.myhouse.it/<slug>
                    ┌───────────────┐        ┌────────────────────┐      ┌────────────────────┐
                    │  Marketing    │        │  Client dashboard  │      │   Guest guide      │
                    │  + Pricing    │        │  Onboarding wizard │      │   (public, fast)   │
                    │  + Checkout   │        │  Admin console     │      │   no JS required   │
                    └───────┬───────┘        └─────────┬──────────┘      └─────────┬──────────┘
                            │                          │                           │
                            └──────────────┬───────────┘                           │
                                           │                                       │
                                  ┌────────▼────────┐                     ┌────────▼────────┐
                                  │   Core API      │                     │ Published guide │
                                  │  (authn/authz,  │───── publishes ────▶│   snapshot      │
                                  │   entitlements) │                     │  (edge cached)  │
                                  └────┬───────┬────┘                     └─────────────────┘
                                       │       │
                  ┌────────────────────┘       └──────────────────┐
                  │                                               │
         ┌────────▼────────┐   ┌──────────────┐        ┌──────────▼─────────┐
         │   PostgreSQL    │   │ Object store │        │   Job workers      │
         │  (source of     │   │ (media +     │        │  media variants,   │
         │   truth)        │   │  variants)   │        │  translation,      │
         └─────────────────┘   └──────────────┘        │  email, rollups    │
                                                        └────────────────────┘
                                       ▲
                                       │  webhooks (verified, idempotent)
                                 ┌─────┴─────┐
                                 │  Stripe   │
                                 └───────────┘
```

---

## Three principles that decide arguments

1. **One product, not three.** Essential / Plus / Pro are rows in a table, not
   branches in the code. Every gated behaviour asks the entitlement service.
   See [05](docs/05-packages-and-entitlements.md).

2. **The guest guide is a document, not an application.** It is published as an
   immutable snapshot and served from cache. A guest with a bad hotel Wi-Fi
   connection and an old phone must read the door code in under two seconds.
   See [09](docs/09-guest-guide.md) and [21](docs/21-accessibility-and-performance.md).

3. **The host is not configuring software.** They are answering questions about
   their home. Every label, every empty state and every error is written that way.
   See [20](docs/20-content-states-and-copy.md).

---

## Note on language

The specification and the prototype copy are in English, matching the brief. All
user-facing strings in the product are i18n keys — `it-IT` is the default locale
for the Italian market and ships alongside `en-GB` in the MVP. The copy deck in
[document 20](docs/20-content-states-and-copy.md) carries both columns.
