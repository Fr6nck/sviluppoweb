# 07 — Onboarding wizard

## What it must feel like

A host is answering questions about their home. They are not filling in a form
and they are definitely not configuring software.

| Never write | Write |
| --- | --- |
| Configure Wi-Fi module | Do your guests have Wi-Fi? |
| Enable parking section | Is there somewhere to park? |
| Upload media asset | Add a photo |
| Set arrival metadata | How do guests get to you? |
| Save configuration | Saved |

One question per screen block. Short answers. Everything optional except two
fields. A progress bar that tells the truth.

---

## Shape

```
┌───────────────────────────────────────────────────────────────┐
│  ← Back        Step 5 of 17 · Check-in            Save & exit  │
│  ▓▓▓▓▓▓▓▓▓▓▓▓░░░░░░░░░░░░░░░░░░░░░░░░  29%                     │
├───────────────────────────────────────────────────────────────┤
│                                                               │
│   How do guests get in?                                       │
│   This is the question guests ask most.                       │
│                                                               │
│   What time can guests check in?                              │
│   [ 15:00 ▾ ]        ☐ Flexible — I agree it with each guest  │
│                                                               │
│   Is it a self check-in?                                      │
│   ( ) Yes, guests let themselves in                           │
│   ( ) No, I meet them                                         │
│                                                               │
│   ┌── shown only when "Yes" ──────────────────────────────┐   │
│   │ Where are the keys?                                   │   │
│   │ [ Lockbox ▾ ]                                         │   │
│   │ Tell guests how to find and open it                   │   │
│   │ [                                                  ]  │   │
│   │ ⓘ Codes are stored securely. With Pro you can hide    │   │
│   │   them behind a PIN or show them only near arrival.   │   │
│   │ Add photos of the way in            [ + Add photos ]  │   │
│   └───────────────────────────────────────────────────────┘   │
│                                                               │
│                              Skip this step   [ Continue → ]  │
└───────────────────────────────────────────────────────────────┘
```

On mobile the same content is a single column with a sticky footer holding
**Continue**, and the progress bar collapses to a thin line under the header.

---

## The 17 steps

Steps marked **gated** appear only when the account is entitled; they are not
shown greyed out during onboarding, because a wizard is the wrong place to sell.
The upsell happens later, in Content, where the host has context.

| # | Step | Key | Required | Gate |
| --- | --- | --- | --- | --- |
| 1 | Your property | `property` | name | — |
| 2 | Your welcome | `welcome` | — | — |
| 3 | How to arrive | `arrival` | — | — |
| 4 | Parking | `parking` | — | — |
| 5 | Check-in | `checkin` | check-in time | — |
| 6 | Wi-Fi | `wifi` | — | — |
| 7 | Services & amenities | `services` | — | `welcome.sections.services` |
| 8 | Appliances | `appliances` | — | `welcome.sections.appliances` |
| 9 | House rules | `rules` | — | — |
| 10 | Waste & recycling | `waste` | — | `welcome.sections.waste` |
| 11 | Check-out | `checkout` | — | — |
| 12 | Safety & emergency | `emergency` | — | — |
| 13 | Places you recommend | `recommendations` | — | `welcome.sections.recommendations` |
| 14 | Extra services | `extras` | — | `welcome.sections.extras` |
| 15 | Languages | `languages` | — | — |
| 16 | Preview | `preview` | — | — |
| 17 | Publish | `publish` | — | — |

**Only two required fields in the whole wizard**: property name and check-in
time. Everything else can be skipped and added later. A guide with a name, a
check-in time and a Wi-Fi password is already more useful than seven WhatsApp
messages.

Essential accounts see 13 steps and finish in roughly 12 minutes. Pro accounts
see 17 and take around 25.

---

## Step content

### 1 — Your property
`name*`, `type` (apartment / house / B&B / room / villa / other), `logo`,
`cover_image`, `address` (with map pin), `short_description`, `phone`,
`whatsapp` (toggle "same as phone"), `email`, `website`.

Cover image is the single highest-impact field for perceived quality. It is asked
for first, with an inline gallery of "what makes a good cover" and a one-tap
camera option on mobile.

### 2 — Your welcome
`welcome_message` (textarea, with three starter templates the host can pick and
edit), `host_name`, `host_photo` (optional, with an explicit "guests trust a face"
nudge and an equally explicit skip).

### 3 — How to arrive
`by_car`, `by_train`, `by_bus`, `from_airport`, `transfer_available` (toggle →
`transfer_details`), `map_link` (prefilled from the step-1 address),
`arrival_notes`, photos.

### 4 — Parking
`has_parking` (yes/no) → `parking_type` (private / public / street / garage),
`parking_address`, `instructions`, `maps_url`, `photos`, `cost`.

### 5 — Check-in
`checkin_time*`, `checkin_flexible`, `self_checkin` (yes/no) → `key_location`
(lockbox / key safe / reception / neighbour / I hand them over / smart lock),
`access_instructions`, `door_code` *(flagged sensitive)*, `photos`, `pdf`,
`video_url` (Pro), `late_arrival_notes`.

### 6 — Wi-Fi
`has_wifi` (yes/no) → `ssid`, `password`, `instructions`, `guest_network_notes`.
The guest page renders a copy button and a `WIFI:` QR generated in the browser.

### 7 — Services & amenities *(gated)*
A checkbox grid of 24 common amenities (kitchen, dishwasher, washing machine,
dryer, air conditioning, heating, TV, Netflix, pool, terrace, garden, BBQ, crib,
high chair, iron, hairdryer, lift, accessible entrance, pets welcome, workspace,
bed linen, towels, safe, parking) plus free-text additions. Each checked item can
optionally carry a note ("the washing machine is in the cellar").

### 8 — Appliances *(gated)*
Repeatable item list. Each: `title`, `description`, `image`, `instructions`,
`pdf` (manual), `video_url`. Seeded with five common ones the host can accept or
delete — an empty repeater is where hosts stop.

### 9 — House rules
Toggles with three states (allowed / not allowed / ask me) for smoking, pets,
parties, visitors; plus `quiet_hours`, `max_guests`, `shoes_off`, `common_areas`,
and repeatable custom rules.

### 10 — Waste & recycling *(gated)*
`system_type` (separate collection / single bin / bring to collection point),
per-stream instructions (paper, plastic, glass, organic, general), `bin_location`,
`collection_calendar` (day-of-week per stream), `photos`, `pdf`.

This is the section Italian hosts ask for most; it is also the one guests get
wrong most. It gets a photo-first layout on the guest side.

### 11 — Check-out
`checkout_time`, `key_return`, `cleaning_expectations` (checklist),
`waste_on_departure`, `luggage_storage`, `late_checkout_available` (toggle →
conditions and cost), `farewell_message`.

### 12 — Safety & emergency
Prefilled with country defaults (Italy: 112 single emergency number), then
`host_emergency_contact`, `doctor`, `hospital`, `pharmacy` (with the on-duty
pharmacy note), `fire_extinguisher_location`, `first_aid_kit`, `gas_valve`,
`electrical_panel`, repeatable custom contacts.

### 13 — Places you recommend *(gated)*
Repeatable places grouped by category (restaurants, bars, breakfast,
supermarkets, attractions, experiences, museums, shopping, transport).
Each: `name`, `category`, `image`, `short_description`, `address`, `maps_url`,
`phone`, `website`, `distance`, `host_pick` badge, `price_level`.

Paste-a-Google-Maps-link is the primary input: paste the URL and name, address,
coordinates and phone are extracted. Typing every field is the fallback, not the
default.

### 14 — Extra services *(gated)*
Repeatable offers: transfer, breakfast, bike rental, experiences, late checkout,
extra cleaning, extra linen, custom. Each: `title`, `description`, `price`,
`how_to_book`, `image`.

### 15 — Languages
Choose the original language (defaults to the account locale) and the additional
ones, up to the entitlement. Shows exactly what is included and what the next
package adds. If `auto_translate` is entitled, offers to run a first machine pass
now and review later.

### 16 — Preview
Full-screen phone frame, real rendering of the real content, locale switcher,
tap-through. Below it: "Anything missing? You can add it later — your guide will
still be online."

### 17 — Publish
Shows the slug (editable, with live availability check), the generated URL, the
QR code, and one button: **Publish my guide**.

Afterwards: a success screen with the URL, a copy button, QR downloads, and
three suggested next steps. No confetti.

---

## Conditional logic

Declarative, in [`schema/wizard-schema.json`](../schema/wizard-schema.json).
Fields declare a `visibleWhen`; the renderer knows nothing about Wi-Fi or
parking.

```json
{
  "key": "wifi",
  "title": "Wi-Fi",
  "question": "Do your guests have Wi-Fi?",
  "fields": [
    { "key": "has_wifi", "type": "boolean", "control": "yes_no",
      "label": "Do your guests have Wi-Fi?", "default": true },

    { "key": "ssid", "type": "string", "label": "Network name",
      "placeholder": "Casa San Francesco",
      "visibleWhen": { "field": "has_wifi", "equals": true },
      "maxLength": 64, "translatable": false },

    { "key": "password", "type": "string", "label": "Password",
      "visibleWhen": { "field": "has_wifi", "equals": true },
      "sensitive": true, "translatable": false,
      "help": "Guests will be able to copy it with one tap." },

    { "key": "instructions", "type": "text", "label": "Anything else to know?",
      "placeholder": "The router is in the hallway cupboard…",
      "visibleWhen": { "field": "has_wifi", "equals": true },
      "translatable": true, "maxLength": 600 }
  ]
}
```

Supported operators: `equals`, `notEquals`, `in`, `notIn`, `isEmpty`,
`isNotEmpty`, `gte`, `lte`, and `allOf` / `anyOf` for composition.

Field attributes that drive behaviour everywhere else in the product:

| Attribute | Effect |
| --- | --- |
| `translatable: true` | Appears in the Languages screen; gets a `translations` row per locale. |
| `sensitive: true` | Excluded from machine translation, masked in logs, eligible for PIN protection, never included in a printed QR card. |
| `type: media` | Routed through the media pipeline ([12](12-media-pipeline.md)). |
| `requiresFeature` | Whole step or field hidden without the entitlement. |
| `repeatable` | Renders as an add/remove list with drag reordering. |

Because the wizard is data, a super admin can reorder steps, change a question's
wording, or add a field to the Italian market without a deploy. The definition is
versioned; in-flight onboardings keep the version they started on.

---

## Persistence and autosave

- Every change writes to the real content tables immediately — the wizard is a
  view over `guide_sections` and `content_blocks`, not a staging area. There is
  no "import into the CMS" step to go wrong.
- Debounce 800 ms; `PATCH` only the changed field group.
- `onboarding_progress` tracks `last_step`, `completed_steps[]` and
  `skipped_steps[]`. Login routes to `last_step` when onboarding is incomplete.
- **Offline queue**: writes that fail with a network error are buffered in
  IndexedDB and replayed on reconnect. The save indicator shows
  `Saved` / `Saving…` / `Not saved yet — we'll save when you're back online`.
- Media uploads are direct-to-storage with a presigned URL, so a large photo does
  not block the form. The field shows a real progress ring and the wizard remains
  usable.

---

## Completion percentage

Not "fields filled ÷ fields total" — that punishes hosts for skipping things that
do not apply to them, and it is the number they see most.

```
completion = Σ (weight of completed sections) / Σ (weight of applicable sections)
```

- A section answered "no" (no parking, no Wi-Fi) counts as **complete**, not
  skipped. Answering is completing.
- Weights: core arrival-critical sections (check-in, Wi-Fi, arrival, address) = 3;
  standard sections = 2; optional enrichment (recommendations, extras) = 1.
- Sections the package does not include are excluded from the denominator, so an
  Essential guide can legitimately reach 100%.

The number is stored on `guides.completion_percent`, recomputed on write, and
shown in the dashboard, in admin, and in the nudge emails.

---

## Mobile

More than half of onboarding will happen on a phone, so this is the primary
layout, not the adaptation.

- Photo fields open the camera roll directly; multi-select supported; HEIC is
  accepted and converted server-side ([12](12-media-pipeline.md)).
- Uploads continue in the background while the host keeps typing; leaving the
  step does not cancel them.
- Inputs use the right `inputmode` and `autocomplete` (`tel`, `email`, `url`), and
  a 16px minimum font size so iOS does not zoom.
- The sticky footer sits above the safe area inset.
- Long lists (amenities, categories) use large tap targets, minimum 44×44.
- **Save & exit** is always in the header. A host interrupted by a guest at the
  door must be able to leave in one tap and lose nothing.
