<?php
declare(strict_types=1);

$root = '/home/chavevertical-loja/htdocs/loja.chavevertical.com';
require_once $root . '/wp-load.php';

if ( ! function_exists( 'wc_get_product' ) ) {
    fwrite(STDERR, "WooCommerce unavailable\n");
    exit(2);
}

function cv_diag_step(string $label, callable $fn): void {
    echo "\n=== {$label} ===\n";
    try {
        $result = $fn();
        if (is_wp_error($result)) {
            echo "WP_Error: " . $result->get_error_code() . " | " . $result->get_error_message() . "\n";
        } elseif ($result instanceof WP_Post) {
            echo "OK WP_Post #{$result->ID} status={$result->post_status}\n";
        } else {
            echo "OK result=" . var_export($result, true) . "\n";
        }
    } catch (Throwable $e) {
        echo "THROWABLE " . get_class($e) . ": " . $e->getMessage() . "\n";
        echo $e->getTraceAsString() . "\n";
        throw $e;
    }
}

$id = 0;
try {
    cv_diag_step('create draft product', function() use (&$id) {
        $id = wp_insert_post([
            'post_type' => 'product',
            'post_status' => 'draft',
            'post_title' => 'CV TRASH DIAGNOSTIC ' . gmdate('YmdHis'),
        ], true);
        return $id;
    });

    if (!$id || is_wp_error($id)) {
        throw new RuntimeException('Could not create diagnostic product');
    }

    echo "Diagnostic product ID={$id}\n";

    cv_diag_step('trash', fn() => wp_trash_post($id));
    cv_diag_step('restore', fn() => wp_untrash_post($id));
    cv_diag_step('trash again', fn() => wp_trash_post($id));
    cv_diag_step('permanent delete', fn() => wp_delete_post($id, true));

    echo "\nFINAL exists=" . (get_post($id) ? 'yes' : 'no') . "\n";
} finally {
    if ($id && get_post($id)) {
        echo "\nCleanup forcing delete for #{$id}\n";
        wp_delete_post($id, true);
    }
}
