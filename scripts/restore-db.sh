#!/usr/bin/env bash
# UniPark · Restauración de un respaldo (Linux, macOS o Git Bash en Windows).
# Uso: scripts/restore-db.sh storage/backups/archivo.sql [--db=base]
# Pide escribir el nombre de la base para confirmar y crea un respaldo previo del estado actual.
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"

if [ "$#" -lt 1 ]; then
  echo "Uso: $0 <archivo.sql> [--db=base]" >&2
  exit 1
fi
if [ ! -f "$1" ]; then
  echo "No existe el archivo: $1" >&2
  exit 1
fi
if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
  echo "No se encontró PHP. Define PHP_BIN, por ejemplo: PHP_BIN=/c/xampp/php/php.exe $0 $1" >&2
  exit 1
fi

exec "$PHP_BIN" "$DIR/scripts/restore.php" "$@"
