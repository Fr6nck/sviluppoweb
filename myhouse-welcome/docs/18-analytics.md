# 18 — Analytics

## Principle

We measure **the guide**, never **the guest**. Every question the product needs
answered is about content — which sections get opened, which languages get used,
whether the QR card on the fridge works. None of them requires knowing who
anybody is.

That is not only an ethical position; it is what removes the cookie banner from
the guest experience, and a cookie banner on a welcome guide is an insult to a
guest holding a suitcase.

---

## No cookies, no identity

```
guest opens the guide
  → server records one row:
      guide_id, occurred_at, event_type, section_key, locale,
      entry_source, device_type, country, referrer_host,
      visitor_hash = sha256(ip + user_agent + daily_salt)[:16]
```

- `daily_salt` is generated at 00:00 UTC, held in memory and in Redis, and
  **never persisted**. Yesterday's hashes cannot be linked to today's, even by us,
  even with the database.
- The raw IP is used to derive the hash and the country, and is then discarded.
  It is never stored.
- `visitor_hash` exists for one purpose: distinguishing "125 visits" from "125
  different people" within a single day. It is not an identifier and cannot be
  reversed or joined across days.
- No third-party script, no pixel, no fingerprinting, no `localStorage` for
  analytics. The one `localStorage` key on the guest surface is the chosen
  language, which is a preference, not a measurement.

Under GDPR this is not personal data processing requiring consent, and under the
ePrivacy Directive there is no terminal storage or access requiring it either.
Reasoning in [22](22-privacy-gdpr.md).

---

## Events

| Event | Fired when | Extra fields |
| --- | --- | --- |
| `guide_view` | The guide home renders | `entry_source`, `locale` |
| `section_view` | A section page renders | `section_key` |
| `outbound_click` | Phone, WhatsApp, maps, website or recommendation tapped | `target_type`, `target_id` |
| `language_switch` | Locale changed | `locale` (the new one) |
| `search` | A search is run | query **length only**, never the query |
| `copy` | A copy button used | `target_type` (`wifi_password`, `door_code`, `address`) |
| `qr_scan` | `/q/:token` resolved | — |
| `pin_unlock` | A protected section unlocked | `section_key` |

**Search queries are never stored.** A guest typing "where is the hospital" into
a welcome guide has told us something we have no business keeping. The length
alone answers the only product question ("are people searching?").

### Collection

- `guide_view` and `section_view` are recorded **server-side during render**, so
  they work with JavaScript disabled and cannot be blocked by an ad blocker. They
  are also therefore accurate, which client-side analytics is not.
- `outbound_click`, `copy`, `language_switch` and `search` use a single
  `navigator.sendBeacon` to `/api/public/events`, fire-and-forget, never blocking
  navigation.
- `qr_scan` is recorded at `/q/:token` **after** issuing the redirect. Analytics
  never delay a guest.
- Bot traffic is classified by user agent and stored with `device_type = 'bot'`,
  excluded from every host-facing number but kept so that an unexplained spike
  can be explained.
- Host previews and admin support sessions set `is_internal = true` and are
  excluded from host-facing counts. A host refreshing their own guide twenty
  times while editing should not see "20 visits".

---

## Storage and rollup

```
analytics_events          partitioned monthly, 14-month retention
      │
      │  hourly rollup job
      ▼
analytics_daily           (guide, date, metric, dimension) → count
                          kept indefinitely: pure aggregate, no personal data
```

Retention is enforced by `DETACH PARTITION` + `DROP`, which is instant and
complete — not a `DELETE` that leaves rows in the heap until vacuum.

Every host-facing chart reads `analytics_daily`. Raw events are queried only by
the rollup job and by platform staff investigating an anomaly. A host's
statistics page never touches the raw table, so it stays fast no matter how
popular a guide becomes.

---

## What a host sees

### All packages — `welcome.analytics.basic`

```
  Your guide this month

  125 visits
  Guests opened your guide 125 times.

  ▁▃▅▂▇▄▆▃▅▇▄▂▁▃▅▆▇▅▃▂▄▆▅▃▂▁▄▆▇▅

  Most opened:  Wi-Fi
```

Three numbers. Every one has a sentence beneath it in plain language. Nothing is
called a metric, a session, an event or a KPI.

### Pro — `welcome.analytics.advanced`

- Section ranking, with the open rate per section
- Language split — "38% of your guests read it in English"
- Entry source — QR code vs link. **The one that pays for itself**: it tells a
  host whether printing and placing the card was worth it
- Outbound clicks by type — how often guests called, opened WhatsApp, opened maps
- Which recommendations guests actually tap
- Device split
- Day-of-week and hour-of-day pattern — which is when check-ins happen
- 12-month history with month-on-month comparison
- CSV export

### Insights, not just numbers

The statistics page leads with sentences, because a host does not want a
dashboard. Generated from thresholds on the rollup data:

> Most of your guests open the guide between 14:00 and 17:00 — just before
> check-in.

> 38% of your guests read the guide in English. German is your next most common
> browser language and you haven't added it yet. **[Add German]**

> Nobody has opened "Appliances" this month. It might be worth a clearer title.

> 71% of your visits come from the QR code. The card is working.

Each insight links to the action it implies. An insight a host cannot act on is
a statistic wearing a costume.

---

## What the platform sees

`/admin/analytics`, aggregated across accounts and never joined to guest data:

- Guides published per week, activation funnel by package
- Median time from purchase to publish
- Onboarding step drop-off — **the most valuable number in the product**, because
  it says which question is too hard to answer
- Completion distribution
- Feature adoption per entitlement key, which tells us whether Plus is worth what
  we charge
- Translation usage and machine-translation cost per account
- Media storage per account against quota
- Guide view volume, to size infrastructure

---

## Honesty rules

The numbers shown to hosts obey rules that make them trustworthy rather than
flattering:

1. **Never inflate.** Bot traffic, host previews and support sessions are
   excluded. A host who cannot trust "125" will not trust anything else either.
2. **Say when there is not enough data.** Under 20 visits, trend lines are
   suppressed and replaced with "Not enough visits yet to show a trend."
3. **Never show a percentage without its denominator.** "38% (47 of 125)".
4. **Never invent precision.** Unique visitors are approximate by construction,
   and the interface says "about 80 people" rather than "80".
5. **Explain the gap.** Where basic and advanced differ, the basic view says what
   the fuller picture would show, once, quietly — not as a locked chart with a
   blurred overlay.
