# 09 — Guest guide

The only surface with real users at scale, on bad connections, in a hurry.

## Design constraints, in priority order

1. **Answer in seconds.** A guest at a locked door wants the code, not a brand
   experience.
2. **Works on anything.** A five-year-old Android on 3G in a stone stairwell.
3. **No install, no login, no cookie banner.**
4. **Readable in sunlight and at night.**
5. **Never leaks a draft.**

Everything else is subordinate to these.

---

## Home

```
┌─────────────────────────────┐
│ ┌─────────────────────────┐ │
│ │                         │ │  cover image, 16:10
│ │      [cover photo]      │ │  AVIF → WebP → JPEG
│ │                         │ │  LQIP blur placeholder
│ └─────────────────────────┘ │
│                             │
│  ⌂ logo                🇮🇹 ▾│
│                             │
│  Casa San Francesco         │  Display serif
│  Welcome, and make          │
│  yourself at home.          │  Host's welcome message, 2 lines, expandable
│                             │
│  ┌────────┐  ┌────────┐     │
│  │  🔑    │  │  📶    │     │
│  │Check-in│  │ Wi-Fi  │     │  2-up grid, 44px+ targets
│  └────────┘  └────────┘     │
│  ┌────────┐  ┌────────┐     │
│  │  🚗    │  │  🅿️    │     │
│  │ Arrive │  │Parking │     │
│  └────────┘  └────────┘     │
│  ┌────────┐  ┌────────┐     │
│  │  🛋    │  │  📋    │     │
│  │Services│  │ Rules  │     │
│  └────────┘  └────────┘     │
│  ┌────────┐  ┌────────┐     │
│  │  🍽    │  │  🎭    │     │
│  │  Eat   │  │   Do   │     │
│  └────────┘  └────────┘     │
│  ┌────────┐  ┌────────┐     │
│  │  ♻️    │  │  👋    │     │
│  │ Waste  │  │Checkout│     │
│  └────────┘  └────────┘     │
│  ┌─────────────────────┐    │
│  │  🚨  Emergency      │    │  full width, semantic danger tint
│  └─────────────────────┘    │
│                             │
│  📞 Call  ·  💬 WhatsApp    │  sticky contact bar
│                             │
│  Powered by MyHouse         │
└─────────────────────────────┘
```

- Two columns on phones, three from 600px, four from 900px, capped at a 720px
  content width on desktop so it never becomes a website.
- Cards carry an icon, a short label, and optionally one line of description. The
  label is the guest's word, not the host's: "Eat", not "Gastronomic
  recommendations".
- Emergency is always last, always full width, always present — even in an
  otherwise empty guide, prefilled with the country emergency number.
- The sticky contact bar appears only if the host provided a phone or WhatsApp.

---

## Section page

```
┌─────────────────────────────┐
│ ←  Wi-Fi              🇮🇹 ▾ │
├─────────────────────────────┤
│                             │
│  Network                    │
│  Casa San Francesco         │
│                       [Copy]│
│                             │
│  Password                   │
│  ••••••••••          [Show] │
│                       [Copy]│
│                             │
│  [ Connect with QR ]        │
│                             │
│  The router is in the       │
│  hallway cupboard. If it    │
│  stops working, switch it   │
│  off for ten seconds.       │
│                             │
├─────────────────────────────┤
│  Next: How to arrive  →     │
└─────────────────────────────┘
```

Each block type has one job and one layout:

| Block | Guest rendering |
| --- | --- |
| `wifi` | Network + password with copy buttons and a `WIFI:` QR built in the browser |
| `key_value` | Label / value pairs, value selectable, copy button for codes |
| `rich_text` | Constrained typography: paragraphs, bold, lists, links. No arbitrary HTML. |
| `image` / `gallery` | Responsive `<picture>`, lazy below the fold, tap to open a lightbox |
| `file` | PDF card with name and size, opens in a new tab, never auto-downloads |
| `video` | Facade thumbnail; the YouTube/Vimeo iframe loads only on tap ([21](21-accessibility-and-performance.md)) |
| `map` | Static map image + `Open in Google Maps` / `Open in Apple Maps` |
| `contact` | Name, `tel:` and `https://wa.me/` links, both tracked as outbound clicks |
| `list` | Checklist style, used by house rules and check-out |
| `faq` | Native `<details>` / `<summary>` — accessible and JS-free |
| `hours` | Simple time table, used by waste collection |

"Next: …" at the foot of each section follows the host's own order, which turns
the guide into something a guest can read straight through on the train.

---

## Recommendations

```
┌─────────────────────────────┐
│ ←  Where to eat       🇮🇹 ▾ │
├─────────────────────────────┤
│ [All] [Restaurants] [Bars]  │  horizontal scroll chips
│ [Breakfast] [Shops]         │
├─────────────────────────────┤
│ ┌─────────────────────────┐ │
│ │ [photo]                 │ │
│ │ Trattoria del Porto  ★  │ │  ★ = host's pick
│ │ Restaurant · 400 m      │ │
│ │ Simple fish, family run.│ │
│ │ [Maps] [Call]           │ │
│ └─────────────────────────┘ │
│ ┌─────────────────────────┐ │
│ │ …                       │ │
│ └─────────────────────────┘ │
└─────────────────────────────┘
```

Host picks sort first within each category. Distance is shown when the host gave
coordinates; it is computed at publish time, not in the browser, because we do
not ask the guest for location.

---

## Search — Plus and Pro

A single field at the top of home. The index is built at publish time and
embedded in the snapshot as a small JSON blob (typically 8–20 KB), so search
works with no network round-trip and no server. Matching is substring +
accent-insensitive across section titles, block labels, body text and
recommendation names.

Essential guides have no search field. With ~10 sections on one screen, search is
not what is missing.

---

## Language switching

- A flag-free, text-based selector (`Italiano · English · Deutsch`) in the header.
  Flags are wrong — English is not a flag.
- Order of resolution: `?lang=` → `localStorage` → `Accept-Language` → guide
  default.
- The choice is remembered in `localStorage`, not a cookie, so no consent banner
  is required ([22](22-privacy-gdpr.md)).
- Untranslated fields **fall back to the original language** rather than showing
  blank, with a one-time notice: "Some parts are only available in Italian."
  A guest who can read some of it is better served than a guest who reads nothing.
- Crawlable locale paths (`/<slug>/en/wi-fi`) exist alongside the query parameter,
  with `hreflang` links between them.

---

## Protected sections — Pro

Three mechanisms, all modelled now, shipped in Phase 2:

| Mechanism | Behaviour |
| --- | --- |
| PIN | Section asks for a code the host shares with the guest. Verified server-side, rate-limited, PIN stored as an Argon2 hash. |
| Date window | Section exists only between `visible_from` and `visible_to`. Absent, not locked, outside it. |
| Booking-linked | Phase 2+: a signed token in the URL unlocks sensitive sections for the stay dates only. |

A protected section is never in the published snapshot in plaintext. Its content
is fetched over a separate authorised request after the PIN is verified, so a
snapshot leak cannot expose a door code.

---

## Rendering strategy

```
publish
  ▼
guide_versions.snapshot  (immutable JSON, complete, per-locale)
  ▼
static HTML generated per (slug, locale, section)
  ▼
edge cache, s-maxage=300, stale-while-revalidate=86400
  ▼
guest: HTML + critical inline CSS, ~14 KB before images
```

- **No JavaScript is required to read anything.** JS adds copy buttons, the
  lightbox, the Wi-Fi QR, search and the language memory — all progressive
  enhancements. With JS blocked, the password is still on the page and still
  selectable.
- Purge on publish, on slug change, and on unpublish.
- The snapshot is what is served. A host editing a draft cannot break a live
  guide, and a database outage does not take published guides down.

Performance budget and targets in [21](21-accessibility-and-performance.md).

---

## Error and edge states

| Situation | What the guest sees |
| --- | --- |
| Slug does not exist | "This guide isn't available." Nothing about accounts or properties. |
| Guide unpublished | The same page. We never reveal that a guide exists but is hidden. |
| Guide suspended (refund) | The same page. |
| Slug changed | `301` to the new slug, permanently, from the `public_urls` history. |
| Section empty | The section is not shown at all. Guests never meet an empty page. |
| Image failed to load | The layout holds its aspect ratio; alt text is shown. |
| PDF too large on mobile | Card shows the size; opening is always a deliberate tap. |
| Offline after first load | Browser cache serves the last version; a quiet bar says "You're offline — showing the last version you loaded." |

---

## What is deliberately absent

- No cookie banner (no cookies).
- No chat widget.
- No newsletter prompt.
- No review request during the stay (Phase 2, and only at check-out).
- No guest accounts, ever.
- No third-party analytics scripts.
- No autoplaying anything.

Each of these was considered and rejected against constraint 1.
