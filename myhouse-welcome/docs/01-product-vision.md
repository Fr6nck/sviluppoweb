# 01 — Product vision

## The problem, concretely

A host in Liguria rents out one apartment. Every booking, she sends the same
seven WhatsApp messages: how to find the building, where to park, the lockbox
code, the Wi-Fi password, how the induction hob works, which bin day is which,
and what time to leave the keys. Guests lose the messages. They ask again at
23:40. Half of them ask in English, a third in German.

She already tried a PDF. Nobody opens PDFs on a phone in a stairwell.

## The product

One digital guide per property, reachable from a link and a QR code, readable on
any phone without installing anything.

```
welcome.myhouse.it/casa-san-francesco
```

The host fills it in once by answering plain questions. The guide updates
instantly when she changes something — the printed QR code never changes.

## The principle

> **Everything your guests need. In one place.**

Every product decision resolves against it:

- If it does not help a guest find an answer in seconds, it does not belong on
  the guest surface.
- If it makes the host feel like they are configuring software, it is wrong.
- If it forces a reprint of a physical QR card, it is a bug.

## What MyHouse Welcome is not

| Not | Why it matters |
| --- | --- |
| A booking engine or channel manager | We start after the booking exists. No PMS sync in MVP. |
| A website builder | Hosts pick from a constrained, high-quality system. No arbitrary CSS. |
| A messaging platform | We remove the need for repeat messages; we do not replace the inbox. |
| A guest CRM | We collect no guest personal data. See [22](22-privacy-gdpr.md). |

## Positioning in the MyHouse ecosystem

MyHouse Welcome is the first product in a suite for vacation rentals, B&Bs, guest
houses and small hospitality operators. The consequences for architecture:

- **Accounts are shared.** `accounts` and `users` are modelled as ecosystem-level
  tenancy, not Welcome-specific. A future MyHouse product attaches to the same
  account without a migration.
- **Entitlements are product-scoped.** Every feature key is namespaced
  (`welcome.languages.max`), so a second product adds keys rather than tables.
- **Billing is per-product.** An order references a package version; package
  versions reference a Stripe price. A second product is a second package family.

## Target users

| Segment | Properties | What they need | Package fit |
| --- | --- | --- | --- |
| Small host | 1 | Stop repeating themselves. Fill it in from a phone in 20 minutes. | Essential |
| Professional host | 2–5 | Consistency across properties, multiple languages, local recommendations. | Plus |
| Property manager | 6+ | Templates, bulk operations, branding, analytics, multi-property. | Pro |
| Agency (MyHouse / Blackout) | all | Sell, onboard, assist, and operate on behalf of customers. | Super Admin |

**Architectural consequence:** properties are a first-class collection from day
one (`accounts 1─* properties`), even though MVP UI assumes one property per
account and hides the property switcher when the count is 1. There is no
"single-property schema" to migrate away from later.

## Why hosts will finish onboarding

The single biggest risk in this product is an account that pays and never
publishes. The design answers it in four ways:

1. **Questions, not fields.** "Do guests have Wi-Fi?" not "Wi-Fi module configuration".
2. **Everything is skippable.** Only property name and check-in time are required
   to publish. The guide grows after the first guest.
3. **Save and continue later, always.** Every step autosaves; the wizard resumes
   at the last incomplete step.
4. **The guide is useful at 40% complete.** Publication is never gated on
   completeness — the completion meter is encouragement, not a gate.

## Success metrics

| Metric | Definition | MVP target |
| --- | --- | --- |
| Activation | Paid accounts that publish a guide within 7 days | 70% |
| Time to publish | Median minutes from first login to first publish | < 25 min |
| Mobile onboarding share | Onboarding sessions completed on a phone | > 50% |
| Guest reach | Median guide views per published guide per month | > 15 |
| Guide freshness | Published guides edited at least once after month 1 | > 40% |
| Support load | Accounts requiring admin assistance to publish | < 15% |

These are instrumented through `analytics_events` and `activity_logs` — see
[18](18-analytics.md). They are business metrics, not guest tracking; no guest
personal data is involved.

## Product bets, stated as risks

| Bet | If wrong |
| --- | --- |
| Hosts will self-serve onboarding | Admin support mode ([10](10-admin-console.md)) becomes the primary onboarding channel; it is built in MVP for exactly this reason. |
| One-off purchase is the right commercial model | `subscriptions` and recurring billing are modelled but dormant; switching on a recurring package is a data change plus a Stripe mode, not a rewrite. See [06](06-billing-stripe.md). |
| Manual translation is acceptable for Plus | The translation state machine and `translations` table already support a machine engine; enabling DeepL is a worker plus a feature flag. See [11](11-multilingual.md). |
| Guests will not want an app | Nothing in the architecture prevents a PWA manifest later; the guide is already installable-shaped. |
