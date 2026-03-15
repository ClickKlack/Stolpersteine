#!/usr/bin/env bash
# =============================================================================
# cleanup.sh – Laufdaten bereinigen, Konfigurationsdaten erhalten
#
# Immer gelöscht:
#   stolpersteine, personen, verlegeorte, dokumente, suchindex,
#   audit_log, validierungen
#
# Nur mit --adressen-loeschen zusätzlich gelöscht:
#   adress_lokationen, plz, stadtteile, strassen, staedte
#
# Erhalten bleiben (immer):
#   konfiguration, benutzer, templates
#
# Uploads (Dateien): werden ebenfalls geleert (--uploads-behalten überspringt das)
# =============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_PHP="${SCRIPT_DIR}/backend/config.php"
UPLOADS_DIR="${SCRIPT_DIR}/uploads"
SPIEGEL_DIR="${SCRIPT_DIR}/storage/spiegel"

# -----------------------------------------------------------------------------
# Argumente
# -----------------------------------------------------------------------------
UPLOADS_BEHALTEN=false
SPIEGEL_BEHALTEN=false
ADRESSEN_LOESCHEN=false
FORCE=false

for arg in "$@"; do
    case "$arg" in
        --uploads-behalten)  UPLOADS_BEHALTEN=true ;;
        --spiegel-behalten)  SPIEGEL_BEHALTEN=true ;;
        --adressen-loeschen) ADRESSEN_LOESCHEN=true ;;
        --force)             FORCE=true ;;
        --help|-h)
            echo "Verwendung: $0 [Optionen]"
            echo ""
            echo "  --adressen-loeschen  Adressnormalisierung EBENFALLS löschen"
            echo "                       (adress_lokationen, plz, stadtteile, strassen, staedte)"
            echo "  --uploads-behalten   Hochgeladene Dateien im uploads/-Verzeichnis NICHT löschen"
            echo "  --spiegel-behalten   Gespiegelte Dateien in storage/spiegel/ NICHT löschen"
            echo "  --force              Keine Sicherheitsabfrage"
            exit 0
            ;;
        *)
            echo "Unbekannte Option: $arg" >&2
            exit 1
            ;;
    esac
done

# -----------------------------------------------------------------------------
# Konfiguration aus config.php auslesen
# -----------------------------------------------------------------------------
if [[ ! -f "$CONFIG_PHP" ]]; then
    echo "Fehler: $CONFIG_PHP nicht gefunden." >&2
    exit 1
fi

DB_HOST=$(php -r "\$c = require '$CONFIG_PHP'; echo \$c['db']['host'] ?? 'localhost';")
DB_PORT=$(php -r "\$c = require '$CONFIG_PHP'; echo \$c['db']['port'] ?? 3306;")
DB_NAME=$(php -r "\$c = require '$CONFIG_PHP'; echo \$c['db']['name'];")
DB_USER=$(php -r "\$c = require '$CONFIG_PHP'; echo \$c['db']['user'];")
DB_PASS=$(php -r "\$c = require '$CONFIG_PHP'; echo \$c['db']['password'];")

# -----------------------------------------------------------------------------
# Sicherheitsabfrage
# -----------------------------------------------------------------------------
echo "=========================================="
echo "  Stolpersteine – Laufdaten bereinigen"
echo "=========================================="
echo ""
echo "  Datenbank : ${DB_NAME} @ ${DB_HOST}:${DB_PORT}"
echo "  Benutzer  : ${DB_USER}"
echo ""
echo "  GELÖSCHT werden:"
echo "    stolpersteine, personen, verlegeorte, dokumente"
echo "    suchindex, audit_log, validierungen"
if [[ "$ADRESSEN_LOESCHEN" == true ]]; then
    echo "    adress_lokationen, plz, stadtteile, strassen, staedte  [--adressen-loeschen]"
fi
if [[ "$UPLOADS_BEHALTEN" == false ]]; then
    echo "    uploads/* (alle hochgeladenen Dateien)"
fi
if [[ "$SPIEGEL_BEHALTEN" == false ]]; then
    echo "    storage/spiegel/* (alle gespiegelten Dateien)"
fi
echo ""
echo "  ERHALTEN bleiben:"
echo "    konfiguration, benutzer, templates"
if [[ "$ADRESSEN_LOESCHEN" == false ]]; then
    echo "    adress_lokationen, plz, stadtteile, strassen, staedte"
fi
echo ""

if [[ "$FORCE" == false ]]; then
    read -rp "Wirklich fortfahren? Alle Laufdaten werden unwiderruflich gelöscht. [j/N] " ANTWORT
    if [[ "$(echo "$ANTWORT" | tr '[:upper:]' '[:lower:]')" != "j" ]]; then
        echo "Abgebrochen."
        exit 0
    fi
fi

# -----------------------------------------------------------------------------
# Hilfsfunktion: SQL via PHP PDO ausführen
# Credentials als Umgebungsvariablen, SQL via stdin
# -----------------------------------------------------------------------------
run_mysql() {
    _DB_HOST="$DB_HOST" _DB_PORT="$DB_PORT" _DB_NAME="$DB_NAME" \
    _DB_USER="$DB_USER" _DB_PASS="$DB_PASS" \
    php -r "
        \$pdo = new PDO(
            'mysql:host=' . getenv('_DB_HOST') . ';port=' . getenv('_DB_PORT')
                . ';dbname=' . getenv('_DB_NAME') . ';charset=utf8mb4',
            getenv('_DB_USER'),
            getenv('_DB_PASS'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        \$sql = stream_get_contents(STDIN);
        foreach (array_filter(array_map('trim', explode(';', \$sql))) as \$stmt) {
            if (\$stmt !== '') \$pdo->exec(\$stmt);
        }
    " <<< "$1" || { echo "Fehler beim Ausführen der SQL-Anweisung." >&2; exit 1; }
}

# -----------------------------------------------------------------------------
# Datenbank bereinigen
# -----------------------------------------------------------------------------
echo ""
echo "Bereinige Datenbank …"

run_mysql "
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE suchindex;
TRUNCATE TABLE validierungen;
TRUNCATE TABLE audit_log;
TRUNCATE TABLE dokumente;
TRUNCATE TABLE stolpersteine;
TRUNCATE TABLE verlegeorte;
TRUNCATE TABLE personen;

SET FOREIGN_KEY_CHECKS = 1;
"

if [[ "$ADRESSEN_LOESCHEN" == true ]]; then
    run_mysql "
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE adress_lokationen;
TRUNCATE TABLE plz;
TRUNCATE TABLE stadtteile;
TRUNCATE TABLE strassen;
TRUNCATE TABLE staedte;

SET FOREIGN_KEY_CHECKS = 1;
"
    echo "  ✓ Adressnormalisierung geleert."
fi

echo "  ✓ Datenbank bereinigt."

# -----------------------------------------------------------------------------
# Uploads löschen
# -----------------------------------------------------------------------------
if [[ "$UPLOADS_BEHALTEN" == false ]]; then
    if [[ -d "$UPLOADS_DIR" ]]; then
        echo "Leere uploads/ …"
        find "$UPLOADS_DIR" -mindepth 1 -delete
        echo "  ✓ uploads/ geleert."
    else
        echo "  uploads/-Verzeichnis nicht gefunden, übersprungen."
    fi
fi

# -----------------------------------------------------------------------------
# storage/spiegel löschen
# -----------------------------------------------------------------------------
if [[ "$SPIEGEL_BEHALTEN" == false ]]; then
    if [[ -d "$SPIEGEL_DIR" ]]; then
        echo "Leere storage/spiegel/ …"
        find "$SPIEGEL_DIR" -mindepth 1 -delete
        echo "  ✓ storage/spiegel/ geleert."
    else
        echo "  storage/spiegel/-Verzeichnis nicht gefunden, übersprungen."
    fi
fi

echo ""
echo "Fertig."
