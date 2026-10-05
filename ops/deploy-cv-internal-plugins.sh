#!/usr/bin/env bash
set -euo pipefail
umask 0077

WP_ROOT=/home/chavevertical-loja/htdocs/loja.chavevertical.com
PLUGINS="$WP_ROOT/wp-content/plugins"
PACKAGE=$(realpath -- "${1:?Usage: deploy-cv-internal-plugins.sh package.zip}")

[[ "$(id -un)" == codex1 ]]
[[ "$(realpath -- "$PLUGINS")" == "$PLUGINS" ]]
test -f "$WP_ROOT/wp-load.php"
test -f "$PACKAGE"
test -w "$PLUGINS"

while IFS= read -r entry; do
  case "$entry" in
    chavevertical-core/|chavevertical-core/*|cv-astro-bridge/|cv-astro-bridge/*|cv-pdf-reader/|cv-pdf-reader/*|cv-r2-media-linker/|cv-r2-media-linker/*|cv-legacy-orders-archive/|cv-legacy-orders-archive/*) ;;
    *) echo "Unexpected archive entry: $entry" >&2; exit 1 ;;
  esac
  case "/$entry/" in
    */../*|*/./*|*\\*) echo "Unsafe archive entry" >&2; exit 1 ;;
  esac
done < <(unzip -Z1 "$PACKAGE")

unzip -tq "$PACKAGE"

STAGE=$(mktemp -d /home/codex1/tmp/cv-internal-plugins.XXXXXXXX)
cleanup() {
  if [[ "$STAGE" == /home/codex1/tmp/cv-internal-plugins.* && -d "$STAGE" && ! -L "$STAGE" ]]; then
    rm -rf -- "$STAGE"
  fi
}
trap cleanup EXIT

unzip -q "$PACKAGE" -d "$STAGE"

for slug in chavevertical-core cv-astro-bridge cv-pdf-reader cv-r2-media-linker cv-legacy-orders-archive; do
  SRC="$STAGE/$slug"
  DEST="$PLUGINS/$slug"

  test -d "$SRC"
  test -z "$(find "$SRC" -type l -print -quit)"
  find "$SRC" -type f -name '*.php' -print0 | xargs -0 -r -n1 php -l

  if [[ -d "$DEST" ]]; then
    BACKUP_DIR=/home/codex1/backups/github-plugins
    mkdir -p "$BACKUP_DIR"
    BACKUP=$(mktemp "$BACKUP_DIR/${slug}-$(date -u +%Y%m%dT%H%M%SZ).XXXXXXXX.tar.gz")
    tar -czf "$BACKUP" -C "$PLUGINS" "$slug"
    tar -tzf "$BACKUP" >/dev/null
    echo "Previous plugin backup: $BACKUP"
  fi

  mkdir -p "$DEST"

  if [[ "$slug" == "cv-r2-media-linker" ]]; then
    # Este plugin já existia no servidor com ownership diferente.
    # Não tentar alterar permissões das pastas; sincronizar apenas conteúdo.
    rsync -r --delete --no-perms --omit-dir-times "$SRC/" "$DEST/"
  else
    rsync -rp --delete --chmod=D2770,F660 "$SRC/" "$DEST/"
  fi

  DIFF=$(rsync -rcn --delete --out-format='%i %n' "$SRC/" "$DEST/")
  if [[ -n "$DIFF" ]]; then
    printf 'Deployed files differ from package for %s:\n%s\n' "$slug" "$DIFF" >&2
    exit 1
  fi
done

wp --path="$WP_ROOT" plugin activate chavevertical-core
wp --path="$WP_ROOT" plugin activate cv-astro-bridge
wp --path="$WP_ROOT" plugin activate cv-pdf-reader
wp --path="$WP_ROOT" plugin activate cv-r2-media-linker
wp --path="$WP_ROOT" plugin activate cv-legacy-orders-archive
wp --path="$WP_ROOT" plugin is-active chavevertical-core
wp --path="$WP_ROOT" plugin is-active cv-astro-bridge
wp --path="$WP_ROOT" plugin is-active cv-pdf-reader
wp --path="$WP_ROOT" plugin is-active cv-r2-media-linker
wp --path="$WP_ROOT" plugin is-active cv-legacy-orders-archive

ACCOUNT_ROOT="$(dirname "$(dirname "$WP_ROOT")")"
ARCHIVE_ROOT="$ACCOUNT_ROOT/private-data/chavevertical/legacy-orders"
OLD_ARCHIVE_ROOT="$WP_ROOT/wp-content/cv-private-data/legacy-orders"

mkdir -p "$ARCHIVE_ROOT"
# O arquivo pode ter sido criado pelo PHP/FPM com ownership diferente do runner.
# Não falhar o deploy ao tentar mudar grupo/permissões de ficheiros que o runner
# não possui. A escrita real é validada abaixo pelo próprio WordPress.
chmod 0770 "$ACCOUNT_ROOT/private-data" "$ACCOUNT_ROOT/private-data/chavevertical" "$ARCHIVE_ROOT" 2>/dev/null || true
find "$ACCOUNT_ROOT/private-data" -type f -exec chmod 0660 {} + 2>/dev/null || true

if [[ -d "$OLD_ARCHIVE_ROOT" ]]; then
  for file in orders.ndjson.php orders-index.json.php; do
    if [[ -f "$OLD_ARCHIVE_ROOT/$file" && ! -f "$ARCHIVE_ROOT/$file" ]]; then
      cp "$OLD_ARCHIVE_ROOT/$file" "$ARCHIVE_ROOT/$file"
      chmod 0660 "$ARCHIVE_ROOT/$file" 2>/dev/null || true
    fi
  done
fi

stat -c 'legacy-orders-private-perms=%A %U:%G %n' "$ARCHIVE_ROOT" || true

wp --path="$WP_ROOT" eval 'echo defined("CV_CORE_VERSION") ? CV_CORE_VERSION : "missing";'
echo
wp --path="$WP_ROOT" eval 'echo defined("CVAB_VERSION") ? CVAB_VERSION : "missing";'
echo
wp --path="$WP_ROOT" eval 'echo defined("CVR2_VERSION") ? CVR2_VERSION : "missing";'
echo
wp --path="$WP_ROOT" eval 'echo defined("CVLOA_VERSION") ? CVLOA_VERSION : "missing";'
echo
wp --path="$WP_ROOT" eval '$r=CVLOA_Archive::ensure_storage(); if (is_wp_error($r)) { fwrite(STDERR, $r->get_error_message()); exit(1); } $s=CVLOA_Archive::stats(); echo "legacy-orders-storage=" . $s["storage_path"] . ";count=" . $s["count"];'
echo

for migration in ops/migrations/repair-*.php; do
  [[ -f "$migration" ]] || continue
  echo "Running order recovery migration: $migration"
  wp --path="$WP_ROOT" eval-file "$migration"
done

if [[ -d "$OLD_ARCHIVE_ROOT" && -f "$ARCHIVE_ROOT/orders.ndjson.php" && -f "$ARCHIVE_ROOT/orders-index.json.php" ]]; then
  rm -rf "$WP_ROOT/wp-content/cv-private-data"
  echo "legacy-orders-public-copy=removed"
fi
echo "Chave Vertical internal plugins deployed and active."
