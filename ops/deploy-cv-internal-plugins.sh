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
    chavevertical-core/|chavevertical-core/*|cv-astro-bridge/|cv-astro-bridge/*|cv-pdf-reader/|cv-pdf-reader/*) ;;
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

for slug in chavevertical-core cv-astro-bridge cv-pdf-reader; do
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
  rsync -rp --delete --chmod=D2770,F660 "$SRC/" "$DEST/"

  DIFF=$(rsync -rcn --delete --out-format='%i %n' "$SRC/" "$DEST/")
  if [[ -n "$DIFF" ]]; then
    printf 'Deployed files differ from package for %s:\n%s\n' "$slug" "$DIFF" >&2
    exit 1
  fi
done

wp --path="$WP_ROOT" plugin activate chavevertical-core
wp --path="$WP_ROOT" plugin activate cv-astro-bridge
wp --path="$WP_ROOT" plugin activate cv-pdf-reader
wp --path="$WP_ROOT" plugin is-active chavevertical-core
wp --path="$WP_ROOT" plugin is-active cv-astro-bridge
wp --path="$WP_ROOT" plugin is-active cv-pdf-reader
wp --path="$WP_ROOT" eval 'echo defined("CV_CORE_VERSION") ? CV_CORE_VERSION : "missing";'
echo
wp --path="$WP_ROOT" eval 'echo defined("CVAB_VERSION") ? CVAB_VERSION : "missing";'
echo
echo "Chave Vertical internal plugins deployed and active."
