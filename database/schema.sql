-- Arco del Vento — schema del database.
--
-- Serve quando si passa dai contenuti su file (content/*.php) a MySQL.
-- Il prototipo gira benissimo senza: per cinque camere che cambiano i dati
-- due volte l'anno, un file di testo è più facile da mantenere di un
-- database. Lo schema c'è perché il giorno in cui servono disponibilità
-- reali, tariffe stagionali e uno storico delle prenotazioni, la strada sia
-- già tracciata e non si debba rifare il sito.
--
-- Come si attiva:
--   1. dall'hPanel di Hostinger: crea un database MySQL e un utente
--   2. importa questo file (phpMyAdmin → Importa, oppure dalla riga di comando)
--   3. compila DB_DSN, DB_USER, DB_PASSWORD nel file .env
--   4. App sceglie da sola PdoRoomRepository: nessuna vista cambia
--
-- MariaDB 10.4+ / MySQL 8+. InnoDB e utf8mb4 perché il sito è multilingue e
-- i nomi propri italiani vanno scritti giusti.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ---------------------------------------------------------------------------
-- Impostazioni del sito
-- Le stesse chiavi di content/settings.php, una riga per chiave. Il valore è
-- JSON così una chiave può contenere un oggetto (l'indirizzo) o un valore
-- semplice (l'anno di apertura) senza inventare una colonna per ciascuno.
-- `confirmed` è la colonna che tiene in piedi la regola di tutto il progetto:
-- un dato non confermato non si mostra come se lo fosse.
-- ---------------------------------------------------------------------------
CREATE TABLE site_settings (
  setting_key   VARCHAR(64)  NOT NULL,
  value_json    JSON             NULL,
  confirmed     TINYINT(1)   NOT NULL DEFAULT 0,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Pagine
-- Il sito ha pagine fisse, con gli indirizzi in src/I18n/Routes.php. Questa
-- tabella serve a chi vorrà modificarne i testi da un pannello invece che
-- nei file di lingua: `page_key` è la stessa chiave usata dalle rotte.
-- ---------------------------------------------------------------------------
CREATE TABLE pages (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  page_key    VARCHAR(48)  NOT NULL,
  published   TINYINT(1)   NOT NULL DEFAULT 1,
  position    SMALLINT     NOT NULL DEFAULT 0,
  updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pages_key (page_key)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Traduzioni
-- Tabella generica: entità, identificativo, lingua, campo, valore. Le camere
-- hanno la loro tabella dedicata (room_translations) perché i loro campi sono
-- pochi e sempre gli stessi, e una tabella tipizzata si legge in una query
-- invece di cinque. Questa serve per tutto il resto — pagine, blocchi di
-- testo, etichette editoriali — e per lo spagnolo, che si aggiunge come
-- righe nuove e non come colonne nuove.
-- ---------------------------------------------------------------------------
CREATE TABLE translations (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  entity      VARCHAR(32)  NOT NULL,
  entity_id   INT UNSIGNED NOT NULL,
  locale      VARCHAR(5)   NOT NULL,
  field       VARCHAR(48)  NOT NULL,
  value       TEXT             NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_translation (entity, entity_id, locale, field),
  KEY idx_translation_lookup (entity, entity_id, locale)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Camere
-- Rispecchia esattamente la forma che PdoRoomRepository si aspetta.
-- `view_san_rufino` è NULL finché nessuno ha confermato quali camere vedano
-- il campanile: è il dato che più conta per il racconto di questo sito, e
-- NULL è l'unico valore onesto finché non lo si sa.
-- ---------------------------------------------------------------------------
CREATE TABLE rooms (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ref                 VARCHAR(32)  NOT NULL,
  position            SMALLINT     NOT NULL DEFAULT 0,
  published           TINYINT(1)   NOT NULL DEFAULT 1,
  confirmed           TINYINT(1)   NOT NULL DEFAULT 0,
  name_confirmed      TINYINT(1)   NOT NULL DEFAULT 0,
  type_key            VARCHAR(32)  NOT NULL DEFAULT 'double',
  occupancy_standard  TINYINT UNSIGNED NOT NULL DEFAULT 2,
  occupancy_max       TINYINT UNSIGNED NOT NULL DEFAULT 2,
  beds_json           JSON             NULL,
  layouts_json        JSON             NULL,
  size_sqm            SMALLINT UNSIGNED NULL,
  floor               VARCHAR(32)      NULL,
  view_key            VARCHAR(32)      NULL,
  bathroom_json       JSON             NULL,
  price_confirmed     TINYINT(1)   NOT NULL DEFAULT 0,
  currency            CHAR(3)      NOT NULL DEFAULT 'EUR',
  base_rate           DECIMAL(8,2)     NULL,
  view_san_rufino     TINYINT(1)       NULL,
  created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rooms_ref (ref),
  KEY idx_rooms_published (published, position)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE room_translations (
  room_id     INT UNSIGNED NOT NULL,
  locale      VARCHAR(5)   NOT NULL,
  name        VARCHAR(120) NOT NULL,
  slug        VARCHAR(120) NOT NULL,
  description TEXT             NULL,
  PRIMARY KEY (room_id, locale),
  -- Lo slug è unico per lingua: /it/camere/camera-01 e /en/rooms/room-01
  -- possono coesistere, due camere con lo stesso slug nella stessa lingua no.
  UNIQUE KEY uq_room_slug (locale, slug),
  CONSTRAINT fk_room_tr_room FOREIGN KEY (room_id) REFERENCES rooms (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Servizi
-- La chiave è la stessa usata nei file di lingua sotto «amenities», così un
-- servizio si traduce senza passare dal database, e chi aggiunge un servizio
-- aggiunge una chiave in due file di testo.
-- ---------------------------------------------------------------------------
CREATE TABLE amenities (
  amenity_key VARCHAR(48) NOT NULL,
  position    SMALLINT    NOT NULL DEFAULT 0,
  icon        VARCHAR(48)     NULL,
  PRIMARY KEY (amenity_key)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE room_amenities (
  room_id     INT UNSIGNED NOT NULL,
  amenity_key VARCHAR(48)  NOT NULL,
  position    SMALLINT     NOT NULL DEFAULT 0,
  PRIMARY KEY (room_id, amenity_key),
  CONSTRAINT fk_ra_room    FOREIGN KEY (room_id)     REFERENCES rooms (id)          ON DELETE CASCADE,
  CONSTRAINT fk_ra_amenity FOREIGN KEY (amenity_key) REFERENCES amenities (amenity_key) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Fotografie delle camere
-- `role` è il posto che l'immagine occupa: card (4:3), list (3:2), hero (16:9),
-- gallery (1:1). Sono i rapporti del design system, e la ragione per cui la
-- fotografia vera può sostituire un segnaposto senza spostare un pixel.
-- ---------------------------------------------------------------------------
CREATE TABLE room_images (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  room_id   INT UNSIGNED NOT NULL,
  role      ENUM('card','list','hero','gallery') NOT NULL DEFAULT 'gallery',
  path      VARCHAR(255) NOT NULL,
  ratio     VARCHAR(12)  NOT NULL DEFAULT '4/3',
  alt       VARCHAR(255)     NULL,
  position  SMALLINT     NOT NULL DEFAULT 0,
  -- Ogni fotografia pubblicata deve avere una provenienza dichiarata: è la
  -- regola del design system, ed è anche ciò che evita di mettere online la
  -- camera di casa d'altri.
  credit    VARCHAR(160)     NULL,
  licence   VARCHAR(80)      NULL,
  PRIMARY KEY (id),
  KEY idx_room_images (room_id, role, position),
  CONSTRAINT fk_ri_room FOREIGN KEY (room_id) REFERENCES rooms (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Tariffe
-- `prices` tiene la tariffa base di una camera; `seasonal_rates` la sovrascrive
-- in un periodo. `priority` decide chi vince quando due periodi si accavallano:
-- senza quella colonna, due offerte sovrapposte danno due prezzi diversi per
-- la stessa notte, che è il modo più rapido di perdere la fiducia di un ospite.
-- ---------------------------------------------------------------------------
CREATE TABLE prices (
  room_id      INT UNSIGNED NOT NULL,
  nightly_rate DECIMAL(8,2) NOT NULL,
  currency     CHAR(3)      NOT NULL DEFAULT 'EUR',
  min_nights   TINYINT UNSIGNED NOT NULL DEFAULT 1,
  confirmed    TINYINT(1)   NOT NULL DEFAULT 0,
  updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (room_id),
  CONSTRAINT fk_prices_room FOREIGN KEY (room_id) REFERENCES rooms (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE seasonal_rates (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  room_id      INT UNSIGNED     NULL,   -- NULL = vale per tutte le camere
  label        VARCHAR(80)  NOT NULL,
  starts_on    DATE         NOT NULL,
  ends_on      DATE         NOT NULL,
  nightly_rate DECIMAL(8,2) NOT NULL,
  min_nights   TINYINT UNSIGNED NOT NULL DEFAULT 1,
  priority     SMALLINT     NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_seasonal_period (starts_on, ends_on),
  KEY idx_seasonal_room (room_id, starts_on),
  CONSTRAINT fk_seasonal_room FOREIGN KEY (room_id) REFERENCES rooms (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Disponibilità
-- Una riga per camera per notte. Con cinque camere sono 1.825 righe l'anno:
-- niente, e in cambio una query per data è immediata e una notte chiusa a
-- mano resta chiusa. `booking_id` dice perché una notte è occupata, così si
-- distingue un blocco manutenzione da una prenotazione vera.
-- ---------------------------------------------------------------------------
CREATE TABLE availability (
  room_id    INT UNSIGNED NOT NULL,
  night      DATE         NOT NULL,
  status     ENUM('free','booked','blocked','hold') NOT NULL DEFAULT 'free',
  booking_id INT UNSIGNED     NULL,
  note       VARCHAR(160)     NULL,
  PRIMARY KEY (room_id, night),
  KEY idx_availability_night (night, status),
  CONSTRAINT fk_av_room FOREIGN KEY (room_id) REFERENCES rooms (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Ospiti e prenotazioni
-- ---------------------------------------------------------------------------
CREATE TABLE guests (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  first_name VARCHAR(80)  NOT NULL,
  last_name  VARCHAR(80)  NOT NULL,
  email      VARCHAR(180) NOT NULL,
  phone      VARCHAR(40)      NULL,
  country    VARCHAR(80)      NULL,
  locale     VARCHAR(5)   NOT NULL DEFAULT 'it',
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_guests_email (email)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE bookings (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference    VARCHAR(24)  NOT NULL,
  room_id      INT UNSIGNED NOT NULL,
  guest_id     INT UNSIGNED NOT NULL,
  arrival      DATE         NOT NULL,
  departure    DATE         NOT NULL,
  nights       SMALLINT UNSIGNED NOT NULL,
  guests       TINYINT UNSIGNED  NOT NULL DEFAULT 1,
  nightly_rate DECIMAL(8,2)     NULL,
  total        DECIMAL(10,2)    NULL,
  currency     CHAR(3)      NOT NULL DEFAULT 'EUR',
  -- «requested» è una richiesta, non una prenotazione: la distinzione è
  -- scritta anche nella pagina di conferma, ed è la sola cosa che l'ospite
  -- deve capire di quella schermata.
  status       ENUM('requested','confirmed','cancelled','declined','stayed') NOT NULL DEFAULT 'requested',
  source       VARCHAR(32)  NOT NULL DEFAULT 'site',
  notes        TEXT             NULL,
  locale       VARCHAR(5)   NOT NULL DEFAULT 'it',
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  answered_at  TIMESTAMP        NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bookings_reference (reference),
  KEY idx_bookings_period (arrival, departure),
  KEY idx_bookings_status (status, created_at),
  CONSTRAINT fk_bookings_room  FOREIGN KEY (room_id)  REFERENCES rooms (id),
  CONSTRAINT fk_bookings_guest FOREIGN KEY (guest_id) REFERENCES guests (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

ALTER TABLE availability
  ADD CONSTRAINT fk_av_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE SET NULL;

-- ---------------------------------------------------------------------------
-- Recensioni
-- La tabella c'è, il componente c'è, e la sezione del sito non compare finché
-- non ci sono righe. `source` e `source_url` esistono perché una recensione si
-- riporta come è stata scritta e si dice dove è stata lasciata: riscriverla
-- per farla suonare meglio smette di essere una recensione, e inventarla è
-- fuori discussione.
-- ---------------------------------------------------------------------------
CREATE TABLE reviews (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  author       VARCHAR(120) NOT NULL,
  rating       TINYINT UNSIGNED NULL,
  body         TEXT         NOT NULL,
  locale       VARCHAR(5)   NOT NULL DEFAULT 'it',
  source       VARCHAR(48)      NULL,
  source_url   VARCHAR(255)     NULL,
  stayed_on    DATE             NULL,
  published    TINYINT(1)   NOT NULL DEFAULT 0,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_reviews_published (published, stayed_on)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Messaggi dal modulo contatti
-- `ip_hash` e non l'indirizzo IP: per accorgersi di un abuso basta sapere che
-- due messaggi vengono dallo stesso posto, non da quale.
-- ---------------------------------------------------------------------------
CREATE TABLE contact_requests (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(120) NOT NULL,
  email       VARCHAR(180) NOT NULL,
  phone       VARCHAR(40)      NULL,
  subject_key VARCHAR(32)  NOT NULL DEFAULT 'info',
  message     TEXT         NOT NULL,
  locale      VARCHAR(5)   NOT NULL DEFAULT 'it',
  ip_hash     CHAR(64)         NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  handled_at  TIMESTAMP        NULL,
  PRIMARY KEY (id),
  KEY idx_contact_created (created_at),
  KEY idx_contact_handled (handled_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
