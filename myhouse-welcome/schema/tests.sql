-- =============================================================================
-- MyHouse Welcome — schema guarantee tests
--
-- These assert the invariants the product design depends on. They are not
-- exhaustive coverage; they pin the promises made in the documentation:
--
--   docs/05  a sold package version can never change
--   docs/11  a machine run can never overwrite human translation work
--   docs/13  a slug is unique and well-formed; one primary URL per guide
--   docs/14  denormalised account_id cannot drift from its parent
--   docs/06  a Stripe event can only be processed once
--
-- Run:
--   createdb mhw_test
--   psql -d mhw_test -v ON_ERROR_STOP=1 -f schema.sql
--   psql -d mhw_test -f tests.sql
--
-- Every block marked "must FAIL" is expected to print an ERROR. A run with no
-- ERROR lines means the guards are NOT working.
-- =============================================================================

\set ON_ERROR_STOP off

-- ---------------------------------------------------------------- fixtures --
INSERT INTO locales(code,name,native_name)
  VALUES ('it-IT','Italian','Italiano'),('en-GB','English','English');
INSERT INTO features(key,name,category,value_type,default_value)
  VALUES ('welcome.languages.max','Languages','content','limit','1'::jsonb),
         ('welcome.media.pdf','PDF uploads','media','boolean','false'::jsonb);
INSERT INTO packages(key,name,sort_order) VALUES ('plus','Plus',2);
INSERT INTO package_versions(package_id,version,amount_cents,stripe_price_id,is_current)
  SELECT id,1,9900,'price_test_1',true FROM packages WHERE key='plus';
INSERT INTO accounts(reference,name,billing_email,package_version_id)
  SELECT 'MH-2026-0001','Casa San Francesco','marco@example.com',id FROM package_versions LIMIT 1;
INSERT INTO properties(account_id,name) SELECT id,'Casa San Francesco' FROM accounts LIMIT 1;
INSERT INTO guides(property_id,account_id) SELECT p.id,p.account_id FROM properties p LIMIT 1;


-- =========================================================== tenancy guards ==

\echo '=== 1  cross-tenant account_id on a section  → must FAIL'
INSERT INTO guide_sections(guide_id,account_id,key,title)
  SELECT id,'00000000-0000-0000-0000-000000000099'::uuid,'wifi','Wi-Fi' FROM guides LIMIT 1;

\echo '=== 2  matching account_id                   → must SUCCEED'
INSERT INTO guide_sections(guide_id,account_id,key,title)
  SELECT id,account_id,'wifi','Wi-Fi' FROM guides LIMIT 1;

\echo '=== 3  pin_protected without a pin_hash      → must FAIL'
UPDATE guide_sections SET visibility='pin_protected' WHERE key='wifi';


-- ================================================== package version freezing ==

\echo '=== 4  price change on a sold version        → must FAIL'
UPDATE package_versions SET is_frozen=true;
UPDATE package_versions SET amount_cents=12900;

\echo '=== 5  adding a feature to a sold version    → must FAIL'
INSERT INTO package_features(package_version_id,feature_id,value_json)
  SELECT pv.id,f.id,'5'::jsonb FROM package_versions pv, features f
   WHERE f.key='welcome.languages.max';

\echo '=== 6  removing a feature from a sold version→ must FAIL'
UPDATE package_versions SET is_frozen=false;
INSERT INTO package_features(package_version_id,feature_id,value_json)
  SELECT pv.id,f.id,'5'::jsonb FROM package_versions pv, features f
   WHERE f.key='welcome.languages.max';
UPDATE package_versions SET is_frozen=true;
DELETE FROM package_features;                        -- blocked
SELECT count(*) AS "feature_rows_survived (expect 1)" FROM package_features;

\echo '=== 7  same edits on an unsold version       → must SUCCEED'
UPDATE package_versions SET is_frozen=false;
UPDATE package_features SET value_json='20'::jsonb;
DELETE FROM package_features;


-- ======================================================= translation guards ==

INSERT INTO translations(account_id,guide_id,translatable_type,translatable_id,field_path,
                         locale,source_locale,value_text,status)
  SELECT g.account_id,g.id,'content_block',g.id,'data.instructions','en-GB','it-IT',
         'The router is in the hallway cupboard','reviewed'
  FROM guides g LIMIT 1;

\echo '=== 8  machine output over a REVIEWED value  → must FAIL'
UPDATE translations SET value_text='Machine output', status='machine_translated', engine='deepl';

\echo '=== 9  same machine output into proposed_value → must SUCCEED'
UPDATE translations SET proposed_value='Machine output', engine='deepl';

\echo '=== 10 machine output over a MISSING value   → must SUCCEED'
UPDATE translations SET status='missing', value_text=NULL;
UPDATE translations SET value_text='Der Router ist im Schrank',
                        status='machine_translated', engine='deepl';

\echo '=== 11 machine output over a LOCKED value    → must FAIL'
UPDATE translations SET is_locked=true, status='reviewed';
UPDATE translations SET value_text='clobbered', engine='deepl';

\echo '=== 12 manual edit of a LOCKED value         → must SUCCEED'
UPDATE translations SET value_text='host edit', engine='manual';

\echo '=== 13 translating a locale into itself      → must FAIL'
INSERT INTO translations(account_id,guide_id,translatable_type,translatable_id,field_path,
                         locale,source_locale,value_text)
  SELECT g.account_id,g.id,'content_block',g.id,'x','it-IT','it-IT','x' FROM guides g LIMIT 1;


-- ============================================================= url identity ==

\echo '=== 14 malformed slug                        → must FAIL'
INSERT INTO public_urls(guide_id,account_id,slug)
  SELECT id,account_id,'Casa San Francesco!' FROM guides LIMIT 1;

\echo '=== 15 well-formed slug                      → must SUCCEED'
INSERT INTO public_urls(guide_id,account_id,slug)
  SELECT id,account_id,'casa-san-francesco' FROM guides LIMIT 1;

\echo '=== 16 same slug on the same host            → must FAIL'
INSERT INTO public_urls(guide_id,account_id,slug,is_primary)
  SELECT id,account_id,'casa-san-francesco',false FROM guides LIMIT 1;

\echo '=== 17 a second primary URL for one guide    → must FAIL'
INSERT INTO public_urls(guide_id,account_id,slug) SELECT id,account_id,'casa-2' FROM guides LIMIT 1;


-- ================================================================= commerce ==

INSERT INTO orders(reference,account_id,package_version_id,total_cents)
  SELECT 'MH-O-1',a.id,a.package_version_id,9900 FROM accounts a LIMIT 1;

\echo '=== 18 refund exceeding the payment          → must FAIL'
INSERT INTO payments(order_id,account_id,amount_cents,refunded_amount_cents)
  SELECT o.id,o.account_id,9900,19900 FROM orders o LIMIT 1;

\echo '=== 19 percentage discount above 100         → must FAIL'
INSERT INTO discount_codes(code,type,value) VALUES ('BIG','percent',150);

\echo '=== 20 fixed discount with no currency       → must FAIL'
INSERT INTO discount_codes(code,type,value) VALUES ('TEN','amount',1000);

\echo '=== 21 replaying a Stripe event              → must FAIL (idempotency)'
INSERT INTO webhook_events(provider_event_id,type,payload) VALUES ('evt_1','checkout.session.completed','{}');
INSERT INTO webhook_events(provider_event_id,type,payload) VALUES ('evt_1','checkout.session.completed','{}');

\echo '=== 22 manual order with no reason           → must FAIL'
INSERT INTO orders(reference,account_id,package_version_id,payment_provider)
  SELECT 'MH-O-2',a.id,a.package_version_id,'manual' FROM accounts a LIMIT 1;

\echo '=== 23 global setting carrying a scope_id    → must FAIL'
INSERT INTO settings(scope,scope_id,key,value_json) SELECT 'global',id,'x','1'::jsonb FROM accounts LIMIT 1;


-- ================================================================ machinery ==

\echo '=== 24 updated_at is maintained by the database'
SELECT created_at = updated_at AS "equal_on_insert (expect t)" FROM guides;
UPDATE guides SET completion_percent = 42;
SELECT updated_at > created_at AS "advanced_on_update (expect t)" FROM guides;

\echo '=== 25 uuid_generate_v7 is a valid, time-ordered UUIDv7'
SELECT DISTINCT substring(id::text,15,1) AS "version (expect 7)"
  FROM (SELECT uuid_generate_v7() id FROM generate_series(1,200)) s;
SELECT to_timestamp(('x'||replace(substring(uuid_generate_v7()::text,1,13),'-',''))::bit(48)::bigint/1000.0)
         AS "decoded timestamp", now() AS "actual now";
DO $$
DECLARE ids uuid[] := '{}'; i int; bad int := 0;
BEGIN
  FOR i IN 1..40 LOOP ids := ids || uuid_generate_v7(); PERFORM pg_sleep(0.002); END LOOP;
  FOR i IN 2..array_length(ids,1) LOOP
    IF ids[i] < ids[i-1] THEN bad := bad + 1; END IF;
  END LOOP;
  RAISE NOTICE 'out of order across milliseconds: % of 39 (expect 0)', bad;
END $$;
-- NOTE: UUIDs generated within the SAME millisecond sort randomly by design -
-- only the 48-bit timestamp prefix is ordered. Do not assert on those.

\echo '=== 26 row level security isolates tenants'
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname='app_request') THEN
    CREATE ROLE app_request NOLOGIN;      -- roles are cluster-wide, so re-runs must not fail
  END IF;
END $$;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO app_request;
SELECT account_id AS real_account FROM guides \gset
SET ROLE app_request;
SELECT set_config('app.account_id', :'real_account', false) IS NOT NULL AS ctx;
SELECT count(*) AS "correct tenant sees (expect 1)" FROM guide_sections;
SET app.account_id = '00000000-0000-0000-0000-000000000099';
SELECT count(*) AS "wrong tenant sees (expect 0)" FROM guide_sections;
RESET ROLE;

\echo '=== 27 the guest hot path uses an index, never a sequential scan'
EXPLAIN (COSTS OFF) SELECT g.published_version_id FROM public_urls pu
  JOIN guides g ON g.id = pu.guide_id
 WHERE pu.host='welcome.myhouse.it' AND pu.slug='casa-san-francesco';
