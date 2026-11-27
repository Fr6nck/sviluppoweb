# 13 — URLs, slugs and QR codes

## The constraint that drives everything

> A QR code printed on a card, stuck to a fridge, laminated on a wall, must keep
> working forever — through slug changes, rebrands, custom domains and product
> migrations.

A QR code is physical. We cannot reissue it. So the QR never encodes the guide
URL.

---

## The indirection

```
QR encodes:     https://mh.li/q/7f3a9c2b        ← permanent, opaque, never changes
                        │
                        │  302, no cache
                        ▼
Resolves to:    https://welcome.myhouse.it/casa-san-francesco?src=qr
                        │
                        │  301 if the slug has since changed
                        ▼
Current guide
```

`qr_codes.token` is a 10-character base32 identifier, generated once, immutable
for the life of the guide. The redirect is a `302` (temporary) precisely because
the destination is allowed to change; a `301` here would be cached by browsers and
would defeat the whole mechanism.

`mh.li` is a short domain to keep the QR's data payload small — fewer modules,
larger squares, scannable from further away and on worse cameras, which matters
when the card is on a fridge and the guest is holding a suitcase.

Regenerating a QR (admin action, or host action for a lost/compromised card)
issues a **new** token and revokes the old one. The confirmation says exactly what
it costs: "Printed codes with the old QR will stop working."

---

## URL architecture

| Purpose | URL |
| --- | --- |
| Marketing | `myhouse.it/welcome` |
| Pricing | `myhouse.it/welcome/pricing` |
| Example guide | `myhouse.it/welcome/example` → the demo guide |
| Application | `app.myhouse.it` |
| Admin | `app.myhouse.it/admin` |
| Guest guide | `welcome.myhouse.it/casa-san-francesco` |
| Guest section | `welcome.myhouse.it/casa-san-francesco/wi-fi` |
| Guest locale | `welcome.myhouse.it/casa-san-francesco/en/wi-fi` |
| QR permalink | `mh.li/q/7f3a9c2b` |
| Custom domain (Pro, Phase 2) | `benvenuti.casasanfrancesco.it` |

Path-based guest URLs rather than `casa-san-francesco.myhouse.it` subdomains:

- One TLS certificate instead of on-demand issuance per customer.
- One edge cache configuration.
- Slug changes stay inside one origin, so redirects are trivial.
- Subdomains cannot be chosen freely without a reserved-word problem across the
  whole `myhouse.it` zone.

The brief mentions both forms. If subdomain-per-property is later required for
marketing reasons, the `public_urls` table already has a `host` column and the
router already dispatches on `(host, slug)` — it is a DNS and certificate
problem, not a data problem.

---

## Slugs

### Generation

From the property name, at creation:

```
"Casa San Francesco"   → casa-san-francesco
"B&B Il Glicine"       → bb-il-glicine
"Appartamento N°3"     → appartamento-n-3
"Città di Mare"        → citta-di-mare          (NFD → strip diacritics)
```

Rules: lowercase, NFD-normalise and strip diacritics, non-alphanumerics → `-`,
collapse repeats, trim, 3–63 characters, must not start or end with `-`.

Collisions get a numeric suffix (`casa-san-francesco-2`). The host is shown the
result before publishing and can change it.

### Reserved

`api`, `admin`, `app`, `www`, `mail`, `static`, `assets`, `media`, `cdn`, `q`,
`login`, `signup`, `checkout`, `support`, `help`, `status`, `blog`, `legal`,
`privacy`, `terms`, `example`, `demo`, `test`, `welcome`, `myhouse`, plus a
profanity list per locale and any string matching `^[0-9]+$`.

### Changing a slug

```
public_urls
  id     guide_id  host                 slug                   is_primary  status    redirect_to
  pu_1   g_27      welcome.myhouse.it   casa-san-francisco     false       redirect  pu_2
  pu_2   g_27      welcome.myhouse.it   casa-san-francesco     true        active    null
```

- The old row is never deleted. It becomes a permanent `301` to the primary.
- Redirect chains are flattened on write: a third change points row 1 at row 3
  directly, so there is never a chain to follow.
- Hosts are limited to 3 slug changes per 30 days; admins are not limited.
- Old slugs are never released for reuse by another account. Reuse would send a
  guest holding an old link to a stranger's apartment.

### The host-facing message

The word "slug" appears nowhere. The screen says:

```
  Your guide address
  welcome.myhouse.it/ [ casa-san-francesco        ]  ✓ Available

  Your old address will keep working and send guests here.
  Printed QR codes are not affected.
```

---

## QR code generation

Generated at publish, stored as a record and rendered on demand.

```
qr_codes
  id, guide_id, token (unique, immutable), label,
  style_json { fg, bg, logo, margin, ecc },
  scan_count, created_at, revoked_at
```

### Output formats

| Format | How | Use |
| --- | --- | --- |
| PNG | Server-rendered, 1024×1024 and 2048×2048 | Screen, WhatsApp, quick print |
| SVG | Vector, generated on demand | Any print size, sign makers |
| PDF | A6 card with the property name, the URL in text, and the code | Ready to print at home |
| Branded card (Pro) | A6 and A5 with logo, cover image, custom message, in all guide languages | Professional printing |

### Encoding parameters

- **Error correction level Q (25%)** — high enough to survive a logo in the centre
  and real-world wear on a fridge; higher would inflate the module count.
- **Quiet zone 4 modules**, never trimmed. This is the most common cause of QR
  codes that will not scan.
- **Contrast**: dark foreground on light background, minimum 4.5:1 luminance
  ratio, enforced. Inverted codes and low-contrast brand colours are rejected by
  the style validator with an explanation.
- **Minimum print size 2.5 cm**; the PDF templates never go below it.
- Every PDF also prints the URL as readable text. Some guests do not scan QR
  codes, and some phone cameras in dim stairwells do not either.

### Scan counting

`/q/:token` increments `scan_count` and records an `analytics_event` with
`entry_source = 'qr'` before redirecting. The redirect is issued first and the
write is fire-and-forget, so analytics can never delay a guest.

---

## Indexing

| Surface | Robots | Reason |
| --- | --- | --- |
| Marketing | Indexed, sitemap, schema.org | We want to be found |
| Application | `noindex, nofollow` | Private |
| Admin | `noindex, nofollow` + 404 to non-staff | Private |
| Guest guide — default | `noindex, nofollow` | It is a private document for a specific guest, containing the host's phone number and their home's address |
| Guest guide — opted in | Indexed, with a sitemap entry | Host toggle in Appearance, off by default |
| Protected sections | `noindex` always, never in a sitemap, `Cache-Control: private` | Contains codes |

The default is `noindex` because a welcome guide is not marketing material. A host
who wants theirs found turns it on knowingly, and the toggle says what it means:
"Let search engines show your guide. Your Wi-Fi password and door codes are never
included in search results — but anyone could find the page."

When indexing is on, sensitive-flagged fields are excluded from the indexable
render entirely and loaded client-side.

---

## Custom domains — Pro, Phase 2

Modelled today via `public_urls.host`:

1. Host enters `benvenuti.casasanfrancesco.it`.
2. We show the required `CNAME` and verify it.
3. Certificate issued through the edge provider's on-demand TLS.
4. A new `public_urls` row with the custom host becomes primary; the
   `welcome.myhouse.it` row becomes a `301` redirect to it.
5. The QR token is untouched — it now resolves to the custom domain. **The
   printed cards keep working.**

That last line is the entire reason for the indirection.
