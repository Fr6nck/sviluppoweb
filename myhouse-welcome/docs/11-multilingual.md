# 11 — Multilingual architecture

Multilingual is in the schema from day one. Retrofitting translation into a
content model is one of the most expensive mistakes a CMS can make.

---

## The model

Content is stored **once** in its original language, on the content row. Every
other language lives in a polymorphic `translations` table.

```
content_blocks
  id = cb_92
  data = { "title": "Wi-Fi", "instructions": "Il router è nell'armadio…" }

translations
  translatable_type  translatable_id  field_path       locale  status     value
  content_block      cb_92            title            en-GB   published  "Wi-Fi"
  content_block      cb_92            instructions     en-GB   reviewed   "The router is in the cupboard…"
  content_block      cb_92            title            de-DE   published  "WLAN"
  content_block      cb_92            instructions     de-DE   machine    "Der Router ist im Schrank…"
```

Unique index on `(translatable_type, translatable_id, field_path, locale)`.

### Why this shape rather than the alternatives

| Alternative | Why not |
| --- | --- |
| A column per locale (`title_it`, `title_en`) | Adding a language is a migration. Twenty locales is unusable. |
| A JSON blob per field (`{"it": …, "en": …}`) | No per-locale status, no per-locale timestamps, no reviewer, no partial index, no efficient "what is missing in German". |
| A full row per locale of every content table | Duplicates non-translatable data (coordinates, phone numbers, media IDs) and makes "change the phone number" a multi-row write. |

The polymorphic table gives per-field, per-locale **state** — which is the thing
the whole feature depends on.

### What is not translatable

Marked `translatable: false` in the field schema and excluded from every
translation surface:

Wi-Fi SSID and password · door and alarm codes · phone numbers, WhatsApp, email ·
URLs · addresses and coordinates · times · prices · media files · person names ·
property name (unless the host explicitly opts in — some do have a second name
for international guests).

A translated Wi-Fi password is a support call. A machine-translated address is a
guest in the wrong town.

---

## Locales

`locales` is a table, not an enum: `code` (BCP 47), `name`, `native_name`,
`direction`, `is_active`, `sort_order`.

MVP ships `it-IT`, `en-GB`, `de-DE`, `fr-FR`, `es-ES`, `nl-NL`. Adding Polish is
an admin insert.

`guide_locales` records, per guide: the locale, whether it is the default,
whether it is enabled for guests, its completeness percentage, and when it was
last published.

Exactly one locale per guide has `is_default = true`. It is the language the host
writes in and the fallback for everything untranslated. Changing it is possible
and is a deliberate, confirmed action that re-points the fallback.

---

## Translation states

```
        ┌──────────┐
        │ missing  │  no row, or empty value
        └────┬─────┘
             │ machine run
             ▼
   ┌────────────────────┐    human edit     ┌──────────┐
   │ machine_translated │ ───────────────▶  │ reviewed │
   └────────────────────┘                   └────┬─────┘
             │                                   │ publish
             │ human edit (direct)               ▼
             │                              ┌───────────┐
             └─────────────────────────────▶│ published │
                                            └───────────┘
```

| State | Meaning | Guest sees it? |
| --- | --- | --- |
| `missing` | Never translated | No — falls back to the original |
| `machine_translated` | Produced by an engine, unreviewed | No — not until published |
| `reviewed` | A human accepted or edited it | No — not until published |
| `published` | Live in the published snapshot | Yes |

**The invariant, enforced in the repository layer:**

```sql
UPDATE translations
   SET value_text = :machine_output, status = 'machine_translated', engine = :engine
 WHERE translatable_type = :t AND translatable_id = :id
   AND field_path = :path AND locale = :locale
   AND status IN ('missing','machine_translated')   -- ← never reviewed or published
   AND is_locked = false;
```

A machine run **cannot** overwrite `reviewed` or `published` content. It is not a
convention or a code review rule; it is in the WHERE clause, and there is a
database `CHECK` plus a test that asserts it.

### When the original changes

If the Italian text changes after English was reviewed, the English is now stale
but not wrong to show. We mark it rather than discard it:

- `translations.source_hash` stores a hash of the original value at translation
  time.
- On original edit, any translation whose `source_hash` no longer matches is
  flagged `is_stale = true`. Status is untouched.
- The Languages screen shows "2 items need review" and the editor shows the old
  original beside the new one.
- A machine run **may** re-translate a stale `reviewed` item, but only with
  explicit confirmation, and it writes to a `proposed_value` column, leaving the
  live value in place until a human accepts.

---

## Host experience

### Languages screen

```
  Languages
  Your guide is written in Italian. Guests can read it in these languages.

  🇮🇹 Italiano      Original                                       ●━
  🇬🇧 English       Ready · 2 items need review            Review ▸ ●━
  🇩🇪 Deutsch       38 of 54 translated                  Continue ▸ ━○
  🇫🇷 Français      Not started                              Start ▸ ━○

  [ + Add a language ]                       2 of 5 used · Pro adds 20
```

A language cannot be enabled for guests until it is at least 80% complete — a
half-German guide is worse than an Italian one. The toggle explains why it is
disabled and what is missing.

### Per-field translation

The 🇮🇹 chip beside a translatable field opens a popover — the host never leaves
the section they are editing:

```
┌─ Instructions ──────────────────────────────┐
│ 🇮🇹 Italiano (original)                     │
│ Il router è nell'armadio dell'ingresso.     │
│                                             │
│ 🇬🇧 English                      ✓ Reviewed │
│ [ The router is in the hallway cupboard.  ] │
│                                             │
│ 🇩🇪 Deutsch                   ⚡ Translated │
│ [ Der Router ist im Flurschrank.          ] │
│ Translated automatically · [ Looks good ]   │
└─────────────────────────────────────────────┘
```

"Looks good" moves `machine_translated → reviewed` in one tap, which is the
entire review UX for a host who speaks the language well enough to sanity-check
it.

### Side-by-side mode

For hosts translating a lot: `/guide/languages/:locale` lists every translatable
field, original on the left, translation on the right, grouped by section, with
keyboard navigation (`Tab` to advance, `⌘↵` to mark reviewed and move on) and a
filter for missing / stale / machine-translated only.

---

## Machine translation — Pro

Implemented after MVP; the architecture is in place.

### Provider abstraction

```ts
interface TranslationEngine {
  readonly key: 'deepl' | 'openai' | 'google';
  readonly supportedLocales: string[];
  translate(input: {
    text: string;
    sourceLocale: string;
    targetLocale: string;
    glossary?: Record<string, string>;
    context?: string;          // e.g. "hospitality guest guide, check-in instructions"
    formality?: 'default' | 'more' | 'less';
  }): Promise<{ text: string; engineMeta: Record<string, unknown> }>;
}
```

DeepL first: best European quality, formality control, and a glossary API. An
LLM engine is a second implementation of the same interface, useful for locales
DeepL lacks and for tone consistency. Nothing above the interface knows which is
in use.

### Job flow

```
POST /api/guide/translations/run  { targetLocales: ['de-DE'], scope: 'missing' }
  ▼
validate entitlement welcome.languages.auto_translate
estimate character count → show the host what will be translated
  ▼
enqueue translation.run
  ▼
worker, per field:
  skip if translatable = false
  skip if sensitive = true
  skip if status IN ('reviewed','published') and not stale
  call engine with glossary + context + formality
  UPSERT translations (status='machine_translated', engine, engine_meta, source_hash)
  ▼
progress via SSE → "Translating… 38 of 54"
  ▼
done → "German is ready to review. 54 items translated."
```

### Quality measures

- **Glossary per account**: property name, host name, local place names, and any
  term the host pins. Prevents "Casa San Francesco" becoming "House of Saint
  Francis".
- **Context string** per field type so the engine knows it is translating check-in
  instructions, not marketing copy.
- **Formality**: `more` for German and French by default — guests are addressed
  formally in Italian hospitality, and the default should match.
- **Placeholder protection**: values like `{{host_name}}` are masked before the
  call and restored after.
- **Cost guard**: per-account monthly character budget, checked before the job
  starts, with a clear message rather than a silent truncation.

### Cost and caching

Translations are cached by `(source_hash, source_locale, target_locale, engine)`.
Re-running a pass after changing one paragraph costs one paragraph. A property
manager cloning a guide across five properties pays once for the shared text.

---

## Publishing and locales

At publish, the snapshot is generated **per locale**:

```json
{
  "guide": { "slug": "casa-san-francesco", "defaultLocale": "it-IT" },
  "locales": {
    "it-IT": { "sections": [ … ] },
    "en-GB": { "sections": [ … ] }
  }
}
```

- Only `published` translations are included. A `reviewed` translation is
  promoted to `published` as part of the publish action.
- Untranslated fields are emitted with the original value and a
  `"fallback": true` marker so the renderer can show the one-time notice.
- A locale below the completeness threshold is not emitted at all and does not
  appear in the guest's language selector.

---

## Guest side

Covered in [09](09-guest-guide.md#language-switching). Two points that belong
here:

- **`hreflang`**: each locale path declares alternates, including `x-default`
  pointing at the guide's default locale.
- **No auto-redirect.** We resolve an initial locale from `Accept-Language` but
  never redirect — a Dutch guest whose browser is set to Dutch may well prefer the
  English version, and a redirect steals that choice. We render the guessed
  locale and show the switcher prominently.
