#!/bin/sh
set -e

DB_WAIT_TIMEOUT="${DB_WAIT_TIMEOUT:-120}"

# Das Passwort NICHT als Argument übergeben — es stünde sonst in der Prozessliste.
export MYSQL_PWD="${DB_PASS}"

echo "Warte auf Datenbank ${DB_HOST} (max. ${DB_WAIT_TIMEOUT}s)..."
waited=0
until mysql -h "${DB_HOST}" -u"${DB_USER}" "${DB_NAME}" -e "SELECT 1" >/dev/null 2>&1; do
    if [ "${waited}" -ge "${DB_WAIT_TIMEOUT}" ]; then
        echo "Datenbank ${DB_HOST} nach ${DB_WAIT_TIMEOUT}s nicht erreichbar — Abbruch." >&2
        exit 1
    fi
    waited=$((waited + 2))
    sleep 2
done
unset MYSQL_PWD

echo "Führe Migrationen aus..."
php /var/www/html/bin/migrate.php
php /var/www/html/bin/create-admin.php

# Volumes gehören nach dem ersten Start root – Webserver und Worker müssen schreiben können
chown www-data:www-data /var/www/html/uploads /var/www/html/uploads/queue /var/www/html/import 2>/dev/null || true
mkdir -p /var/www/html/uploads/queue && chown www-data:www-data /var/www/html/uploads/queue

exec "$@"
