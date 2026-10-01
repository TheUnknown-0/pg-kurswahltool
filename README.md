# Kurswahl-Planer

Webanwendung für die Kurswahl der Qualifikationsphase am Paulsen-Gymnasium. Schüler melden sich mit
`vorname.nachname` an, planen ihre Kurse mit Prüfung nach VO-GO und laden ihr **Kurswahlformular der
Schule** herunter: Das ist ihre eigene PDF vom Schulserver, in der der Planer nur die Kreuze und die
Form der 5. PK setzt. Alles andere bleibt Byte für Byte unverändert.

## Ablauf

| Wer | Was |
|---|---|
| **Admin** | lädt die Kurswahl-PDFs des Schulservers hoch (einzeln, mehrere oder als ZIP) **oder** legt sie in den Import-Ordner. Ein Hintergrund-Worker liest sie ein und ordnet sie über den Namen dem Konto `vorname.nachname` zu. |
| **Admin** | importiert die Schülerkonten mit Passwörtern (CSV, siehe unten). Passende PDFs werden danach automatisch zugeordnet; den Rest ordnet der Admin von Hand zu. |
| **Schüler** | meldet sich an. Name, Klasse, Jahrgang und Schüler-ID kommen aus seiner PDF. Die Wahl wird automatisch auf dem Server gespeichert. Ändert er auf zwei Geräten gleichzeitig, fragt der Planer, welche Wahl gelten soll. |
| **Schüler (Notfall)** | kann seine eigene Schul-PDF hochladen. Dann bleiben PDF, Stammdaten und Wahl **nur in diesem Browser** und gehen nicht an den Server. Mit „Eigene PDF entfernen“ kehrt er zum Server-Stand zurück. Enthält die PDF schon Kreuze, werden sie übernommen (außer den Sportkursen). |

| **Schüler** | gibt seine Wahl mit „Wahl abgeben“ verbindlich ab. Danach ist sie gesperrt, bis der Admin sie wieder freischaltet. |
| **Admin** | stellt eine Abgabefrist ein und wählt, was danach gilt: nur noch ansehen, Anmeldung gesperrt oder nur Hinweis. |
| **Admin** | sieht in der Schülerliste, wer gespeichert bzw. abgegeben hat und wie viele Fehler die Wahl noch hat, öffnet die Wahl eines Schülers im Planer (nur lesen) und lädt sein ausgefülltes Formular herunter. |
| **Admin** | exportiert alle Formulare als ZIP (jede Original-PDF mit den Kreuzen der gespeicherten Wahl, byte-gleich und unter dem Original-Dateinamen; wahlweise nur abgegebene) und alle Wahlen als CSV für Excel. |

Liegt für ein Konto keine PDF vor, erzeugt der Planer das Formular selbst aus der eingebetteten Vorlage
(siehe `tools/`). Name, Klasse, Jahrgang und Schüler-ID trägt der Schüler dann von Hand ein.

### Login aus dem Namen

Für die Zuordnung der PDFs wird aus dem Namen in der PDF der Login abgeleitet (`src/Services/LoginName.php`):
Kleinbuchstaben, ä→ae, ö→oe, ü→ue, ß→ss, andere Akzente entfallen. Das letzte Wort ist der Nachname,
weitere Vornamen werden mit Bindestrich verbunden: *Anna Maria Schmidt* → `anna-maria.schmidt`.

### Format der Konten-Datei (vorläufig)

CSV in UTF-8 mit Kopfzeile, Trennzeichen `;`, `,` oder Tab:

```
login;passwort;name
moritz.lingens;geheim123;Moritz Lingens
```

`name` ist optional; fehlt `login`, wird er aus `name` abgeleitet. Das endgültige Format der Schule wird
nur in `src/Services/UserImport.php` (`parse()`) angepasst.

## Schnellstart (Docker)

```bash
cp .env.example .env      # Passwörter und ADMIN_PASSWORD setzen!
docker network create proxy_network   # falls der Reverse-Proxy es nicht schon anlegt
docker compose up -d --build
```

Anwendung: `http://localhost:9020`. Anmeldung als `admin` mit `ADMIN_PASSWORD`, dann im Admin-Bereich
PDFs und Konten importieren. phpMyAdmin bei Bedarf: `docker compose --profile tools up -d` (Port 8091).

Container: `db` (MariaDB), `app` (Apache/PHP), `worker` (Hintergrund-Import). Der Import-Ordner liegt
auf dem Host unter `IMPORT_PATH` (Standard `./import`). Eingelesene Dateien wandern nach
`import/verarbeitet/`, fehlerhafte nach `import/fehler/`. Das Protokoll steht im Admin-Bereich.

## Umgebungsvariablen

| Variable | Standard | Beschreibung |
|---|---|---|
| `DB_PASS` / `DB_ROOT_PASS` | *(leer)* | **Pflicht** |
| `ADMIN_LOGIN` / `ADMIN_PASSWORD` | `admin` / *(leer)* | Admin-Konto, wird beim ersten Start angelegt (Passwort ≥ 10 Zeichen). Neu setzen: `docker compose exec app php bin/create-admin.php --reset` |
| `APP_PORT` | `9020` | Host-Port |
| `BASE_URL` | `/` | Basis-Pfad bei Betrieb im Unterverzeichnis |
| `IMPORT_PATH` | `./import` | Import-Ordner auf dem Host |
| `IMPORT_INTERVAL` | `15` | Sekunden zwischen zwei Durchläufen des Workers |
| `SECURE_COOKIES` | Auto | `1` hinter einem HTTPS-Reverse-Proxy |
| `TRUSTED_PROXIES` | *(leer)* | Adressen des eigenen Reverse-Proxys für X-Forwarded-For |

`/healthz` antwortet, solange die Anwendung läuft; `/readyz` prüft zusätzlich die Datenbank.

## Backup

```bash
docker compose exec db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysqldump -u"$MYSQL_USER" "$MYSQL_DATABASE"' > backup.sql
```

Die Datenbank enthält alles: Konten, PDFs und gespeicherte Wahlen.

## Lokale Entwicklung ohne Docker

PHP ≥ 8.2 (`pdo_mysql`, `zip`, `intl`) und MariaDB:

```bash
export DB_HOST=127.0.0.1 DB_USER=kurswahl DB_PASS=... DB_NAME=kurswahl APP_ENV=development ADMIN_PASSWORD=...
php bin/migrate.php && php bin/create-admin.php
php -S 127.0.0.1:8080 -t public tools/dev-router.php   # Anwendung
php bin/worker.php                                     # Hintergrund-Import
```

## Aufbau

- `public/index.php`: Front-Controller; `public/planer.html` + `public/assets/planer.js`: der Planer.
- `src/`: schlankes MVC nach dem Vorbild von *berufsmesse* (`Core/`, `Controllers/`, `Services/`).
- `src/Services/SchoolPdf.php`: liest Name, Klasse, Jahrgang, IDs und Kreuze aus einer Schul-PDF.
- `bin/worker.php`: Hintergrund-Import, `bin/migrate.php`: Migrationen (laufen beim Start).
- `tools/`: Formularvorlage für Konten ohne PDF (`build_form_template.py`), zlib-ng als WebAssembly,
  `test_form.js` (byte-genauer Nachbau einer Schul-PDF).
