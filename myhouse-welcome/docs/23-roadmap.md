# 23 — Roadmap

## MVP — what ships first

The test: **a host can buy, build and publish a guide without talking to us, and
a guest can use it.** Anything not on that path waits.

### In scope

| Area | Included |
| --- | --- |
| Marketing | Home, pricing, comparison, FAQ, example guide, legal pages |
| Commerce | Stripe Checkout, webhooks, discount codes, invoices, refunds, manual activation |
| Accounts | Activation, login, password reset, sessions, one owner per account |
| Packages | Three packages as data, feature entitlements, version pinning, upgrades |
| Onboarding | 17-step wizard, conditional logic, autosave, save-and-resume, mobile |
| Property | One property per account (schema supports many) |
| CMS | Section list, drag reorder, enable/disable, section editor, custom sections |
| Content | All core sections, recommendations, appliances, waste, services |
| Media | Images, HEIC conversion, variants, PDFs, media library, quotas |
| Languages | Manual multilingual, per-field translation, completeness, fallback |
| Preview | Live phone preview on desktop, full-screen on mobile |
| Publish | Snapshot versions, rollback, slug management, redirects |
| QR | Permanent token, PNG/SVG/PDF download |
| Guest guide | Mobile-first, all block types, locale switching, search (Plus+) |
| Statistics | Basic for all, advanced for Pro |
| Admin | Dashboard, customers, customer detail, orders, packages, support mode, audit, webhooks |
| Platform | GDPR export and deletion, backups, monitoring, rate limits |

### Explicitly out of MVP

| Deferred | Why | Cost of adding later |
| --- | --- | --- |
| Automatic translation | Manual multilingual proves the demand first | One worker + a feature flag. Tables, states and provider interface already exist. |
| Multiple properties | MVP is one property; the schema is already multi-property | UI work only — a switcher and a list. No migration. |
| Custom domains | Certificate automation is real work for a small audience | `public_urls.host` exists; it is DNS and TLS, not data. |
| Protected sections (PIN) | Needs its own threat review | `visibility`, `pin_hash`, `visible_from/to` columns already exist. |
| Time-windowed content | Same | Same. |
| Team members | One owner covers every MVP customer | `account_members` and `account_editor` already exist. |
| Recurring billing | One-off is the commercial model to test | `subscriptions` table and handlers exist, dormant. |
| Review requests | Needs a privacy review | — |
| PMS / channel-manager integration | Different product | — |
| Native app | Guests must not install anything | — |
| Guest forms or messaging | Would make every host a data controller ([22](22-privacy-gdpr.md)) | Deliberate, possibly permanent. |

Every deferred item has **space in the schema and none in the code**. That is the
line we held: model for the future, build for now.

### Build order

```
1  Foundations      schema, auth, roles, entitlement service, design tokens
2  Commerce         packages as data, Checkout, webhooks, provisioning
3  Content core     properties, guides, sections, blocks, media pipeline
4  Onboarding       schema-driven wizard, autosave, mobile
5  Guest guide      snapshot publish, renderer, QR, slugs      ← first demo-able
6  Dashboard        home, content editor, live preview, publish bar
7  Languages        manual translation, completeness, fallback
8  Admin            customers, orders, packages, support mode, audit
9  Polish           empty states, errors, a11y pass, performance budgets
10 Launch           legal, backups, monitoring, runbooks, seeded example guide
```

Step 5 before step 6 is deliberate. The guest guide is the product; the CMS is
how it gets filled in. Building the guest experience first means every CMS
decision is made against something real.

---

## Phase 2

Ordered by expected value, not by ease.

### 1. Automatic translation (Pro)

The clearest differentiator. The interface, state machine and non-overwrite
guarantee already exist ([11](11-multilingual.md)); this is a DeepL client, a
worker, a progress stream and a review screen.

### 2. Multiple properties

Unlocks property managers, who are the segment with budget. The work is a
property switcher, a list, a bulk publish, and the **copy-from-existing** flow
that clones sections and blocks while resetting identity and sensitive fields.
The schema is already there.

### 3. Protected sections

Door codes behind a PIN, and sections visible only near the stay dates. Needs its
own threat review, rate limiting, and the separate-fetch architecture described
in [09](09-guest-guide.md#protected-sections--pro) so codes never enter the
published snapshot.

### 4. Advanced analytics

Deepen what Pro already sees: trends, comparisons, the insight sentences in
[18](18-analytics.md#insights-not-just-numbers), CSV export.

### 5. Custom domains

`benvenuti.casasanfrancesco.it`. CNAME verification, on-demand TLS, and the
`public_urls` host switch. **The QR code keeps working** — that indirection was
built in MVP for exactly this ([13](13-urls-slugs-qr.md#custom-domains--pro-phase-2)).

### 6. Team members

Invitations, `account_editor`, per-property permissions for managers.

### 7. Review requests

A prompt at check-out linking to the platform the booking came from. Deliberately
a link-out: the review and its personal data stay with that platform.

### 8. Printable branded cards

A6 and A5 PDF templates with logo, cover, message, in every guide language.
Small, and hosts ask for it constantly.

---

## Phase 3 — considered, not committed

| Idea | Condition for building it |
| --- | --- |
| PMS / channel manager sync | A partner with enough shared customers to justify the integration surface |
| Guest messaging | Only if it can be done without making every host a controller |
| Booking-linked guides | After protected sections prove the access-token model |
| White-label for agencies | A second agency asks. The entitlement model already supports it |
| Offline PWA | If analytics show guests losing connection mid-stay |
| AI-assisted content | Suggesting a welcome message, writing alt text, drafting recommendations from an address — cheap and genuinely useful once translation infrastructure exists |
| A second MyHouse product | The account, role and entitlement model is already ecosystem-shaped ([01](01-product-vision.md#positioning-in-the-myhouse-ecosystem)) |

---

## What would make us change course

Stated in advance so they are not rationalised away later:

- **Activation below 50%** → the wizard is too long. Cut to five steps and let
  the rest happen in the CMS.
- **Support mode becomes the main onboarding path** → self-service failed. The
  product is a service with software attached, and pricing should say so.
- **Hosts never edit after publishing** → the guide is a one-off document, not a
  living one. Recurring billing is the wrong model.
- **Guests do not use language switching** → the multilingual investment was
  wrong, and Pro needs a different headline feature.
- **QR entry share is low** → the physical card is not reaching guests. Fix
  distribution, not software.

Each is instrumented from day one ([18](18-analytics.md#what-the-platform-sees)).
