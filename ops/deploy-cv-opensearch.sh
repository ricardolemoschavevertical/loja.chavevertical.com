#!/usr/bin/env bash
set -euo pipefail
umask 0077

WP_ROOT=/home/chavevertical-loja/htdocs/loja.chavevertical.com
PLUGINS="$WP_ROOT/wp-content/plugins"
DEST="$PLUGINS/cv-opensearch"
ZIP=$(realpath -- "${1:?Usage: deploy-cv-opensearch.sh plugin.zip}")

[[ "$(id -un)" == codex1 ]]
[[ "$(realpath -- "$PLUGINS")" == "$PLUGINS" ]]
[[ ! -L "$DEST" ]]
[[ ! -e "$DEST" || -d "$DEST" ]]
test -f "$WP_ROOT/wp-load.php"
test -f "$ZIP"
test -w "$PLUGINS"

while IFS= read -r entry; do
  case "$entry" in
    cv-opensearch/|cv-opensearch/*) ;;
    *) echo "Unexpected archive entry: $entry" >&2; exit 1 ;;
  esac
  case "/$entry/" in
    */../*|*/./*|*\\*) echo "Unsafe archive entry" >&2; exit 1 ;;
  esac
done < <(unzip -Z1 "$ZIP")

unzip -tq "$ZIP"

STAGE=$(mktemp -d /home/codex1/tmp/plugin-deploy.XXXXXXXX)
cleanup() {
  if [[ "$STAGE" == /home/codex1/tmp/plugin-deploy.* && -d "$STAGE" && ! -L "$STAGE" ]]; then
    rm -rf -- "$STAGE"
  fi
}
trap cleanup EXIT

unzip -q "$ZIP" -d "$STAGE"
SRC="$STAGE/cv-opensearch"

test -f "$SRC/cv-opensearch.php"
test -z "$(find "$SRC" -type l -print -quit)"
find "$SRC" -type f -name '*.php' -print0 | xargs -0 -r -n1 php -l

if [[ -d "$DEST" ]]; then
  BACKUP_DIR=/home/codex1/backups/github-plugins
  mkdir -p "$BACKUP_DIR"
  BACKUP=$(mktemp "$BACKUP_DIR/cv-opensearch-$(date -u +%Y%m%dT%H%M%SZ).XXXXXXXX.tar.gz")
  tar -czf "$BACKUP" -C "$PLUGINS" cv-opensearch
  tar -tzf "$BACKUP" >/dev/null
  echo "Previous plugin backup: $BACKUP"
fi

mkdir -p "$DEST"
rsync -rp --delete --chmod=D2770,F660 "$SRC/" "$DEST/"

DIFF=$(rsync -rcn --delete --out-format='%i %n' "$SRC/" "$DEST/")
if [[ -n "$DIFF" ]]; then
  printf 'Deployed files differ from package:\n%s\n' "$DIFF" >&2
  exit 1
fi

wp --path="$WP_ROOT" plugin activate cv-opensearch
wp --path="$WP_ROOT" plugin is-active cv-opensearch
wp --path="$WP_ROOT" eval 'echo defined("CVOS_VERSION") ? CVOS_VERSION : "missing";'

echo
echo "CV OpenSearch deployed and active."
