# 03 — Information architecture

Four surfaces, three hostnames, one API.

| Surface | Host | Audience | Auth | Rendering |
| --- | --- | --- | --- | --- |
| Marketing | `myhouse.it/welcome` | Visitors | none | Static, prerendered |
| Application | `app.myhouse.it` | Hosts | session | Client-rendered behind auth |
| Admin | `app.myhouse.it/admin` | MyHouse staff | session + platform role | Client-rendered |
| Guest guide | `welcome.myhouse.it/<slug>` | Guests | none (optional section PIN) | Static snapshot, edge cached |

Admin lives under the application host rather than its own subdomain so that
support mode can hand off into the customer UI without a cross-origin session
dance. It is protected by role, and by returning `404` rather than `403`.

---

## 1. Marketing site — `myhouse.it/welcome`

```
/welcome
├── (home)
│   ├── Hero — "Everything your guests need. In one place."
│   ├── The problem — the seven WhatsApp messages
│   ├── How it works — 3 steps: answer, preview, publish
│   ├── Live example — embedded phone frame, real guide
│   ├── What guests see — section gallery
│   ├── Packages — 3 cards, monthly-equivalent framing
│   ├── For property managers — multi-property teaser
│   ├── FAQ — 9 questions
│   └── Final CTA
├── /example                     → redirects to a real demo guide
├── /pricing                     → anchor + standalone page (shareable)
├── /pricing/compare             → full feature table
├── /faq
├── /contact                     → form, routes to Blackout
├── /legal/privacy
├── /legal/terms
├── /legal/cookies
└── /checkout/:packageKey        → creates Stripe Checkout Session, redirects
```

Primary CTA everywhere: **Create your Welcome Guide**.
Secondary CTA: **See an example**.

The example guide is a real published guide on a reserved account
(`slug = demo-casa-esempio`) so marketing and product can never drift apart. It
is seeded, not mocked.

**SEO:** the marketing site is indexed and carries the schema.org `Product` and
`FAQPage` markup. Guest guides are handled separately — see
[13](13-urls-slugs-qr.md#indexing).

---

## 2. Application — `app.myhouse.it`

### Unauthenticated

```
/activate?token=…     Set your password (arrives from the purchase email)
/login
/forgot-password
/reset-password?token=…
/verify-email?token=…
```

There is no public `/register`. Accounts are created by a paid order or by a
super admin. This removes an entire class of spam and support ambiguity.

### Authenticated — first run

```
/welcome              The "Let's create your guest guide" screen (section 7 of the brief)
/onboarding/:step     The wizard, 17 steps
```

`/welcome` is shown once, on first login, before any CMS chrome exists. It shows
the package purchased, an estimated time, and one button.

### Authenticated — steady state

```
/                                 Home
/guide                            My Welcome Guide (status, publish, preview)
/guide/content                    Content — the section list
/guide/content/:sectionKey        Section editor (slide-over on desktop, page on mobile)
/guide/recommendations            Local recommendations, grouped by category
/guide/languages                  Languages and translation status
/guide/appearance                 Logo, cover, colours, typography, branding
/guide/qr                         QR & Link
/guide/preview                    Full-screen mobile preview
/statistics                       Visits, sections, languages, outbound clicks
/media                            Media library
/account                          Profile, password, billing details
/account/billing                  Orders, invoices, package, upgrade
/account/privacy                  Data export, account deletion
/properties                       Property list — hidden when count == 1
/properties/:id                   Property switcher target
```

### Sidebar navigation (deliberately short)

```
   Home
   My Welcome Guide
   Content
   Languages
   QR & Link
   Appearance
   Statistics            ← "Available with Pro" when not entitled
   Account
```

Seven items. Media library is reached from inside upload fields, not from the
sidebar, because hosts think in "add a photo here", not in "manage my assets".
Recommendations sit inside Content because to a host they are a section of the
guide, not a separate system.

Items the package does not include remain visible, muted, with a small
`Available with Plus` chip. Clicking opens a one-screen explanation with a single
upgrade button. No interstitials, no modals on load, no countdowns.

---

## 3. Admin console — `app.myhouse.it/admin`

Higher information density is allowed here. Same tokens, tighter spacing scale,
tables instead of cards.

```
/admin                            Dashboard — KPIs, recent activity
/admin/customers                  Table: search, filter by package/status/completion
/admin/customers/:id              Customer detail (see below)
/admin/customers/:id/activity     Full activity log for that account
/admin/properties                 All properties across accounts
/admin/orders                     Orders and payments, Stripe status
/admin/orders/:id                 Order detail, refund, resend invoice
/admin/packages                   Package list
/admin/packages/:key              Package versions, features, limits, Stripe price
/admin/packages/:key/versions/:v  A specific version — read-only once in use
/admin/discounts                  Discount and promotional codes
/admin/content/templates          Global section templates
/admin/content/categories         Recommendation categories
/admin/locales                    Supported languages
/admin/translations               Machine-translation queue and review
/admin/analytics                  Platform-wide usage
/admin/system/settings            Global settings
/admin/system/audit               Audit log, filterable by actor
/admin/system/webhooks            Stripe webhook events, replay
/admin/system/jobs                Background job health
```

### Customer detail page

One page, five blocks, one action rail. No tabs — staff on a support call should
not hunt.

```
┌─ Customer ──────────────────────────┐  ┌─ Actions ─────────────┐
│ Name, email, phone, company         │  │ Assist customer  ▸    │
│ VAT / codice fiscale / SDI          │  │ Change package        │
│ Created, last login, status         │  │ Resend welcome email  │
├─ Purchase ──────────────────────────┤  │ Reset onboarding      │
│ Package + version, price, discount  │  │ Preview guide         │
│ Payment status, Stripe ID, date     │  │ Regenerate QR         │
│ Invoice link, refund history        │  │ Grant feature ⚠       │
├─ Properties ────────────────────────┤  │ Suspend               │
│ Each: name, slug, status            │  │ Archive account ⚠     │
├─ Guide ─────────────────────────────┤  └───────────────────────┘
│ Completion %, status, languages     │
│ Public URL, QR preview              │
├─ Activity & notes ──────────────────┤
│ Recent edits, admin notes           │
└─────────────────────────────────────┘
```

Destructive actions (`⚠`) require a typed reason and appear in the audit log with
it.

---

## 4. Guest guide — `welcome.myhouse.it/<slug>`

```
/<slug>                    Home — cover, welcome, section grid
/<slug>/:sectionSlug       A section page
/<slug>/eat                Recommendations, filtered by category
/<slug>/eat/:placeSlug     A single place
/<slug>/search             Search (Plus and Pro only)
/<slug>?lang=en            Locale switch — also at /<slug>/en/… for crawlable variants
/q/:token                  Permanent QR endpoint → 302 to current primary URL
```

The information architecture here is **flat on purpose**. Home is a grid of
destinations; every destination is one tap away; no destination nests more than
two levels. A guest standing at a locked door with a suitcase does not browse.

Default section order, which is also the default answer order in onboarding:

```
1  Welcome          7  Services          13  Local recommendations
2  Check-in         8  Appliances        14  Extras
3  Wi-Fi            9  House rules
4  How to arrive   10  Waste
5  Parking         11  Check-out
6  Contacts        12  Safety & emergency
```

Order is host-editable by drag and drop. The default ordering is not
alphabetical or logical-for-us; it is ordered by *when a guest needs it*:
arrival information first, departure information last.

---

## Cross-surface object map

What a URL points at, in data terms:

```
account ──1:N── property ──1:1── guide ──1:N── guide_section ──1:N── content_block
                   │                 │                │                    │
                   │                 ├─1:N── guide_locale                  └── media
                   │                 ├─1:N── public_url  (slug history)
                   │                 ├─1:N── qr_code     (permanent token)
                   │                 ├─1:N── guide_version (published snapshots)
                   │                 └─1:N── recommendation
                   └── onboarding_progress

account ──1:N── order ──1:N── payment
   │              └── package_version ──1:N── package_feature ── feature
   └──1:N── entitlement  (resolved grants, overrides, promos)
```

Full field-level detail in [14](14-data-model.md).
