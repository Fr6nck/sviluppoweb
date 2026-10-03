-- =============================================================================
-- MyHouse Welcome — PostgreSQL schema
-- Target: PostgreSQL 16+
-- Conventions documented in docs/14-data-model.md
--
--   * UUIDv7 primary keys (time-ordered)
--   * TIMESTAMPTZ everywhere, UTC storage
--   * money = BIGINT minor units + CHAR(3) currency
--   * enums as CHECK constraints, not Postgres ENUM types
--   * soft delete via deleted_at, partial indexes match
--   * account_id denormalised onto every tenant-owned table for RLS
-- =============================================================================

CREATE EXTENSION IF NOT EXISTS "pgcrypto";
CREATE EXTENSION IF NOT EXISTS "citext";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";

-- UUIDv7: time-ordered UUIDs for B-tree locality.
-- Replace with the native uuidv7() on PostgreSQL 18+.
CREATE OR REPLACE FUNCTION uuid_generate_v7() RETURNS uuid AS $$
DECLARE
  unix_ts_ms BYTEA;
  uuid_bytes BYTEA;
BEGIN
  unix_ts_ms := substring(int8send((extract(epoch FROM clock_timestamp()) * 1000)::bigint) FROM 3);
  uuid_bytes := unix_ts_ms || gen_random_bytes(10);
  uuid_bytes := set_byte(uuid_bytes, 6, (b'0111' || get_byte(uuid_bytes, 6)::bit(4))::bit(8)::int);
  uuid_bytes := set_byte(uuid_bytes, 8, (b'10'   || get_byte(uuid_bytes, 8)::bit(6))::bit(8)::int);
  RETURN encode(uuid_bytes, 'hex')::uuid;
END $$ LANGUAGE plpgsql VOLATILE;

-- updated_at maintained by the database, not the application
CREATE OR REPLACE FUNCTION set_updated_at() RETURNS trigger AS $$
BEGIN NEW.updated_at = now(); RETURN NEW; END $$ LANGUAGE plpgsql;


-- =============================================================================
-- 1. IDENTITY AND TENANCY
-- =============================================================================

CREATE TABLE users (
  id                  UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  email               CITEXT NOT NULL,
  email_verified_at   TIMESTAMPTZ,
  password_hash       TEXT,                       -- NULL until activation
  password_changed_at TIMESTAMPTZ,
  full_name           TEXT,
  phone               TEXT,
  locale              TEXT NOT NULL DEFAULT 'it-IT',
  timezone            TEXT NOT NULL DEFAULT 'Europe/Rome',
  avatar_media_id     UUID,                       -- FK added after media
  status              TEXT NOT NULL DEFAULT 'pending'
                        CHECK (status IN ('pending','active','suspended','locked')),
  mfa_secret          TEXT,                       -- required for platform roles
  mfa_enabled_at      TIMESTAMPTZ,
  failed_login_count  INTEGER NOT NULL DEFAULT 0,
  locked_until        TIMESTAMPTZ,
  last_login_at       TIMESTAMPTZ,
  last_login_ip       INET,
  terms_accepted_at   TIMESTAMPTZ,
  marketing_opt_in    BOOLEAN NOT NULL DEFAULT false,
  created_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
  deleted_at          TIMESTAMPTZ
);
CREATE UNIQUE INDEX idx_users_email ON users(email) WHERE deleted_at IS NULL;
CREATE INDEX idx_users_status ON users(status) WHERE deleted_at IS NULL;
CREATE TRIGGER trg_users_updated BEFORE UPDATE ON users
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();

COMMENT ON COLUMN users.password_hash IS
  'Argon2id. NULL between purchase and activation - the user exists but cannot log in yet.';


CREATE TABLE accounts (
  id                   UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  reference            TEXT NOT NULL,             -- MH-2026-0341, human readable
  name                 TEXT NOT NULL,
  type                 TEXT NOT NULL DEFAULT 'individual'
                         CHECK (type IN ('individual','company')),
  -- billing identity (Italian e-invoicing fields included)
  legal_name           TEXT,
  vat_number           TEXT,                      -- Partita IVA / EU VAT
  tax_code             TEXT,                      -- Codice fiscale
  sdi_code             TEXT,                      -- Codice destinatario SDI
  pec_email            CITEXT,
  billing_email        CITEXT NOT NULL,
  billing_address_line1 TEXT,
  billing_address_line2 TEXT,
  billing_city         TEXT,
  billing_postal_code  TEXT,
  billing_province     TEXT,
  billing_country      CHAR(2) NOT NULL DEFAULT 'IT',
  -- commerce
  stripe_customer_id   TEXT,
  package_version_id   UUID,                      -- FK added after package_versions
  -- lifecycle
  status               TEXT NOT NULL DEFAULT 'pending'
                         CHECK (status IN ('pending','active','suspended','archived')),
  status_reason        TEXT,
  suspended_at         TIMESTAMPTZ,
  archived_at          TIMESTAMPTZ,
  activated_at         TIMESTAMPTZ,
  locale               TEXT NOT NULL DEFAULT 'it-IT',
  notes_count          INTEGER NOT NULL DEFAULT 0,
  created_by_user_id   UUID REFERENCES users(id),
  created_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
  deleted_at           TIMESTAMPTZ
);
CREATE UNIQUE INDEX idx_accounts_reference ON accounts(reference);
CREATE UNIQUE INDEX idx_accounts_stripe ON accounts(stripe_customer_id)
  WHERE stripe_customer_id IS NOT NULL;
CREATE INDEX idx_accounts_admin ON accounts(status, created_at DESC) WHERE deleted_at IS NULL;
CREATE INDEX idx_accounts_search ON accounts USING gin(
  to_tsvector('simple',
    coalesce(name,'') || ' ' || coalesce(legal_name,'') || ' ' ||
    coalesce(vat_number,'') || ' ' || coalesce(billing_email::text,'')));
CREATE TRIGGER trg_accounts_updated BEFORE UPDATE ON accounts
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();


CREATE TABLE roles (
  id          UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  key         TEXT NOT NULL UNIQUE,
  name        TEXT NOT NULL,
  description TEXT,
  scope       TEXT NOT NULL CHECK (scope IN ('platform','account')),
  is_system   BOOLEAN NOT NULL DEFAULT true,
  created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE permissions (
  id          UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  key         TEXT NOT NULL UNIQUE,              -- 'guide.publish'
  description TEXT NOT NULL,
  domain      TEXT NOT NULL                       -- 'guide', 'billing', 'system'
);

CREATE TABLE role_permissions (
  role_id       UUID NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
  permission_id UUID NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
  PRIMARY KEY (role_id, permission_id)
);

CREATE TABLE user_roles (
  id          UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  user_id     UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  role_id     UUID NOT NULL REFERENCES roles(id) ON DELETE RESTRICT,
  account_id  UUID REFERENCES accounts(id) ON DELETE CASCADE,  -- NULL = platform scope
  granted_by  UUID REFERENCES users(id),
  granted_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
  expires_at  TIMESTAMPTZ
);
CREATE UNIQUE INDEX idx_user_roles_unique
  ON user_roles(user_id, role_id, coalesce(account_id, '00000000-0000-0000-0000-000000000000'::uuid));
CREATE INDEX idx_user_roles_user ON user_roles(user_id);
CREATE INDEX idx_user_roles_account ON user_roles(account_id) WHERE account_id IS NOT NULL;

COMMENT ON COLUMN user_roles.account_id IS
  'NULL means platform scope (MyHouse staff). Non-null scopes the role to one account.';


CREATE TABLE account_members (
  id          UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  account_id  UUID NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  user_id     UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  role_key    TEXT NOT NULL CHECK (role_key IN ('account_owner','account_editor')),
  invited_by  UUID REFERENCES users(id),
  invited_at  TIMESTAMPTZ,
  accepted_at TIMESTAMPTZ,
  created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
  deleted_at  TIMESTAMPTZ
);
CREATE UNIQUE INDEX idx_account_members_unique ON account_members(account_id, user_id)
  WHERE deleted_at IS NULL;
-- exactly one owner per account
CREATE UNIQUE INDEX idx_account_one_owner ON account_members(account_id)
  WHERE role_key = 'account_owner' AND deleted_at IS NULL;


CREATE TABLE auth_tokens (
  id          UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  user_id     UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  type        TEXT NOT NULL CHECK (type IN
                ('activation','email_verification','password_reset','email_change')),
  token_hash  TEXT NOT NULL,                      -- SHA-256 of the token; plaintext never stored
  payload     JSONB,                              -- e.g. the new email for email_change
  expires_at  TIMESTAMPTZ NOT NULL,
  consumed_at TIMESTAMPTZ,
  created_ip  INET,
  created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX idx_auth_tokens_hash ON auth_tokens(token_hash);
CREATE INDEX idx_auth_tokens_user ON auth_tokens(user_id, type) WHERE consumed_at IS NULL;


-- =============================================================================
-- 2. CATALOG AND ENTITLEMENTS
-- =============================================================================

CREATE TABLE features (
  id            UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  key           TEXT NOT NULL UNIQUE,             -- 'welcome.languages.max'
  name          TEXT NOT NULL,
  description   TEXT,
  category      TEXT NOT NULL,                    -- 'content','media','branding','analytics'
  value_type    TEXT NOT NULL CHECK (value_type IN ('boolean','limit','enum')),
  default_value JSONB NOT NULL,                   -- false | 0 | "none"
  enum_options  JSONB,                            -- for value_type = 'enum'
  sort_order    INTEGER NOT NULL DEFAULT 0,
  is_visible    BOOLEAN NOT NULL DEFAULT true,    -- shown on the pricing comparison
  created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TRIGGER trg_features_updated BEFORE UPDATE ON features
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();

COMMENT ON COLUMN features.default_value IS
  'Applied when no entitlement row exists. A limit of -1 means unlimited.';


CREATE TABLE packages (
  id              UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  key             TEXT NOT NULL UNIQUE,           -- 'essential','plus','pro'
  name            TEXT NOT NULL,                  -- editable by admin
  tagline         TEXT,
  description     TEXT,
  sort_order      INTEGER NOT NULL DEFAULT 0,
  is_public       BOOLEAN NOT NULL DEFAULT true,  -- sellable to new customers
  is_recommended  BOOLEAN NOT NULL DEFAULT false,
  status          TEXT NOT NULL DEFAULT 'active'
                    CHECK (status IN ('active','archived')),
  created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TRIGGER trg_packages_updated BEFORE UPDATE ON packages
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();


CREATE TABLE package_versions (
  id                 UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  package_id         UUID NOT NULL REFERENCES packages(id) ON DELETE RESTRICT,
  version            INTEGER NOT NULL,
  amount_cents       BIGINT NOT NULL CHECK (amount_cents >= 0),
  currency           CHAR(3) NOT NULL DEFAULT 'EUR',
  billing_interval   TEXT NOT NULL DEFAULT 'one_time'
                       CHECK (billing_interval IN ('one_time','month','year')),
  stripe_product_id  TEXT,
  stripe_price_id    TEXT,
  changelog          TEXT,
  effective_from     TIMESTAMPTZ NOT NULL DEFAULT now(),
  effective_to       TIMESTAMPTZ,
  is_current         BOOLEAN NOT NULL DEFAULT false,
  is_frozen          BOOLEAN NOT NULL DEFAULT false,  -- true once an order references it
  created_by         UUID REFERENCES users(id),
  created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT uq_package_version UNIQUE (package_id, version)
);
CREATE UNIQUE INDEX idx_package_current ON package_versions(package_id) WHERE is_current;
CREATE UNIQUE INDEX idx_package_stripe_price ON package_versions(stripe_price_id)
  WHERE stripe_price_id IS NOT NULL;
CREATE TRIGGER trg_package_versions_updated BEFORE UPDATE ON package_versions
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();

COMMENT ON COLUMN package_versions.is_frozen IS
  'Set true the first time an order references this version. Frozen versions are '
  'immutable, which is how existing customers keep what they bought.';

ALTER TABLE accounts ADD CONSTRAINT fk_accounts_package_version
  FOREIGN KEY (package_version_id) REFERENCES package_versions(id) ON DELETE RESTRICT;


CREATE TABLE package_features (
  id                  UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  package_version_id  UUID NOT NULL REFERENCES package_versions(id) ON DELETE CASCADE,
  feature_id          UUID NOT NULL REFERENCES features(id) ON DELETE RESTRICT,
  value_json          JSONB NOT NULL,             -- true | 5 | -1 | "editorial"
  created_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT uq_package_feature UNIQUE (package_version_id, feature_id)
);
CREATE INDEX idx_package_features_version ON package_features(package_version_id);


CREATE TABLE entitlements (
  id                 UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  account_id         UUID NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  feature_key        TEXT NOT NULL REFERENCES features(key) ON UPDATE CASCADE,
  value_json         JSONB NOT NULL,
  source             TEXT NOT NULL CHECK (source IN ('package','override','promo','grandfathered')),
  package_version_id UUID REFERENCES package_versions(id) ON DELETE SET NULL,
  reason             TEXT,                        -- required by the service for override/promo
  granted_by         UUID REFERENCES users(id),
  starts_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
  ends_at            TIMESTAMPTZ,
  revoked_at         TIMESTAMPTZ,
  revoked_by         UUID REFERENCES users(id),
  created_at         TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_entitlements_lookup ON entitlements(account_id, feature_key)
  WHERE revoked_at IS NULL;
CREATE INDEX idx_entitlements_source ON entitlements(source, ends_at)
  WHERE revoked_at IS NULL AND source <> 'package';

COMMENT ON TABLE entitlements IS
  'Resolved grants. Multiple rows per (account, feature) are expected: package grant, '
  'override, promo. Resolution order is override > promo > package > feature default.';


-- =============================================================================
-- 3. COMMERCE
-- =============================================================================

CREATE TABLE discount_codes (
  id                       UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  code                     TEXT NOT NULL,
  description              TEXT,
  type                     TEXT NOT NULL CHECK (type IN ('percent','amount')),
  value                    INTEGER NOT NULL CHECK (value > 0),   -- percent 1-100, or minor units
  currency                 CHAR(3),
  stripe_coupon_id         TEXT,
  stripe_promotion_code_id TEXT,
  applies_to_package_ids   JSONB,                 -- null = all packages
  max_redemptions          INTEGER,
  redeemed_count           INTEGER NOT NULL DEFAULT 0,
  starts_at                TIMESTAMPTZ,
  ends_at                  TIMESTAMPTZ,
  status                   TEXT NOT NULL DEFAULT 'active'
                             CHECK (status IN ('active','paused','expired')),
  created_by               UUID REFERENCES users(id),
  created_at               TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at               TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT chk_amount_currency CHECK (type <> 'amount' OR currency IS NOT NULL),
  CONSTRAINT chk_percent_range   CHECK (type <> 'percent' OR value BETWEEN 1 AND 100)
);
CREATE UNIQUE INDEX idx_discount_code ON discount_codes(upper(code));
CREATE TRIGGER trg_discount_codes_updated BEFORE UPDATE ON discount_codes
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();


CREATE TABLE orders (
  id                         UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  reference                  TEXT NOT NULL,       -- MH-O-2026-00412
  account_id                 UUID REFERENCES accounts(id) ON DELETE RESTRICT,
  package_version_id         UUID NOT NULL REFERENCES package_versions(id) ON DELETE RESTRICT,
  type                       TEXT NOT NULL DEFAULT 'purchase'
                               CHECK (type IN ('purchase','upgrade','manual','renewal')),
  status                     TEXT NOT NULL DEFAULT 'created'
                               CHECK (status IN ('created','pending_payment','paid','fulfilled',
                                                 'failed','abandoned','refunded','partially_refunded')),
  currency                   CHAR(3) NOT NULL DEFAULT 'EUR',
  subtotal_cents             BIGINT NOT NULL DEFAULT 0,
  discount_cents             BIGINT NOT NULL DEFAULT 0,
  tax_cents                  BIGINT NOT NULL DEFAULT 0,
  total_cents                BIGINT NOT NULL DEFAULT 0,
  discount_code_id           UUID REFERENCES discount_codes(id) ON DELETE SET NULL,
  payment_provider           TEXT NOT NULL DEFAULT 'stripe'
                               CHECK (payment_provider IN ('stripe','manual')),
  stripe_checkout_session_id TEXT,
  customer_email             CITEXT,              -- captured before the account exists
  customer_locale            TEXT,
  manual_reason              TEXT,                -- required when payment_provider = 'manual'
  created_by                 UUID REFERENCES users(id),
  placed_at                  TIMESTAMPTZ NOT NULL DEFAULT now(),
  completed_at               TIMESTAMPTZ,
  created_at                 TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at                 TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT chk_manual_reason CHECK (payment_provider <> 'manual' OR manual_reason IS NOT NULL)
);
CREATE UNIQUE INDEX idx_orders_reference ON orders(reference);
CREATE UNIQUE INDEX idx_orders_checkout_session ON orders(stripe_checkout_session_id)
  WHERE stripe_checkout_session_id IS NOT NULL;
CREATE INDEX idx_orders_account ON orders(account_id, placed_at DESC);
CREATE INDEX idx_orders_status ON orders(status, placed_at DESC);
CREATE TRIGGER trg_orders_updated BEFORE UPDATE ON orders
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();


CREATE TABLE payments (
  id                       UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  order_id                 UUID NOT NULL REFERENCES orders(id) ON DELETE RESTRICT,
  account_id               UUID REFERENCES accounts(id) ON DELETE RESTRICT,
  stripe_payment_intent_id TEXT,
  stripe_charge_id         TEXT,
  status                   TEXT NOT NULL DEFAULT 'pending'
                             CHECK (status IN ('pending','processing','succeeded','failed',
                                               'refunded','partially_refunded','disputed')),
  amount_cents             BIGINT NOT NULL,
  currency                 CHAR(3) NOT NULL DEFAULT 'EUR',
  refunded_amount_cents    BIGINT NOT NULL DEFAULT 0,
  method_type              TEXT,                  -- 'card','sepa_debit','paypal'
  method_brand             TEXT,                  -- 'visa'
  method_last4             CHAR(4),
  receipt_url              TEXT,
  failure_code             TEXT,
  failure_message          TEXT,
  paid_at                  TIMESTAMPTZ,
  created_at               TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at               TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT chk_refund_le_amount CHECK (refunded_amount_cents <= amount_cents)
);
CREATE UNIQUE INDEX idx_payments_pi ON payments(stripe_payment_intent_id)
  WHERE stripe_payment_intent_id IS NOT NULL;
CREATE INDEX idx_payments_order ON payments(order_id);
CREATE INDEX idx_payments_account ON payments(account_id, paid_at DESC);
CREATE TRIGGER trg_payments_updated BEFORE UPDATE ON payments
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();


CREATE TABLE refunds (
  id               UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  payment_id       UUID NOT NULL REFERENCES payments(id) ON DELETE RESTRICT,
  stripe_refund_id TEXT,
  amount_cents     BIGINT NOT NULL CHECK (amount_cents > 0),
  currency         CHAR(3) NOT NULL DEFAULT 'EUR',
  reason           TEXT NOT NULL,
  status           TEXT NOT NULL DEFAULT 'pending'
                     CHECK (status IN ('pending','succeeded','failed','canceled')),
  initiated_by     UUID REFERENCES users(id),
  created_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at       TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX idx_refunds_stripe ON refunds(stripe_refund_id)
  WHERE stripe_refund_id IS NOT NULL;


CREATE TABLE invoices (
  id                UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  account_id        UUID NOT NULL REFERENCES accounts(id) ON DELETE RESTRICT,
  order_id          UUID REFERENCES orders(id) ON DELETE SET NULL,
  stripe_invoice_id TEXT,
  number            TEXT,
  amount_cents      BIGINT NOT NULL,
  currency          CHAR(3) NOT NULL DEFAULT 'EUR',
  status            TEXT NOT NULL DEFAULT 'draft'
                      CHECK (status IN ('draft','open','paid','void','uncollectible')),
  pdf_url           TEXT,
  hosted_url        TEXT,
  issued_at         TIMESTAMPTZ,
  created_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at        TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX idx_invoices_stripe ON invoices(stripe_invoice_id)
  WHERE stripe_invoice_id IS NOT NULL;
CREATE INDEX idx_invoices_account ON invoices(account_id, issued_at DESC);


-- Dormant until recurring packages ship. Schema is ready; see docs/06.
CREATE TABLE subscriptions (
  id                     UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  account_id             UUID NOT NULL REFERENCES accounts(id) ON DELETE RESTRICT,
  package_version_id     UUID NOT NULL REFERENCES package_versions(id) ON DELETE RESTRICT,
  stripe_subscription_id TEXT,
  status                 TEXT NOT NULL DEFAULT 'active'
                           CHECK (status IN ('trialing','active','past_due','canceled','unpaid','paused')),
  current_period_start   TIMESTAMPTZ,
  current_period_end     TIMESTAMPTZ,
  cancel_at_period_end   BOOLEAN NOT NULL DEFAULT false,
  canceled_at            TIMESTAMPTZ,
  trial_end              TIMESTAMPTZ,
  grace_until            TIMESTAMPTZ,
  created_at             TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at             TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX idx_subscriptions_stripe ON subscriptions(stripe_subscription_id)
  WHERE stripe_subscription_id IS NOT NULL;


CREATE TABLE discount_redemptions (
  id               UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  discount_code_id UUID NOT NULL REFERENCES discount_codes(id) ON DELETE RESTRICT,
  order_id         UUID NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  account_id       UUID REFERENCES accounts(id) ON DELETE SET NULL,
  amount_cents     BIGINT NOT NULL,
  redeemed_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT uq_redemption_order UNIQUE (discount_code_id, order_id)
);


-- The idempotency table. The unique index below is the whole mechanism.
CREATE TABLE webhook_events (
  id                UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  provider          TEXT NOT NULL DEFAULT 'stripe',
  provider_event_id TEXT NOT NULL,
  type              TEXT NOT NULL,
  api_version       TEXT,
  payload           JSONB NOT NULL,
  status            TEXT NOT NULL DEFAULT 'received'
                      CHECK (status IN ('received','processing','processed','failed','ignored')),
  attempts          INTEGER NOT NULL DEFAULT 0,
  last_error        TEXT,
  related_order_id  UUID REFERENCES orders(id) ON DELETE SET NULL,
  received_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
  processed_at      TIMESTAMPTZ
);
CREATE UNIQUE INDEX idx_webhook_event_id ON webhook_events(provider, provider_event_id);
CREATE INDEX idx_webhook_status ON webhook_events(status, received_at DESC)
  WHERE status IN ('received','failed');


-- =============================================================================
-- 4. MEDIA
-- =============================================================================

CREATE TABLE media (
  id                UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  account_id        UUID NOT NULL REFERENCES accounts(id) ON DELETE RESTRICT,
  uploaded_by       UUID REFERENCES users(id) ON DELETE SET NULL,
  kind              TEXT NOT NULL CHECK (kind IN ('image','pdf')),
  original_filename TEXT NOT NULL,
  storage_key       TEXT NOT NULL,
  mime_type         TEXT NOT NULL,
  detected_mime     TEXT,                         -- sniffed from magic bytes
  bytes             BIGINT NOT NULL,
  width             INTEGER,
  height            INTEGER,
  page_count        INTEGER,                      -- PDFs
  checksum_sha256   TEXT NOT NULL,
  dominant_color    CHAR(7),
  blur_placeholder  TEXT,                         -- tiny base64 LQIP
  alt_text          TEXT,
  title             TEXT,
  focal_x           REAL CHECK (focal_x BETWEEN 0 AND 1),
  focal_y           REAL CHECK (focal_y BETWEEN 0 AND 1),
  status            TEXT NOT NULL DEFAULT 'uploading'
                      CHECK (status IN ('uploading','processing','ready','failed','quarantined')),
  failure_reason    TEXT,
  scan_status       TEXT NOT NULL DEFAULT 'pending'
                      CHECK (scan_status IN ('pending','clean','infected','error','skipped')),
  processed_at      TIMESTAMPTZ,
  created_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
  deleted_at        TIMESTAMPTZ
);
CREATE INDEX idx_media_account ON media(account_id, created_at DESC) WHERE deleted_at IS NULL;
CREATE UNIQUE INDEX idx_media_dedupe ON media(account_id, checksum_sha256)
  WHERE deleted_at IS NULL AND status = 'ready';
CREATE INDEX idx_media_orphans ON media(created_at) WHERE status = 'uploading';
CREATE TRIGGER trg_media_updated BEFORE UPDATE ON media
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();

ALTER TABLE users ADD CONSTRAINT fk_users_avatar
  FOREIGN KEY (avatar_media_id) REFERENCES media(id) ON DELETE SET NULL;


CREATE TABLE media_variants (
  id          UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  media_id    UUID NOT NULL REFERENCES media(id) ON DELETE CASCADE,
  purpose     TEXT NOT NULL CHECK (purpose IN ('thumb','card','content','cover','cover2x')),
  format      TEXT NOT NULL CHECK (format IN ('avif','webp','jpeg')),
  width       INTEGER NOT NULL,
  height      INTEGER NOT NULL,
  bytes       BIGINT NOT NULL,
  storage_key TEXT NOT NULL,
  created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT uq_media_variant UNIQUE (media_id, purpose, format)
);
CREATE INDEX idx_media_variants_media ON media_variants(media_id);


-- Written by the same service call that sets a media reference, so it cannot drift.
CREATE TABLE media_usages (
  id          UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  media_id    UUID NOT NULL REFERENCES media(id) ON DELETE CASCADE,
  account_id  UUID NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  usable_type TEXT NOT NULL,                      -- 'content_block','property','recommendation'
  usable_id   UUID NOT NULL,
  field       TEXT NOT NULL,                      -- 'cover_image','gallery[2]'
  created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT uq_media_usage UNIQUE (media_id, usable_type, usable_id, field)
);
CREATE INDEX idx_media_usages_media ON media_usages(media_id);
CREATE INDEX idx_media_usages_usable ON media_usages(usable_type, usable_id);


-- =============================================================================
-- 5. CONTENT
-- =============================================================================

CREATE TABLE locales (
  code        TEXT PRIMARY KEY,                   -- BCP 47: 'it-IT'
  name        TEXT NOT NULL,                      -- 'Italian'
  native_name TEXT NOT NULL,                      -- 'Italiano'
  direction   TEXT NOT NULL DEFAULT 'ltr' CHECK (direction IN ('ltr','rtl')),
  is_active   BOOLEAN NOT NULL DEFAULT true,
  sort_order  INTEGER NOT NULL DEFAULT 0
);


CREATE TABLE properties (
  id                  UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  account_id          UUID NOT NULL REFERENCES accounts(id) ON DELETE RESTRICT,
  name                TEXT NOT NULL,
  type                TEXT NOT NULL DEFAULT 'apartment'
                        CHECK (type IN ('apartment','house','villa','bnb','room','guesthouse','other')),
  short_description   TEXT,
  address_line1       TEXT,
  address_line2       TEXT,
  city                TEXT,
  postal_code         TEXT,
  province            TEXT,
  country             CHAR(2) NOT NULL DEFAULT 'IT',
  latitude            NUMERIC(9,6),
  longitude           NUMERIC(9,6),
  timezone            TEXT NOT NULL DEFAULT 'Europe/Rome',
  phone               TEXT,
  whatsapp            TEXT,
  email               CITEXT,
  website             TEXT,
  host_name           TEXT,
  host_photo_media_id UUID REFERENCES media(id) ON DELETE SET NULL,
  logo_media_id       UUID REFERENCES media(id) ON DELETE SET NULL,
  cover_media_id      UUID REFERENCES media(id) ON DELETE SET NULL,
  status              TEXT NOT NULL DEFAULT 'active'
                        CHECK (status IN ('active','inactive','archived')),
  created_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
  deleted_at          TIMESTAMPTZ
);
CREATE INDEX idx_properties_account ON properties(account_id) WHERE deleted_at IS NULL;
CREATE TRIGGER trg_properties_updated BEFORE UPDATE ON properties
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();


CREATE TABLE guides (
  id                       UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  property_id              UUID NOT NULL REFERENCES properties(id) ON DELETE RESTRICT,
  account_id               UUID NOT NULL REFERENCES accounts(id) ON DELETE RESTRICT,
  status                   TEXT NOT NULL DEFAULT 'draft'
                             CHECK (status IN ('draft','published','unpublished')),
  default_locale           TEXT NOT NULL DEFAULT 'it-IT' REFERENCES locales(code),
  published_version_id     UUID,                  -- FK added after guide_versions
  has_unpublished_changes  BOOLEAN NOT NULL DEFAULT false,
  completion_percent       SMALLINT NOT NULL DEFAULT 0
                             CHECK (completion_percent BETWEEN 0 AND 100),
  search_enabled           BOOLEAN NOT NULL DEFAULT false,
  seo_indexable            BOOLEAN NOT NULL DEFAULT false,
  theme_json               JSONB NOT NULL DEFAULT '{}'::jsonb,
  branding_json            JSONB NOT NULL DEFAULT '{}'::jsonb,
  welcome_message          TEXT,
  published_at             TIMESTAMPTZ,
  published_by             UUID REFERENCES users(id),
  unpublished_at           TIMESTAMPTZ,
  created_at               TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at               TIMESTAMPTZ NOT NULL DEFAULT now(),
  deleted_at               TIMESTAMPTZ
);
CREATE UNIQUE INDEX idx_guides_property ON guides(property_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_guides_account ON guides(account_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_guides_published ON guides(id) INCLUDE (published_version_id)
  WHERE status = 'published';
CREATE TRIGGER trg_guides_updated BEFORE UPDATE ON guides
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();

COMMENT ON COLUMN guides.theme_json IS
  'Bounded settings object: accent, typography pairing, corner style. Fixed schema, '
  'single row, never queried by its internals - see docs/14.';


CREATE TABLE guide_versions (
  id             UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  guide_id       UUID NOT NULL REFERENCES guides(id) ON DELETE CASCADE,
  version_number INTEGER NOT NULL,
  snapshot       JSONB NOT NULL,
  locales        TEXT[] NOT NULL DEFAULT '{}',
  byte_size      INTEGER,
  note           TEXT,
  published_by   UUID REFERENCES users(id),
  published_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT uq_guide_version UNIQUE (guide_id, version_number)
);
CREATE INDEX idx_guide_versions_guide ON guide_versions(guide_id, version_number DESC);

ALTER TABLE guides ADD CONSTRAINT fk_guides_published_version
  FOREIGN KEY (published_version_id) REFERENCES guide_versions(id) ON DELETE SET NULL;

COMMENT ON COLUMN guide_versions.snapshot IS
  'Immutable, read-optimised publish artifact, complete per locale. Serving a guest '
  'is one row read. Never written to after creation.';


CREATE TABLE section_templates (
  id               UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  key              TEXT NOT NULL UNIQUE,          -- 'wifi','checkin','parking'
  name             TEXT NOT NULL,
  icon             TEXT NOT NULL,
  category         TEXT NOT NULL,                 -- 'arrival','stay','departure','local'
  default_order    INTEGER NOT NULL DEFAULT 0,
  required_feature TEXT REFERENCES features(key) ON UPDATE CASCADE,
  field_schema     JSONB NOT NULL,                -- drives the wizard and the editor
  default_copy     JSONB NOT NULL DEFAULT '{}'::jsonb,  -- per-locale labels and placeholders
  is_system        BOOLEAN NOT NULL DEFAULT true,
  is_active        BOOLEAN NOT NULL DEFAULT true,
  created_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at       TIMESTAMPTZ NOT NULL DEFAULT now()
);


CREATE TABLE guide_sections (
  id                  UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  guide_id            UUID NOT NULL REFERENCES guides(id) ON DELETE CASCADE,
  account_id          UUID NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  section_template_id UUID REFERENCES section_templates(id) ON DELETE SET NULL,
  key                 TEXT NOT NULL,              -- template key, or 'custom-<n>'
  type                TEXT NOT NULL DEFAULT 'standard'
                        CHECK (type IN ('standard','custom')),
  title               TEXT NOT NULL,
  subtitle            TEXT,
  icon                TEXT NOT NULL DEFAULT 'circle',
  sort_order          INTEGER NOT NULL DEFAULT 0,
  is_enabled          BOOLEAN NOT NULL DEFAULT true,
  is_complete         BOOLEAN NOT NULL DEFAULT false,
  visibility          TEXT NOT NULL DEFAULT 'public'
                        CHECK (visibility IN ('public','pin_protected','date_window')),
  pin_hash            TEXT,                       -- Argon2id, never the PIN itself
  visible_from        TIMESTAMPTZ,
  visible_to          TIMESTAMPTZ,
  required_feature    TEXT REFERENCES features(key) ON UPDATE CASCADE,
  created_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
  deleted_at          TIMESTAMPTZ,
  CONSTRAINT uq_guide_section_key UNIQUE (guide_id, key),
  CONSTRAINT chk_pin_present CHECK (visibility <> 'pin_protected' OR pin_hash IS NOT NULL),
  CONSTRAINT chk_date_window CHECK (visibility <> 'date_window'
                                    OR (visible_from IS NOT NULL OR visible_to IS NOT NULL))
);
CREATE INDEX idx_guide_sections_guide ON guide_sections(guide_id, sort_order)
  WHERE deleted_at IS NULL;
CREATE TRIGGER trg_guide_sections_updated BEFORE UPDATE ON guide_sections
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();


CREATE TABLE content_blocks (
  id               UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  guide_section_id UUID NOT NULL REFERENCES guide_sections(id) ON DELETE CASCADE,
  guide_id         UUID NOT NULL REFERENCES guides(id) ON DELETE CASCADE,
  account_id       UUID NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  type             TEXT NOT NULL CHECK (type IN
                     ('rich_text','key_value','wifi','image','gallery','file','video',
                      'map','contact','link','list','faq','hours','custom')),
  schema_version   SMALLINT NOT NULL DEFAULT 1,
  sort_order       INTEGER NOT NULL DEFAULT 0,
  is_enabled       BOOLEAN NOT NULL DEFAULT true,
  is_sensitive     BOOLEAN NOT NULL DEFAULT false, -- excluded from MT, masked in logs
  data             JSONB NOT NULL DEFAULT '{}'::jsonb,
  media_id         UUID REFERENCES media(id) ON DELETE SET NULL,
  created_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
  deleted_at       TIMESTAMPTZ
);
CREATE INDEX idx_content_blocks_section ON content_blocks(guide_section_id, sort_order)
  WHERE deleted_at IS NULL;
CREATE INDEX idx_content_blocks_guide ON content_blocks(guide_id) WHERE deleted_at IS NULL;
CREATE TRIGGER trg_content_blocks_updated BEFORE UPDATE ON content_blocks
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();

COMMENT ON COLUMN content_blocks.data IS
  'Payload whose shape depends on type, validated against a versioned JSON Schema on '
  'write. Structure (which block, where, enabled) stays relational - see docs/14.';


CREATE TABLE guide_locales (
  id                  UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  guide_id            UUID NOT NULL REFERENCES guides(id) ON DELETE CASCADE,
  locale              TEXT NOT NULL REFERENCES locales(code),
  is_default          BOOLEAN NOT NULL DEFAULT false,
  is_enabled          BOOLEAN NOT NULL DEFAULT false,   -- visible to guests
  completeness        SMALLINT NOT NULL DEFAULT 0 CHECK (completeness BETWEEN 0 AND 100),
  fields_total        INTEGER NOT NULL DEFAULT 0,
  fields_translated   INTEGER NOT NULL DEFAULT 0,
  fields_stale        INTEGER NOT NULL DEFAULT 0,
  last_published_at   TIMESTAMPTZ,
  created_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT uq_guide_locale UNIQUE (guide_id, locale)
);
CREATE UNIQUE INDEX idx_guide_default_locale ON guide_locales(guide_id) WHERE is_default;


CREATE TABLE recommendation_categories (
  id          UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  key         TEXT NOT NULL,
  name        TEXT NOT NULL,
  icon        TEXT NOT NULL DEFAULT 'pin',
  sort_order  INTEGER NOT NULL DEFAULT 0,
  is_system   BOOLEAN NOT NULL DEFAULT true,
  account_id  UUID REFERENCES accounts(id) ON DELETE CASCADE,  -- NULL = system category
  created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX idx_rec_category_key ON recommendation_categories(key)
  WHERE account_id IS NULL;


CREATE TABLE recommendations (
  id                UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  guide_id          UUID NOT NULL REFERENCES guides(id) ON DELETE CASCADE,
  account_id        UUID NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  category_id       UUID NOT NULL REFERENCES recommendation_categories(id) ON DELETE RESTRICT,
  name              TEXT NOT NULL,
  short_description TEXT,
  address           TEXT,
  latitude          NUMERIC(9,6),
  longitude         NUMERIC(9,6),
  maps_url          TEXT,
  phone             TEXT,
  website           TEXT,
  distance_meters   INTEGER,                      -- computed at publish, not in the browser
  price_level       SMALLINT CHECK (price_level BETWEEN 1 AND 4),
  media_id          UUID REFERENCES media(id) ON DELETE SET NULL,
  is_host_pick      BOOLEAN NOT NULL DEFAULT false,
  is_enabled        BOOLEAN NOT NULL DEFAULT true,
  sort_order        INTEGER NOT NULL DEFAULT 0,
  created_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
  deleted_at        TIMESTAMPTZ
);
CREATE INDEX idx_recommendations_guide ON recommendations(guide_id, category_id, sort_order)
  WHERE deleted_at IS NULL;
CREATE TRIGGER trg_recommendations_updated BEFORE UPDATE ON recommendations
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();


-- =============================================================================
-- 6. TRANSLATION
-- =============================================================================

CREATE TABLE translations (
  id                UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  account_id        UUID NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  guide_id          UUID REFERENCES guides(id) ON DELETE CASCADE,
  translatable_type TEXT NOT NULL,                -- 'content_block','guide_section','recommendation'
  translatable_id   UUID NOT NULL,
  field_path        TEXT NOT NULL,                -- 'title', 'data.instructions', 'items[2].label'
  locale            TEXT NOT NULL REFERENCES locales(code),
  source_locale     TEXT NOT NULL REFERENCES locales(code),
  value_text        TEXT,
  proposed_value    TEXT,                         -- machine re-translation awaiting acceptance
  status            TEXT NOT NULL DEFAULT 'missing'
                      CHECK (status IN ('missing','machine_translated','reviewed','published')),
  source_hash       TEXT,                         -- hash of the original at translation time
  is_stale          BOOLEAN NOT NULL DEFAULT false,
  is_locked         BOOLEAN NOT NULL DEFAULT false,  -- host pinned: never machine-touch
  engine            TEXT,                         -- 'deepl','openai','manual'
  engine_meta       JSONB,
  translated_at     TIMESTAMPTZ,
  reviewed_by       UUID REFERENCES users(id) ON DELETE SET NULL,
  reviewed_at       TIMESTAMPTZ,
  created_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT uq_translation UNIQUE (translatable_type, translatable_id, field_path, locale),
  CONSTRAINT chk_not_self CHECK (locale <> source_locale)
);
CREATE INDEX idx_translations_lookup
  ON translations(translatable_type, translatable_id, locale) INCLUDE (field_path, status);
CREATE INDEX idx_translations_pending ON translations(guide_id, locale, status)
  WHERE status IN ('missing','machine_translated');
CREATE INDEX idx_translations_stale ON translations(guide_id, locale) WHERE is_stale;
CREATE TRIGGER trg_translations_updated BEFORE UPDATE ON translations
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- The guarantee from docs/11: a machine run can never overwrite human work.
-- Enforced in the repository WHERE clause AND here, as a last line of defence.
CREATE OR REPLACE FUNCTION protect_reviewed_translations() RETURNS trigger AS $$
BEGIN
  IF OLD.status IN ('reviewed','published')
     AND NEW.status = 'machine_translated'
     AND NEW.value_text IS DISTINCT FROM OLD.value_text THEN
    RAISE EXCEPTION
      'Refusing to overwrite a % translation with machine output (translation %). '
      'Write to proposed_value instead.', OLD.status, OLD.id;
  END IF;
  IF OLD.is_locked AND NEW.engine IS DISTINCT FROM 'manual'
     AND NEW.value_text IS DISTINCT FROM OLD.value_text THEN
    RAISE EXCEPTION 'Translation % is locked by the host.', OLD.id;
  END IF;
  RETURN NEW;
END $$ LANGUAGE plpgsql;

CREATE TRIGGER trg_protect_translations BEFORE UPDATE ON translations
  FOR EACH ROW EXECUTE FUNCTION protect_reviewed_translations();


-- =============================================================================
-- 7. PUBLISHING: URLS AND QR
-- =============================================================================

CREATE TABLE public_urls (
  id             UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  guide_id       UUID NOT NULL REFERENCES guides(id) ON DELETE CASCADE,
  account_id     UUID NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  host           TEXT NOT NULL DEFAULT 'welcome.myhouse.it',
  slug           TEXT NOT NULL CHECK (slug ~ '^[a-z0-9]([a-z0-9-]{1,61}[a-z0-9])?$'),
  is_primary     BOOLEAN NOT NULL DEFAULT true,
  status         TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','redirect')),
  redirect_to_id UUID REFERENCES public_urls(id) ON DELETE SET NULL,
  verified_at    TIMESTAMPTZ,                     -- custom domains
  created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT chk_redirect_target CHECK (status <> 'redirect' OR redirect_to_id IS NOT NULL)
);
CREATE UNIQUE INDEX idx_public_urls_lookup ON public_urls(host, slug);
CREATE UNIQUE INDEX idx_public_urls_primary ON public_urls(guide_id) WHERE is_primary;

COMMENT ON TABLE public_urls IS
  'Slug history. Old rows become permanent redirects and are never deleted or reused - '
  'reuse would send a guest holding an old link to a stranger''s home.';


CREATE TABLE qr_codes (
  id             UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  guide_id       UUID NOT NULL REFERENCES guides(id) ON DELETE CASCADE,
  account_id     UUID NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  token          TEXT NOT NULL,                   -- 10-char base32, immutable
  label          TEXT,                            -- 'Fridge card', 'Entrance'
  style_json     JSONB NOT NULL DEFAULT '{}'::jsonb,
  scan_count     BIGINT NOT NULL DEFAULT 0,
  last_scanned_at TIMESTAMPTZ,
  created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
  revoked_at     TIMESTAMPTZ,
  revoked_by     UUID REFERENCES users(id)
);
CREATE UNIQUE INDEX idx_qr_token ON qr_codes(token);
CREATE INDEX idx_qr_guide ON qr_codes(guide_id) WHERE revoked_at IS NULL;

COMMENT ON COLUMN qr_codes.token IS
  'Permanent. The QR encodes mh.li/q/<token> and never the guide URL, so printed '
  'codes survive slug changes and custom domains - see docs/13.';


-- =============================================================================
-- 8. ANALYTICS
-- =============================================================================

CREATE TABLE analytics_events (
  id            UUID NOT NULL DEFAULT uuid_generate_v7(),
  guide_id      UUID NOT NULL,
  account_id    UUID NOT NULL,
  occurred_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
  event_type    TEXT NOT NULL CHECK (event_type IN
                  ('guide_view','section_view','outbound_click','language_switch',
                   'search','copy','qr_scan','pin_unlock')),
  section_key   TEXT,
  locale        TEXT,
  entry_source  TEXT CHECK (entry_source IN ('qr','link','direct','search')),
  device_type   TEXT CHECK (device_type IN ('mobile','tablet','desktop','bot')),
  country       CHAR(2),
  referrer_host TEXT,
  target_type   TEXT,                             -- 'phone','whatsapp','maps','website'
  target_id     UUID,
  visitor_hash  TEXT,                             -- daily-rotating salted hash, never an ID
  is_internal   BOOLEAN NOT NULL DEFAULT false,   -- host preview or support session
  PRIMARY KEY (id, occurred_at)
) PARTITION BY RANGE (occurred_at);

CREATE INDEX idx_events_guide_time ON analytics_events(guide_id, occurred_at DESC);
CREATE INDEX idx_events_type ON analytics_events(event_type, occurred_at DESC);

-- Partitions are created a month ahead by a scheduled job; 14-month retention
-- is a DETACH + DROP, not a DELETE.
CREATE TABLE analytics_events_2026_09 PARTITION OF analytics_events
  FOR VALUES FROM ('2026-09-01') TO ('2026-10-01');
CREATE TABLE analytics_events_2026_10 PARTITION OF analytics_events
  FOR VALUES FROM ('2026-10-01') TO ('2026-11-01');

COMMENT ON COLUMN analytics_events.visitor_hash IS
  'sha256(ip + user_agent + daily_salt), truncated. The salt rotates every 24h and is '
  'never stored, so yesterday''s visitors cannot be linked to today''s. See docs/22.';


CREATE TABLE analytics_daily (
  id          UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  guide_id    UUID NOT NULL REFERENCES guides(id) ON DELETE CASCADE,
  account_id  UUID NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  date        DATE NOT NULL,
  metric      TEXT NOT NULL,                      -- 'views','unique_visitors','section_views'
  dimension   TEXT,                               -- section key, locale, target type
  dimension_2 TEXT,
  count       BIGINT NOT NULL DEFAULT 0,
  CONSTRAINT uq_analytics_daily UNIQUE (guide_id, date, metric, dimension, dimension_2)
);
CREATE INDEX idx_analytics_daily_guide ON analytics_daily(guide_id, date DESC);


-- =============================================================================
-- 9. OPERATIONS
-- =============================================================================

CREATE TABLE activity_logs (
  id                      UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  actor_user_id           UUID REFERENCES users(id) ON DELETE SET NULL,
  actor_type              TEXT NOT NULL DEFAULT 'user'
                            CHECK (actor_type IN ('user','admin','system','webhook')),
  actor_label             TEXT,                   -- preserved if the user is later deleted
  account_id              UUID REFERENCES accounts(id) ON DELETE SET NULL,
  impersonation_session_id UUID,                  -- FK added below
  action                  TEXT NOT NULL,          -- 'guide.publish','package.assign'
  subject_type            TEXT,
  subject_id              UUID,
  subject_label           TEXT,
  changes                 JSONB,                  -- { field: { from, to } }, sensitive masked
  reason                  TEXT,
  ip                      INET,
  user_agent              TEXT,
  created_at              TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_activity_account ON activity_logs(account_id, created_at DESC);
CREATE INDEX idx_activity_actor ON activity_logs(actor_user_id, created_at DESC);
CREATE INDEX idx_activity_action ON activity_logs(action, created_at DESC);
CREATE INDEX idx_activity_subject ON activity_logs(subject_type, subject_id, created_at DESC);


CREATE TABLE impersonation_sessions (
  id            UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  admin_user_id UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  account_id    UUID NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  reason        TEXT NOT NULL,
  started_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
  expires_at    TIMESTAMPTZ NOT NULL,
  ended_at      TIMESTAMPTZ,
  ended_reason  TEXT CHECK (ended_reason IN ('manual','expired','revoked')),
  actions_count INTEGER NOT NULL DEFAULT 0,
  ip            INET,
  user_agent    TEXT
);
CREATE INDEX idx_impersonation_account ON impersonation_sessions(account_id, started_at DESC);
CREATE INDEX idx_impersonation_active ON impersonation_sessions(admin_user_id)
  WHERE ended_at IS NULL;

ALTER TABLE activity_logs ADD CONSTRAINT fk_activity_impersonation
  FOREIGN KEY (impersonation_session_id) REFERENCES impersonation_sessions(id) ON DELETE SET NULL;


CREATE TABLE admin_notes (
  id             UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  account_id     UUID NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  author_user_id UUID REFERENCES users(id) ON DELETE SET NULL,
  author_label   TEXT,
  body           TEXT NOT NULL,
  is_pinned      BOOLEAN NOT NULL DEFAULT false,
  created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
  deleted_at     TIMESTAMPTZ
);
CREATE INDEX idx_admin_notes_account ON admin_notes(account_id, created_at DESC)
  WHERE deleted_at IS NULL;


CREATE TABLE onboarding_progress (
  id              UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  account_id      UUID NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  property_id     UUID REFERENCES properties(id) ON DELETE CASCADE,
  wizard_version  TEXT NOT NULL DEFAULT '1',      -- in-flight onboardings keep their version
  current_step    TEXT NOT NULL DEFAULT 'property',
  completed_steps TEXT[] NOT NULL DEFAULT '{}',
  skipped_steps   TEXT[] NOT NULL DEFAULT '{}',
  started_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
  completed_at    TIMESTAMPTZ,
  last_active_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
  device_hint     TEXT CHECK (device_hint IN ('mobile','tablet','desktop')),
  CONSTRAINT uq_onboarding_property UNIQUE (property_id)
);
CREATE INDEX idx_onboarding_incomplete ON onboarding_progress(last_active_at)
  WHERE completed_at IS NULL;


CREATE TABLE settings (
  id         UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  scope      TEXT NOT NULL CHECK (scope IN ('global','account')),
  scope_id   UUID,                                -- account id when scope = 'account'
  key        TEXT NOT NULL,
  value_json JSONB NOT NULL,
  updated_by UUID REFERENCES users(id),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT uq_setting UNIQUE (scope, scope_id, key),
  CONSTRAINT chk_scope_id CHECK ((scope = 'global') = (scope_id IS NULL))
);


CREATE TABLE email_log (
  id                  UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  account_id          UUID REFERENCES accounts(id) ON DELETE SET NULL,
  user_id             UUID REFERENCES users(id) ON DELETE SET NULL,
  template_key        TEXT NOT NULL,
  locale              TEXT NOT NULL,
  to_email            CITEXT NOT NULL,
  subject             TEXT,
  provider_message_id TEXT,
  status              TEXT NOT NULL DEFAULT 'queued'
                        CHECK (status IN ('queued','sent','delivered','bounced','complained','failed')),
  error               TEXT,
  sent_at             TIMESTAMPTZ,
  created_at          TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_email_log_account ON email_log(account_id, created_at DESC);
CREATE INDEX idx_email_log_template ON email_log(template_key, created_at DESC);


CREATE TABLE jobs_dead_letter (
  id           UUID PRIMARY KEY DEFAULT uuid_generate_v7(),
  queue        TEXT NOT NULL,
  job_name     TEXT NOT NULL,
  payload      JSONB NOT NULL,
  attempts     INTEGER NOT NULL,
  last_error   TEXT,
  failed_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
  requeued_at  TIMESTAMPTZ,
  requeued_by  UUID REFERENCES users(id)
);
CREATE INDEX idx_dead_letter_queue ON jobs_dead_letter(queue, failed_at DESC)
  WHERE requeued_at IS NULL;


-- =============================================================================
-- 10. INTEGRITY GUARDS
-- =============================================================================

-- The denormalised account_id on child tables must match the parent's.
-- Makes a missing tenant filter a constraint violation instead of a data leak.
CREATE OR REPLACE FUNCTION assert_guide_account() RETURNS trigger AS $$
DECLARE parent_account UUID;
BEGIN
  SELECT account_id INTO parent_account FROM guides WHERE id = NEW.guide_id;
  IF parent_account IS DISTINCT FROM NEW.account_id THEN
    RAISE EXCEPTION 'account_id % does not match guide %''s account %',
      NEW.account_id, NEW.guide_id, parent_account;
  END IF;
  RETURN NEW;
END $$ LANGUAGE plpgsql;

CREATE TRIGGER trg_sections_account BEFORE INSERT OR UPDATE ON guide_sections
  FOR EACH ROW EXECUTE FUNCTION assert_guide_account();
CREATE TRIGGER trg_blocks_account BEFORE INSERT OR UPDATE ON content_blocks
  FOR EACH ROW EXECUTE FUNCTION assert_guide_account();
CREATE TRIGGER trg_recommendations_account BEFORE INSERT OR UPDATE ON recommendations
  FOR EACH ROW EXECUTE FUNCTION assert_guide_account();
CREATE TRIGGER trg_public_urls_account BEFORE INSERT OR UPDATE ON public_urls
  FOR EACH ROW EXECUTE FUNCTION assert_guide_account();
CREATE TRIGGER trg_qr_account BEFORE INSERT OR UPDATE ON qr_codes
  FOR EACH ROW EXECUTE FUNCTION assert_guide_account();

-- A package version that has been sold can never be edited.
CREATE OR REPLACE FUNCTION freeze_sold_package_versions() RETURNS trigger AS $$
BEGIN
  IF OLD.is_frozen AND (
       NEW.amount_cents     IS DISTINCT FROM OLD.amount_cents OR
       NEW.currency         IS DISTINCT FROM OLD.currency OR
       NEW.stripe_price_id  IS DISTINCT FROM OLD.stripe_price_id OR
       NEW.billing_interval IS DISTINCT FROM OLD.billing_interval) THEN
    RAISE EXCEPTION
      'Package version % is frozen (sold to customers). Create a new version instead.', OLD.id;
  END IF;
  RETURN NEW;
END $$ LANGUAGE plpgsql;

CREATE TRIGGER trg_freeze_package_versions BEFORE UPDATE ON package_versions
  FOR EACH ROW EXECUTE FUNCTION freeze_sold_package_versions();

CREATE OR REPLACE FUNCTION freeze_sold_package_features() RETURNS trigger AS $$
DECLARE frozen BOOLEAN;
BEGIN
  SELECT is_frozen INTO frozen FROM package_versions
   WHERE id = coalesce(NEW.package_version_id, OLD.package_version_id);
  IF frozen THEN
    RAISE EXCEPTION 'Features of a sold package version cannot change. Create a new version.';
  END IF;
  RETURN coalesce(NEW, OLD);
END $$ LANGUAGE plpgsql;

CREATE TRIGGER trg_freeze_package_features
  BEFORE INSERT OR UPDATE OR DELETE ON package_features
  FOR EACH ROW EXECUTE FUNCTION freeze_sold_package_features();


-- =============================================================================
-- 11. ROW LEVEL SECURITY (defence in depth behind application scoping)
-- =============================================================================

ALTER TABLE properties      ENABLE ROW LEVEL SECURITY;
ALTER TABLE guides          ENABLE ROW LEVEL SECURITY;
ALTER TABLE guide_sections  ENABLE ROW LEVEL SECURITY;
ALTER TABLE content_blocks  ENABLE ROW LEVEL SECURITY;
ALTER TABLE recommendations ENABLE ROW LEVEL SECURITY;
ALTER TABLE media           ENABLE ROW LEVEL SECURITY;
ALTER TABLE translations    ENABLE ROW LEVEL SECURITY;

CREATE POLICY tenant_isolation ON properties      USING (account_id = current_setting('app.account_id', true)::uuid);
CREATE POLICY tenant_isolation ON guides          USING (account_id = current_setting('app.account_id', true)::uuid);
CREATE POLICY tenant_isolation ON guide_sections  USING (account_id = current_setting('app.account_id', true)::uuid);
CREATE POLICY tenant_isolation ON content_blocks  USING (account_id = current_setting('app.account_id', true)::uuid);
CREATE POLICY tenant_isolation ON recommendations USING (account_id = current_setting('app.account_id', true)::uuid);
CREATE POLICY tenant_isolation ON media           USING (account_id = current_setting('app.account_id', true)::uuid);
CREATE POLICY tenant_isolation ON translations    USING (account_id = current_setting('app.account_id', true)::uuid);

-- The platform role bypasses RLS; the publisher role reads everything to build snapshots.
-- Both are distinct database roles from the application's request-scoped role.
