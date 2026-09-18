# 22 — Privacy and GDPR

## Who is who

| Role | Who | For what |
| --- | --- | --- |
| **Controller** | MyHouse / Blackout | Host account data, billing data, platform usage |
| **Processor** | MyHouse / Blackout | Guide content the host writes |
| **Controller** | The host | The content of their own guide |
| **Data subject** | The host | Their account |
| — | The guest | **Not a data subject here — we hold no guest personal data** |

This last line is the most important decision in the document, and it is
structural: there is no guest account, no guest email field, no booking record,
no guest cookie, no guest identifier that persists beyond a day.

---

## What we hold

| Category | Data | Lawful basis | Retention |
| --- | --- | --- | --- |
| Account | Name, email, phone, locale | Contract | Life of account + 30 days |
| Billing | Legal name, VAT, codice fiscale, SDI/PEC, address | Legal obligation | **10 years** (Italian accounting law) |
| Payment | Card brand, last 4, Stripe IDs | Contract | 10 years. **Full card numbers never touch us.** |
| Content | Everything the host writes about their property | Contract | Life of account + 30 days |
| Media | Photos and PDFs, EXIF stripped | Contract | Life of account + 30 days |
| Sensitive content | Door codes, Wi-Fi passwords | Contract | Life of account + 30 days |
| Security | Login IPs, session metadata | Legitimate interest | 12 months |
| Audit | Admin actions on the account | Legal obligation + legitimate interest | 24 months |
| Guide analytics | Section views, locale, country, daily visitor hash | Legitimate interest — **aggregate, non-identifying** | Raw 14 months, aggregates indefinitely |
| Email delivery | Address, template, status | Contract | 12 months |

### Why guide analytics needs no consent

The ePrivacy Directive requires consent for **storing or accessing information on
a user's device**. We store nothing on the guest's device for analytics — no
cookie, no `localStorage` entry, no fingerprint. The one `localStorage` key is
the chosen language, which is strictly necessary to provide the service the guest
asked for.

The server-side record contains no identifier that persists beyond 24 hours:
`visitor_hash` is `sha256(ip + user_agent + daily_salt)`, the salt rotates at
midnight and is never written to disk, and the raw IP is discarded after deriving
the hash and the country. Yesterday's hashes cannot be linked to today's by us or
by anyone who obtains the database.

**Therefore: no cookie banner on the guest guide.** This was designed for, not
discovered afterwards. A consent dialog between a guest and their door code would
be a product failure as well as a legal irrelevance.

---

## Host rights

All self-service at `/account/privacy`, no support ticket required.

| Right | How |
| --- | --- |
| Access | Export produces a ZIP: JSON of account, properties, guide content, translations, orders and invoices, plus every original media file. Emailed as a signed link within 24 hours, valid 7 days. |
| Rectification | Everything is editable in the dashboard. |
| Erasure | "Delete my account", password confirmation, 30-day grace with reminder emails at day 1 and day 25. |
| Restriction | Suspension — guide offline, content preserved. |
| Portability | The same export, machine-readable JSON with a documented schema. |
| Objection | Marketing opt-out per email and in the account. |
| Complain | Contact for the Garante per la protezione dei dati personali is in the privacy policy. |

### The erasure pipeline

The one place in the system where hard `DELETE` runs. It is deliberate,
two-person and audited.

```
day 0   request (password confirmed)
        → account.status = 'archived', guide unpublished, public URL 404
        → confirmation email with a cancel link
day 1   reminder
day 25  final reminder
day 30  erasure job:
          DELETE  media objects from storage (originals, variants, quarantine)
          DELETE  content_blocks, guide_sections, guides, guide_versions,
                  recommendations, translations, media, media_variants,
                  media_usages, properties, public_urls, qr_codes,
                  onboarding_progress, admin_notes, entitlements,
                  auth_tokens, account_members, user_roles, email_log
          ANONYMISE users:      email → deleted-<uuid>@invalid, name → 'Deleted user',
                                phone/avatar/password → NULL
          ANONYMISE accounts:   name/legal_name → 'Deleted account',
                                billing_email → NULL
          RETAIN               orders, payments, refunds, invoices — with the
                               account_id kept but every personal field cleared.
                               Italian law requires the accounting record for ten
                               years; it does not require a name to sit in it.
          RETAIN               activity_logs, with actor_label replaced by
                               'Deleted user' and actor_user_id set NULL
          RETAIN               analytics_daily — aggregates, no personal data
          DELETE               analytics_events rows for the guide
          WRITE                one erasure record: account reference, requested_at,
                               completed_at, operator. No personal data in it.
```

A super admin can trigger erasure on request, but it requires a second super
admin to confirm. Irreversible destruction of a customer's business records is
not a one-click operation.

---

## Sub-processors

| Processor | Purpose | Location | Transfer basis |
| --- | --- | --- | --- |
| Stripe | Payments | EU + US | SCCs, DPF |
| Cloudflare R2 | Media storage | EU region pinned | SCCs |
| Cloudflare | CDN, edge | Global | SCCs |
| Database host | PostgreSQL | EU (Frankfurt) | — |
| Email provider | Transactional email | EU | — |
| DeepL | Translation (Pro) | EU (Germany) | — |
| Sentry | Error tracking | EU region | — |

DeepL is chosen over a US LLM API for the default translation engine partly on
quality and partly because EU-resident processing is a materially simpler
compliance story for a product whose customers are EU micro-businesses.

**What is never sent to a translation engine**: anything marked
`sensitive` — door codes, Wi-Fi passwords, phone numbers, addresses. This is
enforced in the translation worker by the same field-schema flag that masks it in
logs ([11](11-multilingual.md)), not by a separate list that could drift.

The sub-processor list is published and versioned; hosts are notified 30 days
before one changes.

---

## Data protection by design

| Decision | Privacy effect |
| --- | --- |
| No guest accounts | The largest category of personal data simply does not exist |
| No cookies on the guest guide | No consent mechanism needed |
| Daily-rotating, unstored analytics salt | Cross-day tracking is impossible by construction |
| EXIF stripped on upload | Hosts do not accidentally publish their home's GPS coordinates |
| Guides `noindex` by default | A home's address and phone number are not indexed without a decision |
| Sensitive fields excluded from MT, logs, snapshots and indexable renders | One flag, four protections |
| Audit log on admin reads of customer data | Insider access is visible |
| Erasure requires two people | Accidental destruction is hard |
| Search queries never stored | Guests' questions stay theirs |

---

## The host's own obligations

A host collecting guest data through our product would become a controller. We
make that hard on purpose:

- There is **no form builder**, no guest registration, no contact capture in the
  guide.
- The guide is one-way: the host publishes, the guest reads.
- If a host asks for guest forms (it comes up), the answer is that we will build
  it when we can do it without making every host a data controller — and that is
  a Phase 3 conversation with a DPIA, not a feature request.

Review requests (Phase 2) will link out to the platform the booking came from,
so the review and its personal data stay with that platform.

---

## Documents

Maintained alongside the product, versioned, with a changelog:

- Privacy policy (host-facing) — `myhouse.it/welcome/legal/privacy`
- Guest privacy notice — linked in the guide footer, one screen, plain language,
  stating that we hold nothing about them
- Terms of service
- DPA with sub-processor list, offered to every business customer
- Cookie policy — for the marketing site only, which is the only surface with
  cookies
- Record of processing activities (Art. 30)
- DPIA — not required at this scale and data sensitivity, but a short screening
  assessment is on file and is revisited when protected sections and booking
  links ship
