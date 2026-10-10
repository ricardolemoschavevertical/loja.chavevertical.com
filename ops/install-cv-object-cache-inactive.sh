#!/usr/bin/env bash
# Instala exclusivamente os ficheiros de CV Object Cache 0.5.0.
# Nao ativa o plugin, nao instala o drop-in, nao altera Redis/APO/BD.
set -euo pipefail
umask 0022

WP_ROOT=/home/chavevertical-loja/htdocs/loja.chavevertical.com
PLUGINS="$WP_ROOT/wp-content/plugins"
DEST="$PLUGINS/cv-object-cache"
DROPIN="$WP_ROOT/wp-content/object-cache.php"
PACKAGE="$(realpath -- "${1:?Uso: install-cv-object-cache-inactive.sh cvoc-code.tar.xz}")"
EXPECTED_SHA256=b85df457cb739f4b198571c77e6d406c560739dc9dea6a11b05eb054096375f1

[[ "$(id -un)" == "codex1" ]] || { echo 'Runner inesperado' >&2; exit 1; }
[[ "$(realpath -- "$PLUGINS")" == "$PLUGINS" ]] || exit 1
[[ -f "$WP_ROOT/wp-load.php" ]] || exit 1
[[ -f "$PACKAGE" && -r "$PACKAGE" ]] || exit 1
[[ -d "$PLUGINS" && -w "$PLUGINS" ]] || exit 1
command -v wp >/dev/null
command -v php >/dev/null
printf '%s  %s\n' "$EXPECTED_SHA256" "$PACKAGE" | sha256sum -c -

if [[ -e "$DEST" || -L "$DEST" ]]; then
  echo 'CV Object Cache ja existe no servidor. Nenhum ficheiro foi substituido.' >&2
  exit 1
fi
if wp --path="$WP_ROOT" plugin is-active cv-object-cache >/dev/null 2>&1; then
  echo 'CV Object Cache ja esta ativo; instalacao recusada.' >&2
  exit 1
fi

if [[ -f "$DROPIN" ]]; then
  DROPIN_BEFORE="$(sha256sum "$DROPIN" | cut -d ' ' -f 1)"
else
  DROPIN_BEFORE=missing
fi

STAGE="$(mktemp -d "${TMPDIR:-/tmp}/cvoc-extract.XXXXXXXX")"
INCOMING=
cleanup() {
  if [[ -n "${INCOMING:-}" && -d "$INCOMING" && ! -L "$INCOMING" ]]; then
    rm -rf -- "$INCOMING"
  fi
  if [[ -d "$STAGE" && ! -L "$STAGE" ]]; then
    rm -rf -- "$STAGE"
  fi
}
trap cleanup EXIT

while IFS= read -r entry; do
  case "$entry" in
    cv-object-cache/*) ;;
    *) echo "Entrada inesperada no pacote: $entry" >&2; exit 1 ;;
  esac
  case "/$entry/" in
    */../*|*/./*|*\\*) echo 'Entrada insegura no pacote' >&2; exit 1 ;;
  esac
done < <(tar -tJf "$PACKAGE")

tar -xJf "$PACKAGE" -C "$STAGE" --no-same-owner --no-same-permissions
SRC="$STAGE/cv-object-cache"
[[ -d "$SRC" && -f "$SRC/cv-object-cache.php" ]] || exit 1
[[ -z "$(find "$SRC" -type l -print -quit)" ]] || exit 1
grep -q '^ \* Plugin Name: CV Object Cache$' "$SRC/cv-object-cache.php"
grep -q '^ \* Version: 0\.5\.0$' "$SRC/cv-object-cache.php"
while IFS= read -r -d '' file; do php -l "$file" >/dev/null; done < <(find "$SRC" -type f -name '*.php' -print0)

INCOMING="$(mktemp -d "$PLUGINS/.cv-object-cache-incoming.XXXXXXXX")"
cp -R "$SRC/." "$INCOMING/"
chmod -R u=rwX,g=rX,o=rX "$INCOMING"
[[ ! -e "$DEST" && ! -L "$DEST" ]] || exit 1
mv -T -- "$INCOMING" "$DEST"
INCOMING=

STATUS="$(wp --path="$WP_ROOT" plugin get cv-object-cache --field=status --quiet)"
[[ "$STATUS" == 'inactive' ]] || { echo "Estado inesperado: $STATUS" >&2; exit 1; }
if [[ -f "$DROPIN" ]]; then
  DROPIN_AFTER="$(sha256sum "$DROPIN" | cut -d ' ' -f 1)"
else
  DROPIN_AFTER=missing
fi
[[ "$DROPIN_AFTER" == "$DROPIN_BEFORE" ]] || { echo 'AVISO: drop-in mudou externamente durante o deploy' >&2; exit 1; }

echo "CV Object Cache instalado em $DEST"
echo "estado-wordpress=$STATUS"
echo 'dropin-existente=inalterado'
echo 'plugin-ativado=nao'
