-- MyHouse Welcome — 002: quello che serve all'MVP vendibile.
-- Solo aggiunte: nessuna tabella ricreata, nessuna colonna tolta, nessun dato perso.
-- Sintassi comune a SQLite e MySQL (TEXT senza valore predefinito per MySQL).

-- Chi si registra accetta termini e privacy in una versione precisa, e verifica l'email.
ALTER TABLE users ADD COLUMN email_verified_at VARCHAR(25);
ALTER TABLE users ADD COLUMN terms_version VARCHAR(20) NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN terms_accepted_at VARCHAR(25);
ALTER TABLE users ADD COLUMN privacy_version VARCHAR(20) NOT NULL DEFAULT '';
ALTER TABLE users ADD COLUMN privacy_accepted_at VARCHAR(25);

-- Il piano scelto prima di pagare governa la configurazione; il cliente Stripe resta legato all'account.
ALTER TABLE accounts ADD COLUMN intended_package_version_id INTEGER;
ALTER TABLE accounts ADD COLUMN stripe_customer_id VARCHAR(80) NOT NULL DEFAULT '';

-- Il testo commerciale di ogni piano sta nel database, non nel codice: lo cambia l'admin.
ALTER TABLE packages ADD COLUMN headline VARCHAR(200) NOT NULL DEFAULT '';
ALTER TABLE packages ADD COLUMN description VARCHAR(1000) NOT NULL DEFAULT '';
ALTER TABLE packages ADD COLUMN bullets VARCHAR(2000) NOT NULL DEFAULT '';
ALTER TABLE packages ADD COLUMN badge VARCHAR(60) NOT NULL DEFAULT '';
ALTER TABLE packages ADD COLUMN cta_label VARCHAR(80) NOT NULL DEFAULT '';
ALTER TABLE packages ADD COLUMN family VARCHAR(40) NOT NULL DEFAULT '';
ALTER TABLE packages ADD COLUMN public INTEGER NOT NULL DEFAULT 1;

-- Il prezzo ricorrente su Stripe appartiene alla versione: una versione venduta non cambia.
ALTER TABLE package_versions ADD COLUMN stripe_price_id VARCHAR(120) NOT NULL DEFAULT '';

-- L'abbonamento come lo racconta Stripe.
ALTER TABLE subscriptions ADD COLUMN provider_price_id VARCHAR(120) NOT NULL DEFAULT '';
ALTER TABLE subscriptions ADD COLUMN current_period_start VARCHAR(25) NOT NULL DEFAULT '';
ALTER TABLE subscriptions ADD COLUMN cancel_at_period_end INTEGER NOT NULL DEFAULT 0;
ALTER TABLE subscriptions ADD COLUMN payment_status VARCHAR(20) NOT NULL DEFAULT '';
ALTER TABLE subscriptions ADD COLUMN updated_at VARCHAR(25) NOT NULL DEFAULT '';
CREATE INDEX idx_subs_provider ON subscriptions(provider_subscription_id);

-- Un ordine sa quale guida pubblicare quando arriva il pagamento.
ALTER TABLE orders ADD COLUMN property_id INTEGER;
ALTER TABLE orders ADD COLUMN updated_at VARCHAR(25) NOT NULL DEFAULT '';

-- L'aspetto della guida, i media dell'intestazione, la demo, la procedura guidata.
ALTER TABLE properties ADD COLUMN palette VARCHAR(30) NOT NULL DEFAULT 'terracotta';
ALTER TABLE properties ADD COLUMN text_tone VARCHAR(10) NOT NULL DEFAULT 'scuro';
ALTER TABLE properties ADD COLUMN logo_media_id INTEGER;
ALTER TABLE properties ADD COLUMN profile_media_id INTEGER;
ALTER TABLE properties ADD COLUMN is_demo INTEGER NOT NULL DEFAULT 0;
ALTER TABLE properties ADD COLUMN wizard_step VARCHAR(20) NOT NULL DEFAULT '';
ALTER TABLE properties ADD COLUMN published_at VARCHAR(25) NOT NULL DEFAULT '';

-- Le sezioni: una è il nucleo (check-in e check-out), le altre si attivano e si spengono.
-- I campi strutturati stanno in JSON: quelli uguali in ogni lingua qui, quelli da tradurre
-- in section_translations.data.
ALTER TABLE sections ADD COLUMN is_core INTEGER NOT NULL DEFAULT 0;
ALTER TABLE sections ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1;
ALTER TABLE sections ADD COLUMN data TEXT;
ALTER TABLE sections ADD COLUMN pdf_media_id INTEGER;
ALTER TABLE section_translations ADD COLUMN data TEXT;

-- I luoghi consigliati diventano schede vere.
ALTER TABLE places ADD COLUMN address VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE places ADD COLUMN maps_url VARCHAR(500) NOT NULL DEFAULT '';
ALTER TABLE places ADD COLUMN phone VARCHAR(40) NOT NULL DEFAULT '';
ALTER TABLE places ADD COLUMN website VARCHAR(500) NOT NULL DEFAULT '';
ALTER TABLE places ADD COLUMN booking_url VARCHAR(500) NOT NULL DEFAULT '';
ALTER TABLE places ADD COLUMN walk_minutes INTEGER NOT NULL DEFAULT 0;
ALTER TABLE places ADD COLUMN drive_minutes INTEGER NOT NULL DEFAULT 0;

-- Quello che di un luogo si traduce.
CREATE TABLE place_translations (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  place_id    INTEGER NOT NULL REFERENCES places(id) ON DELETE CASCADE,
  locale      VARCHAR(5) NOT NULL,
  category    VARCHAR(80) NOT NULL DEFAULT '',
  description VARCHAR(600) NOT NULL DEFAULT '',
  note        VARCHAR(400) NOT NULL DEFAULT '',
  badge       VARCHAR(80) NOT NULL DEFAULT ''
);
CREATE UNIQUE INDEX idx_pt_unique ON place_translations(place_id, locale);

-- I media: immagini o PDF, su disco o su S3, legati a una struttura.
ALTER TABLE media ADD COLUMN kind VARCHAR(10) NOT NULL DEFAULT 'image';
ALTER TABLE media ADD COLUMN storage VARCHAR(10) NOT NULL DEFAULT 'local';
ALTER TABLE media ADD COLUMN object_key VARCHAR(300) NOT NULL DEFAULT '';
ALTER TABLE media ADD COLUMN property_id INTEGER;
ALTER TABLE media ADD COLUMN original_name VARCHAR(190) NOT NULL DEFAULT '';

-- Da dove arriva una lettura (qr, link), quando si sa. Nessun dato personale.
ALTER TABLE analytics_events ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT '';

-- Verifica dell'email e recupero della password: si conserva solo l'impronta del token.
CREATE TABLE email_tokens (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  purpose    VARCHAR(10) NOT NULL,
  token_hash VARCHAR(64) NOT NULL,
  expires_at VARCHAR(25) NOT NULL,
  used_at    VARCHAR(25),
  created_at VARCHAR(25) NOT NULL
);
CREATE UNIQUE INDEX idx_et_hash ON email_tokens(token_hash);

-- Limiti di frequenza su accesso, registrazione e recupero password.
CREATE TABLE rate_limits (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  bucket     VARCHAR(120) NOT NULL,
  created_at INTEGER NOT NULL
);
CREATE INDEX idx_rl_bucket ON rate_limits(bucket, created_at);
