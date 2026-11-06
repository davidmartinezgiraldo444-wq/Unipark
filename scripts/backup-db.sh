#!/usr/bin/env bash
# UniPark · Respaldo de la base de datos (Linux, macOS o Git Bash en Windows).
# Uso: scripts/backup-db.sh [--db=base] [--out=archivo.sql]
# Lee la conexión de .env. Ver scripts/backup.php para el detalle.
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"

if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
  echo "No se encontró PHP. Define PHP_BIN, por ejemplo: PHP_BIN=/c/xampp/php/php.exe $0" >&2
  exit 1
fi

exec "$PHP_BIN" "$DIR/scripts/backup.php" "$@"
