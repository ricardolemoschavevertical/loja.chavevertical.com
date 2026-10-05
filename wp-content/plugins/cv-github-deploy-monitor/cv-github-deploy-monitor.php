<?php
/**
 * Plugin Name: Chave Vertical — GitHub Deploy Monitor
 * Description: Monitoriza GitHub Actions, Cloudflare Pages e checks de deploy dos projetos Chave Vertical diretamente no WordPress.
 * Version: 1.0.0
 * Author: Chave Vertical
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

final class CV_GitHub_Deploy_Monitor {
    const VERSION = '1.0.0';
    const OPTION_TOKEN = 'cvgdm_github_token';
    const CRON_HOOK = 'cvgdm_refresh_status';
    const CACHE_TTL = 120;

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'wp_dashboard_setup', array( $this, 'dashboard_widget' ) );
        add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
        add_action( self::CRON_HOOK, array( $this, 'prime_cache' ) );
        add_action( 'admin_post_cvgdm_save_settings', array( $this, 'save_settings' ) );
        add_action( 'admin_post_cvgdm_refresh', array( $this, 'manual_refresh' ) );
    }

    public static function activate() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + 60, 'cvgdm_five_minutes', self::CRON_HOOK );
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    public function cron_schedules( $schedules ) {
        $schedules['cvgdm_five_minutes'] = array(
            'interval' => 300,
            'display'  => __( 'A cada 5 minutos', 'cv-github-deploy-monitor' ),
        );

        return $schedules;
    }

    public function repositories() {
        $repositories = array(
            array(
                'label'  => 'Woo / Loja',
                'repo'   => 'ricardolemoschavevertical/loja.chavevertical.com',
                'branch' => 'main',
                'url'    => 'https://github.com/ricardolemoschavevertical/loja.chavevertical.com',
            ),
            array(
                'label'  => 'Astro',
                'repo'   => 'ricardolemoschavevertical/chavevertical.com',
                'branch' => 'main',
                'url'    => 'https://github.com/ricardolemoschavevertical/chavevertical.com',
            ),
        );

        return apply_filters( 'cvgdm_repositories', $repositories );
    }

    private function token() {
        if ( defined( 'CV_GITHUB_DEPLOY_TOKEN' ) && CV_GITHUB_DEPLOY_TOKEN ) {
            return trim( (string) CV_GITHUB_DEPLOY_TOKEN );
        }

        $token = get_option( self::OPTION_TOKEN, '' );

        return is_string( $token ) ? trim( $token ) : '';
    }

    private function cache_key( $repository ) {
        return 'cvgdm_' . md5( strtolower( $repository ) );
    }

    private function clear_cache() {
        foreach ( $this->repositories() as $repository ) {
            delete_transient( $this->cache_key( $repository['repo'] ) );
        }
    }

    private function github_request( $path ) {
        $token = $this->token();

        if ( '' === $token ) {
            return new WP_Error(
                'missing_token',
                __( 'Token GitHub em falta. Configure um token de leitura para consultar repositórios privados.', 'cv-github-deploy-monitor' )
            );
        }

        $headers = array(
            'Accept'               => 'application/vnd.github+json',
            'Authorization'        => 'Bearer ' . $token,
            'User-Agent'           => 'ChaveVertical-GitHub-Deploy-Monitor/' . self::VERSION,
            'X-GitHub-Api-Version' => '2022-11-28',
        );

        $response = wp_remote_get(
            'https://api.github.com' . $path,
            array(
                'headers'     => $headers,
                'timeout'     => 15,
                'redirection' => 2,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code >= 300 ) {
            $message = is_array( $body ) && ! empty( $body['message'] )
                ? (string) $body['message']
                : sprintf( 'GitHub API HTTP %d', $code );

            return new WP_Error( 'github_api_error', $message, array( 'status' => $code ) );
        }

        return is_array( $body ) ? $body : array();
    }

    private function repo_path( $repository ) {
        $parts = explode( '/', $repository, 2 );

        if ( 2 !== count( $parts ) ) {
            return '';
        }

        return rawurlencode( $parts[0] ) . '/' . rawurlencode( $parts[1] );
    }

    public function get_repository_status( $repository, $force = false ) {
        $repo   = $repository['repo'];
        $branch = ! empty( $repository['branch'] ) ? $repository['branch'] : 'main';
        $key    = $this->cache_key( $repo );

        if ( ! $force ) {
            $cached = get_transient( $key );

            if ( is_array( $cached ) ) {
                $cached['cache'] = 'HIT';
                return $cached;
            }
        }

        $repo_path = $this->repo_path( $repo );

        if ( '' === $repo_path ) {
            return array(
                'ok'         => false,
                'repository' => $repository,
                'error'      => 'Repositório inválido.',
                'items'      => array(),
            );
        }

        $runs = $this->github_request(
            '/repos/' . $repo_path . '/actions/runs?branch=' . rawurlencode( $branch ) . '&per_page=30'
        );

        if ( is_wp_error( $runs ) ) {
            return array(
                'ok'         => false,
                'repository' => $repository,
                'error'      => $runs->get_error_message(),
                'items'      => array(),
            );
        }

        $branch_data = $this->github_request(
            '/repos/' . $repo_path . '/branches/' . rawurlencode( $branch )
        );

        $head_sha = '';
        if ( ! is_wp_error( $branch_data ) ) {
            $head_sha = isset( $branch_data['commit']['sha'] ) ? (string) $branch_data['commit']['sha'] : '';
        }

        $checks = array();
        if ( $head_sha ) {
            $check_data = $this->github_request(
                '/repos/' . $repo_path . '/commits/' . rawurlencode( $head_sha ) . '/check-runs?per_page=100'
            );

            if ( ! is_wp_error( $check_data ) && ! empty( $check_data['check_runs'] ) && is_array( $check_data['check_runs'] ) ) {
                $checks = $check_data['check_runs'];
            }
        }

        $items = array();

        foreach ( isset( $runs['workflow_runs'] ) && is_array( $runs['workflow_runs'] ) ? $runs['workflow_runs'] : array() as $run ) {
            $name = isset( $run['name'] ) ? (string) $run['name'] : 'GitHub Actions';

            if ( ! $this->is_deploy_related( $name ) ) {
                continue;
            }

            $items[] = array(
                'id'         => 'run-' . ( isset( $run['id'] ) ? (string) $run['id'] : wp_generate_uuid4() ),
                'source'     => 'GitHub Actions',
                'name'       => $name,
                'status'     => isset( $run['status'] ) ? (string) $run['status'] : '',
                'conclusion' => isset( $run['conclusion'] ) ? (string) $run['conclusion'] : '',
                'sha'        => isset( $run['head_sha'] ) ? (string) $run['head_sha'] : '',
                'branch'     => isset( $run['head_branch'] ) ? (string) $run['head_branch'] : $branch,
                'url'        => isset( $run['html_url'] ) ? (string) $run['html_url'] : '',
                'created_at' => isset( $run['created_at'] ) ? (string) $run['created_at'] : '',
                'updated_at' => isset( $run['updated_at'] ) ? (string) $run['updated_at'] : '',
                'number'     => isset( $run['run_number'] ) ? (int) $run['run_number'] : 0,
            );
        }

        foreach ( $checks as $check ) {
            $name = isset( $check['name'] ) ? (string) $check['name'] : 'Check';

            if ( ! $this->is_deploy_related( $name ) ) {
                continue;
            }

            $items[] = array(
                'id'         => 'check-' . ( isset( $check['id'] ) ? (string) $check['id'] : wp_generate_uuid4() ),
                'source'     => 'Commit check',
                'name'       => $name,
                'status'     => isset( $check['status'] ) ? (string) $check['status'] : '',
                'conclusion' => isset( $check['conclusion'] ) ? (string) $check['conclusion'] : '',
                'sha'        => $head_sha,
                'branch'     => $branch,
                'url'        => isset( $check['details_url'] ) ? (string) $check['details_url'] : '',
                'created_at' => isset( $check['started_at'] ) ? (string) $check['started_at'] : '',
                'updated_at' => isset( $check['completed_at'] ) && $check['completed_at']
                    ? (string) $check['completed_at']
                    : ( isset( $check['started_at'] ) ? (string) $check['started_at'] : '' ),
                'number'     => 0,
            );
        }

        usort(
            $items,
            static function ( $a, $b ) {
                return strcmp( (string) $b['created_at'], (string) $a['created_at'] );
            }
        );

        $latest_by_name = array();
        foreach ( $items as $item ) {
            $identity = strtolower( $item['source'] . '|' . $item['name'] );
            if ( ! isset( $latest_by_name[ $identity ] ) ) {
                $latest_by_name[ $identity ] = $item;
            }
        }

        $overall = $this->overall_status( array_values( $latest_by_name ) );

        $result = array(
            'ok'         => true,
            'repository' => $repository,
            'head_sha'   => $head_sha,
            'overall'    => $overall,
            'items'      => array_slice( $items, 0, 20 ),
            'updated_at' => gmdate( 'c' ),
            'cache'      => 'MISS',
        );

        set_transient( $key, $result, self::CACHE_TTL );

        return $result;
    }

    private function is_deploy_related( $name ) {
        return (bool) preg_match(
            '/deploy|package|build|cloudflare|worker|pages|validate\s+astro|production/i',
            (string) $name
        );
    }

    private function overall_status( $items ) {
        if ( empty( $items ) ) {
            return 'unknown';
        }

        foreach ( $items as $item ) {
            if ( in_array( $item['status'], array( 'queued', 'pending', 'in_progress', 'waiting', 'requested' ), true ) ) {
                return 'in_progress';
            }
        }

        foreach ( $items as $item ) {
            if ( in_array( $item['conclusion'], array( 'failure', 'timed_out', 'action_required', 'startup_failure' ), true ) ) {
                return 'failure';
            }
        }

        foreach ( $items as $item ) {
            if ( 'cancelled' === $item['conclusion'] ) {
                return 'cancelled';
            }
        }

        return 'success';
    }

    private function display_state( $status, $conclusion = '' ) {
        $key = $conclusion ? $conclusion : $status;

        $labels = array(
            'queued'          => array( 'Em fila', 'queued' ),
            'pending'         => array( 'Em fila', 'queued' ),
            'requested'       => array( 'Em fila', 'queued' ),
            'waiting'         => array( 'Em espera', 'queued' ),
            'in_progress'     => array( 'Em execução', 'running' ),
            'success'         => array( 'Sucesso', 'success' ),
            'failure'         => array( 'Falhou', 'failure' ),
            'timed_out'       => array( 'Tempo esgotado', 'failure' ),
            'action_required' => array( 'Ação necessária', 'failure' ),
            'startup_failure' => array( 'Falha ao iniciar', 'failure' ),
            'cancelled'       => array( 'Cancelado', 'cancelled' ),
            'skipped'         => array( 'Ignorado', 'muted' ),
            'neutral'         => array( 'Neutro', 'muted' ),
            'stale'           => array( 'Obsoleto', 'muted' ),
            'unknown'         => array( 'Desconhecido', 'muted' ),
        );

        return isset( $labels[ $key ] ) ? $labels[ $key ] : array( ucfirst( str_replace( '_', ' ', $key ) ), 'muted' );
    }

    private function format_time( $iso ) {
        if ( ! $iso ) {
            return '—';
        }

        $timestamp = strtotime( $iso );
        if ( ! $timestamp ) {
            return '—';
        }

        return wp_date( 'd/m/Y H:i:s', $timestamp );
    }

    private function short_sha( $sha ) {
        return $sha ? substr( $sha, 0, 8 ) : '—';
    }

    public function prime_cache() {
        if ( ! $this->token() ) {
            return;
        }

        foreach ( $this->repositories() as $repository ) {
            $this->get_repository_status( $repository, true );
        }
    }

    public function admin_menu() {
        add_menu_page(
            __( 'Deploys GitHub', 'cv-github-deploy-monitor' ),
            __( 'Deploys GitHub', 'cv-github-deploy-monitor' ),
            'manage_options',
            'cv-github-deploy-monitor',
            array( $this, 'render_admin_page' ),
            'dashicons-update-alt',
            58
        );
    }

    public function save_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sem permissões.', 'cv-github-deploy-monitor' ) );
        }

        check_admin_referer( 'cvgdm_save_settings' );

        if ( ! defined( 'CV_GITHUB_DEPLOY_TOKEN' ) ) {
            if ( ! empty( $_POST['clear_token'] ) ) {
                delete_option( self::OPTION_TOKEN );
            } elseif ( isset( $_POST['github_token'] ) && '' !== trim( (string) wp_unslash( $_POST['github_token'] ) ) ) {
                update_option(
                    self::OPTION_TOKEN,
                    sanitize_text_field( wp_unslash( $_POST['github_token'] ) ),
                    false
                );
            }
        }

        $this->clear_cache();

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'    => 'cv-github-deploy-monitor',
                    'updated' => '1',
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    public function manual_refresh() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sem permissões.', 'cv-github-deploy-monitor' ) );
        }

        check_admin_referer( 'cvgdm_refresh' );
        $this->clear_cache();
        $this->prime_cache();

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'      => 'cv-github-deploy-monitor',
                    'refreshed' => '1',
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    public function dashboard_widget() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        wp_add_dashboard_widget(
            'cvgdm_dashboard',
            __( 'Deploys GitHub — Chave Vertical', 'cv-github-deploy-monitor' ),
            array( $this, 'render_dashboard_widget' )
        );
    }

    public function render_dashboard_widget() {
        echo '<div class="cvgdm-dashboard">';

        if ( ! $this->token() ) {
            echo '<p><strong>Token GitHub em falta.</strong></p>';
            echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=cv-github-deploy-monitor' ) ) . '">Configurar monitor</a></p>';
            echo '</div>';
            return;
        }

        foreach ( $this->repositories() as $repository ) {
            $status = $this->get_repository_status( $repository );
            $state  = $status['ok'] ? $this->display_state( $status['overall'] ) : array( 'Erro', 'failure' );

            echo '<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:9px 0;border-bottom:1px solid #eee;">';
            echo '<strong>' . esc_html( $repository['label'] ) . '</strong>';
            echo '<span class="cvgdm-pill cvgdm-' . esc_attr( $state[1] ) . '">' . esc_html( $state[0] ) . '</span>';
            echo '</div>';
        }

        echo '<p style="margin:12px 0 0;"><a href="' . esc_url( admin_url( 'admin.php?page=cv-github-deploy-monitor' ) ) . '">Ver detalhes dos deploys →</a></p>';
        echo '</div>';
        echo $this->shared_styles(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $has_constant = defined( 'CV_GITHUB_DEPLOY_TOKEN' ) && CV_GITHUB_DEPLOY_TOKEN;
        $has_token    = (bool) $this->token();

        echo '<div class="wrap cvgdm-wrap">';
        echo '<h1>Deploys GitHub</h1>';
        echo '<p class="description">Estado dos deploys GitHub Actions e dos checks externos, incluindo Cloudflare Pages e Workers Builds.</p>';

        if ( isset( $_GET['updated'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>Configuração atualizada.</p></div>';
        }
        if ( isset( $_GET['refreshed'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>Estados atualizados agora.</p></div>';
        }

        echo '<div class="cvgdm-toolbar">';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="cvgdm_refresh">';
        wp_nonce_field( 'cvgdm_refresh' );
        submit_button( 'Atualizar agora', 'primary', 'submit', false );
        echo '</form>';
        echo '<span>Cache: ' . esc_html( self::CACHE_TTL ) . ' segundos · atualização automática de fundo: 5 minutos</span>';
        echo '</div>';

        if ( ! $has_token ) {
            echo '<div class="notice notice-warning"><p><strong>É necessário configurar um token GitHub.</strong> Os repositórios monitorizados são privados.</p></div>';
        }

        echo '<div class="cvgdm-grid">';
        foreach ( $this->repositories() as $repository ) {
            $status = $this->get_repository_status( $repository );
            $state  = $status['ok'] ? $this->display_state( $status['overall'] ) : array( 'Erro', 'failure' );

            echo '<section class="cvgdm-repo-card">';
            echo '<div class="cvgdm-repo-head">';
            echo '<div><h2>' . esc_html( $repository['label'] ) . '</h2><a href="' . esc_url( $repository['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $repository['repo'] ) . '</a></div>';
            echo '<span class="cvgdm-pill cvgdm-' . esc_attr( $state[1] ) . '">' . esc_html( $state[0] ) . '</span>';
            echo '</div>';

            if ( ! $status['ok'] ) {
                echo '<div class="cvgdm-error">' . esc_html( $status['error'] ) . '</div>';
                echo '</section>';
                continue;
            }

            echo '<div class="cvgdm-meta">';
            echo '<span><strong>Branch</strong> ' . esc_html( $repository['branch'] ) . '</span>';
            echo '<span><strong>HEAD</strong> <code>' . esc_html( $this->short_sha( $status['head_sha'] ) ) . '</code></span>';
            echo '<span><strong>Consulta</strong> ' . esc_html( $status['cache'] ) . '</span>';
            echo '</div>';

            echo '<div class="cvgdm-table-wrap"><table class="widefat striped cvgdm-table">';
            echo '<thead><tr><th>Estado</th><th>Deploy / Check</th><th>Commit</th><th>Atualizado</th><th></th></tr></thead><tbody>';

            if ( empty( $status['items'] ) ) {
                echo '<tr><td colspan="5">Sem deploys/checks encontrados.</td></tr>';
            } else {
                foreach ( $status['items'] as $item ) {
                    $item_state = $this->display_state( $item['status'], $item['conclusion'] );
                    echo '<tr>';
                    echo '<td><span class="cvgdm-pill cvgdm-' . esc_attr( $item_state[1] ) . '">' . esc_html( $item_state[0] ) . '</span></td>';
                    echo '<td><strong>' . esc_html( $item['name'] ) . '</strong><small>' . esc_html( $item['source'] ) . '</small></td>';
                    echo '<td><code>' . esc_html( $this->short_sha( $item['sha'] ) ) . '</code></td>';
                    echo '<td>' . esc_html( $this->format_time( $item['updated_at'] ) ) . '</td>';
                    echo '<td>';
                    if ( $item['url'] ) {
                        echo '<a class="button button-small" href="' . esc_url( $item['url'] ) . '" target="_blank" rel="noopener">Abrir</a>';
                    }
                    echo '</td>';
                    echo '</tr>';
                }
            }

            echo '</tbody></table></div>';
            echo '</section>';
        }
        echo '</div>';

        echo '<section class="cvgdm-settings">';
        echo '<h2>Autenticação GitHub</h2>';

        if ( $has_constant ) {
            echo '<p><span class="cvgdm-pill cvgdm-success">Configurado via wp-config.php</span></p>';
            echo '<p>Está definida a constante <code>CV_GITHUB_DEPLOY_TOKEN</code>. O token nunca é apresentado pelo plugin.</p>';
        } else {
            echo '<p>Use um Fine-grained Personal Access Token com acesso apenas de leitura aos dois repositórios e permissões <strong>Actions: Read</strong>, <strong>Checks: Read</strong>, <strong>Contents: Read</strong> e <strong>Metadata: Read</strong>.</p>';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="cvgdm-token-form">';
            echo '<input type="hidden" name="action" value="cvgdm_save_settings">';
            wp_nonce_field( 'cvgdm_save_settings' );
            echo '<label for="cvgdm-github-token"><strong>GitHub token</strong></label>';
            echo '<input id="cvgdm-github-token" type="password" name="github_token" value="" autocomplete="new-password" placeholder="' . ( $has_token ? 'Token guardado — deixe vazio para manter' : 'github_pat_…' ) . '">';
            echo '<label><input type="checkbox" name="clear_token" value="1"> Apagar token guardado</label>';
            submit_button( 'Guardar configuração', 'secondary', 'submit', false );
            echo '</form>';
        }

        echo '<p class="description">Recomendado para produção: definir <code>CV_GITHUB_DEPLOY_TOKEN</code> no <code>wp-config.php</code> em vez de guardar o token na base de dados.</p>';
        echo '</section>';

        echo $this->shared_styles(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</div>';
    }

    private function shared_styles() {
        return '<style>
            .cvgdm-wrap{max-width:1500px}
            .cvgdm-toolbar{margin:18px 0;display:flex;align-items:center;gap:16px;flex-wrap:wrap}
            .cvgdm-toolbar form{margin:0}
            .cvgdm-toolbar span{color:#646970;font-size:12px}
            .cvgdm-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin-top:18px}
            .cvgdm-repo-card,.cvgdm-settings{padding:20px;border:1px solid #dcdcde;border-radius:10px;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.03)}
            .cvgdm-settings{margin-top:18px}
            .cvgdm-repo-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:14px}
            .cvgdm-repo-head h2{margin:0 0 3px;font-size:17px}
            .cvgdm-repo-head a{font-size:11px;text-decoration:none}
            .cvgdm-meta{margin:0 0 14px;display:flex;gap:16px;flex-wrap:wrap;color:#50575e;font-size:11px}
            .cvgdm-meta strong{color:#1d2327}
            .cvgdm-table-wrap{overflow:auto}
            .cvgdm-table{min-width:650px}
            .cvgdm-table td,.cvgdm-table th{vertical-align:middle}
            .cvgdm-table td:nth-child(2) small{display:block;margin-top:2px;color:#787c82}
            .cvgdm-pill{display:inline-flex;align-items:center;justify-content:center;min-height:24px;padding:3px 9px;border-radius:999px;font-size:10px;font-weight:800;line-height:1;white-space:nowrap}
            .cvgdm-success{background:#e7f7ed;color:#087d3e}
            .cvgdm-running{background:#e9f2ff;color:#135e96}
            .cvgdm-queued{background:#fff4d6;color:#8a6100}
            .cvgdm-failure{background:#fde8e8;color:#b42318}
            .cvgdm-cancelled{background:#f1f1f1;color:#50575e}
            .cvgdm-muted{background:#f0f0f1;color:#646970}
            .cvgdm-error{padding:12px;border-left:4px solid #d63638;background:#fff5f5;color:#8a2424}
            .cvgdm-token-form{max-width:720px;display:grid;grid-template-columns:1fr;gap:10px}
            .cvgdm-token-form input[type=password]{width:100%;max-width:720px}
            @media(max-width:1100px){.cvgdm-grid{grid-template-columns:1fr}}
        </style>';
    }
}

register_activation_hook( __FILE__, array( 'CV_GitHub_Deploy_Monitor', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CV_GitHub_Deploy_Monitor', 'deactivate' ) );

CV_GitHub_Deploy_Monitor::instance();
