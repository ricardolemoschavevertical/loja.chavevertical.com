<?php
/**
 * Read-only diagnostic for WooCommerce New Order email preview.
 * Runs in GitHub Actions on the verified Chave Vertical WooCommerce origin.
 */
declare(strict_types=1);
$root = '/home/chavevertical-loja/htdocs/loja.chavevertical.com';
if (PHP_SAPI !== 'cli' || ! is_readable($root . '/wp-load.php')) {
    fwrite(STDERR, "Invalid CLI environment.\n");
    exit(1);
}
defined('WP_ADMIN') || define('WP_ADMIN', true);
require_once $root . '/wp-load.php';
$home_host = wp_parse_url(home_url(), PHP_URL_HOST);
$site_host = wp_parse_url(site_url(), PHP_URL_HOST);
echo 'home_host=' . (string) $home_host . "\n";
echo 'site_host=' . (string) $site_host . "\n";
$allowed_hosts = array('loja.chavevertical.com', 'chavevertical.com');
if (! in_array($home_host, $allowed_hosts, true)
    || ! in_array($site_host, $allowed_hosts, true)) {
    fwrite(STDERR, "Unexpected WordPress origin; diagnostic refused.\n");
    exit(1);
}
// No test message may be sent during diagnosis.
add_filter('pre_wp_mail', static function ($preempt) { return true; }, PHP_INT_MAX);
echo "email_preview_diagnostic=read_only\n";
echo 'woocommerce_version=' . (defined('WC_VERSION') ? WC_VERSION : 'unavailable') . "\n";
echo 'php_version=' . PHP_VERSION . "\n";
$plugins = (array) get_option('active_plugins', array());
sort($plugins, SORT_STRING);
echo 'active_plugins=' . wp_json_encode($plugins, JSON_UNESCAPED_SLASHES) . "\n";
if (class_exists('Automattic\WooCommerce\Utilities\FeaturesUtil')) {
    echo 'email_improvements=' .
         (\Automattic\WooCommerce\Utilities\FeaturesUtil::feature_is_enabled('email_improvements')
             ? 'enabled' : 'disabled') . "\n";
}
foreach (array('woocommerce/emails/admin-new-order.php', 'woocommerce/emails/email-order-details.php') as $path) {
    echo 'template_' . basename($path) . '=' . (locate_template($path) ? 'custom' : 'standard') . "\n";
}
function cv_email_preview_report(Throwable $e): void {
    for ($d = 0; $e && $d < 3; $d++, $e = $e->getPrevious()) {
        $message = preg_replace('/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/', '[email]', $e->getMessage());
        echo "error[$d]=" . get_class($e) . ': ' . substr((string) $message, 0, 250) . "\n";
        echo 'location=' . str_replace(ABSPATH, '', $e->getFile()) . ':' . $e->getLine() . "\n";
        foreach (array_slice($e->getTrace(), 0, 10) as $i => $frame) {
            echo "frame[$i]=" . str_replace(ABSPATH, '', $frame['file'] ?? '') . ':'
                . ($frame['line'] ?? '?') . ' ' . ($frame['class'] ?? '') . ($frame['type'] ?? '')
                . ($frame['function'] ?? '') . "\n";
        }
    }
}
$class = 'Automattic\WooCommerce\Internal\Admin\EmailPreview\EmailPreview';
if (! class_exists($class) || ! function_exists('wc_get_container')) {
    echo "preview_api=missing\n";
    exit(0);
}
foreach (array('WC_Email_New_Order', 'WC_Email_Customer_Processing_Order') as $type) {
    $level = ob_get_level();
    ob_start();
    try {
        $preview = wc_get_container()->get($class);
        $preview->set_email_type($type);
        $html = $preview->render();
        while (ob_get_level() > $level) { ob_end_clean(); }
        echo "preview[$type]=ok:" . strlen((string) $html) . "\n";
    } catch (Throwable $e) {
        while (ob_get_level() > $level) { ob_end_clean(); }
        echo "preview[$type]=failed\n";
        cv_email_preview_report($e);
    }
}
