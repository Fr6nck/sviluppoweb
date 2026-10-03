# 06 — Billing and Stripe

## Principle

**The webhook is the truth.** The browser returning from Stripe is a hint. Every
account creation, entitlement grant and email is triggered by a verified webhook
event, never by a redirect the user could forge, replay or never reach.

---

## Objects

| MyHouse | Stripe | Relationship |
| --- | --- | --- |
| `accounts.stripe_customer_id` | Customer | 1:1, created on first successful payment |
| `package_versions.stripe_price_id` | Price | Set by admin, validated against the API on save |
| `package_versions.stripe_product_id` | Product | One product per package, prices per version |
| `orders.stripe_checkout_session_id` | Checkout Session | 1:1 |
| `payments.stripe_payment_intent_id` | PaymentIntent | 1:1 |
| `refunds.stripe_refund_id` | Refund | N per payment |
| `discount_codes.stripe_promotion_code_id` | Promotion Code | 1:1, mirrored both ways |
| `invoices.stripe_invoice_id` | Invoice | 1:1 when invoicing is on |
| `subscriptions.stripe_subscription_id` | Subscription | Dormant, ready |

We store the Stripe ID and the fields we need to display and reconcile
(`amount_cents`, `currency`, `status`, `brand`, `last4`, `receipt_url`). We do
not mirror Stripe's full object, and we never render a Stripe ID to a customer.

---

## Checkout flow

```
1. Browser  GET  myhouse.it/welcome/pricing
2. Browser  POST /api/checkout            { packageKey, locale, promoCode?, email? }
3. API      validate package is public and has a current version
            create order (status = pending_payment, package_version pinned)
            create Checkout Session:
              mode: 'payment'
              line_items: [{ price: pv.stripe_price_id, quantity: 1 }]
              allow_promotion_codes: true
              customer_email: email (if known)
              automatic_tax: { enabled: true }
              tax_id_collection: { enabled: true }        ← Italian VAT / P.IVA
              invoice_creation: { enabled: true }
              locale: 'it' | 'en'
              client_reference_id: order.id
              metadata: { order_id, package_version_id }
              success_url: app.myhouse.it/checkout/success?order={ORDER_ID}
              cancel_url:  myhouse.it/welcome/pricing?canceled=1
4. Browser  redirect to Stripe
5. Guest    pays on Stripe's page                          ← no card data touches us
6. Stripe   POST /api/webhooks/stripe  checkout.session.completed
7. API      verify signature → idempotent handler → account, user, entitlements, email
8. Browser  lands on /checkout/success, polls GET /api/orders/:id until fulfilled
```

Card data never reaches our servers or our DOM. PCI scope is SAQ-A.

### The race between redirect and webhook

The user is often back on our success page before the webhook arrives. The
success page is a state machine, not a celebration:

| Order state | Screen |
| --- | --- |
| `pending_payment`, < 10 s | "Confirming your payment…" spinner |
| `pending_payment`, 10–60 s | "Still confirming. This usually takes a few seconds." |
| `pending_payment`, > 60 s | "Your payment went through. We're finishing setup and will email you within a few minutes." + support link. **This is not an error state** — the email path is guaranteed. |
| `paid` / `fulfilled` | "Payment received. Check your email to set your password." + a direct activation button if the session is the same browser. |
| `failed` | "The payment didn't go through." + retry with the same package preselected. |

Polling is `GET /api/orders/:id/status` every 2 s, backing off to 5 s, capped at
5 minutes, then the page settles on the "we'll email you" state.

---

## Webhooks

### Endpoint contract

```
POST /api/webhooks/stripe
  - raw body (no JSON middleware before signature verification)
  - stripe.webhooks.constructEvent(body, sig, STRIPE_WEBHOOK_SECRET)
  - INSERT INTO webhook_events (stripe_event_id, ...) ON CONFLICT DO NOTHING
      → 0 rows affected means we have seen it: return 200 immediately
  - enqueue job, return 200 within ~50 ms
  - the job does the work, with retries and a dead-letter queue
```

Returning `200` fast and processing asynchronously means a slow email provider
can never cause Stripe to retry a payment event. Idempotency is enforced by a
unique index on `stripe_event_id`, not by application logic.

### Handled events

| Event | Effect |
| --- | --- |
| `checkout.session.completed` | Provision: account, owner user, entitlements, activation token, welcome email. Idempotent by `order_id`. |
| `checkout.session.expired` | `order.status = abandoned`. Optional recovery email. |
| `payment_intent.succeeded` | Write/confirm `payments` row, `paid_at`, card brand and last4. |
| `payment_intent.payment_failed` | `payments.status = failed`, store `failure_code`/`failure_message`, order → `failed`. |
| `charge.refunded` | Write `refunds`; set payment `refunded`/`partially_refunded`; on full refund suspend the account and unpublish the guide. |
| `charge.dispute.created` | Flag the account, alert `#billing`, do **not** auto-unpublish — a dispute is not a verdict. |
| `invoice.paid` / `invoice.payment_failed` | Store invoice number and PDF URL. |
| `customer.updated` | Sync billing name/address if changed in the Stripe dashboard. |
| `customer.subscription.*` | Handlers exist and are registered; dormant until recurring packages ship. |

Unhandled event types are stored with `status = ignored` so that turning one on
later starts from a real sample.

### Provisioning, in order, in one transaction

```sql
BEGIN;
  -- idempotency guard
  SELECT id FROM orders WHERE id = :order_id FOR UPDATE;
  -- account: find by billing email, else create
  -- user:    find by email, else create with password_hash = NULL
  -- grant account_owner role scoped to the account
  -- pin accounts.package_version_id
  -- write entitlements rows from package_features (source='package')
  -- create the first property shell + guide (status=draft) + onboarding_progress
  -- orders.status = 'paid', completed_at = now()
COMMIT;
-- after commit: issue activation token, enqueue welcome email, purge entitlement cache
```

Side effects (email, token) happen after commit so that a failed email never
rolls back a paid order.

---

## Discount and promotional codes

Two layers, kept in sync:

- **Stripe Promotion Codes** do the arithmetic. `allow_promotion_codes: true`
  lets the customer type the code on Stripe's page.
- **`discount_codes`** mirrors them so admin can list, search, report and
  restrict without leaving MyHouse.

Admin creating a code writes to Stripe first, then stores the returned
`coupon`/`promotion_code` IDs. If the Stripe call fails, nothing is written
locally — no local code that Stripe will reject.

Fields: `code`, `type` (`percent` | `amount`), `value`, `currency`,
`max_redemptions`, `redeemed_count`, `applies_to_package_ids`, `starts_at`,
`ends_at`, `status`, `created_by`. Redemptions are recorded in
`discount_redemptions` with the order, for revenue attribution.

**100% codes** are supported and take the separate "manual activation" path: no
PaymentIntent exists, so provisioning is triggered by
`checkout.session.completed` with `amount_total = 0`. This is also how admin
comps an account.

---

## Manual activation by admin

A super admin can create an account with no payment (partnership, comp, migration
from a legacy customer). It creates an `order` with
`payment_provider = 'manual'`, `total_cents = 0`, a mandatory reason, and the
same provisioning transaction. The customer experience is identical from the
activation email onward.

---

## Upgrades

One-off packages, so no proration:

```
POST /api/checkout/upgrade  { toPackageKey }
  → validates target is strictly above current (sort_order)
  → creates an order of type 'upgrade' at the full price of the target
    minus the amount already paid for the current package version
  → Checkout Session with a one-off line item (dynamic price_data)
  → on webhook: pin the new package_version, recompute entitlements as a UNION
```

Entitlement recomputation on upgrade is a **union with the existing grants**, so
an account that was given a Pro feature by override while on Plus does not lose
it by upgrading to Pro.

---

## Refunds

Admin-initiated from `/admin/orders/:id`, full or partial, reason required.

```
POST /api/admin/orders/:id/refund { amountCents?, reason }
  → stripe.refunds.create(...)
  → returns immediately; state changes happen on charge.refunded
```

State on full refund: payment `refunded`, order `refunded`, account `suspended`,
guide `unpublished`, public URL `404`, customer emailed. Content, media and
entitlements are preserved. Reactivation is one admin click.

Partial refunds do not change account state — they are a goodwill adjustment, not
a cancellation.

---

## What the admin sees

`/admin/orders` columns: customer, package + version, amount, discount, status,
payment date, Stripe ID (click-through to the Stripe dashboard), invoice link.

Filters: status, package, date range, has-discount, has-refund, manual-only.

`/admin/system/webhooks` lists `webhook_events` with type, status, attempts, last
error, and a **Replay** button that re-enqueues the stored payload through the
same handler. Because handlers are idempotent, replay is safe.

**Reconciliation job** — nightly, compares the last 7 days of Stripe charges
against `payments` and reports any row present in one and not the other. Missed
webhooks are the single most common cause of "customer paid but has no account";
this job means we find out before the customer tells us.

---

## Money handling

- All amounts are integer minor units (`amount_cents`) with an explicit
  `currency`. No floats anywhere, including in JSON.
- Display formatting is locale-aware (`Intl.NumberFormat`), always server-supplied
  currency code.
- Tax is Stripe Tax; `tax_id_collection` captures P.IVA for Italian business
  customers. Invoice fields (`legal_name`, `vat_number`, `tax_code`, `sdi_code`,
  `pec_email`) are on `accounts` for Italian e-invoicing downstream.

---

## Future: recurring

Everything needed is modelled: `subscriptions`, `invoices`, the subscription
webhook handlers, and `package_versions.billing_interval`. Turning it on is:

1. Create a recurring Price in Stripe, set it on a new package version.
2. Set `billing_interval = 'month' | 'year'` on that version.
3. Checkout switches `mode: 'payment'` → `mode: 'subscription'` based on that field.
4. Dunning: `invoice.payment_failed` → grace period 14 days → suspend.

No schema migration. This is why `subscriptions` exists as an empty table today.

---

## Keys and secrets

`STRIPE_SECRET_KEY` and `STRIPE_WEBHOOK_SECRET` live in the server environment
only. The publishable key is the only Stripe value that reaches a browser, and
with hosted Checkout it is not strictly needed. There is a startup assertion that
fails the boot if a key beginning `sk_` appears in any client-exposed config, and
a CI check that greps the client bundle for `sk_live`.
