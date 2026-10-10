<?php
// Self-contained CLI regression tests: never call WooCommerce or Cloudflare.
define('ABSPATH', __DIR__);
$GLOBALS['cv_test_options'] = [];
$GLOBALS['cv_test_expected'] = str_repeat('A', 80);
$GLOBALS['cv_test_seen'] = [];
$GLOBALS['cv_test_force_status'] = 0;

class WP_Error {
    private $error_code;
    private $error_message;
    public function __construct($code, $message, $data = null) {
        $this->error_code = $code;
        $this->error_message = $message;
    }
    public function get_error_message() { return $this->error_message; }
}
function is_wp_error($v) { return $v instanceof WP_Error; }
function add_action() {}
function get_option($key, $default = false) {
    return array_key_exists($key, $GLOBALS['cv_test_options']) ? $GLOBALS['cv_test_options'][$key] : $default;
}
function update_option($key, $value, $autoload = null) {
    $GLOBALS['cv_test_options'][$key] = $value;
    return true;
}
function delete_option($key) {
    unset($GLOBALS['cv_test_options'][$key]);
    return true;
}
function wp_salt($key) { return str_repeat('test-salt-'.(string)$key, 6); }
function esc_url_raw($url) { return filter_var((string)$url, FILTER_SANITIZE_URL); }
function untrailingslashit($s) { return rtrim((string)$s, '/'); }
function wp_parse_url($u, $component = -1) { return parse_url($u, $component); }
function wp_http_validate_url($u) { return filter_var($u, FILTER_VALIDATE_URL) !== false; }
function wp_remote_get($url, $args = []) {
    $GLOBALS['cv_test_seen'][] = ['url'=>$url, 'args'=>$args];
    if ((int)$GLOBALS['cv_test_force_status'] !== 0) {
        return ['status'=>(int)$GLOBALS['cv_test_force_status'], 'body'=>''];
    }
    if (strpos($url, 'https://cv-ai-enricher.') !== 0) return ['status'=>403,'body'=>''];
    $given = (string)($args['headers']['X-CV-AI-Token'] ?? '');
    $status = hash_equals($GLOBALS['cv_test_expected'], $given) ? 200 : 401;
    return ['status'=>$status,'body'=>$status===200 ? '{"ok":true,"lists":[]}' : '{"ok":false}'];
}
function wp_remote_request($url, $args) { return wp_remote_get($url, $args); }
function wp_remote_retrieve_response_code($r) { return $r['status']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
function wp_json_encode($v) { return json_encode($v); }
function wp_unslash($v) { return $v; }
function current_user_can($v) { return $v === 'manage_options'; }
function check_admin_referer($a, $f = null) { return true; }
function nocache_headers() {}
function sanitize_key($k) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', $k)); }
function esc_attr($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function esc_html($v) { return esc_attr($v); }
function esc_url($v) { return esc_attr($v); }
function admin_url($url) { return 'https://loja.chavevertical.com/wp-admin/'.$url; }
function wp_nonce_field($a, $f) { echo '<input type="hidden" name="'.esc_attr($f).'">'; }
function submit_button($s, $c = 'primary') { echo '<button>'.esc_html($s).'</button>'; }
function assert_cv($condition, $message) {
    if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
}

require __DIR__.'/../../wp-content/plugins/cv-cloudflare-ai-enricher/cv-cloudflare-ai-enricher.php';
$main = 'CV_Cloudflare_AI_Enricher';
$secret = 'CV_CFAI_Secrets';
$default = $main::DEFAULT_WORKER_URL;
assert_cv($main::worker_url() === $default, 'Worker URL defaults to actual Cloudflare Worker');
assert_cv(is_wp_error($main::validate_worker_url('https://example.com')), 'Reject arbitrary URL (token exfiltration)');
assert_cv(is_wp_error($main::validate_worker_url($default.'/unexpected')), 'Reject Worker subpaths');
assert_cv(is_wp_error($main::validate_worker_url($default.'?token=test')), 'Reject query string');
assert_cv(is_wp_error($main::validate_worker_url('http://cv-ai-enricher.chavevertical.workers.dev')), 'Reject HTTP');
assert_cv(count($GLOBALS['cv_test_seen'])===0, 'Invalid URLs do not trigger remote requests');

$token = str_repeat('A', 80);
assert_cv($secret::save_worker_token($token)===true, 'Store Worker token with libsodium');
$cipher = get_option($secret::ENCRYPTED_OPTION);
assert_cv(is_string($cipher) && strpos($cipher,$token)===false, 'Database stores ciphertext, not token');
assert_cv($secret::has_saved_worker_token(), 'Saved token availability');
define('CV_CFAI_TOKEN',str_repeat('Z',80));
define('CV_CFAI_WORKER_URL',$default);
assert_cv($secret::open()===$token, 'Plugin token takes precedence over wp-config legacy constant');
assert_cv($main::probe_worker($default,$token)===true, 'Authenticated probe succeeds');
assert_cv($GLOBALS['cv_test_seen'][0]['args']['redirection']===0, 'Redirects disabled when sending token');
assert_cv($GLOBALS['cv_test_seen'][0]['args']['sslverify']===true, 'TLS certificate verification enabled');
assert_cv(is_wp_error($main::probe_worker($default,str_repeat('B',80))), 'Bad token refused');
$GLOBALS['cv_test_force_status']=302;
assert_cv(is_wp_error($main::probe_worker($default,$token)), '3xx redirect rejected');
$GLOBALS['cv_test_force_status']=0;

$previous_cipher=get_option($secret::ENCRYPTED_OPTION);
$_POST=[
    'cv_cfai_admin_action'=>'save_worker_connection',
    'cv_cfai_nonce'=>'stub',
    'worker_url'=>$default,
    'worker_shared_token'=>str_repeat('B',80)
];
CV_CFAI_Admin::process();
assert_cv(get_option($secret::ENCRYPTED_OPTION)===$previous_cipher, 'Wrong secret never overwrites saved token');
assert_cv(get_option($main::SETTINGS,[])===[], 'Wrong secret never changes URL');

$GLOBALS['cv_test_expected']=str_repeat('C',80);
$_POST['worker_shared_token']=$GLOBALS['cv_test_expected'];
CV_CFAI_Admin::process();
assert_cv($secret::open()===$GLOBALS['cv_test_expected'], 'Correct secret saved from the app');
assert_cv(get_option($main::SETTINGS)['worker_url']===$default, 'URL saved from the app');
assert_cv($main::probe_worker($main::worker_url(),$secret::open())===true, 'Saved settings work end-to-end');

$_POST['worker_shared_token']='';
$_POST['worker_url']='https://cv-ai-enricher.chavevertical.com';
CV_CFAI_Admin::process();
assert_cv($main::worker_url()==='https://cv-ai-enricher.chavevertical.com', 'Admin URL overrides legacy wp-config');
assert_cv($secret::open()===$GLOBALS['cv_test_expected'], 'Empty password preserves current secret');

$method=new ReflectionMethod('CV_CFAI_Admin','cloudflare');
$method->setAccessible(true);
ob_start();
$method->invoke(null,$main::worker_url(),true);
$html=ob_get_clean();
assert_cv(strpos($html,'name="worker_url"')!==false, 'Worker URL shown on admin panel');
assert_cv(strpos($html,'name="worker_shared_token"')!==false, 'Worker token password shown on admin panel');
assert_cv(strpos($html,$GLOBALS['cv_test_expected'])===false, 'Secret never rendered in admin HTML');
assert_cv(strpos($html,'Guardar e testar ligação')!==false, 'Save and test action present');

echo "CV AI Worker admin settings: PASS\n";
