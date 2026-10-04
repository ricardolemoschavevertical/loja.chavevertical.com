<?php
declare(strict_types=1);

$root = '/home/chavevertical-loja/htdocs/loja.chavevertical.com';
require_once $root . '/wp-load.php';

$result = array(
    'home_url' => home_url('/'),
    'stylesheet' => get_stylesheet(),
    'taxonomy_exists' => taxonomy_exists('product_brand'),
    'brand_count' => taxonomy_exists('product_brand') ? wp_count_terms(array('taxonomy'=>'product_brand','hide_empty'=>false)) : null,
);

$page = get_page_by_path('marcas', OBJECT, 'page');

if ($page instanceof WP_Post) {
    $result['page'] = array(
        'id' => (int) $page->ID,
        'status' => $page->post_status,
        'slug' => $page->post_name,
        'permalink' => get_permalink($page->ID),
        'template' => get_post_meta($page->ID, '_wp_page_template', true),
    );
} else {
    $result['page'] = null;
}

$result['template_file_exists'] = is_file(get_stylesheet_directory() . '/page-marcas.php');

$target = isset($result['page']['permalink']) ? $result['page']['permalink'] : home_url('/?pagename=marcas');
$response = wp_remote_get($target, array('timeout' => 20, 'redirection' => 3));

if (is_wp_error($response)) {
    $result['http_error'] = $response->get_error_message();
} else {
    $body = (string) wp_remote_retrieve_body($response);
    $result['http_status'] = (int) wp_remote_retrieve_response_code($response);
    $result['renders_brands_template'] = false !== strpos($body, 'cvl-brands-page');
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
