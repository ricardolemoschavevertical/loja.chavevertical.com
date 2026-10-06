<?php
defined( 'ABSPATH' ) || exit;

final class CVR2_Slug_Audit {
    private const DB_VERSION   = '1.0';
    private const DB_OPTION    = 'cvr2_slug_audit_db_version';
    private const STATE_OPTION = 'cvr2_slug_audit_state';

    public static function init(): void {
        add_action( 'admin_init', array( __CLASS__, 'maybe_install' ) );
        add_action( 'wp_ajax_cvr2_slug_audit_start', array( __CLASS__, 'ajax_start' ) );
        add_action( 'wp_ajax_cvr2_slug_audit_batch', array( __CLASS__, 'ajax_batch' ) );
        add_action( 'wp_ajax_cvr2_slug_audit_status', array( __CLASS__, 'ajax_status' ) );
        add_action( 'wp_ajax_cvr2_slug_audit_correct_one', array( __CLASS__, 'ajax_correct_one' ) );
        add_action( 'wp_ajax_cvr2_slug_audit_correct_batch', array( __CLASS__, 'ajax_correct_batch' ) );
        add_action( 'wp_ajax_cvr2_slug_audit_reset', array( __CLASS__, 'ajax_reset' ) );
        add_action( 'admin_post_cvr2_slug_audit_export', array( __CLASS__, 'export_redirects' ) );
    }

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'cvr2_slug_audit';
    }

    public static function maybe_install(): void {
        if ( get_option( self::DB_OPTION ) === self::DB_VERSION ) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        global $wpdb;
        $table   = self::table();
        $charset = $wpdb->get_charset_collate();

        dbDelta(
            "CREATE TABLE {$table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                source_product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                destination_product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                sku VARCHAR(191) NOT NULL DEFAULT '',
                product_name TEXT NULL,
                source_slug VARCHAR(255) NOT NULL DEFAULT '',
                destination_slug VARCHAR(255) NOT NULL DEFAULT '',
                source_url TEXT NULL,
                destination_url TEXT NULL,
                redirect_from TEXT NULL,
                redirect_to TEXT NULL,
                status VARCHAR(40) NOT NULL DEFAULT '',
                reason TEXT NULL,
                matched_by VARCHAR(40) NOT NULL DEFAULT '',
                run_id VARCHAR(64) NOT NULL DEFAULT '',
                checked_at DATETIME NOT NULL,
                corrected_at DATETIME NULL,
                PRIMARY KEY (id),
                UNIQUE KEY source_product_id (source_product_id),
                KEY status (status),
                KEY destination_product_id (destination_product_id),
                KEY run_id (run_id)
            ) {$charset};"
        );

        update_option( self::DB_OPTION, self::DB_VERSION, false );
    }

    private static function guard_ajax(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Sem permissões.' ), 403 );
        }

        check_ajax_referer( 'cvr2_import', 'nonce' );
    }

    private static function public_root(): string {
        return untrailingslashit( CVR2_REST_Client::source_url() );
    }

    private static function product_url( string $slug ): string {
        return $slug ? self::public_root() . '/produto/' . rawurlencode( $slug ) . '/' : '';
    }

    private static function save_row( array $row ): void {
        global $wpdb;

        $table     = self::table();
        $source_id = absint( $row['source_product_id'] ?? 0 );
        $existing  = $source_id
            ? absint(
                $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM {$table} WHERE source_product_id = %d LIMIT 1",
                        $source_id
                    )
                )
            )
            : 0;

        $data = array(
            'source_product_id'      => $source_id,
            'destination_product_id' => absint( $row['destination_product_id'] ?? 0 ),
            'sku'                    => wc_clean( (string) ( $row['sku'] ?? '' ) ),
            'product_name'           => sanitize_text_field( (string) ( $row['product_name'] ?? '' ) ),
            'source_slug'            => sanitize_title( (string) ( $row['source_slug'] ?? '' ) ),
            'destination_slug'       => sanitize_title( (string) ( $row['destination_slug'] ?? '' ) ),
            'source_url'             => esc_url_raw( (string) ( $row['source_url'] ?? '' ) ),
            'destination_url'        => esc_url_raw( (string) ( $row['destination_url'] ?? '' ) ),
            'redirect_from'          => esc_url_raw( (string) ( $row['redirect_from'] ?? '' ) ),
            'redirect_to'            => esc_url_raw( (string) ( $row['redirect_to'] ?? '' ) ),
            'status'                 => sanitize_key( (string) ( $row['status'] ?? '' ) ),
            'reason'                 => sanitize_text_field( (string) ( $row['reason'] ?? '' ) ),
            'matched_by'             => sanitize_key( (string) ( $row['matched_by'] ?? '' ) ),
            'run_id'                 => sanitize_text_field( (string) ( $row['run_id'] ?? '' ) ),
            'checked_at'             => current_time( 'mysql', true ),
        );

        if ( $existing ) {
            $wpdb->update( $table, $data, array( 'id' => $existing ) );
        } else {
            $wpdb->insert( $table, $data );
        }
    }

    private static function compare_product( array $source, string $run_id ): array {
        $source_id   = absint( $source['id'] ?? 0 );
        $raw_slug    = trim( (string) ( $source['slug'] ?? '' ) );
        $source_slug = sanitize_title( $raw_slug );
        $sku         = wc_clean( (string) ( $source['sku'] ?? '' ) );
        $name        = sanitize_text_field( (string) ( $source['name'] ?? '' ) );

        $row = array(
            'source_product_id'      => $source_id,
            'destination_product_id' => 0,
            'sku'                    => $sku,
            'product_name'           => $name,
            'source_slug'            => $source_slug,
            'destination_slug'       => '',
            'source_url'             => self::product_url( $source_slug ),
            'destination_url'        => '',
            'redirect_from'          => '',
            'redirect_to'            => '',
            'status'                 => '',
            'reason'                 => '',
            'matched_by'             => '',
            'run_id'                 => $run_id,
        );

        if ( ! $raw_slug || ! $source_slug || $raw_slug !== $source_slug ) {
            $row['status']        = 'invalid_source_slug';
            $row['reason']        = 'O slug da origem está vazio ou não é estável após sanitização.';
            $row['redirect_from'] = $row['source_url'];
            self::save_row( $row );
            return $row;
        }

        $match          = CVR2_Product_Importer::locate_existing_product( $source );
        $destination_id = absint( $match['id'] ?? 0 );

        if ( ! $destination_id ) {
            $row['status']        = 'missing_destination';
            $row['reason']        = 'Produto correspondente não encontrado no destino.';
            $row['redirect_from'] = $row['source_url'];
            self::save_row( $row );
            return $row;
        }

        $destination_slug = (string) get_post_field( 'post_name', $destination_id );

        $row['destination_product_id'] = $destination_id;
        $row['destination_slug']       = $destination_slug;
        $row['destination_url']        = self::product_url( $destination_slug );
        $row['matched_by']             = sanitize_key( (string) ( $match['matched_by'] ?? '' ) );

        if ( $destination_slug === $source_slug ) {
            $row['status'] = 'same';
            $row['reason'] = 'Slug idêntico.';
            self::save_row( $row );
            return $row;
        }

        $owner = get_page_by_path( $source_slug, OBJECT, 'product' );

        if ( $owner instanceof WP_Post && (int) $owner->ID !== $destination_id ) {
            $row['status']        = 'blocked_collision';
            $row['reason']        = sprintf( 'O slug da origem já pertence ao produto #%d no destino.', (int) $owner->ID );
            $row['redirect_from'] = $row['source_url'];
            $row['redirect_to']   = $row['destination_url'];
            self::save_row( $row );
            return $row;
        }

        $row['status'] = 'correctable';
        $row['reason'] = 'Pode ser corrigido com segurança.';
        self::save_row( $row );
        return $row;
    }

    public static function ajax_start(): void {
        self::guard_ajax();
        self::maybe_install();

        $state = array(
            'status'      => 'running',
            'page'        => 1,
            'batch_size'  => 25,
            'total'       => 0,
            'total_pages' => 0,
            'processed'   => 0,
            'same'        => 0,
            'correctable' => 0,
            'blocked'     => 0,
            'missing'     => 0,
            'invalid'     => 0,
            'run_id'      => wp_generate_uuid4(),
            'started_at'  => time(),
            'updated_at'  => time(),
        );

        update_option( self::STATE_OPTION, $state, false );
        wp_send_json_success( array( 'state' => $state, 'message' => 'Comparação de slugs iniciada.' ) );
    }

    public static function ajax_status(): void {
        self::guard_ajax();
        wp_send_json_success( array( 'state' => (array) get_option( self::STATE_OPTION, array() ) ) );
    }

    public static function ajax_reset(): void {
        self::guard_ajax();
        delete_option( self::STATE_OPTION );
        wp_send_json_success( array( 'message' => 'Estado da comparação limpo. Os resultados foram mantidos.' ) );
    }

    public static function ajax_batch(): void {
        self::guard_ajax();

        $state = (array) get_option( self::STATE_OPTION, array() );

        if ( empty( $state ) || 'done' === ( $state['status'] ?? '' ) ) {
            wp_send_json_success( array( 'done' => true, 'state' => $state ) );
        }

        $page       = max( 1, absint( $state['page'] ?? 1 ) );
        $batch_size = max( 1, min( 50, absint( $state['batch_size'] ?? 25 ) ) );

        $args = array(
            'per_page' => $batch_size,
            'page'     => $page,
            'orderby'  => 'id',
            'order'    => 'asc',
            'status'   => 'any',
            '_fields'  => 'id,name,sku,slug,status',
        );

        $result = CVR2_REST_Client::request( 'products', $args, 45 );

        if ( is_wp_error( $result ) ) {
            unset( $args['status'] );
            $result = CVR2_REST_Client::request( 'products', $args, 45 );
        }

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message(), 'state' => $state ) );
        }

        $state['total']       = (int) $result['total'];
        $state['total_pages'] = max( 1, (int) $result['total_pages'] );

        foreach ( (array) $result['data'] as $source ) {
            $row = self::compare_product( (array) $source, (string) $state['run_id'] );
            $state['processed']++;

            switch ( $row['status'] ) {
                case 'same':
                    $state['same']++;
                    break;
                case 'correctable':
                    $state['correctable']++;
                    break;
                case 'blocked_collision':
                    $state['blocked']++;
                    break;
                case 'missing_destination':
                    $state['missing']++;
                    break;
                case 'invalid_source_slug':
                    $state['invalid']++;
                    break;
            }
        }

        if ( $page >= (int) $state['total_pages'] || empty( $result['data'] ) ) {
            $state['status']      = 'done';
            $state['finished_at'] = time();
        } else {
            $state['page'] = $page + 1;
        }

        $state['updated_at'] = time();
        update_option( self::STATE_OPTION, $state, false );

        wp_send_json_success(
            array(
                'done'    => 'done' === $state['status'],
                'state'   => $state,
                'message' => sprintf(
                    'Slugs comparados: %1$s / %2$s.',
                    number_format_i18n( (int) $state['processed'] ),
                    number_format_i18n( (int) $state['total'] )
                ),
            )
        );
    }

    private static function get_row( int $id ): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d LIMIT 1', $id ),
            ARRAY_A
        );
        return is_array( $row ) ? $row : null;
    }

    private static function correct_row( array $row ) {
        $destination_id = absint( $row['destination_product_id'] ?? 0 );
        $source_slug    = sanitize_title( (string) ( $row['source_slug'] ?? '' ) );

        if ( ! $destination_id || ! $source_slug ) {
            return new WP_Error( 'cvr2_slug_audit_missing_data', 'Faltam dados para corrigir este slug.' );
        }

        $result = CVR2_Product_Importer::set_exact_slug( $destination_id, $source_slug );

        global $wpdb;

        if ( is_wp_error( $result ) ) {
            $current_slug = (string) get_post_field( 'post_name', $destination_id );
            $wpdb->update(
                self::table(),
                array(
                    'status'           => 'blocked_correction',
                    'reason'           => $result->get_error_message(),
                    'destination_slug' => $current_slug,
                    'destination_url'  => self::product_url( $current_slug ),
                    'redirect_from'    => self::product_url( $source_slug ),
                    'redirect_to'      => self::product_url( $current_slug ),
                    'checked_at'       => current_time( 'mysql', true ),
                ),
                array( 'id' => absint( $row['id'] ) )
            );
            return $result;
        }

        $wpdb->update(
            self::table(),
            array(
                'status'           => 'corrected',
                'reason'           => 'Slug corrigido e validado no destino.',
                'destination_slug' => $source_slug,
                'destination_url'  => self::product_url( $source_slug ),
                'redirect_from'    => '',
                'redirect_to'      => '',
                'corrected_at'     => current_time( 'mysql', true ),
                'checked_at'       => current_time( 'mysql', true ),
            ),
            array( 'id' => absint( $row['id'] ) )
        );

        return true;
    }

    public static function ajax_correct_one(): void {
        self::guard_ajax();

        $row = self::get_row( absint( $_POST['id'] ?? 0 ) );

        if ( ! $row ) {
            wp_send_json_error( array( 'message' => 'Registo não encontrado.' ), 404 );
        }

        if ( 'correctable' !== $row['status'] ) {
            wp_send_json_error( array( 'message' => 'Este registo não está marcado como corrigível.' ) );
        }

        $result = self::correct_row( $row );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => 'Slug corrigido e validado.' ) );
    }

    public static function ajax_correct_batch(): void {
        self::guard_ajax();

        global $wpdb;
        $rows = $wpdb->get_results(
            'SELECT * FROM ' . self::table() . " WHERE status = 'correctable' ORDER BY id ASC LIMIT 25",
            ARRAY_A
        );

        $corrected = 0;
        $blocked   = 0;

        foreach ( (array) $rows as $row ) {
            $result = self::correct_row( $row );
            is_wp_error( $result ) ? $blocked++ : $corrected++;
        }

        $remaining = absint(
            $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() . " WHERE status = 'correctable'" )
        );

        wp_send_json_success(
            array(
                'done'      => 0 === $remaining,
                'corrected' => $corrected,
                'blocked'   => $blocked,
                'remaining' => $remaining,
                'message'   => sprintf( '%d corrigidos neste lote; %d ainda corrigíveis.', $corrected, $remaining ),
            )
        );
    }

    public static function export_redirects(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Sem permissões.' );
        }

        check_admin_referer( 'cvr2_slug_audit_export' );

        global $wpdb;
        $rows = $wpdb->get_results(
            'SELECT source_product_id, sku, product_name, source_slug, destination_slug, redirect_from, redirect_to, status, reason
             FROM ' . self::table() . "
             WHERE status IN ('blocked_collision','blocked_correction','missing_destination','invalid_source_slug')
             ORDER BY id ASC",
            ARRAY_A
        );

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="cvr2-slug-redirects-' . gmdate( 'Y-m-d-His' ) . '.csv"' );

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, array( 'source_product_id', 'sku', 'produto', 'slug_origem', 'slug_destino', 'url_origem', 'url_destino', 'estado', 'motivo' ), ';' );

        foreach ( (array) $rows as $row ) {
            fputcsv(
                $out,
                array(
                    $row['source_product_id'],
                    $row['sku'],
                    $row['product_name'],
                    $row['source_slug'],
                    $row['destination_slug'],
                    $row['redirect_from'],
                    $row['redirect_to'],
                    $row['status'],
                    $row['reason'],
                ),
                ';'
            );
        }

        fclose( $out );
        exit;
    }

    private static function summary(): array {
        global $wpdb;
        $table = self::table();
        $rows  = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status", ARRAY_A );
        $out   = array();

        foreach ( (array) $rows as $row ) {
            $out[ (string) $row['status'] ] = absint( $row['total'] );
        }

        return $out;
    }

    private static function rows( int $limit = 200 ): array {
        global $wpdb;
        return (array) $wpdb->get_results(
            $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' ORDER BY id DESC LIMIT %d', $limit ),
            ARRAY_A
        );
    }

    public static function render(): void {
        self::maybe_install();

        $state   = (array) get_option( self::STATE_OPTION, array() );
        $summary = self::summary();
        $rows    = self::rows();
        $nonce   = wp_create_nonce( 'cvr2_import' );
        $export  = wp_nonce_url(
            admin_url( 'admin-post.php?action=cvr2_slug_audit_export' ),
            'cvr2_slug_audit_export'
        );
        ?>
        <div class="cvr2-slug-audit">
            <div class="cvr2-card">
                <h2>Comparar slugs — origem vs destino</h2>
                <p>Compara os slugs dos produtos do <strong>chavevertical.com</strong> com os produtos correspondentes no <strong>loja.chavevertical.com</strong>. A correspondência usa os mesmos critérios do importador: ID de origem, slug de origem já registado, SKU, slug e título exato como último recurso.</p>

                <div class="cvr2-actions">
                    <button type="button" class="button button-primary" data-slug-action="start">Comparar todos</button>
                    <button type="button" class="button" data-slug-action="resume">Retomar</button>
                    <button type="button" class="button" data-slug-action="pause">Pausar</button>
                    <button type="button" class="button" data-slug-action="correct">Corrigir todos os possíveis</button>
                    <a class="button" href="<?php echo esc_url( $export ); ?>">Exportar casos para 301 (CSV)</a>
                    <button type="button" class="button" data-slug-action="reset">Limpar estado</button>
                </div>

                <div class="cvr2-progress-meta">
                    <span data-slug-progress-text><?php echo esc_html( number_format_i18n( absint( $state['processed'] ?? 0 ) ) ); ?> / <?php echo esc_html( number_format_i18n( absint( $state['total'] ?? 0 ) ) ); ?> produtos</span>
                    <span data-slug-progress-percent>0%</span>
                </div>
                <div class="cvr2-progress"><span data-slug-progress></span></div>
                <div class="cvr2-stats">
                    <div class="cvr2-stat"><span>Idênticos</span><strong data-slug-stat="same"><?php echo esc_html( number_format_i18n( absint( $summary['same'] ?? 0 ) ) ); ?></strong></div>
                    <div class="cvr2-stat"><span>Corrigíveis</span><strong data-slug-stat="correctable"><?php echo esc_html( number_format_i18n( absint( $summary['correctable'] ?? 0 ) ) ); ?></strong></div>
                    <div class="cvr2-stat"><span>Corrigidos</span><strong data-slug-stat="corrected"><?php echo esc_html( number_format_i18n( absint( $summary['corrected'] ?? 0 ) ) ); ?></strong></div>
                    <div class="cvr2-stat"><span>Bloqueados</span><strong data-slug-stat="blocked"><?php echo esc_html( number_format_i18n( absint( $summary['blocked_collision'] ?? 0 ) + absint( $summary['blocked_correction'] ?? 0 ) ) ); ?></strong></div>
                    <div class="cvr2-stat"><span>Sem destino</span><strong data-slug-stat="missing"><?php echo esc_html( number_format_i18n( absint( $summary['missing_destination'] ?? 0 ) ) ); ?></strong></div>
                </div>

                <div class="cvr2-log" data-slug-log>Pronto.</div>
            </div>

            <div class="cvr2-card" style="margin-top:18px">
                <h2>Resultados</h2>
                <p class="description">São mostrados os últimos 200 registos. Os casos que não podem ser corrigidos ficam gravados para exportação e posterior criação de redirecionamentos 301.</p>
                <div class="cvr2-results-wrap" style="max-height:620px">
                    <table class="cvr2-results" style="min-width:1150px">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>SKU</th>
                                <th>Slug origem</th>
                                <th>Slug destino</th>
                                <th>Estado</th>
                                <th>Motivo</th>
                                <th>Redirecionamento</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody data-slug-rows>
                            <?php if ( ! $rows ) : ?>
                                <tr><td colspan="8">Ainda sem comparação.</td></tr>
                            <?php else : ?>
                                <?php foreach ( $rows as $row ) : ?>
                                    <tr>
                                        <td><?php echo esc_html( (string) $row['product_name'] ); ?></td>
                                        <td><?php echo esc_html( (string) $row['sku'] ); ?></td>
                                        <td><code><?php echo esc_html( (string) $row['source_slug'] ); ?></code></td>
                                        <td><code><?php echo esc_html( (string) $row['destination_slug'] ); ?></code></td>
                                        <td><?php echo esc_html( (string) $row['status'] ); ?></td>
                                        <td><?php echo esc_html( (string) $row['reason'] ); ?></td>
                                        <td>
                                            <?php if ( ! empty( $row['redirect_from'] ) ) : ?>
                                                <a href="<?php echo esc_url( (string) $row['redirect_from'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( (string) $row['redirect_from'] ); ?></a>
                                                <br>→<br>
                                                <?php if ( ! empty( $row['redirect_to'] ) ) : ?>
                                                    <a href="<?php echo esc_url( (string) $row['redirect_to'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( (string) $row['redirect_to'] ); ?></a>
                                                <?php else : ?>
                                                    <em>Destino por definir</em>
                                                <?php endif; ?>
                                            <?php else : ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ( 'correctable' === $row['status'] ) : ?>
                                                <button type="button" class="button button-small" data-slug-correct-id="<?php echo esc_attr( (string) $row['id'] ); ?>">Corrigir</button>
                                            <?php elseif ( ! empty( $row['redirect_from'] ) ) : ?>
                                                <small>Registado para 301</small>
                                            <?php else : ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script>
        (() => {
            const nonce = <?php echo wp_json_encode( $nonce ); ?>;
            let running = false;
            let correcting = false;
            const log = document.querySelector('[data-slug-log]');
            const progress = document.querySelector('[data-slug-progress]');
            const progressText = document.querySelector('[data-slug-progress-text]');
            const progressPercent = document.querySelector('[data-slug-progress-percent]');

            const write = (value) => { if (log) log.textContent = String(value || ''); };

            async function call(action, params = {}) {
                const body = new URLSearchParams({action, nonce, ...params});
                const response = await fetch(ajaxurl, {
                    method:'POST',
                    credentials:'same-origin',
                    headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
                    body:body.toString()
                });
                const json = await response.json();
                if (!json.success) throw new Error(json.data?.message || 'Erro.');
                return json.data || {};
            }

            function renderState(state) {
                state = state || {};
                const total = Number(state.total || 0);
                const processed = Number(state.processed || 0);
                const percent = total > 0 ? Math.min(100, processed / total * 100) : 0;
                if (progress) progress.style.width = percent + '%';
                if (progressText) progressText.textContent = processed.toLocaleString('pt-PT') + ' / ' + total.toLocaleString('pt-PT') + ' produtos';
                if (progressPercent) progressPercent.textContent = percent.toLocaleString('pt-PT',{maximumFractionDigits:1}) + '%';
            }

            async function loop() {
                if (running) return;
                running = true;
                try {
                    while (running) {
                        const data = await call('cvr2_slug_audit_batch');
                        renderState(data.state);
                        write(data.message || 'A comparar…');
                        if (data.done) {
                            running = false;
                            write('Comparação concluída. Recarrega a página para ver a lista final.');
                            window.setTimeout(() => location.reload(), 500);
                            break;
                        }
                        await new Promise(resolve => setTimeout(resolve, 75));
                    }
                } catch (error) {
                    running = false;
                    write(error.message || error);
                }
            }

            document.querySelector('[data-slug-action="start"]')?.addEventListener('click', async () => {
                try {
                    const data = await call('cvr2_slug_audit_start');
                    renderState(data.state);
                    write(data.message);
                    loop();
                } catch (error) { write(error.message || error); }
            });

            document.querySelector('[data-slug-action="resume"]')?.addEventListener('click', () => loop());
            document.querySelector('[data-slug-action="pause"]')?.addEventListener('click', () => {
                running = false;
                write('Comparação pausada. O progresso ficou guardado.');
            });

            document.querySelector('[data-slug-action="reset"]')?.addEventListener('click', async () => {
                try { const data = await call('cvr2_slug_audit_reset'); write(data.message); }
                catch (error) { write(error.message || error); }
            });

            document.querySelector('[data-slug-action="correct"]')?.addEventListener('click', async () => {
                if (correcting) return;
                correcting = true;
                try {
                    while (correcting) {
                        const data = await call('cvr2_slug_audit_correct_batch');
                        write(data.message);
                        if (data.done) {
                            correcting = false;
                            location.reload();
                            break;
                        }
                        await new Promise(resolve => setTimeout(resolve, 100));
                    }
                } catch (error) {
                    correcting = false;
                    write(error.message || error);
                }
            });

            document.addEventListener('click', async (event) => {
                const button = event.target.closest('[data-slug-correct-id]');
                if (!button) return;
                button.disabled = true;
                try {
                    const data = await call('cvr2_slug_audit_correct_one',{id:button.dataset.slugCorrectId});
                    write(data.message);
                    location.reload();
                } catch (error) {
                    button.disabled = false;
                    write(error.message || error);
                }
            });
        })();
        </script>
        <?php
    }
}
