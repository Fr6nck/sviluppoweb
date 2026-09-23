#!/usr/bin/env sh
# Avvia il sito in locale su http://localhost:8080
#
#     sh tools/serve.sh [porta]
#
# Non serve installare nulla: basta PHP 8.1 o superiore.
set -e
PORTA="${1:-8080}"
cd "$(dirname "$0")/.."
echo "Arco del Vento — http://localhost:${PORTA}/"
exec php -S "localhost:${PORTA}" -t public tools/router.php
