# 20 — Content, empty and error states

## UX writing rules

The host is not technical. Every screen answers three questions: **Where am I?
What can I do? What should I do next?**

| Rule | Instead of | Write |
| --- | --- | --- |
| Name the thing, not the system | Configure modules | Choose what your guests can see |
| No jargon | Manage localization | Languages |
| Say what happened, not what ran | Deployment completed | Your guide is online |
| Use their noun | Media asset | Photo |
| Address the guest, not the record | Guest-facing entity | Your guests |
| Verbs on buttons | OK · Submit | Publish my guide · Add restaurant |
| Sentence case everywhere | Add New Section | Add a new section |
| No exclamation marks | Published! | Your guide is online |
| Say the number | Limit exceeded | You've added 15 photos, which is what Essential includes |
| Never blame | Invalid input | We need a phone number guests can call |

Two words are banned from the host-facing product entirely: **slug** and
**deploy**. One is banned from the guest guide: **error**.

---

## Empty states

Every collection has one. A blank table is a dead end.

### Recommendations

```
        ┌──────┐
        │  🍽  │
        └──────┘

   No places added yet

   Add the restaurants, bars and shops
   you'd recommend to a friend.

   [ Add a place ]        Not sure? See examples
```

### Media library

```
   Nothing here yet

   Photos you add to your guide will
   collect here, so you can reuse them.

   [ Add photos ]
```

### Statistics, before any visits

```
   No visits yet

   Once you share your link or QR code,
   you'll see how guests use your guide.

   [ Get my QR code ]     [ Copy my link ]
```

Not "0 visits" with an empty chart. Zero is a fact; this is a next step.

### Statistics, published but quiet

```
   4 visits this month

   Not enough yet to show a trend. Most hosts
   see visits pick up once the QR code is
   somewhere guests will find it — on the fridge,
   by the door, or in the welcome message you
   send before arrival.
```

### Translations, nothing started

```
   German isn't translated yet

   54 things to translate. You can do it
   yourself, or start from an automatic
   translation and correct it.

   [ Start translating ]      Automatic translation · Pro
```

### Admin, no results

```
   No customers match these filters

   [ Clear filters ]
```

Never "No data available". Say what was searched and offer the way back.

---

## Error states

Three rules: say what happened in their words, say whether it is their problem,
say what to do next. Never show a code unless a human will need to quote it to
support.

### Payment failed

```
   The payment didn't go through

   Your bank declined the card. Nothing has been
   charged. This is usually a card limit or an
   expiry date.

   [ Try again ]     [ Use a different card ]

   Still stuck? Email support@myhouse.it
```

Not "Error 402: payment_intent_declined". The host cannot act on that, and it
makes a routine decline feel like a system failure.

### Upload failed

| Cause | Message |
| --- | --- |
| Too large | This photo is 22 MB, which is larger than we can handle. Most phones can send a smaller version — or try a different photo. |
| Wrong type | We can't use this kind of file. Photos (JPG, PNG or HEIC) and PDFs work best. |
| Network dropped | The upload stopped. **[Try again]** |
| Failed a safety scan | We couldn't accept this file. If you're sure it's safe, email us and we'll look. |
| Quota reached | You've added 15 photos, which is what Essential includes. Delete one, or add more with Plus. **[See what Plus includes]** |

### Field validation

Inline, below the field, on blur — never on every keystroke, never all at once at
submit.

| Field | Message |
| --- | --- |
| Property name empty | Your guests will see this name. What's your place called? |
| Check-in time empty | Guests ask this most. What time can they arrive? |
| Email malformed | This doesn't look like an email address. |
| Phone malformed | This doesn't look like a phone number guests could call. |
| Slug taken | Someone already has this address. **casa-san-francesco-2** is free. |
| URL malformed | Web addresses start with https:// |

### Connection lost

A bar, not a modal. Editing continues; saves queue.

```
┌──────────────────────────────────────────────┐
│ ⚠ You're offline. We'll save your changes    │
│   as soon as you're back.                     │
└──────────────────────────────────────────────┘
```

When it returns: `✓ Back online. Everything's saved.` — auto-dismissing after 3s.

### Unpublished changes

```
┌────────────────────────────────────────────────┐
│ You have changes guests can't see yet          │
│                      [ Preview ]  [ Publish ▸ ] │
└────────────────────────────────────────────────┘
```

Never "unsaved". It *is* saved. The distinction between saved and published is
taught once, here, and never uses a word that contradicts the save indicator.

### Webhook pending after checkout

```
   Your payment went through

   We're setting up your account. This usually takes
   a few seconds. We'll email you the moment it's
   ready — you can safely close this page.
```

Framed as progress, because it is. The customer paid; nothing is wrong.

### Guide address unavailable

```
   welcome.myhouse.it/[ casa-san-francesco ]   ✗ Taken

   Someone already has this address.
   Try casa-san-francesco-2, or casa-sanfrancesco.
```

Suggestions, not a rejection.

### Publishing with exclusions

```
   Your guide is online

   Two things weren't included:
     • Experiences — available with Pro
     • Deutsch — your package includes 1 language

   Everything else is live. Nothing was deleted —
   it'll be included if you upgrade.

   [ Copy my link ]  [ See my guide ]     What Pro adds ▸
```

The reassurance that nothing was deleted is the important sentence.

### Guest-side 404

```
   This guide isn't available

   The link might be old, or the host may have
   taken it offline.
```

Identical whether the slug never existed, the guide is unpublished, or the
account is suspended. A guest cannot be told the difference without leaking
whether a property exists.

---

## Copy deck — key strings, both locales

| Key | English | Italiano |
| --- | --- | --- |
| `marketing.hero` | Everything your guests need. In one place. | Tutto quello che serve ai tuoi ospiti. In un unico posto. |
| `marketing.cta.primary` | Create your Welcome Guide | Crea la tua guida |
| `marketing.cta.secondary` | See an example | Guarda un esempio |
| `activate.title` | Set your password | Scegli la tua password |
| `welcome.title` | Welcome to MyHouse Welcome | Benvenuto in MyHouse Welcome |
| `welcome.subtitle` | Let's create your guest guide. | Creiamo la guida per i tuoi ospiti. |
| `welcome.cta` | Start configuration | Iniziamo |
| `welcome.time` | About 20 minutes. You can stop and come back any time. | Circa 20 minuti. Puoi fermarti e riprendere quando vuoi. |
| `wizard.save_exit` | Save & exit | Salva ed esci |
| `wizard.skip` | Skip this step | Salta questo passaggio |
| `wizard.continue` | Continue | Continua |
| `dashboard.greeting` | Hello, {name} | Ciao, {name} |
| `dashboard.status.online` | Your MyHouse Welcome guide is online. | La tua guida è online. |
| `dashboard.status.draft` | Your guide isn't online yet. | La tua guida non è ancora online. |
| `dashboard.status.pending` | You have changes guests can't see yet. | Ci sono modifiche che i tuoi ospiti non vedono ancora. |
| `content.title` | Your guide | La tua guida |
| `content.subtitle` | Choose what your guests can see, and in what order. | Scegli cosa possono vedere i tuoi ospiti, e in che ordine. |
| `save.saved` | Saved | Salvato |
| `save.saving` | Saving… | Salvataggio… |
| `save.offline` | Not saved yet — we'll save when you're back online. | Non ancora salvato — salveremo appena torni online. |
| `publish.cta` | Publish my guide | Pubblica la guida |
| `publish.success` | Your guide is online | La tua guida è online |
| `qr.permanent` | This code always works, even when you change your guide or your address. | Questo codice funziona sempre, anche se cambi la guida o l'indirizzo. |
| `upgrade.chip` | Available with {package} | Disponibile con {package} |
| `guest.wifi.copy` | Copy password | Copia la password |
| `guest.wifi.qr` | Connect with QR | Connettiti con il QR |
| `guest.maps` | Open in Google Maps | Apri in Google Maps |
| `guest.call` | Call | Chiama |
| `guest.fallback` | Some parts are only available in {locale}. | Alcune parti sono disponibili solo in {locale}. |
| `guest.404` | This guide isn't available | Questa guida non è disponibile |
| `admin.impersonating` | You are assisting {account} as administrator. | Stai assistendo {account} come amministratore. |

Italian is not a translation of the English here — both were written as originals
in their own register. The Italian uses *tu* throughout, because a host renting
out their own home is not a corporate customer, and *lei* would be cold.

---

## Email

| Template | Subject (EN) | Trigger |
| --- | --- | --- |
| `purchase_confirmation` | Your guide is ready to build | Order paid |
| `activation_reminder` | Your guide is waiting | +1, +3, +7 days unactivated |
| `onboarding_nudge` | Your guide is {percent}% ready | +24h incomplete |
| `published` | Your guide is online | First publish |
| `password_reset` | Reset your password | Requested |
| `payment_failed` | We couldn't take the payment | Payment failed |
| `refund_processed` | Your refund is on its way | Refund succeeded |
| `support_edit` | MyHouse support updated your guide | Impersonation published |
| `package_change` | Your package has changed | Admin change or upgrade |
| `quota_warning` | You're close to your photo limit | 80% of quota |

Every email: one purpose, one button, plain text alternative, the host's locale,
a real reply-to address, and an unsubscribe link on anything that is not
transactional.

The **published** email carries the QR code as an attachment and inline, because
the first thing a host does after publishing is try to print it.
