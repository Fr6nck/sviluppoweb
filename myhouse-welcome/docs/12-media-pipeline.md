# 12 — Media pipeline

Hosts upload photos from a phone. They are 4 MB HEIC files, rotated wrong, named
`IMG_4821.HEIC`. Everything downstream must cope with that without the host
knowing it was a problem.

---

## Accepted input

| Type | Formats | Max | Notes |
| --- | --- | --- | --- |
| Image | JPEG, PNG, WebP, AVIF, HEIC/HEIF | 15 MB | HEIC converted server-side |
| Document | PDF | 20 MB | Plus and Pro only |
| Video | none — **URL only** | — | YouTube / Vimeo, Pro only |

Video files are deliberately not hosted. A host who wants a check-in video puts it
on YouTube unlisted and pastes the link. Hosting video would mean transcoding,
bandwidth and a storage bill that does not fit a one-off price, and it would slow
the guest guide down.

### Rejected, explicitly

SVG (script vector), HTML, any archive, any executable, Office documents, and
anything whose sniffed type disagrees with its extension. The error is human:
"We can't use this kind of file. Photos (JPG, PNG) and PDFs work best."

---

## Upload flow

```
Browser                      API                         Object store        Worker
───────                      ───                         ────────────        ──────
pick file
  │ client-side pre-check
  │ (type, size, count, entitlement)
  ▼
POST /api/media/presign ─────▶ check entitlements:
  { filename, mime,             welcome.media.images.max
    bytes, sha256 }             welcome.media.storage_mb
                                welcome.media.pdf
                                ▼
                              INSERT media (status='uploading')
                              presigned PUT, 15 min, content-length
                              and content-type locked
  ◀───────────────────────────  { mediaId, uploadUrl, headers }
  │
  │ PUT file ──────────────────────────────────────────▶ stored at
  │ (progress events)                                    originals/{accountId}/{mediaId}
  ▼
POST /api/media/:id/complete ─▶ verify object exists
                                verify size matches
                                sniff real MIME from magic bytes
                                reject on mismatch
                                status='processing'
                                enqueue media.process ──────────────────────▶
                                                                             sniff + scan
  ◀───────────────────────────  { status: 'processing' }                     strip EXIF
  │                                                                          auto-orient
  │ poll or SSE                                                              HEIC → JPEG
  ▼                                                                          generate variants
status='ready', variants available                                           extract dimensions
                                                                             LQIP placeholder
                                                                             status='ready'
```

The browser never sends the file to our API. Presigned direct upload means a 15 MB
photo on a phone is one hop, not two, and the API never handles large bodies.

**Deduplication**: the SHA-256 is computed client-side and sent with the presign
request. If the same account already has that checksum in `ready` state, no upload
happens — the existing `media` row is returned instantly. Hosts re-upload the same
cover photo constantly.

---

## Processing

1. **Sniff** the real type from magic bytes (`file-type`). Disagreement with the
   declared type → `quarantined`, admin alert. This is the actual defence; the
   extension check is only UX.
2. **Scan** with ClamAV for PDFs and anything unexpected. `scan_status` is
   `pending | clean | infected | error`. Nothing is served while pending.
3. **Strip metadata.** EXIF is removed entirely — including GPS. A host uploading
   a photo of their front door should not publish its coordinates. Orientation is
   applied to the pixels first, then discarded.
4. **Auto-orient** from the EXIF orientation flag so sideways phone photos come
   out upright.
5. **Convert HEIC** to JPEG at quality 88 as the new original. The uploaded HEIC
   is kept for 30 days then deleted.
6. **Generate variants** (below).
7. **Extract** width, height, dominant colour, and a 20-byte LQIP blur hash.
8. **Sanitise PDFs**: refuse embedded JavaScript, embedded files and launch
   actions; re-serialise through a hardened parser; keep the page count and size
   for the guest-side card.

Failures set `status='failed'` with a reason the host can read, and the field
offers to retry.

---

## Variants

Generated once, at processing time, never on request.

| Purpose | Width | Formats | Used by |
| --- | --- | --- | --- |
| `thumb` | 200 | AVIF, WebP, JPEG | Editor lists, media library |
| `card` | 480 | AVIF, WebP, JPEG | Guest section cards, recommendation cards |
| `content` | 960 | AVIF, WebP, JPEG | Guest section body images |
| `cover` | 1600 | AVIF, WebP, JPEG | Guest hero |
| `cover@2x` | 2400 | AVIF, WebP | Retina hero |

Guest markup:

```html
<picture>
  <source type="image/avif" srcset="…/cover.avif 1600w, …/cover@2x.avif 2400w"
          sizes="(max-width: 720px) 100vw, 720px">
  <source type="image/webp" srcset="…/cover.webp 1600w, …/cover@2x.webp 2400w"
          sizes="(max-width: 720px) 100vw, 720px">
  <img src="…/cover.jpg" width="1600" height="1000" alt="Casa San Francesco seen from the garden"
       loading="lazy" decoding="async"
       style="background-image:url(data:image/webp;base64,…)">
</picture>
```

`width` and `height` are always emitted so nothing shifts while loading. JPEG is
kept as the universal fallback; AVIF typically saves 45–55% over it at
equivalent quality.

Storage is counted against `welcome.media.storage_mb` using **originals only** —
variants are our cost, not the host's quota, and counting them would make the
number incomprehensible.

---

## Media library

Reached from inside any upload field ("Choose from your photos") and from
`/media`.

- Grid of thumbnails, newest first, filter by type and by "used / unused".
- Each item: preview, filename, dimensions, size, alt text, and the list of places
  it is used.
- Actions: set alt text, replace (keeps the ID, so every usage updates at once),
  delete, download.
- **Replace** is the important one: a host who repaints the living room replaces
  the photo and every section using it updates. No hunting.

### Deletion safety

`media_usages` records every `(media_id, usable_type, usable_id, field)`.
Deleting a file that is in use is refused with the list:

```
This photo is used in 2 places:
  • Check-in — Photos
  • Your property — Cover image
Remove it there first, or replace it with a different photo.
                                  [ Replace instead ]  [ Cancel ]
```

Admins get the same dialog with a force option that logs the action and leaves
the usages rendering a placeholder rather than a broken image.

Deletion is soft (`deleted_at`). Objects are purged from storage 30 days later by
a scheduled job, which also catches orphans — rows in `uploading` state older
than 24 hours, and storage objects with no row.

---

## Alt text

Every image field has an alt text input with a real label: "Describe this photo
for guests who can't see it". Not required — a host who is forced will type "a"
— but:

- The publish check lists images without alt text as a warning, never a blocker.
- Decorative images (cover, gallery backgrounds) default to `alt=""`, which is
  the correct value, rather than a filename.
- Filenames are **never** used as alt text. `IMG_4821` helps nobody.
- Phase 2: an AI suggestion the host edits, which is how alt text actually gets
  written.

---

## Limits and messages

| Limit | Essential | Plus | Pro |
| --- | --- | --- | --- |
| Images | 15 | 80 | 400 |
| Storage | 50 MB | 400 MB | 2 GB |
| PDFs | ✗ | ✓ | ✓ |
| Video links | ✗ | ✗ | ✓ |
| Per-file image | 15 MB | 15 MB | 15 MB |
| Per-file PDF | — | 20 MB | 20 MB |

Messages when a limit is hit state the number, never a code:

> You've added 15 photos, which is what Essential includes. Delete one, or add
> more with Plus. **[See what Plus includes]**

Approaching a limit is surfaced at 80%, once, quietly, in the media library —
not as a modal.

---

## Storage layout

```
originals/{accountId}/{mediaId}.{ext}          private, never served directly
variants/{accountId}/{mediaId}/{purpose}.{fmt} public-read via CDN, immutable
quarantine/{accountId}/{mediaId}.{ext}         private, admin access only
```

- S3-compatible (Cloudflare R2 in the reference deployment) behind a CDN.
- Variants are content-addressed by media ID and purpose, served with
  `Cache-Control: public, max-age=31536000, immutable`. A replace produces a new
  media ID, so cache invalidation is never needed.
- Originals are private. Nothing links to them. A host downloading their own
  original gets a short-lived signed URL.
- Object-store credentials never reach a browser. Presigned URLs are per-object,
  per-method, time-limited and content-length-bound.
