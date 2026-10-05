#!/usr/bin/env bash
set -euo pipefail

WP_ROOT=/home/chavevertical-loja/htdocs/loja.chavevertical.com
cd "$WP_ROOT"

test_case() {
  local label="$1"
  echo
  echo "===== $label ====="
  wp --path="$WP_ROOT" eval '
    $id = wp_insert_post([
      "post_type" => "product",
      "post_status" => "draft",
      "post_title" => "CV TRASH PLUGIN TEST " . gmdate("YmdHis"),
    ], true);

    if (is_wp_error($id)) {
      fwrite(STDERR, "CREATE ERROR: " . $id->get_error_message() . PHP_EOL);
      exit(10);
    }

    echo "id={$id}\n";

    try {
      $r = wp_trash_post($id);
      echo "trash=" . ($r instanceof WP_Post ? $r->post_status : var_export($r, true)) . "\n";

      $r = wp_untrash_post($id);
      echo "restore=" . ($r instanceof WP_Post ? $r->post_status : var_export($r, true)) . "\n";

      $r = wp_trash_post($id);
      echo "trash2=" . ($r instanceof WP_Post ? $r->post_status : var_export($r, true)) . "\n";

      $r = wp_delete_post($id, true);
      echo "delete=" . ($r instanceof WP_Post ? "WP_Post" : var_export($r, true)) . "\n";
      echo "exists=" . (get_post($id) ? "yes" : "no") . "\n";
    } catch (Throwable $e) {
      fwrite(STDERR, "THROWABLE " . get_class($e) . ": " . $e->getMessage() . PHP_EOL);
      fwrite(STDERR, $e->getTraceAsString() . PHP_EOL);
      if (get_post($id)) {
        wp_delete_post($id, true);
      }
      exit(20);
    }

    if (get_post($id)) {
      wp_delete_post($id, true);
    }
  '
}

astro_was_active=0
r2_was_active=0
wp --path="$WP_ROOT" plugin is-active cv-astro-bridge >/dev/null 2>&1 && astro_was_active=1 || true
wp --path="$WP_ROOT" plugin is-active cv-r2-media-linker >/dev/null 2>&1 && r2_was_active=1 || true

cleanup() {
  if [[ "$astro_was_active" -eq 1 ]]; then
    wp --path="$WP_ROOT" plugin activate cv-astro-bridge >/dev/null 2>&1 || true
  fi
  if [[ "$r2_was_active" -eq 1 ]]; then
    wp --path="$WP_ROOT" plugin activate cv-r2-media-linker >/dev/null 2>&1 || true
  fi
}
trap cleanup EXIT

echo "Initial plugin states:"
wp --path="$WP_ROOT" plugin status cv-astro-bridge || true
wp --path="$WP_ROOT" plugin status cv-r2-media-linker || true

test_case "BOTH ACTIVE"

if [[ "$astro_was_active" -eq 1 ]]; then
  wp --path="$WP_ROOT" plugin deactivate cv-astro-bridge
  test_case "ASTRO OFF / R2 ON"
  wp --path="$WP_ROOT" plugin activate cv-astro-bridge
fi

if [[ "$r2_was_active" -eq 1 ]]; then
  wp --path="$WP_ROOT" plugin deactivate cv-r2-media-linker
  test_case "ASTRO ON / R2 OFF"
  wp --path="$WP_ROOT" plugin activate cv-r2-media-linker
fi

echo
echo "Recent PHP/WP errors:"
for f in "$WP_ROOT/wp-content/debug.log" /var/log/php*-fpm.log /var/log/nginx/error.log; do
  if [[ -f "$f" ]]; then
    echo "--- $f ---"
    tail -n 100 "$f" | grep -Ei "fatal|error|uncaught|trash|untrash|delete|cv-astro|cvr2|woocommerce" || true
  fi
done
