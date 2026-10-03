#!/usr/bin/env bash
set -euo pipefail
umask 0077

WP_ROOT=/home/chavevertical-loja/htdocs/loja.chavevertical.com
THEMES="$WP_ROOT/wp-content/themes"
DEST="$THEMES/chavevertical-lite"
ZIP=$(realpath -- "${1:?Usage: deploy-theme.sh theme.zip [--check]}")
MODE=${2:-deploy}
[[ "$MODE" == deploy || "$MODE" == --check ]]
[[ "$(id -un)" == codex1 ]]
[[ "$(realpath -- "$THEMES")" == "$THEMES" ]]
[[ ! -L "$DEST" ]]
[[ ! -e "$DEST" || -d "$DEST" ]]
test -f "$WP_ROOT/wp-load.php"
test -f "$ZIP"
test -w "$THEMES"

# Refuse traversal paths or unrelated files before extraction.
while IFS= read -r entry; do
  case "$entry" in
    chavevertical-lite/|chavevertical-lite/*) ;;
    *) echo "Unexpected archive entry: $entry" >&2; exit 1 ;;
  esac
  case "/$entry/" in
    */../*|*/./*|*\\*) echo "Unsafe archive entry" >&2; exit 1 ;;
  esac
done < <(unzip -Z1 "$ZIP")
unzip -tq "$ZIP"

STAGE=$(mktemp -d /home/codex1/tmp/theme-deploy.XXXXXXXX)
cleanup() {
  # Only remove this exact temporary directory, never the WordPress tree.
  if [[ "$STAGE" == /home/codex1/tmp/theme-deploy.* && -d "$STAGE" && ! -L "$STAGE" ]]; then
    rm -rf -- "$STAGE"
  fi
}
trap cleanup EXIT
unzip -q "$ZIP" -d "$STAGE"
SRC="$STAGE/chavevertical-lite"
test -d "$SRC"
test -z "$(find "$SRC" -type l -print -quit)"
for file in style.css functions.php index.php; do test -f "$SRC/$file"; done
find "$SRC" -type f -name '*.php' -print0 | xargs -0 -r -n1 php -l

if [[ "$MODE" == --check ]]; then
  echo "Archive, PHP syntax and destination permissions validated; no theme files changed."
  exit 0
fi

# Preserve a private copy of the previous theme before changing any files.
if [[ -d "$DEST" ]]; then
  BACKUP_DIR=/home/codex1/backups/github-theme
  mkdir -p "$BACKUP_DIR"
  BACKUP=$(mktemp "$BACKUP_DIR/chavevertical-lite-$(date -u +%Y%m%dT%H%M%SZ).XXXXXXXX.tar.gz")
  tar -czf "$BACKUP" -C "$THEMES" chavevertical-lite
  tar -tzf "$BACKUP" >/dev/null
  echo "Previous theme backup: $BACKUP"
fi

# Only this named theme is synchronized; group permissions allow PHP to read it.
mkdir -p "$DEST"
rsync -rp --delete --chmod=D2770,F660 "$SRC/" "$DEST/"
DIFF=$(rsync -rcn --delete --out-format='%i %n' "$SRC/" "$DEST/")
if [[ -n "$DIFF" ]]; then
  printf 'Deployed files differ from the validated package:\n%s\n' "$DIFF" >&2
  exit 1
fi
for file in style.css functions.php index.php; do test -f "$DEST/$file"; done
echo "Theme deployed and verified: $DEST"
if [[ -n "${GITHUB_STEP_SUMMARY:-}" ]]; then
  printf '### Chave Vertical Lite\n\nTheme deployed and verified at `%s`.\n\nCommit: `%s`\n' "$DEST" "${GITHUB_SHA:-manual}" >> "$GITHUB_STEP_SUMMARY"
fi
