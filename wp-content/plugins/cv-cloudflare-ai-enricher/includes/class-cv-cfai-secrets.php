<?php
/**
 * Cloudflare transport and credential security.
 * Only the ciphertext of the Worker shared token is saved in wp_options.
 * Gemini keys and Cloudflare API tokens are never stored in WordPress by
 * the CV AI 0.4 integration: they go directly to Cloudflare Secrets.
 */
defined('ABSPATH') || exit;

final class CV_CFAI_Secrets {
    const WORKER = 'cv-ai-enricher';
    const ACCOUNT = 'bbff198a7a2f49bace56e0a681d02fab';
    const ENCRYPTED_OPTION = 'cv_cfai_worker_auth_cipher_v1';
    const MCP_HASH_OPTION = 'cv_mcp_bridge_token_sha256';

    private static function key() {
        if (!function_exists('wp_salt')) return null;
        $salt = wp_salt('auth') . wp_salt('secure_auth') . wp_salt('logged_in');
        if (strlen($salt) < 45 || !function_exists('sodium_crypto_secretbox')) return null;
        return hash('sha256', 'chavevertical:cv-ai:worker:v1:' . $salt, true);
    }

    public static function seal($plaintext) {
        $key = self::key();
        if (!$key || !is_string($plaintext) || strlen($plaintext) < 40) {
            return new WP_Error('cv_cfai_crypto','O servidor não dispõe da cifragem necessária.');
        }
        try {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $encrypted = sodium_crypto_secretbox($plaintext, $nonce, $key);
            return base64_encode($nonce . $encrypted);
        } catch (Throwable $e) {
            return new WP_Error('cv_cfai_crypto','Não foi possível cifrar a credencial.');
        }
    }

    public static function open() {
        if (defined('CV_CFAI_TOKEN') && strlen((string) CV_CFAI_TOKEN) >= 40) {
            return (string) CV_CFAI_TOKEN;
        }
        $encoded = get_option(self::ENCRYPTED_OPTION, '');
        if (!is_string($encoded) || $encoded === '') return '';
        $key = self::key();
        if (!$key) return '';
        $raw = base64_decode($encoded, true);
        if (!is_string($raw) || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return '';
        try {
            $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $value = sodium_crypto_secretbox_open($cipher, $nonce, $key);
            return is_string($value) && strlen($value) >= 40 ? $value : '';
        } catch (Throwable $e) { return ''; }
    }

    /**
     * Ephemeral Cloudflare API token: submitted by the admin, sent to
     * Cloudflare over HTTPS and discarded. Never saved as an option.
     */
    public static function put_cloudflare_secret($api_token, $name, $value) {
        if (!is_string($api_token) || strlen($api_token) < 20
            || strlen($api_token) > 1024 || preg_match('/\s/', $api_token)
            || !in_array($name, ['CV_AI_TOKEN','GEMINI_API_KEYS_JSON','CV_AI_CONFIG_JSON'], true)
            || !is_string($value) || $value === '') {
            return new WP_Error('cv_cfai_input','Credencial ou parâmetro Cloudflare inválido.');
        }
        $url = 'https://api.cloudflare.com/client/v4/accounts/' . self::ACCOUNT .
            '/workers/scripts/' . self::WORKER . '/secrets';
        $response = wp_remote_request($url, [
            'method' => 'PUT', 'timeout' => 25, 'redirection' => 0,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode(['name'=>$name,'type'=>'secret_text','text'=>$value])
        ]);
        if (is_wp_error($response)) {
            return new WP_Error('cv_cfai_transport','A Cloudflare não pôde ser contactada.');
        }
        $status = (int) wp_remote_retrieve_response_code($response);
        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        if ($status < 200 || $status >= 300 || !is_array($decoded) || empty($decoded['success'])) {
            return new WP_Error('cv_cfai_cloudflare','A Cloudflare recusou a operação (HTTP '.$status.').');
        }
        return true;
    }

    public static function pair_worker($api_token) {
        if (defined('CV_CFAI_TOKEN') && strlen((string) CV_CFAI_TOKEN) >= 40) {
            return new WP_Error('cv_cfai_constant','A ligação é gerida pelo wp-config.php. Não é possível alterar a partir do painel.');
        }
        $value = bin2hex(random_bytes(40));
        $cipher = self::seal($value);
        if (is_wp_error($cipher)) return $cipher;
        $sent = self::put_cloudflare_secret($api_token, 'CV_AI_TOKEN', $value);
        if (is_wp_error($sent)) return $sent;
        update_option(self::ENCRYPTED_OPTION, $cipher, false);
        if (!hash_equals($value, self::open())) {
            return new WP_Error('cv_cfai_save','O Worker foi atualizado, mas não foi possível guardar o segredo cifrado no WordPress.');
        }
        return true;
    }

    public static function mcp_token_ready() {
        if (defined('CV_MCP_BRIDGE_TOKEN') && strlen((string) CV_MCP_BRIDGE_TOKEN) >= 40) return true;
        $hash = get_option(self::MCP_HASH_OPTION, '');
        return is_string($hash) && preg_match('/^[a-f0-9]{64}$/D', $hash);
    }

    public static function rotate_mcp_token() {
        $token = 'cvmcp_' . bin2hex(random_bytes(36));
        update_option(self::MCP_HASH_OPTION, hash('sha256', $token), false);
        return $token;
    }

    public static function revoke_mcp_token() {
        delete_option(self::MCP_HASH_OPTION);
    }

    public static function verify_mcp_token($token) {
        if (!is_string($token) || strlen($token) < 40 || strlen($token) > 256) return false;
        if (defined('CV_MCP_BRIDGE_TOKEN') && strlen((string) CV_MCP_BRIDGE_TOKEN) >= 40) {
            return hash_equals((string) CV_MCP_BRIDGE_TOKEN, $token);
        }
        $expected = (string) get_option(self::MCP_HASH_OPTION, '');
        return (bool) preg_match('/^[a-f0-9]{64}$/D', $expected)
            && hash_equals($expected, hash('sha256', $token));
    }
}
