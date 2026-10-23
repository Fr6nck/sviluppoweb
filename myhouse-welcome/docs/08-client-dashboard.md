# 08 — Client dashboard and CMS

## Design philosophy

Calm, spacious, unmistakably not a WordPress admin. The host opens this perhaps
twice a month. It must be instantly re-learnable.

Rules the interface holds itself to:

1. **One primary action per screen.** Everything else is secondary or tertiary.
2. **No table where a card will do.** Hosts have 1–12 of most things, not 400.
3. **No nested navigation.** Maximum depth is two: sidebar item → editor.
4. **Never more than 7 sidebar items.**
5. **No dashboard widget that does not lead somewhere.**
6. **Nothing technical is named.** No "slug", no "module", no "deploy", no "sync".

---

## Home — `/`

```
┌──────────────────────────────────────────────────────────────────────┐
│  Hello, Marco                                                        │
│  Your MyHouse Welcome guide is online.                    [Preview]  │
│                                                       [Edit guide ▸] │
│                                                                      │
│  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌─────────────┐ │
│  │ Guide status │ │ Completion   │ │ Visits       │ │ QR code     │ │
│  │              │ │              │ │ this month   │ │             │ │
│  │ ● Published  │ │   85%        │ │              │ │  ▓▓░▓▓      │ │
│  │              │ │ ▓▓▓▓▓▓▓▓░░   │ │   125        │ │  ░▓░░▓      │ │
│  │ welcome.…/   │ │              │ │   ▁▃▅▂▇▄▆    │ │  ▓▓▓░▓      │ │
│  │ casa-san-…   │ │ 3 things     │ │              │ │             │ │
│  │      [Copy]  │ │ left ▸       │ │ Statistics ▸ │ │ Download ▸  │ │
│  └──────────────┘ └──────────────┘ └──────────────┘ └─────────────┘ │
│                                                                      │
│  ┌──────────────────────────────┐ ┌─────────────────────────────┐   │
│  │ Languages                    │ │ Last update                 │   │
│  │ Italiano · English           │ │ 12 September 2026            │   │
│  │ English: 4 items missing  ▸  │ │ You changed Wi-Fi            │   │
│  └──────────────────────────────┘ └─────────────────────────────┘   │
└──────────────────────────────────────────────────────────────────────┘
```

Three states for the headline, because it is the first thing read:

| Guide state | Headline |
| --- | --- |
| Published, no pending changes | Your MyHouse Welcome guide is online. |
| Published with pending changes | You have changes that guests can't see yet. → **Publish** |
| Draft | Your guide isn't online yet. → **Finish and publish** |

The "3 things left" link opens a checklist of the highest-value incomplete
sections, ranked by guest impact — not by wizard order. Missing Wi-Fi outranks
missing restaurants.

---

## Content — `/guide/content`

The section list. Drag to reorder, toggle to show or hide, tap to edit.

```
  Your guide
  Choose what your guests can see, and in what order.

  ⠿  👋  Welcome                      Complete          ●━  [Edit]
  ⠿  🔑  Check-in                     Complete          ●━  [Edit]
  ⠿  📶  Wi-Fi                        Complete          ●━  [Edit]
  ⠿  🚗  How to arrive                2 fields empty    ●━  [Edit]
  ⠿  🅿️  Parking                      Complete          ●━  [Edit]
  ⠿  🛋  Services                     Empty             ━○  [Edit]
  ⠿  🔌  Appliances                   3 items           ●━  [Edit]
  ⠿  📋  House rules                  Complete          ●━  [Edit]
  ⠿  ♻️  Waste & recycling            Complete          ●━  [Edit]
  ⠿  🍽  Where to eat                 12 places         ●━  [Edit]
  ⠿  🎟  Experiences         Available with Pro         ━○   Learn more
  ⠿  👋  Check-out                    Complete          ●━  [Edit]
  ⠿  🚨  Emergency                    Complete          ●━  [Edit]

  [ + Add your own section ]                        3 of 3 used · Pro adds 20
```

- The toggle controls guest visibility. A disabled section keeps its content.
- Status text is plain: "Complete", "Empty", "2 fields empty", "12 places".
- Gated sections stay in the list, muted, with one quiet link. They are not
  hidden — a host should be able to see the shape of the product they could have.
- Drag handles are keyboard-operable (`Space` to lift, arrows to move, `Space` to
  drop) with a live region announcing the new position.

### Section editor

Opens as a slide-over on desktop (the list stays visible behind it) and as a full
page on mobile.

```
┌─ Wi-Fi ───────────────────────────────────── Saved ✓ ──── ✕ ─┐
│                                                               │
│  Section title shown to guests                                │
│  [ Wi-Fi                                            ]  🇮🇹 ▾  │
│                                                               │
│  Icon    [ 📶 ▾ ]                                             │
│                                                               │
│  Network name                                                 │
│  [ Casa San Francesco                               ]         │
│                                                               │
│  Password                                                     │
│  [ ••••••••••••                              👁 ]             │
│  Guests can copy this with one tap.                           │
│                                                               │
│  Anything else to know?                                       │
│  [ The router is in the hallway cupboard.           ]  🇮🇹 ▾  │
│  [                                                  ]         │
│                                                               │
│  Photos                                                       │
│  [ + Add photos ]                                             │
│                                                               │
│  ─────────────────────────────────────────────────────────    │
│  Show this section to guests                          ●━      │
│  Hide behind a code                    Available with Pro     │
└───────────────────────────────────────────────────────────────┘
```

The 🇮🇹 chip next to a translatable field opens the per-field translation popover
([11](11-multilingual.md)). Non-translatable fields (Wi-Fi password, phone
numbers, codes) have no chip — a Wi-Fi password translated into German is a
support ticket.

### Custom sections

Within the entitlement limit, a host adds a section with title, icon, and a
composable body of blocks: text, image, gallery, file, link, list, contact,
video (Pro). Same editor, same translation flow, same preview.

---

## Live preview

The feature that makes the CMS trustworthy.

**Desktop ≥ 1280px** — split view, persistent:

```
┌────────────────────────────────┬──────────────────────┐
│                                │   ┌──────────────┐   │
│  Section editor                │   │   ▔▔▔▔▔▔▔▔   │   │
│                                │   │              │   │
│  (list or open section)        │   │  Casa San    │   │
│                                │   │  Francesco   │   │
│                                │   │              │   │
│                                │   │  [Wi-Fi]     │   │
│                                │   │  Casa San F. │   │
│                                │   │  ••••••••    │   │
│                                │   │  [Copy]      │   │
│                                │   └──────────────┘   │
│                                │   🇮🇹 ▾   Refresh ↻   │
└────────────────────────────────┴──────────────────────┘
```

- The preview is the **real guest renderer** in an iframe, not a mock. It receives
  the draft snapshot through `postMessage` on every autosave, debounced to 400 ms.
- It follows the editor: opening the Wi-Fi section scrolls the preview there.
- The locale switcher previews any enabled language, including untranslated
  fallbacks, so a host can see what a German guest will actually read.

**Below 1280px** — a `Preview` button opens the same renderer full-screen with a
sticky "← Back to editing" bar. No cramped split.

The preview always shows **draft** content with a small `Draft` marker, so there
is no confusion between what the host sees and what guests see.

---

## Saving

- Autosave, debounced 800 ms after the last keystroke, per field group.
- Indicator states: `Saved` (persistent, calm) · `Saving…` · `Not saved yet —
  we'll save when you're back online` · `Couldn't save — retry`.
- No Save button anywhere. There *is* a **Publish** button, and the distinction is
  taught once, in the publish bar: "Saved means we've kept it. Published means
  guests can see it."
- Optimistic UI with rollback on failure, and a toast naming the field that
  failed.
- Concurrent edits (host on a phone, support on a laptop) are resolved
  last-write-wins **per field**, not per section, with a notice: "Check-in was
  also changed by MyHouse support a moment ago. [See what changed]".

---

## The publish bar

Appears the moment `has_unpublished_changes` becomes true, and stays until
publish. Sticky, bottom on mobile, bottom-right on desktop.

```
┌──────────────────────────────────────────────────────┐
│  You have changes guests can't see yet                │
│                          [ Preview ]  [ Publish ▸ ]   │
└──────────────────────────────────────────────────────┘
```

Publishing shows a short summary of what will change, and — when relevant — what
will be left out and why ([05](05-packages-and-entitlements.md#3-the-publish-pipeline-the-safety-net)).

---

## Languages — `/guide/languages`

```
  Languages
  Your guide is written in Italian. Guests can read it in these languages.

  🇮🇹 Italiano      Original                                       ●━
  🇬🇧 English       Ready · 2 items need review            Review ▸ ●━
  🇩🇪 Deutsch       38 of 54 translated                    Continue ▸ ━○
  🇫🇷 Français      Not started                              Start ▸ ━○

  [ + Add a language ]                        2 of 5 used · Pro adds 20

  Automatic translation                            Available with Pro
  Translate everything into your languages in about a minute,
  then review before publishing.                        Learn more ▸
```

Detail in [11](11-multilingual.md).

---

## QR & Link — `/guide/qr`

```
  Your guide address
  welcome.myhouse.it/casa-san-francesco              [ Copy ]  [ Change ]

  Your QR code
  ┌───────────┐
  │  ▓▓░▓░▓▓  │   This code always works, even when you change
  │  ░▓▓░▓░░  │   your guide or your address.
  │  ▓░░▓▓▓░  │
  │  ▓▓░░▓░▓  │   [ PNG ]  [ SVG ]  [ PDF ]
  └───────────┘
                  Printable card                Available with Pro
```

Changing the address warns plainly: "Your old address will keep working and send
guests to the new one. Printed QR codes are not affected."

---

## Appearance — `/guide/appearance`

Constrained on purpose. No CSS field, no hex-anything for Essential.

- **Logo** and **cover image** (all packages)
- **Colour** — a palette of 12 curated accents, each pre-validated for contrast
  against both guide backgrounds (Plus and Pro)
- **Typography** — three approved pairings: *Editorial* (serif display + sans
  body), *Clean* (sans throughout), *Warm* (humanist) (Pro)
- **Corner style** — soft / sharp (Pro)
- **MyHouse branding** — Essential and Plus show "Powered by MyHouse"; Pro may
  reduce it to a small mark or remove it

Every choice previews live. There is no combination available in this screen that
produces an inaccessible or ugly guide; that is the point of the constraint.

---

## Statistics — `/statistics`

Basic (all packages): visits this month, visits over time, and the single most
opened section.

Advanced (Pro): section ranking, language split, entry source (QR vs link),
outbound clicks by type (phone, WhatsApp, maps, recommendation), device split,
day-of-week pattern, and a 12-month history.

Every number is explained in one line beneath it. "125 visits — guests opened
your guide 125 times this month." Nothing is called a metric, a KPI, a session or
an event.

---

## Account — `/account`

Profile (name, email, phone, language), password, billing details (company, VAT /
codice fiscale, SDI or PEC, address), orders and invoices, current package with a
plain list of what it includes, and — under `/account/privacy` — export my data
and delete my account ([22](22-privacy-gdpr.md)).

---

## Responsive behaviour

| Breakpoint | Layout |
| --- | --- |
| `< 640` | Single column. Bottom tab bar: Home · Guide · Languages · Account. Editor is full-page. Preview is a separate screen. |
| `640–1023` | Single column, collapsible sidebar as a drawer. Two-up dashboard cards. |
| `1024–1279` | Persistent sidebar (icon + label). Editor as a slide-over. Preview on demand. |
| `≥ 1280` | Sidebar + content + persistent preview rail. |
| `≥ 1600` | Same, wider content column, preview stays at device width. |

The dashboard is desktop-first in density but every screen is fully usable on a
phone — including publishing, because hosts fix typos from the road.
