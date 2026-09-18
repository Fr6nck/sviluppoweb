# 21 — Accessibility and performance

These are one document because on the guest surface they are the same problem:
someone needs an answer, and anything between them and it is a defect.

---

## Accessibility

Target: **WCAG 2.2 AA** on all four surfaces. The guest guide additionally meets
AAA for text contrast, because it is read in bad light by people who did not
choose to use it.

### Non-negotiables

| Requirement | How it is held |
| --- | --- |
| Semantic HTML first | Sections are `<section>` with headings; the guest grid is a `<ul>` of links; accordions are `<details>`. ARIA is used only where HTML has no equivalent. |
| Keyboard operable | Every interactive element reachable and operable. Drag-to-reorder has a keyboard path (`Space` lift, arrows move, `Space` drop) with a live region announcing position. |
| Visible focus | 2px `brand-600` ring at 2px offset. `outline: none` without a replacement fails CI. |
| Contrast | Body ≥ 4.5:1, large text ≥ 3:1, UI borders ≥ 3:1. The host colour palette is 12 pre-validated accents — an inaccessible combination cannot be chosen. |
| Labels | Every input has a `<label>`. Placeholders are never labels. |
| Errors | `aria-describedby` on the field, text with an icon, announced politely. |
| Touch targets | 44×44 minimum, 8px minimum between adjacent targets. |
| Motion | `prefers-reduced-motion` collapses all durations at the token level. |
| Zoom | 200% zoom and 320px width both work with no horizontal scroll. |
| Alt text | Every content image has a field with a real prompt; decorative images get `alt=""`. Filenames are never used. |
| Language | `lang` on `<html>`, and on any element in a different language — so a screen reader pronounces an Italian street name correctly inside an English guide. |
| Headings | One `<h1>` per page, no skipped levels. |
| Colour | Never the only signal. Status is always icon + word. |

### Specific to this product

- **The Wi-Fi password is selectable text**, not an image, not a canvas, not
  masked-without-reveal. Someone using a screen reader or a password manager must
  be able to get at it.
- **The copy button announces its result** through a live region: "Password
  copied". A silent state change is invisible to a screen reader user.
- **Phone numbers are `tel:` links**; WhatsApp is a real `https://wa.me/` link.
  Both work with voice control ("tap call").
- **The emergency section is reachable in one tap from anywhere**, and its link
  text is "Emergency", not an icon.
- **Section titles are host-written**, so the editor warns when a title is only an
  emoji or under three characters — it will be read aloud by someone.
- **PDFs are labelled with their size and page count** before opening, because
  opening a 12 MB PDF on a metered connection should be a choice.

### Testing

Automated (`axe-core` in CI on every route, build fails on a violation) catches
roughly a third of real problems. The rest needs:

- Full keyboard pass on every flow before release, by hand.
- VoiceOver on iOS for the guest guide, NVDA on Windows for the dashboard.
- 200% zoom and 320px viewport.
- Forced-colors mode (Windows high contrast).
- One session per quarter with someone who uses a screen reader daily. Nothing
  else finds what this finds.

---

## Performance

### Budgets

| Surface | Metric | Budget |
| --- | --- | --- |
| **Guest guide** | LCP (4G, mid-tier Android) | < 1.5 s |
| | TTFB (edge hit) | < 200 ms |
| | INP | < 150 ms |
| | CLS | < 0.05 |
| | HTML, before images | < 20 KB gzipped |
| | JS, initial | < 30 KB gzipped |
| | **Readable with JS disabled** | required |
| Marketing | LCP | < 2.0 s |
| | JS, initial | < 80 KB |
| Dashboard | LCP | < 2.5 s |
| | JS, initial | < 180 KB |
| | Autosave round trip | < 300 ms p95 |
| Admin | LCP | < 3.0 s |

Budgets are enforced in CI by Lighthouse CI and `size-limit`. Exceeding one fails
the build. A budget nobody enforces is a wish.

### How the guest guide meets its budget

1. **It is a document, not an application.** The published snapshot renders to
   static HTML at publish time. A request is a cache read.
2. **Critical CSS is inline** (~6 KB); the rest loads asynchronously. One render
   pass, no blocking stylesheet.
3. **JavaScript is progressive enhancement only** — copy buttons, the lightbox,
   the Wi-Fi QR, search, language memory. It is deferred, and everything readable
   is readable without it.
4. **Images**: AVIF with WebP and JPEG fallbacks, explicit `width`/`height`,
   `loading="lazy"` below the fold, `fetchpriority="high"` on the cover, and a
   20-byte blur placeholder as a `background-image` so there is never an empty
   box.
5. **Fonts**: self-hosted WOFF2 subsets, `font-display: swap`, preloaded for the
   two faces used above the fold, with metric-matched fallbacks so the swap
   causes no shift.
6. **No third-party scripts at all.** No tag manager, no chat, no analytics
   vendor, no font CDN in the critical path. This is worth more than every other
   optimisation combined.
7. **Video is a facade.** A thumbnail with a play button; the YouTube or Vimeo
   iframe loads on tap. An embedded player costs ~900 KB before anyone presses
   play.
8. **Edge cached** with `s-maxage=300, stale-while-revalidate=86400`, purged on
   publish.

### The number that matters

**Cache hit ratio on the guest surface.** Everything above serves it. It is
alerted below 90% because a miss means a guest at a locked door waits on our
origin — which is exactly the situation the whole architecture exists to avoid.

### Dashboard

- Route-level code splitting; the section editor, the media library and
  statistics are separate chunks.
- The live preview iframe is created once and updated by `postMessage`, never
  re-mounted — remounting an iframe per keystroke is the obvious way to build
  this and it is unusably slow.
- Autosave is debounced at 800 ms and sends only the changed field group.
- Optimistic UI with rollback, so typing never waits on a round trip.
- Lists are virtualised above 100 rows; below that, virtualisation costs more
  than it saves.

### Database

- The guest path is one indexed lookup returning one JSONB row
  ([14](14-data-model.md#indexing-strategy)), verified against an `EXPLAIN` in
  `schema/tests.sql`.
- Slow query log at 100 ms, reviewed weekly.
- `EXPLAIN` in CI for the ten hot queries; a plan regressing to a sequential scan
  fails the build.
- Analytics never blocks a request: rollups are hourly, host charts read
  `analytics_daily`, and the QR redirect writes its event after responding.

---

## SEO

| Surface | Indexed | Why |
| --- | --- | --- |
| Marketing | Yes, with sitemap and schema.org `Product` + `FAQPage` | We want to be found |
| Application and admin | No | Private |
| Guest guide | **No by default**, host opt-in | It is a private document containing a home's address, a phone number and access instructions |
| Protected sections | Never, no exception | They contain codes |

When a host opts in, sensitive-flagged fields are excluded from the indexable
render entirely and loaded client-side, and the toggle says exactly what it
means. The default is off because the safe choice should not require a decision.
