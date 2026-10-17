# 17 — Authentication and security

## Accounts are created by payment, not by signup

There is no public registration form. An account exists because an order was
paid, or because a super admin created it. This removes spam signups, abandoned
empty accounts, and the "I registered but I can't find my guide" support ticket
entirely.

```
order paid (webhook)
  → account created          status = pending
  → user created             password_hash = NULL
  → activation token issued  single use, 7 days
  → email sent
       │
       ▼
  /activate?token=…
  → set password + accept terms
  → user.email_verified_at = now()     ← paying proved the address works
  → account.status = active
  → session created
```

Paying with a card at an address, then following a link sent to it, is stronger
evidence than a click-to-confirm email. So activation *is* verification; we do
not ask twice.

---

## Passwords

- **Argon2id**, `m=19456 KiB (19 MiB), t=2, p=1` — the OWASP baseline. Parameters
  are stored alongside the hash, so raising them later re-hashes on next login
  rather than requiring a reset.
- Minimum 10 characters. No composition rules — they produce `Password1!` and
  nothing else.
- Checked against the k-anonymity range API of a breached-password corpus. Only a
  5-character SHA-1 prefix leaves our servers; the password never does. A match
  is a warning with an override, not a hard block.
- A strength meter that measures actual entropy, not character classes.
- `password_changed_at` invalidates every existing session on change.

---

## Sessions

Opaque random tokens in Redis. **Not JWTs**, because the one thing this system
must be able to do instantly is revoke a session — on logout, on password change,
on role change, on impersonation end, on suspension. A stateless token cannot be
revoked without building a revocation list, which is a session store with extra
steps.

```
Set-Cookie: mh_session=<32 bytes base64url>;
            HttpOnly; Secure; SameSite=Lax; Path=/;
            Domain=app.myhouse.it; Max-Age=2592000
```

- `SameSite=Lax` — the app is navigated to from email links, so `Strict` breaks
  the activation flow. CSRF is handled below.
- Sliding expiry: 30 days, refreshed on use, absolute maximum 90 days.
- The token rotates on privilege change (login, impersonation start and end) to
  prevent session fixation.
- `/account/sessions` lists active sessions with device, location and last use,
  and revokes any or all of them.
- Guest guides set **no cookies at all** — this is what removes the cookie banner
  ([22](22-privacy-gdpr.md)).

### MFA

TOTP. **Mandatory for every platform role** — a super admin account is a key to
every customer's home information. Optional for hosts. Ten single-use recovery
codes, hashed at rest, shown once.

---

## CSRF, XSS, injection

| Attack | Defence |
| --- | --- |
| CSRF | `SameSite=Lax` + double-submit token on every state-changing request + `Origin` check. Three layers because the cost is near zero. |
| XSS | React escapes by default; `dangerouslySetInnerHTML` is banned by lint rule. Host rich text is sanitised server-side to an allowlist (`p, br, strong, em, ul, ol, li, a[href]`), with `a` forced to `rel="noopener noreferrer nofollow"` and `http(s)` schemes only. |
| SQL injection | Parameterised queries everywhere. The two raw SQL sites (publish read, analytics rollup) use parameter binding and are covered by a lint rule forbidding template-literal SQL. |
| SSRF | The only user-supplied URLs we fetch are Google Maps links and video URLs. Both are allowlisted by host, resolved through a DNS guard that rejects private and link-local ranges, and fetched with no redirects followed. |
| Clickjacking | `frame-ancestors 'none'` on app and admin. The guide allows framing only by `app.myhouse.it` for the live preview. |
| Open redirect | Post-login redirects are validated against a path allowlist; absolute URLs are rejected. |
| Mass assignment | Zod schemas are explicit allowlists; unknown keys are stripped, not ignored. |
| Enumeration | Password reset always returns `204`. Login failures are a single message with constant-time comparison. A non-existent slug and an unpublished guide return the identical 404 page. |

### Content Security Policy

```
default-src 'self';
script-src 'self' 'nonce-{random}';
style-src 'self' 'nonce-{random}';
img-src 'self' data: https://cdn.myhouse.it;
font-src 'self' https://fonts.gstatic.com;
frame-src https://www.youtube-nocookie.com https://player.vimeo.com;
connect-src 'self' https://api.myhouse.it;
frame-ancestors 'none';
base-uri 'none';
form-action 'self' https://checkout.stripe.com;
upgrade-insecure-requests
```

Nonce-based, no `unsafe-inline`, no `unsafe-eval`. Violations are reported to an
endpoint and reviewed — a CSP with no reporting is a CSP nobody maintains.

Plus `Strict-Transport-Security: max-age=63072000; includeSubDomains; preload`,
`X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`,
and a `Permissions-Policy` denying camera, microphone, geolocation and payment on
the guest surface.

---

## Support mode (impersonation)

The riskiest capability in the product, so it is the most constrained.

### The principal is dual

A support-mode request carries two identities. It never *becomes* the customer.

```
actor:       user_8f3a   (Giulia, admin_support)
acting_for:  account_27  (Casa San Francesco)
session:     imp_91c     expires 15:02
```

### Rules, enforced in the authorisation layer

1. **Permissions are an intersection.** The admin's platform permissions ∩ what
   the account's package entitles. An admin cannot use support mode to hand a
   customer a Pro feature — that is `entitlement.override`, a separate action
   with its own audit entry.
2. **Some things are blocked regardless of role**, because no legitimate support
   task needs them: changing the customer's password, changing their email,
   buying anything, deleting the account, viewing full payment details, starting
   a nested impersonation.
3. **A typed reason is required** to start. Free text, stored, shown in the audit
   log.
4. **Maximum 60 minutes**, auto-expiring, with a warning at 55.
5. **Every write is attributed** — `actor_user_id` is the admin,
   `impersonated_account_id` is the account, `impersonation_session_id` links the
   whole session together.
6. **The customer is told.** A banner rendered by the app shell (so no page can
   omit it), an entry in their own activity feed reading "Edited by MyHouse
   support", and a summary email if content was published.
7. **Analytics are tagged** `is_internal = true` and excluded from the customer's
   view counts.

### Protected sections

Per-section PIN, for door and alarm codes (Pro, Phase 2).

- PIN is Argon2id-hashed. We can never show a host their own guest PIN — we can
  only let them set a new one, and the UI says so.
- Verification is server-side, rate-limited to 5 attempts per 15 minutes per IP
  per section, with exponential backoff and no distinction between "wrong PIN"
  and "no such section".
- **Protected content is never in the published snapshot.** It is fetched by a
  separate authorised request after verification, so a leaked snapshot cannot
  expose a door code.
- Unlock issues a short-lived, section-scoped token in `sessionStorage`, valid 12
  hours, not a cookie.

---

## File upload safety

Layered, because extension checks are theatre:

1. Client-side pre-check — UX only, assumed bypassed.
2. Presigned URL locked to a content type and content length.
3. **Magic-byte sniffing server-side.** Disagreement with the declared type →
   `quarantined` + alert. This is the real check.
4. ClamAV scan for PDFs; nothing is served while `scan_status = 'pending'`.
5. EXIF stripped entirely, including GPS.
6. PDFs re-serialised through a hardened parser, rejecting embedded JavaScript,
   embedded files and launch actions.
7. Images re-encoded, which destroys any polyglot payload.
8. Served from a separate origin (`cdn.myhouse.it`) with
   `Content-Disposition: attachment` for PDFs and `X-Content-Type-Options: nosniff`,
   so a stored file cannot execute in the app's origin.

SVG is rejected outright. It is a script container, and no host has ever asked
for one.

---

## Secrets

Never in the client bundle, never in a log, never in an error message, never in
the database in plaintext.

- `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`, `DEEPL_API_KEY`,
  `DATABASE_URL`, `S3_SECRET_ACCESS_KEY`, `SESSION_SECRET`, `ANALYTICS_SALT_SECRET`
  are server-only environment variables from a secret manager.
- Boot-time assertion: no variable matching `/^(sk_|whsec_|rk_)/` may appear in
  any client-exposed config object.
- CI greps the built client bundle for `sk_live`, `whsec_` and the database host;
  a hit fails the build.
- Quarterly rotation. The Stripe webhook secret supports two active values during
  the rotation window.
- Door codes and Wi-Fi passwords are stored in `content_blocks.data` and are
  **not** encrypted at column level — the host needs them back in plaintext to
  show a guest, so column encryption would be theatre over a key the application
  holds anyway. They are protected by database encryption at rest, by
  `is_sensitive` masking in logs and analytics, by exclusion from machine
  translation, and by exclusion from indexable renders.

---

## Threat model

| Threat | Impact | Mitigation |
| --- | --- | --- |
| Credential stuffing on host accounts | Door codes exposed | Rate limits per email and per IP, breach-corpus check, optional MFA, new-device email |
| Compromised super admin | Every customer | Mandatory MFA, full audit, 60-min impersonation, `admin_support` role for day-to-day work |
| Forged Stripe webhook | Free accounts | Signature verification before parsing; unsigned requests never reach a handler |
| Replayed Stripe webhook | Double provisioning | Unique index on `(provider, provider_event_id)` |
| Slug squatting | Impersonating a property | Reserved words, profanity list, admin override, old slugs never reused |
| Scraping guides | Host addresses and phone numbers harvested | `noindex` by default, rate limits, no listing endpoint, no enumerable IDs in public URLs |
| Malicious upload | Stored XSS | Sniffing, re-encoding, separate origin, scanning |
| Guest brute-forces a section PIN | Door code exposed | 5 attempts per 15 min, backoff, Argon2id, content never in the snapshot |
| Insider export of customer data | GDPR breach | Audit on every read of a customer detail page, two-person rule on erasure, export is logged |
| Cross-tenant data access via a missing filter | Severe | `account_id` on every tenant table + RLS + a trigger that rejects a mismatched parent ([14](14-data-model.md)) |

---

## What is deliberately not built for MVP

- **Social login.** Every account comes from a purchase with a verified email.
  Google and Apple sign-in add OAuth surface and account-linking edge cases to
  solve a problem we do not have. The `users` table has no password-specific
  assumptions, so adding it later is additive.
- **Field-level encryption for door codes.** Discussed above.
- **A WAF.** The edge provider's managed ruleset covers it at this scale.
- **SSO / SCIM.** No enterprise customer exists yet.

Each of these is a considered omission, recorded in [24](24-decision-log.md), not
an oversight.
