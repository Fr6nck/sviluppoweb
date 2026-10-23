# 24 — Decision log

The twelve decisions that shape everything else, with what they cost.

---

### 1. The guest guide is a published snapshot, not a live query

Publishing writes an immutable JSON document; the guest surface serves it from
the edge.

**Because** a guest at a locked door on hotel Wi-Fi must get an answer in under
two seconds, and because a host editing a draft must not be able to break a live
guide.

**Costs**: content changes require an explicit publish; snapshots consume storage;
there are two representations of the same content to keep coherent.

**Rejected**: render from the database on each request (slow, fragile, couples
guest uptime to our database), and auto-publish on save (a typo in a door code
reaches guests instantly).

---

### 2. Entitlements are materialised rows, not a computed join

**Because** overrides, promotional grants and grandfathering need somewhere to
live, and "why does this account have this?" must be answerable in one query
during a support call.

**Costs**: rows must be written on purchase, upgrade and migration; a bug there
is a wrong entitlement; a cache to invalidate.

**Rejected**: computing from `package_features` on demand — no place to record an
override, and no audit trail.

---

### 3. A sold package version is frozen, enforced by a database trigger

**Because** "existing customers must not unexpectedly lose purchased features" is
a promise, and a promise enforced by convention is eventually broken by a hurried
admin.

**Costs**: changing a price means creating a version; the admin UI is more complex;
migrations need a preview and a grandfathering path.

**Verified**: `schema/tests.sql` cases 4–7.

---

### 4. The QR code encodes an opaque permanent token, never the guide URL

`mh.li/q/7f3a9c2b` → 302 → the current primary URL.

**Because** a QR code is physical. Laminated, stuck to a fridge, printed on fifty
cards. It must survive slug changes, custom domains and rebrands.

**Costs**: an extra redirect on every scan; a short domain to own; a redirect
service that must never go down.

**This single indirection is what makes custom domains a Phase 2 feature instead
of a migration.**

---

### 5. Translations are a polymorphic table with per-field state

**Because** the feature is not "store text in several languages" — it is knowing,
per field per locale, whether it is missing, machine-translated, reviewed or
published, and whether the original has changed since.

**Costs**: more rows; joins to assemble a locale; a completeness percentage that must be
maintained.

**Rejected**: a column per locale (a migration per language) and a JSON blob per
field (no per-locale status, which is the whole feature).

---

### 6. A machine translation can never overwrite reviewed or published text

Enforced in the repository `WHERE` clause **and** by a database trigger.

**Because** a host who corrected the German check-in instructions and then ran a
translation pass must not lose their correction. Losing it once loses the
customer's trust in the feature permanently.

**Costs**: machine re-translation of stale reviewed content needs a separate
`proposed_value` column and an acceptance step.

**Verified**: `schema/tests.sql` cases 8–12.

---

### 7. No cookies and no guest identity on the guest guide

Analytics use a server-side hash with a daily salt that is never persisted.

**Because** it makes the cookie banner legally unnecessary, and a consent dialog
between a guest and their door code is a product failure.

**Costs**: no cross-day unique visitors; no funnel analysis across sessions;
"unique visitors" is approximate and the UI must say so.

**This constrained what analytics could ever be, and we accepted that up front
rather than discovering it during a compliance review.**

---

### 8. Accounts are created by payment, not by a signup form

**Because** it eliminates spam signups, empty abandoned accounts, and the whole
"I registered but I can't find my guide" support category. And because paying
with a card at an address, then following a link sent to it, verifies the email
better than a confirmation click.

**Costs**: no free trial without building a trial package; no self-service demo
account; manual activation is needed for partnerships, so it is built in MVP.

---

### 9. Impersonation is a dual principal, never a switched identity

The request carries both the admin and the account, permissions are the
intersection, and the customer is told.

**Because** support staff editing a customer's guide is the commonest real
support resolution, and doing it by logging in as them destroys the audit trail
and the customer's trust simultaneously.

**Costs**: every authorisation check handles two identities; some operations must
be explicitly blocked inside support mode; the banner must be in the app shell so
no page can omit it.

---

### 10. `account_id` is denormalised onto every tenant-owned table

Backed by row-level security and a trigger asserting it matches the parent.

**Because** a missing `WHERE account_id = ?` in one query is a cross-tenant data
leak, and this product holds people's home addresses and door codes. A
constraint violation is a vastly better failure than a silent leak.

**Costs**: redundant columns; a trigger on every insert and update; the
redundancy must be maintained by the service layer.

**Verified**: `schema/tests.sql` cases 1–2 and 26.

---

### 11. Six JSON columns, and everything else relational

`content_blocks.data`, `guide_versions.snapshot`, `package_features.value_json`,
`entitlements.value_json`, `activity_logs.changes`, `webhook_events.payload`.

**Because** the rule "relational if it is queried, filtered, joined, counted or
permission-checked; JSON only for a type-varying leaf read as a whole" produces
exactly this list, and the tempting alternative — the whole guide as one document
— makes "which guides have no Wi-Fi section?" unanswerable and per-section
permissions impossible.

**Costs**: more tables; JSON Schema validation on write for block payloads;
assembling a guide touches several tables, which is why publishing snapshots it.

---

### 12. The wizard, the packages and the features are data, not code

Three JSON files, validated against each other by `schema/validate.py` in CI.

**Because** the questions a host answers, the price of Plus, and what Plus
includes are all things a non-engineer should be able to change on a Tuesday.

**Costs**: a schema-driven renderer is harder to build than seventeen hard-coded
forms; the definition is versioned so in-flight onboardings do not shift under
their users; a validator is needed to catch a typo that a compiler would
otherwise have caught.

**Verified**: `schema/validate.py` — 28 features, 3 packages, 17 steps, 88 fields,
all cross-references resolving, and package tiers proven monotonic.
