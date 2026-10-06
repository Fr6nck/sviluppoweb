#!/bin/sh
# Schiera l'applicazione come sull'hosting (in una sottocartella, senza URL
# riscritti), avvia uno Stripe finto e un bucket S3 finto, e ci passa sopra
# il giro completo. Alla fine pulisce tutto.
#
# Le chiavi qui sotto sono FINTE e valgono solo per i servizi finti locali.
set -e
QUI=$(cd "$(dirname "$0")" && pwd)
RADICE=$(cd "$QUI/../.." && pwd)
PORTA=${PORTA:-8088}
PORTA_STRIPE=$((PORTA + 1))
PORTA_S3=$((PORTA + 2))
TMP=$(mktemp -d)
W="$TMP/docroot/welcomebook"

pulisci() { for p in $PID $PID_STRIPE $PID_S3; do kill "$p" 2>/dev/null || true; done; rm -rf "$TMP"; }
trap pulisci EXIT

mkdir -p "$W/app/storage/uploads" "$TMP/stripe" "$TMP/s3"
cp "$RADICE/app/public/index.php" "$W/"
for f in .htaccess web.config controllo.php; do
  [ -f "$RADICE/app/public/$f" ] && cp "$RADICE/app/public/$f" "$W/"
done
cp -r "$RADICE/app/public/assets" "$W/assets"
cp -r "$RADICE/app/src" "$RADICE/app/views" "$RADICE/app/migrations" "$RADICE/app/lang" "$RADICE/app/tools" "$RADICE/app/config.php" "$W/app/"
chmod -R 777 "$W/app/storage"

# Il server di prova fa quello che farebbe Apache con mod_rewrite.
cat > "$TMP/docroot/router.php" <<'EOF'
<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file(__DIR__ . $path)) return false;
if (str_starts_with($path, '/welcomebook')) {
    $_SERVER['SCRIPT_NAME'] = '/welcomebook/index.php';
    require __DIR__ . '/welcomebook/index.php';
    return true;
}
return false;
EOF

STRIPE_FINTO_DIR="$TMP/stripe" php -S "127.0.0.1:$PORTA_STRIPE" "$QUI/stripe-finto.php" > "$TMP/stripe.log" 2>&1 &
PID_STRIPE=$!
S3_FINTO_DIR="$TMP/s3" php -S "127.0.0.1:$PORTA_S3" "$QUI/s3-finto.php" > "$TMP/s3.log" 2>&1 &
PID_S3=$!

export STRIPE_SECRET_KEY=sk_test_finto_solo_per_le_prove
export STRIPE_WEBHOOK_SECRET=whsec_finto_solo_per_le_prove
export STRIPE_API_BASE="http://127.0.0.1:$PORTA_STRIPE"
export MHW_STORAGE=${MHW_STORAGE:-s3}
export AWS_REGION=eu-south-1
export AWS_S3_BUCKET=mhw-prove
export AWS_ACCESS_KEY_ID=AKIAFINTOPERLEPROVE
export AWS_SECRET_ACCESS_KEY=segreto-finto-per-le-prove
export AWS_S3_ENDPOINT="http://127.0.0.1:$PORTA_S3"
export MAIL_TRANSPORT=log
export MHW_CRON_TOKEN=token-finto-per-le-prove
export PROVE_DUMP=${PROVE_DUMP:-}
# La vetrina automatica nelle prove: un account suo, non quello dell'agenzia (che le prove usano per il pulsante).
export MHW_VETRINA_EMAIL=vetrina-auto@prova.test

php -S "127.0.0.1:$PORTA" -t "$TMP/docroot" "$TMP/docroot/router.php" > "$TMP/server.log" 2>&1 &
PID=$!
sleep 2

set +e
php "$QUI/giro-completo.php" "http://127.0.0.1:$PORTA/welcomebook/index.php" "$W" "$TMP/stripe" "$TMP/s3"
ESITO=$?
if [ -s "$W/app/storage/logs/app.log" ]; then
  echo; echo "--- registro tecnico dell'applicazione (errori previsti dalle prove inclusi) ---"
  cut -c1-300 "$W/app/storage/logs/app.log" | tail -n 25
fi
exit $ESITO
