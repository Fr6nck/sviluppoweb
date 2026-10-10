# 19 — Design system

Implemented literally in [`prototype/styles/tokens.css`](../prototype/styles/tokens.css)
and demonstrated in [`prototype/design-system.html`](../prototype/design-system.html).

---

## Identity

MyHouse Welcome should feel like a **well-kept house in the Italian countryside,
run by someone who knows what they are doing**. Warm, calm, unhurried,
quietly precise.

The Apple influence is a philosophy, not a skin: restraint, generous whitespace,
one clear action per screen, typography carrying the hierarchy instead of boxes
and rules, motion that explains rather than decorates.

What it is not: not startup-purple, not a gradient, not glassmorphism, not
"luxury" gold-on-black, not a dashboard full of widgets.

### Three deliberate departures from a generic system

1. **The neutral is warm, not grey.** Backgrounds carry a hint of linen
   (`#FAF8F5`), not `#F9FAFB`. Hospitality is warm; cool grey reads as a SaaS
   admin panel.
2. **A serif carries the display sizes.** Fraunces, at optical size, for
   marketing headlines and the property name in the guest guide. It is what makes
   a guide feel like a house rather than an app.
3. **The primary is a deep cypress green**, not blue. Blue is the default of
   every tool the host already dislikes.

---

## Colour

### Neutrals — warm

| Token | Value | Use |
| --- | --- | --- |
| `--mh-surface` | `#FAF8F5` | Page background |
| `--mh-surface-raised` | `#FFFFFF` | Cards, sheets, inputs |
| `--mh-surface-sunken` | `#F2EEE8` | Wells, code blocks, table headers |
| `--mh-border` | `#E7E2DA` | Default hairline |
| `--mh-border-strong` | `#D5CEC3` | Input borders, dividers that must read |
| `--mh-ink` | `#15171A` | Primary text |
| `--mh-ink-muted` | `#5B6068` | Secondary text, descriptions |
| `--mh-ink-subtle` | `#676C73` | Placeholders, metadata, card subtitles |
| `--mh-ink-inverse` | `#FFFFFF` | Text on brand fills |

### Brand — Cipresso

| Token | Value | Contrast on surface |
| --- | --- | --- |
| `--mh-brand-50` | `#EFF5F2` | — (tint background) |
| `--mh-brand-100` | `#D6E6DE` | — |
| `--mh-brand-200` | `#ADCDBE` | — |
| `--mh-brand-300` | `#7FB09C` | — |
| `--mh-brand-400` | `#52927B` | 3.4:1 — large text only |
| `--mh-brand-500` | `#2E7460` | 4.9:1 ✓ |
| `--mh-brand-600` | `#1F5B4B` | 7.3:1 ✓ — **primary action** |
| `--mh-brand-700` | `#17473A` | 9.5:1 ✓ |
| `--mh-brand-800` | `#103228` | 13.0:1 ✓ |

### Accent — Terracotta

Used sparingly: host's picks, the recommended package, one highlight per screen.
Never for a primary action — two competing calls to action is a design failure,
not a colour choice.

`--mh-accent-300 #E8A883` · `--mh-accent-500 #C9713F` — decorative only, never
text. `--mh-accent-600 #A4582D` is the only accent that carries text (4.53:1
worst case).

### Semantic

Each foreground clears 4.5:1 against its own tint **and** against all three
neutral surfaces, so a chip stays legible wherever it is placed.

| Role | Text/Icon | Background | Border | Worst-case ratio |
| --- | --- | --- | --- | --- |
| Success | `#1F7A4D` | `#EAF5EF` | `#BFE0CE` | 4.60:1 |
| Warning | `#916411` | `#FBF3E2` | `#EBD5A6` | 4.50:1 |
| Danger | `#B3392C` | `#FBEDEB` | `#EFC4BE` | 5.14:1 |
| Info | `#24628F` | `#EAF1F7` | `#BFD6E8` | 5.65:1 |
| **Support mode** | `#6B3FA0` | `#F2ECFA` | `#D6C2EE` | 6.38:1 |

Support mode has its own colour, used **nowhere else in the product**, so an
impersonation banner can never be mistaken for anything else
([10](10-admin-console.md#support-mode)).

Colour is never the only signal: status always carries an icon and a word as well.

**These numbers are measured, not asserted.** `prototype/contrast-check.mjs`
walks every visible text node on every prototype page, resolves its effective
background, and fails on anything under the AA threshold. Run it before changing
a colour. On its first run it caught three faults that review had missed:

| Fault | Measured | Cause |
| --- | --- | --- |
| Marketing nav CTA | **1.25:1** | A bare `.nav__links a` rule (specificity 0,2,0) outranked `.btn--primary` (0,1,0) and repainted the white label grey |
| `--mh-ink-subtle` on sunken | **2.78:1** | The value was picked to look right next to `ink-muted`, not measured against the surfaces it sits on |
| Warning chip | **4.28:1** | Close enough to pass by eye |

The first is the instructive one: it was invisible in code review and obvious in
a screenshot. The tonal gap between `ink-muted` (5.97:1) and `ink-subtle`
(4.99:1) is now smaller than originally drawn. That is the correct trade —
`ink-subtle` carries real text.

### Dark mode

The guest guide supports `prefers-color-scheme: dark`, because guests read it in
bed and in dark stairwells. The dashboard and admin are light-only in MVP — an
internal tool used in daylight, and a second theme is maintenance without a user.

Dark is not an inversion. Surfaces lift rather than darken, the brand desaturates
and lightens to hold contrast, and pure black is never used:

`--mh-surface #14161A` · `--mh-surface-raised #1C1F24` · `--mh-ink #ECEAE6` ·
`--mh-ink-muted #A8ADB5` (6.72:1) · `--mh-ink-subtle #878E98` (4.59:1) · brand
action `#7FB09C` (6.20:1). Dark mode is audited by the same script.

---

## Typography

| Role | Family | Fallback |
| --- | --- | --- |
| Display | **Fraunces** (variable, optical size) | `Georgia, 'Times New Roman', serif` |
| UI and body | **Inter** (variable) | `-apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif` |
| Numeric / codes | `ui-monospace, 'SF Mono', Menlo, monospace` | — |

Fonts are self-hosted WOFF2 subsets with `font-display: swap` and a metric-matched
fallback, so there is no layout shift when they load. A guest on 3G reads the
door code in the fallback face and never notices the swap.

Codes — Wi-Fi passwords, door codes, lockbox numbers — are always monospace with
`font-variant-numeric: tabular-nums` and generous letter spacing. A guest is
transcribing them into a keypad, one character at a time.

### Scale

| Token | Size / line-height | Use |
| --- | --- | --- |
| `--mh-text-2xs` | 12 / 16 | Metadata, legal |
| `--mh-text-xs` | 13 / 18 | Captions, chips |
| `--mh-text-sm` | 14 / 20 | Secondary, table cells |
| `--mh-text-base` | 16 / 26 | Body — **never smaller on the guest guide** |
| `--mh-text-lg` | 18 / 28 | Lead paragraphs |
| `--mh-text-xl` | 20 / 28 | Card titles |
| `--mh-text-2xl` | 24 / 32 | Section headings |
| `--mh-text-3xl` | 30 / 38 | Page titles |
| `--mh-text-4xl` | 38 / 44 | Guest guide property name |
| `--mh-text-5xl` | 48 / 54 | Marketing headline |
| `--mh-text-6xl` | 60 / 64 | Marketing hero |

Display sizes (4xl and up) use Fraunces with negative tracking (`-0.02em`) and
optical sizing; body sizes use Inter with normal tracking. Small caps and
all-caps labels get `+0.06em`.

**Measure is capped at 68 characters** for body text and 60 on the guest guide.

---

## Spacing, radius, elevation

Spacing is a 4px base: `2 4 8 12 16 20 24 32 40 48 64 80 96 128`.

The dashboard uses the full scale generously. The admin console uses the same
tokens one step tighter — the density difference between the two surfaces is a
single multiplier, not a second system.

| Radius | Value | Use |
| --- | --- | --- |
| `--mh-radius-sm` | 6px | Chips, small controls |
| `--mh-radius-md` | 10px | Buttons, inputs |
| `--mh-radius-lg` | 14px | Cards |
| `--mh-radius-xl` | 20px | Sheets, modals |
| `--mh-radius-2xl` | 28px | Guest guide cards, phone frame |
| `--mh-radius-full` | 999px | Pills, avatars |

Elevation is restrained — four levels, all of them subtle, all tinted with the
ink colour rather than pure black so shadows stay warm:

```
--mh-shadow-xs  0 1px 2px  rgba(21,23,26,.05)
--mh-shadow-sm  0 1px 3px  rgba(21,23,26,.06), 0 1px 2px rgba(21,23,26,.04)
--mh-shadow-md  0 4px 12px rgba(21,23,26,.07), 0 1px 3px rgba(21,23,26,.04)
--mh-shadow-lg  0 12px 32px rgba(21,23,26,.10), 0 2px 8px rgba(21,23,26,.05)
```

Elevation communicates layering, never decoration. A card at rest has `xs`. A
card does not lift on hover unless it is draggable.

---

## Motion

| Token | Duration | Curve | Use |
| --- | --- | --- | --- |
| `--mh-motion-instant` | 80ms | `ease-out` | Toggle, checkbox |
| `--mh-motion-fast` | 140ms | `cubic-bezier(.2,.8,.3,1)` | Hover, focus, colour |
| `--mh-motion-base` | 220ms | `cubic-bezier(.2,.8,.3,1)` | Slide-over, dropdown, toast |
| `--mh-motion-slow` | 340ms | `cubic-bezier(.32,.72,0,1)` | Sheets, page transitions |

Rules:

- Entering is slower than leaving. Arriving deserves attention; departing does not.
- Nothing animates opacity alone — pair it with a small transform so movement has
  a direction.
- No animation loops. A spinner is the only exception, and only after 400ms of
  waiting (below that, a flash of spinner is worse than nothing).
- `@media (prefers-reduced-motion: reduce)` collapses every duration to 1ms and
  removes transforms. It is applied once, globally, at the token level, so no
  component can forget it.

---

## Components

Twenty-six components, each with defined states. Rendered live in
[`prototype/design-system.html`](../prototype/design-system.html).

### Buttons

| Variant | Use | Appearance |
| --- | --- | --- |
| Primary | The one action | `brand-600` fill, white text |
| Secondary | Alternatives | White fill, `border-strong` |
| Ghost | Tertiary, toolbars | Transparent, ink text |
| Danger | Destructive | Danger fill, confirmation required |
| Link | Inline | Underlined on hover, brand text |

Sizes: `sm` 32px · `md` 40px · `lg` 48px. **Minimum touch target 44×44** on every
surface, achieved with padding rather than by growing the visible control.

States: rest, hover, active, focus-visible, loading (spinner replaces the label,
width held so nothing reflows), disabled (never for a validation failure — show
the error instead).

### Inputs

Label above, always visible — **never a placeholder as a label**. Help text below
the label, error text below the field with an icon. 44px minimum height, 16px
minimum font size on mobile so iOS does not zoom.

Focus is a 2px `brand-600` ring at 2px offset, never `outline: none`.

Specialised inputs: time, phone (with country prefix), URL (with paste-and-clean),
address with map pin, slug (with prefix and live availability), PIN, colour
(swatches only), toggle, tri-state (allowed / not allowed / ask), checkbox grid,
repeater, media drop zone.

### Media drop zone

The most-used input in the product.

```
┌─────────────────────────────────────┐
│           ⊕                          │
│    Drag photos here, or              │
│    [ Choose from your phone ]        │
│    [ Choose from your photos ]       │  ← media library
│                                      │
│    JPG, PNG or HEIC · up to 15 MB    │
└─────────────────────────────────────┘
```

States: empty, dragging over, uploading (per-file progress ring), processing,
ready (thumbnail with alt-text field), failed (reason + retry), quota reached
(plain sentence, not an error).

### Cards

Section card (dashboard list, with drag handle, status, toggle) · Stat card
(dashboard home) · Guest card (guide home grid) · Place card (recommendations) ·
Package card (pricing) · Empty-state card.

### Navigation

Sidebar (icon + label, 7 items max, gated items muted with a chip) · Bottom tab
bar below 640px · Breadcrumbs in admin only · Guest header (back, title, locale).

### Feedback

Toast (bottom-right desktop, bottom-centre mobile, 4s, one at a time, never for a
success that is already visible) · Inline alert · Banner (support mode, offline,
unpublished changes) · Skeleton loaders that match the real content's shape ·
Progress bar · Save indicator.

### Overlays

Slide-over (section editor, 480px, focus-trapped) · Modal (confirmations only,
max 480px) · Popover (translations, locale switch) · Tooltip (never for essential
information — touch devices have no hover).

### Data

Table (admin only; hosts get cards) with sticky header, sortable columns, row
links, and a horizontal scroll shadow. Definition list for guest key/value
content. Empty state for every collection ([20](20-content-states-and-copy.md)).

---

## Iconography

24×24 grid, 1.5px stroke, round caps and joins, no fills. Inline SVG with
`currentColor` — no icon font, no sprite request.

Guest guide section icons are the one exception: at 32px they carry a subtle
brand-tinted circular background, because on a grid of twelve cards, colour is
what a guest scans by.

Every icon that carries meaning has a text label. An icon alone is only
acceptable for universally understood controls (close, back, drag), and those
still carry an `aria-label`.

---

## The guest guide's own rules

The guest surface inherits the tokens and overrides three things:

1. **Bigger base type.** 17px, not 16px. Guests read on a phone, sometimes in the
   dark, often not in their first language.
2. **Bigger radii.** `2xl` (28px) on cards, which reads as friendly rather than
   administrative.
3. **Host branding applies here only.** The accent colour and typography pairing
   a host chooses in Appearance affect the guest guide and nothing else. The
   dashboard stays MyHouse-branded, so support staff always see the same
   interface a screenshot came from.

Host branding is constrained to a curated palette of 12 accents, each
pre-validated for contrast against both the light and dark guide backgrounds.
There is no hex input and no CSS field. A host cannot produce an inaccessible or
ugly guide, which is the entire point of constraining it.
