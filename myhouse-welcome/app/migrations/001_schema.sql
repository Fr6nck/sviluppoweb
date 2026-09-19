-- MyHouse Welcome — schema. Sintassi comune a SQLite e MySQL.

CREATE TABLE users (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  email         VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name          VARCHAR(120) NOT NULL DEFAULT '',
  role          VARCHAR(20)  NOT NULL DEFAULT 'host',
  created_at    VARCHAR(25)  NOT NULL
);

CREATE TABLE accounts (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  name       VARCHAR(160) NOT NULL DEFAULT '',
  created_at VARCHAR(25) NOT NULL
);
CREATE INDEX idx_accounts_user ON accounts(user_id);

CREATE TABLE features (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  code          VARCHAR(60) NOT NULL UNIQUE,
  label         VARCHAR(160) NOT NULL,
  kind          VARCHAR(10) NOT NULL DEFAULT 'bool',   -- bool | int
  default_value VARCHAR(40) NOT NULL DEFAULT '0'
);

CREATE TABLE packages (
  id     INTEGER PRIMARY KEY AUTOINCREMENT,
  code   VARCHAR(40) NOT NULL UNIQUE,
  name   VARCHAR(80) NOT NULL,
  tagline VARCHAR(200) NOT NULL DEFAULT '',
  sort   INTEGER NOT NULL DEFAULT 0,
  active INTEGER NOT NULL DEFAULT 1
);

-- Una versione venduta non si tocca mai piu': chi ha comprato resta su quella.
CREATE TABLE package_versions (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  package_id  INTEGER NOT NULL REFERENCES packages(id) ON DELETE CASCADE,
  version     INTEGER NOT NULL,
  price_cents INTEGER NOT NULL DEFAULT 0,
  currency    VARCHAR(3) NOT NULL DEFAULT 'EUR',
  interval_unit VARCHAR(10) NOT NULL DEFAULT 'year',
  is_current  INTEGER NOT NULL DEFAULT 1,
  sold_count  INTEGER NOT NULL DEFAULT 0,
  created_at  VARCHAR(25) NOT NULL
);
CREATE UNIQUE INDEX idx_pv_unique ON package_versions(package_id, version);

CREATE TABLE package_features (
  package_version_id INTEGER NOT NULL REFERENCES package_versions(id) ON DELETE CASCADE,
  feature_id         INTEGER NOT NULL REFERENCES features(id) ON DELETE CASCADE,
  value              VARCHAR(40) NOT NULL DEFAULT '0',
  PRIMARY KEY (package_version_id, feature_id)
);

CREATE TABLE subscriptions (
  id                 INTEGER PRIMARY KEY AUTOINCREMENT,
  account_id         INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  package_version_id INTEGER NOT NULL REFERENCES package_versions(id),
  status             VARCHAR(20) NOT NULL DEFAULT 'active',  -- active|past_due|canceled
  provider           VARCHAR(20) NOT NULL DEFAULT 'manual',
  provider_customer_id     VARCHAR(80) NOT NULL DEFAULT '',
  provider_subscription_id VARCHAR(80) NOT NULL DEFAULT '',
  current_period_end VARCHAR(25) NOT NULL DEFAULT '',
  created_at         VARCHAR(25) NOT NULL
);
CREATE INDEX idx_subs_account ON subscriptions(account_id);

CREATE TABLE entitlement_overrides (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  account_id INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  feature_id INTEGER NOT NULL REFERENCES features(id) ON DELETE CASCADE,
  value      VARCHAR(40) NOT NULL,
  note       VARCHAR(255) NOT NULL DEFAULT ''
);
CREATE UNIQUE INDEX idx_override_unique ON entitlement_overrides(account_id, feature_id);

CREATE TABLE properties (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  account_id     INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  name           VARCHAR(160) NOT NULL,
  slug           VARCHAR(160) NOT NULL UNIQUE,
  city           VARCHAR(120) NOT NULL DEFAULT '',
  region         VARCHAR(120) NOT NULL DEFAULT '',
  checkin_from   VARCHAR(10) NOT NULL DEFAULT '15:00',
  checkout_by    VARCHAR(10) NOT NULL DEFAULT '10:30',
  host_name      VARCHAR(120) NOT NULL DEFAULT '',
  host_phone     VARCHAR(40) NOT NULL DEFAULT '',
  host_whatsapp  VARCHAR(40) NOT NULL DEFAULT '',
  cover_media_id INTEGER,
  default_locale VARCHAR(5) NOT NULL DEFAULT 'it',
  status         VARCHAR(20) NOT NULL DEFAULT 'draft',   -- draft|published
  created_at     VARCHAR(25) NOT NULL
);
CREATE INDEX idx_props_account ON properties(account_id);

CREATE TABLE property_locales (
  property_id INTEGER NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
  locale      VARCHAR(5) NOT NULL,
  PRIMARY KEY (property_id, locale)
);

CREATE TABLE sections (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  property_id INTEGER NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
  kind        VARCHAR(30) NOT NULL DEFAULT 'text',   -- text|wifi|places|checkin
  icon        VARCHAR(30) NOT NULL DEFAULT 'home',
  color       VARCHAR(10) NOT NULL DEFAULT 'terracotta',
  position    INTEGER NOT NULL DEFAULT 0,
  wifi_ssid   VARCHAR(120) NOT NULL DEFAULT '',
  wifi_pass   VARCHAR(120) NOT NULL DEFAULT '',
  door_code   VARCHAR(40) NOT NULL DEFAULT '',
  media_id    INTEGER,
  created_at  VARCHAR(25) NOT NULL
);
CREATE INDEX idx_sections_prop ON sections(property_id, position);

-- stato: missing -> machine -> reviewed. Una revisione umana non viene mai sovrascritta.
CREATE TABLE section_translations (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  section_id INTEGER NOT NULL REFERENCES sections(id) ON DELETE CASCADE,
  locale     VARCHAR(5) NOT NULL,
  title      VARCHAR(200) NOT NULL DEFAULT '',
  body       TEXT NOT NULL DEFAULT '',
  state      VARCHAR(20) NOT NULL DEFAULT 'missing',
  updated_at VARCHAR(25) NOT NULL
);
CREATE UNIQUE INDEX idx_tr_unique ON section_translations(section_id, locale);

CREATE TABLE places (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  section_id INTEGER NOT NULL REFERENCES sections(id) ON DELETE CASCADE,
  name       VARCHAR(160) NOT NULL,
  category   VARCHAR(80) NOT NULL DEFAULT '',
  distance   VARCHAR(40) NOT NULL DEFAULT '',
  note       VARCHAR(255) NOT NULL DEFAULT '',
  badge      VARCHAR(80) NOT NULL DEFAULT '',
  badge_tone VARCHAR(20) NOT NULL DEFAULT 'pine',
  media_id   INTEGER,
  position   INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX idx_places_section ON places(section_id, position);

CREATE TABLE media (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  account_id INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  filename   VARCHAR(190) NOT NULL,
  mime       VARCHAR(80) NOT NULL,
  bytes      INTEGER NOT NULL DEFAULT 0,
  width      INTEGER NOT NULL DEFAULT 0,
  height     INTEGER NOT NULL DEFAULT 0,
  alt        VARCHAR(255) NOT NULL DEFAULT '',
  created_at VARCHAR(25) NOT NULL
);

-- Il QR punta qui e non cambia mai, anche se cambia lo slug.
CREATE TABLE qr_tokens (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  property_id INTEGER NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
  token       VARCHAR(24) NOT NULL UNIQUE,
  scans       INTEGER NOT NULL DEFAULT 0,
  created_at  VARCHAR(25) NOT NULL
);

CREATE TABLE guide_versions (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  property_id  INTEGER NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
  version      INTEGER NOT NULL,
  snapshot     TEXT NOT NULL,
  published_at VARCHAR(25) NOT NULL
);
CREATE UNIQUE INDEX idx_gv_unique ON guide_versions(property_id, version);

CREATE TABLE orders (
  id                 INTEGER PRIMARY KEY AUTOINCREMENT,
  account_id         INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  package_version_id INTEGER NOT NULL REFERENCES package_versions(id),
  amount_cents       INTEGER NOT NULL,
  currency           VARCHAR(3) NOT NULL DEFAULT 'EUR',
  status             VARCHAR(20) NOT NULL DEFAULT 'pending',  -- pending|paid|failed
  provider           VARCHAR(20) NOT NULL DEFAULT 'stripe',
  provider_session_id VARCHAR(120) NOT NULL DEFAULT '',
  created_at         VARCHAR(25) NOT NULL
);
CREATE INDEX idx_orders_account ON orders(account_id);

-- L'unicita' qui e' quello che rende il webhook sicuro da riconsegnare.
CREATE TABLE webhook_events (
  id                INTEGER PRIMARY KEY AUTOINCREMENT,
  provider          VARCHAR(20) NOT NULL,
  provider_event_id VARCHAR(120) NOT NULL,
  kind              VARCHAR(80) NOT NULL DEFAULT '',
  payload           TEXT NOT NULL,
  processed_at      VARCHAR(25) NOT NULL
);
CREATE UNIQUE INDEX idx_wh_unique ON webhook_events(provider, provider_event_id);

CREATE TABLE audit_log (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  actor_user_id INTEGER,
  target_user_id INTEGER,
  action        VARCHAR(60) NOT NULL,
  meta          TEXT NOT NULL DEFAULT '',
  created_at    VARCHAR(25) NOT NULL
);

CREATE TABLE password_resets (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  token_hash VARCHAR(64) NOT NULL,
  expires_at VARCHAR(25) NOT NULL,
  used_at    VARCHAR(25)
);

CREATE TABLE analytics_events (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  property_id INTEGER NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
  section_id  INTEGER,
  locale      VARCHAR(5) NOT NULL DEFAULT '',
  kind        VARCHAR(30) NOT NULL,   -- open|section|qr
  day         VARCHAR(10) NOT NULL,
  created_at  VARCHAR(25) NOT NULL
);
CREATE INDEX idx_an_prop ON analytics_events(property_id, day);
