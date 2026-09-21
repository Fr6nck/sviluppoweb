#!/bin/sh
# Prepara il pacchetto da caricare via FTP dentro una sottocartella del dominio.
# Uso:  ./costruisci-pacchetto.sh [nome-cartella]       (predefinito: welcomebook)
set -e
QUI=$(cd "$(dirname "$0")" && pwd)
NOME=${1:-welcomebook}
FUORI="$QUI/dist"
BASE="$FUORI/$NOME"

rm -rf "$BASE" "$FUORI/myhouse-welcome-$NOME.zip"
mkdir -p "$BASE/app/storage/uploads"

# Quello che sta sul web: il front controller, il foglio di stile, le fotografie.
cp "$QUI/app/public/index.php" "$BASE/"
cp "$QUI/app/public/controllo.php" "$BASE/" 2>/dev/null || true
cp "$QUI/app/public/.htaccess" "$BASE/" 2>/dev/null || true
cp "$QUI/app/public/web.config" "$BASE/" 2>/dev/null || true
cp -r "$QUI/app/public/assets" "$BASE/assets"

# Quello che sta dietro: codice, viste, schema, configurazione.
cp -r "$QUI/app/src" "$QUI/app/views" "$QUI/app/migrations" "$BASE/app/"
cp "$QUI/app/config.php" "$QUI/app/LEGGIMI.md" "$BASE/app/"

# Un segnaposto, perche' l'FTP non carica le cartelle vuote.
printf 'Questa cartella deve essere scrivibile dal server (755 o 775).\n' \
  > "$BASE/app/storage/LEGGIMI.txt"
cp "$BASE/app/storage/LEGGIMI.txt" "$BASE/app/storage/uploads/LEGGIMI.txt"

cd "$FUORI"
zip -qr "myhouse-welcome-$NOME.zip" "$NOME"
echo "pronto: dist/myhouse-welcome-$NOME.zip  ($(du -h "myhouse-welcome-$NOME.zip" | cut -f1))"
echo "dentro c'e' la cartella '$NOME/': caricatela intera nella radice pubblica."
