-- Arco del Vento — dati iniziali.
--
-- GENERATO da tools/export-seed.php a partire da content/*.php.
-- Non modificare a mano: si rigenera, e le modifiche andrebbero perse.
--
-- Si importa DOPO schema.sql. I valori sono gli stessi che il sito
-- mostra oggi leggendo i file, marcatori compresi: `confirmed = 0`
-- vuol dire che il dato è di prova e il sito lo segnala come tale.
--
-- Generato il 2026-09-23.

SET NAMES utf8mb4;
START TRANSACTION;

-- Impostazioni del sito. `confirmed` distingue quello che il cliente
-- ha confermato da quello che il sito non deve ancora dichiarare.
INSERT INTO site_settings (setting_key, value_json, confirmed) VALUES ('name', '["Arco del Vento"]', 1);
INSERT INTO site_settings (setting_key, value_json, confirmed) VALUES ('legal_name', '["Arco del Vento di Pecetta Daniele"]', 1);
INSERT INTO site_settings (setting_key, value_json, confirmed) VALUES ('type', '["affittacamere"]', 1);
INSERT INTO site_settings (setting_key, value_json, confirmed) VALUES ('owner', '["Daniele Pecetta"]', 1);
INSERT INTO site_settings (setting_key, value_json, confirmed) VALUES ('established', '[2002]', 1);
INSERT INTO site_settings (setting_key, value_json, confirmed) VALUES ('rooms_count', '[5]', 1);
INSERT INTO site_settings (setting_key, value_json, confirmed) VALUES ('address', '{"street":"Via Santa Maria delle Rose 1/A","city":"Assisi","province":"PG","region":"Umbria","country":"IT","postal_code":null}', 1);
INSERT INTO site_settings (setting_key, value_json, confirmed) VALUES ('contacts', '{"phone":null,"mobile":null,"whatsapp":null,"email":null}', 0);
INSERT INTO site_settings (setting_key, value_json, confirmed) VALUES ('stay', '{"check_in_from":null,"check_in_to":null,"check_out_by":null,"min_nights":null,"city_tax":null,"breakfast":null,"parking":null,"lift":null,"stairs":null,"wifi":null,"pets":null,"smoking":null,"languages":null}', 0);
INSERT INTO site_settings (setting_key, value_json, confirmed) VALUES ('legal', '{"cin":null,"cir":null,"vat":null,"rea":null}', 0);
INSERT INTO site_settings (setting_key, value_json, confirmed) VALUES ('geo', '{"latitude":null,"longitude":null}', 0);
INSERT INTO site_settings (setting_key, value_json, confirmed) VALUES ('social', '{"instagram":null,"facebook":null}', 0);

-- I servizi. La chiave è la stessa dei file di lingua, quindi la
-- traduzione resta in content/lang/ e non entra nel database.
INSERT INTO amenities (amenity_key, position) VALUES ('private-bathroom', 10);
INSERT INTO amenities (amenity_key, position) VALUES ('wifi', 20);
INSERT INTO amenities (amenity_key, position) VALUES ('heating', 30);
INSERT INTO amenities (amenity_key, position) VALUES ('linen', 40);
INSERT INTO amenities (amenity_key, position) VALUES ('towels', 50);
INSERT INTO amenities (amenity_key, position) VALUES ('wardrobe', 60);
INSERT INTO amenities (amenity_key, position) VALUES ('desk', 70);

-- Le cinque camere.
INSERT INTO rooms (id, ref, position, published, confirmed, name_confirmed, type_key,
                   occupancy_standard, occupancy_max, beds_json, layouts_json,
                   size_sqm, floor, view_key, bathroom_json,
                   price_confirmed, currency, view_san_rufino)
  VALUES (1, 'camera-01', 1, 1, 1, 0, 'triple', 3, 3, '{"double":1,"single":1}', '["triple","double"]', NULL, NULL, NULL, '{"private":true,"shower":true,"bathtub":false}', 1, 'EUR', 1);
INSERT INTO room_translations (room_id, locale, name, slug) VALUES (1, 'it', 'Camera 01', 'camera-01');
INSERT INTO room_translations (room_id, locale, name, slug) VALUES (1, 'en', 'Room 01', 'room-01');
INSERT INTO room_translations (room_id, locale, name, slug) VALUES (1, 'es', 'Habitación 01', 'habitacion-01');
INSERT INTO room_amenities (room_id, amenity_key, position) VALUES (1, 'private-bathroom', 10);
INSERT INTO room_amenities (room_id, amenity_key, position) VALUES (1, 'wifi', 20);
INSERT INTO room_amenities (room_id, amenity_key, position) VALUES (1, 'heating', 30);
INSERT INTO room_amenities (room_id, amenity_key, position) VALUES (1, 'linen', 40);
INSERT INTO room_amenities (room_id, amenity_key, position) VALUES (1, 'towels', 50);
INSERT INTO room_amenities (room_id, amenity_key, position) VALUES (1, 'wardrobe', 60);
INSERT INTO room_images (room_id, role, path, ratio, alt, position, credit)
  VALUES (1, 'card', 'img/demo/camera-01-4x3.svg', '4/3', NULL, 0, 'segnaposto disegnato — sostituire con la fotografia vera');
INSERT INTO room_images (room_id, role, path, ratio, alt, position, credit)
  VALUES (1, 'list', 'img/demo/camera-01-3x2.svg', '3/2', NULL, 0, 'segnaposto disegnato — sostituire con la fotografia vera');
INSERT INTO room_images (room_id, role, path, ratio, alt, position, credit)
  VALUES (1, 'hero', 'img/demo/camera-01-16x9.svg', '16/9', NULL, 0, 'segnaposto disegnato — sostituire con la fotografia vera');
INSERT INTO room_images (room_id, role, path, ratio, alt, position, credit)
  VALUES (1, 'gallery', 'img/demo/camera-01-1x1-a.svg', '1/1', NULL, 10, 'segnaposto disegnato — sostituire con la fotografia vera');
INSERT INTO room_images (room_id, role, path, ratio, alt, position, credit)
  VALUES (1, 'gallery', 'img/demo/camera-01-1x1-b.svg', '1/1', NULL, 20, 'segnaposto disegnato — sostituire con la fotografia vera');
INSERT INTO room_rates (room_id, guests, nightly_rate, currency, confirmed) VALUES (1, 1, 100, 'EUR', 1);
INSERT INTO room_rates (room_id, guests, nightly_rate, currency, confirmed) VALUES (1, 2, 110, 'EUR', 1);
INSERT INTO room_rates (room_id, guests, nightly_rate, currency, confirmed) VALUES (1, 3, 120, 'EUR', 1);

INSERT INTO rooms (id, ref, position, published, confirmed, name_confirmed, type_key,
                   occupancy_standard, occupancy_max, beds_json, layouts_json,
                   size_sqm, floor, view_key, bathroom_json,
                   price_confirmed, currency, view_san_rufino)
  VALUES (2, 'camera-02', 2, 1, 1, 0, 'double-twin', 2, 2, '{"single":2}', '["double","twin"]', NULL, NULL, NULL, '{"private":true,"shower":true,"bathtub":false}', 1, 'EUR', 1);
INSERT INTO room_translations (room_id, locale, name, slug) VALUES (2, 'it', 'Camera 02', 'camera-02');
INSERT INTO room_translations (room_id, locale, name, slug) VALUES (2, 'en', 'Room 02', 'room-02');
INSERT INTO room_translations (room_id, locale, name, slug) VALUES (2, 'es', 'Habitación 02', 'habitacion-02');
INSERT INTO room_amenities (room_id, amenity_key, position) VALUES (2, 'private-bathroom', 10);
INSERT INTO room_amenities (room_id, amenity_key, position) VALUES (2, 'wifi', 20);
INSERT INTO room_amenities (room_id, amenity_key, position) VALUES (2, 'heating', 30);
INSERT INTO room_amenities (room_id, amenity_key, position) VALUES (2, 'linen', 40);
INSERT INTO room_amenities (room_id, amenity_key, position) VALUES (2, 'towels', 50);
INSERT INTO room_images (room_id, role, path, ratio, alt, position, credit)
  VALUES (2, 'card', 'img/camere/camera-02-4x3', '4/3', NULL, 0, 'segnaposto disegnato — sostituire con la fotografia vera');
INSERT INTO room_images (room_id, role, path, ratio, alt, position, credit)
  VALUES (2, 'list', 'img/camere/camera-02-3x2', '3/2', NULL, 0, 'segnaposto disegnato — sostituire con la fotografia vera');
INSERT INTO room_images (room_id, role, path, ratio, alt, position, credit)
  VALUES (2, 'hero', 'img/camere/camera-02-16x9', '16/9', NULL, 0, 'segnaposto disegnato — sostituire con la fotografia vera');
INSERT INTO room_images (room_id, role, path, ratio, alt, position, credit)
  VALUES (2, 'gallery', 'img/camere/camera-02-1x1', '1/1', NULL, 10, 'segnaposto disegnato — sostituire con la fotografia vera');
INSERT INTO room_rates (room_id, guests, nightly_rate, currency, confirmed) VALUES (2, 1, 80, 'EUR', 1);
INSERT INTO room_rates (room_id, guests, nightly_rate, currency, confirmed) VALUES (2, 2, 90, 'EUR', 1);

INSERT INTO rooms (id, ref, position, published, confirmed, name_confirmed, type_key,
                   occupancy_standard, occupancy_max, beds_json, layouts_json,
                   size_sqm, floor, view_key, bathroom_json,
                   price_confirmed, currency, view_san_rufino)
  VALUES (3, 'camera-03', 3, 1, 1, 0, 'double', 2, 2, '{"double":1}', '["double"]', NULL, NULL, NULL, '{"private":true,"shower":true,"bathtub":false}', 1, 'EUR', 1);
INSERT INTO room_translations (room_id, locale, name, slug) VALUES (3, 'it', 'Camera 03', 'camera-03');
INSERT INTO room_translations (room_id, locale, name, slug) VALUES (3, 'en', 'Room 03', 'room-03');
INSERT INTO room_translations (room_id, locale, name, slug) VALUES (3, 'es', 'Habitación 03', 'habitacion-03');
INSERT INTO room_amenities (room_id, amenity_key, position) VALUES (3, 'private-bathroom', 10);
INSERT INTO room_amenities (room_id, amenity_key, position) VALUES (3, 'wifi', 20);
INSERT INTO room_amenities (room_id, amenity_key, position) VALUES (3, 'heating', 30);
INSERT INTO room_amenities (room_id, amenity_key, position) VALUES (3, 'linen', 40);
INSERT INTO room_amenities (room_id, amenity_key, position) VALUES (3, 'towels', 50);
INSERT INTO room_images (room_id, role, path, ratio, alt, position, credit)
  VALUES (3, 'card', 'img/camere/camera-01-4x3', '4/3', NULL, 0, 'segnaposto disegnato — sostituire con la fotografia vera');
INSERT INTO room_images (room_id, role, path, ratio, alt, position, credit)
  VALUES (3, 'list', 'img/camere/camera-01-3x2', '3/2', NULL, 0, 'segnaposto disegnato — sostituire con la fotografia vera');
INSERT INTO room_images (room_id, role, path, ratio, alt, position, credit)
  VALUES (3, 'hero', 'img/camere/camera-01-16x9', '16/9', NULL, 0, 'segnaposto disegnato — sostituire con la fotografia vera');
INSERT INTO room_images (room_id, role, path, ratio, alt, position, credit)
  VALUES (3, 'gallery', 'img/camere/camera-01-1x1', '1/1', NULL, 10, 'segnaposto disegnato — sostituire con la fotografia vera');
INSERT INTO room_rates (room_id, guests, nightly_rate, currency, confirmed) VALUES (3, 1, 70, 'EUR', 1);
INSERT INTO room_rates (room_id, guests, nightly_rate, currency, confirmed) VALUES (3, 2, 80, 'EUR', 1);

INSERT INTO rooms (id, ref, position, published, confirmed, name_confirmed, type_key,
                   occupancy_standard, occupancy_max, beds_json, layouts_json,
                   size_sqm, floor, view_key, bathroom_json,
                   price_confirmed, currency, view_san_rufino)
  VALUES (4, 'camera-04', 4, 1, 1, 0, 'double', 2, 2, '{"double":1}', '["double"]', NULL, NULL, NULL, '{"private":true,"shower":true,"bathtub":false}', 1, 'EUR', 1);
INSERT INTO room_translations (room_id, locale, name, slug) VALUES (4, 'it', 'Camera 04', 'camera-04');
INSERT INTO room_translations (room_id, locale, name, slug) VALUES (4, 'en', 'Room 04', 'room-04');
INSERT INTO room_translations (room_id, locale, name, slug) VALUES (4, 'es', 'Habitación 04', 'habitacion-04');
INSERT INTO room_amenities (room_id, amenity_key, position) VALUES (4, 'private-bathroom', 10);
INSERT INTO room_amenities (room_id, amenity_key, position) VALUES (4, 'wifi', 20);
INSERT INTO room_amenities (room_id, amenity_key, position) VALUES (4, 'heating', 30);
INSERT INTO room_amenities (room_id, amenity_key, position) VALUES (4, 'linen', 40);
INSERT INTO room_amenities (room_id, amenity_key, position) VALUES (4, 'towels', 50);
INSERT INTO room_amenities (room_id, amenity_key, position) VALUES (4, 'wardrobe', 60);
INSERT INTO room_images (room_id, role, path, ratio, alt, position, credit)
  VALUES (4, 'card', 'img/camere/camera-03-4x3', '4/3', NULL, 0, 'segnaposto disegnato — sostituire con la fotografia vera');
INSERT INTO room_images (room_id, role, path, ratio, alt, position, credit)
  VALUES (4, 'list', 'img/camere/camera-03-3x2', '3/2', NULL, 0, 'segnaposto disegnato — sostituire con la fotografia vera');
INSERT INTO room_images (room_id, role, path, ratio, alt, position, credit)
  VALUES (4, 'hero', 'img/camere/camera-03-16x9', '16/9', NULL, 0, 'segnaposto disegnato — sostituire con la fotografia vera');
INSERT INTO room_images (room_id, role, path, ratio, alt, position, credit)
  VALUES (4, 'gallery', 'img/camere/camera-03-1x1', '1/1', NULL, 10, 'segnaposto disegnato — sostituire con la fotografia vera');
INSERT INTO room_rates (room_id, guests, nightly_rate, currency, confirmed) VALUES (4, 1, 70, 'EUR', 1);
INSERT INTO room_rates (room_id, guests, nightly_rate, currency, confirmed) VALUES (4, 2, 80, 'EUR', 1);

INSERT INTO rooms (id, ref, position, published, confirmed, name_confirmed, type_key,
                   occupancy_standard, occupancy_max, beds_json, layouts_json,
                   size_sqm, floor, view_key, bathroom_json,
                   price_confirmed, currency, view_san_rufino)
  VALUES (5, 'camera-05', 5, 1, 1, 0, 'single-double', 1, 1, '{"double":1}', '["single"]', NULL, NULL, NULL, '{"private":true,"shower":true,"bathtub":false}', 1, 'EUR', NULL);
INSERT INTO room_translations (room_id, locale, name, slug) VALUES (5, 'it', 'Camera 05', 'camera-05');
INSERT INTO room_translations (room_id, locale, name, slug) VALUES (5, 'en', 'Room 05', 'room-05');
INSERT INTO room_translations (room_id, locale, name, slug) VALUES (5, 'es', 'Habitación 05', 'habitacion-05');
INSERT INTO room_amenities (room_id, amenity_key, position) VALUES (5, 'private-bathroom', 10);
INSERT INTO room_amenities (room_id, amenity_key, position) VALUES (5, 'wifi', 20);
INSERT INTO room_amenities (room_id, amenity_key, position) VALUES (5, 'heating', 30);
INSERT INTO room_amenities (room_id, amenity_key, position) VALUES (5, 'linen', 40);
INSERT INTO room_amenities (room_id, amenity_key, position) VALUES (5, 'towels', 50);
INSERT INTO room_amenities (room_id, amenity_key, position) VALUES (5, 'desk', 60);
INSERT INTO room_images (room_id, role, path, ratio, alt, position, credit)
  VALUES (5, 'card', 'img/camere/camera-04-4x3', '4/3', NULL, 0, 'segnaposto disegnato — sostituire con la fotografia vera');
INSERT INTO room_images (room_id, role, path, ratio, alt, position, credit)
  VALUES (5, 'list', 'img/camere/camera-04-3x2', '3/2', NULL, 0, 'segnaposto disegnato — sostituire con la fotografia vera');
INSERT INTO room_images (room_id, role, path, ratio, alt, position, credit)
  VALUES (5, 'hero', 'img/camere/camera-04-16x9', '16/9', NULL, 0, 'segnaposto disegnato — sostituire con la fotografia vera');
INSERT INTO room_images (room_id, role, path, ratio, alt, position, credit)
  VALUES (5, 'gallery', 'img/camere/camera-04-1x1', '1/1', NULL, 10, 'segnaposto disegnato — sostituire con la fotografia vera');
INSERT INTO room_rates (room_id, guests, nightly_rate, currency, confirmed) VALUES (5, 1, 70, 'EUR', 1);

-- Nessuna recensione: la sezione del sito non compare finché questa
-- tabella è vuota, ed è esattamente il comportamento voluto.

COMMIT;
