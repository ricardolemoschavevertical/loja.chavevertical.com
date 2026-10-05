#!/usr/bin/env bash
set -euo pipefail
umask 0077

WP_ROOT=/home/chavevertical-loja/htdocs/loja.chavevertical.com
PLUGINS="$WP_ROOT/wp-content/plugins"
DEST="$PLUGINS/cv-github-deploy-monitor"
ZIP=$(realpath -- "${1:?Usage: deploy-cv-github-deploy-monitor.sh plugin.zip}")

[[ "$(id -un)" == codex1 ]]
[[ "$(realpath -- "$PLUGINS")" == "$PLUGINS" ]]
[[ ! -L "$DEST" ]]
[[ ! -e "$DEST" || -d "$DEST" ]]
test -f "$WP_ROOT/wp-load.php"
test -f "$ZIP"
test -w "$PLUGINS"

while IFS= read -r entry; do
  case "$entry" in
    cv-github-deploy-monitor/|cv-github-deploy-monitor/*) ;;
    *) echo "Unexpected archive entry: $entry" >&2; exit 1 ;;
  esac
  case "/$entry/" in
    */../*|*/./*|*\\*) echo "Unsafe archive entry" >&2; exit 1 ;;
  esac
done < <(unzip -Z1 "$ZIP")

unzip -tq "$ZIP"

STAGE=$(mktemp -d /home/codex1/tmp/cv-github-deploy-monitor.XXXXXXXX)
cleanup() {
  if [[ "$STAGE" == /home/codex1/tmp/cv-github-deploy-monitor.* && -d "$STAGE" && ! -L "$STAGE" ]]; then
    rm -rf -- "$STAGE"
  fi
}
trap cleanup EXIT

unzip -q "$ZIP" -d "$STAGE"
SRC="$STAGE/cv-github-deploy-monitor"

test -d "$SRC"
test -z "$(find "$SRC" -type l -print -quit)"
test -f "$SRC/cv-github-deploy-monitor.php"
find "$SRC" -type f -name '*.php' -print0 | xargs -0 -r -n1 php -l

if [[ -d "$DEST" ]]; then
  BACKUP_DIR=/home/codex1/backups/github-plugins
  mkdir -p "$BACKUP_DIR"
  BACKUP=$(mktemp "$BACKUP_DIR/cv-github-deploy-monitor-$(date -u +%Y%m%dT%H%M%SZ).XXXXXXXX.tar.gz")
  tar -czf "$BACKUP" -C "$PLUGINS" cv-github-deploy-monitor
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

wp --path="$WP_ROOT" plugin activate cv-github-deploy-monitor
wp --path="$WP_ROOT" plugin is-active cv-github-deploy-monitor
wp --path="$WP_ROOT" eval 'echo class_exists("CV_GitHub_Deploy_Monitor") ? "cvgdm=ready" : "cvgdm=missing";'
echo

echo "GitHub Deploy Monitor deployed and active: $DEST"
