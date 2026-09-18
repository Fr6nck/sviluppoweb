# 04 — User journeys

Each journey is given with its happy path, its branches, and the state changes it
causes. State names match the enums in [14](14-data-model.md).

---

## J1 — Visitor to published guide (the main journey)

```
 Marketing        Stripe          Email            App                      Public
 ─────────        ──────          ─────            ───                      ──────
 Home
  │ "Create your Welcome Guide"
  ▼
 Pricing
  │ choose Plus
  ▼
 POST /checkout/plus  ──────▶ Checkout Session
                               │  card + optional promo code
                               ▼
                              paid ──webhook──▶ order.status = paid
                                                account created (status=pending)
                                                user created (no password)
                                                entitlements written
                                                activation token issued
                                                        │
                                       ┌────────────────┘
                                       ▼
                              "Your guide is ready to build"
                                   │ Set your password
                                   ▼
                                                /activate?token
                                                  │ password + accept terms
                                                  ▼
                                                account.status = active
                                                user.email_verified_at = now
                                                  │
                                                  ▼
                                                /welcome
                                                  │ Start configuration
                                                  ▼
                                                /onboarding/property … /onboarding/publish
                                                  │ Publish
                                                  ▼
                                                guide.status = published
                                                guide_version #1 written
                                                public_url + qr_code created
                                                                          │
                                                                          ▼
                                                                welcome.myhouse.it/casa-san-francesco
```

**Guarantee:** the account is created by the **webhook**, not by the browser
returning from Stripe. The success page polls `GET /orders/:id` until the webhook
lands. If the webhook is slow, the user sees "Confirming your payment…" with a
reassurance that the email will arrive regardless. See
[06](06-billing-stripe.md#the-race-between-redirect-and-webhook).

### Branches

| Branch | Behaviour |
| --- | --- |
| Card declined | Stays in Stripe Checkout. Stripe retries. No account created. |
| User abandons Checkout | `checkout.session.expired` after 24h → order `abandoned`. One recovery email at +1h if the email was captured. |
| Webhook delayed > 60s | Success page shows the pending state with a support link; the email is queued and fires when the webhook lands. |
| Duplicate purchase, same email | Webhook finds the existing account, attaches the new order, and either upgrades the package or grants a second property. Never creates a second account. |
| Activation token expired (7 days) | `/activate` shows "This link has expired" and offers to send a new one to the same address. |
| User never activates | Reminder emails at +1 day, +3 days, +7 days. Then it surfaces in the admin "Never activated" filter. |

---

## J2 — Host edits and republishes

```
/guide/content
  │ opens "Wi-Fi"
  ▼
Section editor (slide-over)
  │ types a new password
  ▼
autosave after 800 ms idle  ──▶ PATCH /guide/sections/wifi/blocks/:id
                                 guide.has_unpublished_changes = true
  │
  ▼
"Saved" indicator; live preview updates
  │
  ▼
Sticky bar: "You have unpublished changes  [Preview] [Publish]"
  │ Publish
  ▼
POST /guide/publish
  guide_version #7 written (immutable snapshot)
  guide.published_version_id = #7
  cache purged for the slug
  has_unpublished_changes = false
```

Drafts are **not** visible to guests. The published snapshot is what the public
URL serves; editing never touches it. A host can safely edit at 3 a.m. with
guests in the flat.

**Deliberate decision:** we do *not* auto-publish. Hosts type passwords and door
codes; a typo reaching guests instantly would be worse than one extra tap. The
publish bar is persistent and impossible to miss, and the preview is live, so the
cost of the extra tap is near zero.

---

## J3 — Guest arrives

```
Guest scans the QR on the fridge
  ▼
GET /q/7f3a9c                       ← permanent token, never changes
  ▼
302 → welcome.myhouse.it/casa-san-francesco?src=qr
  ▼
Edge cache hit (snapshot HTML)      ← p95 < 400 ms, no JS required to read
  ▼
Locale resolved: ?lang → cookie-less localStorage → Accept-Language → guide default
  ▼
Grid of sections
  │ taps "Wi-Fi"
  ▼
/casa-san-francesco/wi-fi
  Network: Casa San Francesco
  Password: ●●●●●●●●  [Copy password]
  [Connect with QR]                 ← WIFI: QR rendered client-side, never sent to a server
```

Analytics events are recorded server-side at render and via a single beacon for
outbound clicks. No cookies, no third-party scripts, no guest identity. See
[18](18-analytics.md).

### Branches

| Branch | Behaviour |
| --- | --- |
| Guide unpublished | `404` page: "This guide is not available." No property data leaks. |
| Slug changed | Old `public_url` row has `status=redirect` → `301` to the new slug, forever. |
| Section is PIN-protected | Section page asks for the code. Everything else stays open. |
| Section outside its date window | Section is absent from the grid. It is not shown as locked — a guest cannot act on it, so showing it is noise. |
| Offline after first visit | Service worker (Phase 2) serves the last snapshot. MVP: browser cache with a long `stale-while-revalidate`. |

---

## J4 — First login and onboarding, with interruption

The realistic version. A host starts on a laptop, gets interrupted, finishes on a
phone two days later.

```
Day 0, laptop
  /welcome → Start configuration
  Step 1 Property     ✓ autosaved
  Step 2 Welcome      ✓ autosaved
  Step 3 Arrival      partially filled ✓ autosaved
  closes the tab
        │
        │  onboarding_progress: { last_step: 'arrival', completed: ['property','welcome'] }
        │  email at +24h: "Your guide is 18% ready"
        ▼
Day 2, phone
  /login → detects incomplete onboarding → /onboarding/arrival
  banner: "Welcome back. You were adding arrival instructions."
  Steps 3–8 on the phone, uploading photos from the camera roll
  Step 16 Preview
  Step 17 Publish  ──▶ published
```

Autosave granularity is per-field-group, debounced at 800 ms, with an offline
queue. A host on a train tunnel does not lose the paragraph they just typed —
writes buffer in IndexedDB and flush on reconnect, and the UI says
"Not saved yet — we'll save when you're back online."

---

## J5 — Admin assists a customer

```
/admin/customers/:id
  │ "Assist customer"
  ▼
Modal: reason (required, free text) + duration (default 60 min)
  │ confirm
  ▼
POST /admin/impersonation      → impersonation_session created, logged
  ▼
Redirect to /guide/content with the account context
  ┌──────────────────────────────────────────────────────────┐
  │ ⓘ You are assisting Casa San Francesco as administrator. │
  │   Started 14:02 · Ends 15:02 · [End session]             │
  └──────────────────────────────────────────────────────────┘
  │ admin fixes the check-in text, uploads a PDF
  ▼
each write → activity_logs(actor=admin, impersonated_account_id=account)
  ▼
"End session" or timeout → session closed, summary written
  ▼
Customer's own activity feed: "Check-in updated by MyHouse support · 14:07"
```

The customer is told. Silent impersonation is not an option the system offers.

---

## J6 — Package upgrade

```
Host clicks a muted "Available with Pro" chip
  ▼
/account/billing/upgrade?to=pro
  Shows: what you have, what you gain, price difference
  ▼
Stripe Checkout (upgrade line item, proration not needed for one-off)
  ▼
webhook: order #2 paid
  ▼
account.package_version_id → current Pro version
entitlements recomputed  (union: never removes a previously granted feature)
  ▼
App reloads entitlements; previously muted items become live
Toast: "Pro is active. Automatic translations and analytics are now available."
```

Downgrades are **admin-only** and never automatic. A host who bought Pro keeps
Pro features unless a super admin explicitly revokes them with a reason.
Rationale in [05](05-packages-and-entitlements.md#never-silently-remove).

---

## J7 — Refund

```
/admin/orders/:id → Refund (full or partial), reason required
  ▼
Stripe refund created
  ▼
webhook charge.refunded
  ▼
payment.status = refunded
order.status = refunded
account.status = suspended          ← content preserved
guide.status = unpublished          ← public URL returns 404
email: "Your MyHouse Welcome guide has been unpublished"
```

Nothing is deleted. A refunded customer who returns is reactivated with their
content intact — the commonest real support case, and it should cost one click.

---

## J8 — Adding a second property (Phase 2, modelled now)

```
/properties → "Add property"
  ▼
Entitlement check: welcome.properties.max
  ├── within limit  → new property + guide, wizard restarts at step 1
  │                   with an offer: "Copy from Casa San Francesco?"
  └── at limit      → upgrade screen naming the exact limit
```

The copy-from-existing path clones `guide_sections`, `content_blocks` and
`recommendations`, then resets the property-identity fields (name, address, Wi-Fi,
codes) so nothing sensitive leaks between properties. This is the feature that
makes the product viable for property managers, and it costs nothing today
because the schema is already multi-property.

---

## State machines

### Account

```
pending ──activate──▶ active ──suspend──▶ suspended ──reactivate──▶ active
                        │                     │
                        └──────archive────────┴──────▶ archived  (soft, reversible)
                                                          │
                                                       purge (30d, GDPR) ──▶ deleted
```

### Order

```
created ──▶ pending_payment ──▶ paid ──▶ fulfilled
                │                 │
                ├──▶ failed       ├──▶ refunded
                └──▶ abandoned    └──▶ partially_refunded
```

### Guide

```
draft ──publish──▶ published ──edit──▶ published + has_unpublished_changes
                       │                              │
                       │◀────────── publish ──────────┘
                       │
                   unpublish
                       ▼
                  unpublished ──publish──▶ published
```

### Translation (per field, per locale)

```
missing ──machine──▶ machine_translated ──edit──▶ reviewed ──publish──▶ published
   │                        │                         ▲
   └────── manual edit ─────┴─────────────────────────┘

A machine run NEVER transitions reviewed or published back to machine_translated.
```
