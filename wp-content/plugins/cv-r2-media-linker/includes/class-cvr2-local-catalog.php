<?php
defined( 'ABSPATH' ) || exit;

/**
 * Two-stage local catalogue cache. No product is imported while preparing the snapshot.
 * JSONL files and import checkpoints are deliberately kept outside the web document root.
 */
final class CVR2_Local_Catalog {
    public const SNAPSHOT_OPTION = 'cvr2_local_snapshot_v1';
    public const IMPORT_OPTION   = 'cvr2_local_import_v1';
    // PAGE_SIZE remains the fallback for snapshots started by 2.5.0. Changing it
    // mid-run would shift REST pagination and skip or duplicate products.
    private const PAGE_SIZE      = 40;
    private const STEP_SIZE      = 8;

    private static function rest_profiles(): array {
        return array(
            'safe'     => array( 'page_size' => 10, 'cooldown_ms' => 2500 ),
            'balanced' => array( 'page_size' => 20, 'cooldown_ms' => 1250 ),
            'fast'     => array( 'page_size' => 30, 'cooldown_ms' => 650 ),
        );
    }

    public static function init(): void {
        foreach ( array(
            'cvr2_local_status'       => 'ajax_status',
            'cvr2_local_rest_start'   => 'ajax_rest_start',
            'cvr2_local_prepare_pause'=> 'ajax_prepare_pause',
            'cvr2_local_prepare_step' => 'ajax_prepare_step',
            'cvr2_local_import_start' => 'ajax_import_start',
            'cvr2_local_import_step'  => 'ajax_import_step',
            'cvr2_local_pause'        => 'ajax_pause',
            'cvr2_local_delete'       => 'ajax_delete',
            'cvr2_local_report'       => 'ajax_report',
            'cvr2_local_watchdog'     => 'ajax_watchdog',
        ) as $action => $method ) {
            add_action( 'wp_ajax_' . $action, array( __CLASS__, $method ) );
        }
    }

    public static function guard(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_products' ) ) {
            wp_send_json_error( array( 'message' => 'Permissão insuficiente.' ), 403 );
        }
        check_ajax_referer( 'cvr2_local', 'nonce' );
    }

    public static function respond( $result ): void {
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array(
                'message' => $result->get_error_message(),
                'code'    => $result->get_error_code(),
            ), 400 );
        }
        wp_send_json_success( $result );
    }

    /**
     * A private directory under the hosting account, never in the document root.
     * Override with CVR2_TRANSFER_DIR in wp-config.php if the account layout differs.
     */
    public static function directory() {
        $dir = defined( 'CVR2_TRANSFER_DIR' ) && CVR2_TRANSFER_DIR
            ? (string) CVR2_TRANSFER_DIR
            : dirname( untrailingslashit( ABSPATH ), 2 ) . '/private-data/chavevertical/product-transfer';

        if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
            return new WP_Error( 'cvr2_local_dir', 'Sem permissão para criar pasta privada. Configurar CVR2_TRANSFER_DIR fora da pasta pública.' );
        }
        $resolved = realpath( $dir );
        $docroot  = realpath( ABSPATH );
        if ( ! $resolved || ! $docroot ||
             str_starts_with( trailingslashit( wp_normalize_path( $resolved ) ), trailingslashit( wp_normalize_path( $docroot ) ) ) ) {
            return new WP_Error( 'cvr2_local_public', 'A pasta de importação tem de ficar fora do diretório público do WordPress.' );
        }
        if ( ! is_writable( $resolved ) ) {
            return new WP_Error( 'cvr2_local_write', 'A pasta privada não tem permissão de escrita para o PHP.' );
        }
        // Do not chmod an existing account directory: permissions may be shared with the deployment runner.
        return $resolved;
    }

    public static function snapshot(): array {
        return (array) get_option( self::SNAPSHOT_OPTION, array() );
    }
    public static function save_snapshot( array $state ): void {
        update_option( self::SNAPSHOT_OPTION, $state, false );
    }
    public static function import(): array {
        return (array) get_option( self::IMPORT_OPTION, array() );
    }

    public static function file_path( array $state ) {
        $id = (string) ( $state['id'] ?? '' );
        if ( ! preg_match( '/^[a-f0-9]{32}$/D', $id ) ) {
            return new WP_Error( 'cvr2_local_id', 'Identificador do ficheiro inválido.' );
        }
        $directory = self::directory();
        if ( is_wp_error( $directory ) ) {
            return $directory;
        }
        return $directory . '/catalog-' . $id . '.jsonl';
    }

    private static function report_path( array $state ) {
        $uuid = (string) ( $state['run_id'] ?? '' );
        if ( ! preg_match( '/^[a-f0-9-]{36}$/D', $uuid ) ) {
            return new WP_Error( 'cvr2_local_report_id', 'Identificador de relatório inválido.' );
        }
        $dir = self::directory();
        return is_wp_error( $dir ) ? $dir : $dir . '/report-' . $uuid . '.csv';
    }

    private static function report_row( array &$state, array $source, string $kind, string $message ) {
        $path = self::report_path( $state );
        if ( is_wp_error( $path ) ) { return $path; }
        $fp = @fopen( $path, 'c+b' );
        if ( ! $fp ) {
            return new WP_Error( 'cvr2_local_report_write', 'Não foi possível escrever o relatório.' );
        }
        $offset = max( 0, (int) ( $state['report_bytes'] ?? 0 ) );
        if ( ( fstat( $fp )['size'] ?? 0 ) < $offset || ! ftruncate( $fp, $offset ) ||
             fseek( $fp, $offset ) !== 0 ) {
            fclose( $fp );
            return new WP_Error( 'cvr2_local_report_damage', 'Relatório interrompido num ponto inesperado.' );
        }
        $written = fputcsv( $fp, array(
            $state['phase'], (string) ( $source['id'] ?? '' ),
            (string) ( $source['sku'] ?? '' ),
            $kind, $message,
        ), ';', '"', '' );
        if ( false === $written ) {
            fclose( $fp );
            return new WP_Error( 'cvr2_local_report_disk', 'Erro a guardar o relatório no disco.' );
        }
        fflush( $fp );
        $state['report_bytes'] = ftell( $fp );
        fclose( $fp );
        return true;
    }

    public static function ajax_report(): void {
        self::guard();
        $state = self::import();
        $path = self::report_path( $state );
        if ( is_wp_error( $path ) || ! is_file( $path ) ) {
            wp_die( 'Relatório ainda não disponível.', 404 );
        }
        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Disposition: attachment; filename="cv-import-report.csv"' );
        readfile( $path );
        exit;
    }

    public static function with_lock( callable $callback ) {
        $dir = self::directory();
        if ( is_wp_error( $dir ) ) {
            return $dir;
        }
        $lock = @fopen( $dir . '/.cvr2-local-process.lock', 'c+' );
        if ( ! $lock ) {
            return new WP_Error( 'cvr2_local_lockfile', 'Não foi possível abrir o bloqueio.' );
        }
        if ( ! flock( $lock, LOCK_EX | LOCK_NB ) ) {
            fclose( $lock );
            return new WP_Error( 'cvr2_local_busy', 'Outro lote está em curso. Não iniciar operações simultâneas.' );
        }
        try {
            return $callback();
        } finally {
            flock( $lock, LOCK_UN );
            fclose( $lock );
        }
    }

    public static function new_snapshot( string $source, array $extra = array() ) {
        $running = self::import();
        if ( 'running' === ( $running['status'] ?? '' ) ) {
            return new WP_Error( 'cvr2_local_running', 'Há uma importação local em curso; interromper antes de preparar outro catálogo.' );
        }
        $old = self::snapshot();
        if ( in_array( (string) ( $old['status'] ?? '' ), array( 'building', 'paused_building' ), true ) ) {
            return new WP_Error( 'cvr2_local_building', 'Já existe um catálogo em preparação/pausa. Retomar ou eliminar antes de começar outro.' );
        }
        $legacy = (array) get_option( CVR2_STATE_OPTION, array() );
        if ( 'running' === ( $legacy['status'] ?? '' ) ) {
            return new WP_Error( 'cvr2_local_legacy_busy', 'O importador REST antigo está ativo; não executar em paralelo.' );
        }

        $id = bin2hex( random_bytes( 16 ) );
        $state = array_merge( array(
            'id'         => $id,
            'source'     => $source,
            'status'     => 'building',
            'page'       => 1,
            'total'      => 0,
            'total_pages'=> 0,
            'records'    => 0,
            'bytes'      => 0,
            'created_at' => time(),
            'updated_at' => time(),
            'errors'     => array(),
        ), $extra );
        $path = self::file_path( $state );
        if ( is_wp_error( $path ) ) {
            return $path;
        }
        if ( false === @file_put_contents( $path, '' ) ) {
            return new WP_Error( 'cvr2_local_file', 'Não foi possível criar o ficheiro privado.' );
        }
        @chmod( $path, 0600 );
        // No abandoned previous snapshots after intentionally replacing the selected one.
        $old_path = self::file_path( $old );
        if ( ! is_wp_error( $old_path ) && $old_path !== $path && is_file( $old_path ) ) {
            @unlink( $old_path );
        }
        self::save_snapshot( $state );
        delete_option( self::IMPORT_OPTION );
        delete_option( 'cvr2_local_products_done' );
        return $state;
    }

    /**
     * Idempotent append. If a request failed after writing but before checkpointing,
     * discard the uncommitted tail before writing the same page again.
     */
    public static function append_records( array $rows, array &$state ) {
        $path = self::file_path( $state );
        if ( is_wp_error( $path ) ) {
            return $path;
        }
        $fp = @fopen( $path, 'c+b' );
        if ( ! $fp ) {
            return new WP_Error( 'cvr2_local_open', 'Não foi possível abrir o catálogo.' );
        }
        $offset = max( 0, (int) ( $state['bytes'] ?? 0 ) );
        $size   = fstat( $fp )['size'] ?? 0;
        if ( $size < $offset || ! ftruncate( $fp, $offset ) || fseek( $fp, $offset ) !== 0 ) {
            fclose( $fp );
            return new WP_Error( 'cvr2_local_corrupt', 'O catálogo está incompleto; não é seguro continuar.' );
        }
        foreach ( $rows as $row ) {
            $line = wp_json_encode( $row, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE );
            if ( ! is_string( $line ) ) {
                fclose( $fp );
                return new WP_Error( 'cvr2_local_encode', 'Não foi possível serializar um produto.' );
            }
            $line .= "\n";
            $written = fwrite( $fp, $line );
            if ( $written !== strlen( $line ) ) {
                fclose( $fp );
                return new WP_Error( 'cvr2_local_disk', 'Erro ao gravar ficheiro; verificar espaço em disco.' );
            }
        }
        fflush( $fp );
        $state['bytes'] = ftell( $fp );
        $state['records'] += count( $rows );
        fclose( $fp );
        return true;
    }

    public static function ajax_status(): void {
        self::guard();
        self::respond( array(
            'snapshot' => self::snapshot(),
            'import'   => self::import(),
            'watchdog_enabled' => CVR2_Admin::watchdog_enabled(),
        ) );
    }

    public static function ajax_watchdog(): void {
        self::guard();
        $enabled = '1' === (string) wp_unslash( $_POST['enabled'] ?? '0' );
        update_option( 'cvr2_watchdog_enabled', $enabled ? 'yes' : 'no', false );
        self::respond( array( 'enabled' => $enabled ) );
    }

    public static function ajax_rest_start(): void {
        self::guard();
        $profile = sanitize_key( (string) wp_unslash( $_POST['profile'] ?? 'safe' ) );
        self::respond( self::with_lock( static function () use ( $profile ) {
            $profiles = self::rest_profiles();
            $selected = $profiles[ $profile ] ?? $profiles['safe'];
            return self::new_snapshot( 'rest', array(
                'profile'        => isset( $profiles[$profile] ) ? $profile : 'safe',
                'page_size'      => (int) $selected['page_size'],
                'cooldown_ms'    => (int) $selected['cooldown_ms'],
                'next_request_at'=> 0.0,
            ) );
        } ) );
    }

    public static function ajax_prepare_pause(): void {
        self::guard();
        // The pause flag can be set while a REST request is still in progress.
        // The worker rereads it before saving its next checkpoint.
        $state = self::snapshot();
        if ( 'rest' !== ( $state['source'] ?? '' ) ||
             ! in_array( (string) ( $state['status'] ?? '' ), array( 'building', 'paused_building' ), true ) ) {
            self::respond( new WP_Error( 'cvr2_local_no_active_rest', 'Não existe uma preparação REST ativa para pausar/retomar.' ) );
        }
        $pause = '1' === (string) wp_unslash( $_POST['pause'] ?? '1' );
        $state['status'] = $pause ? 'paused_building' : 'building';
        $state['updated_at'] = time();
        self::save_snapshot( $state );
        self::respond( $state );
    }

    public static function ajax_prepare_step(): void {
        self::guard();
        self::respond( self::with_lock( static function () {
            $state = self::snapshot();
            if ( 'paused_building' === ( $state['status'] ?? '' ) ) {
                return $state; // Do not call Woo REST while paused.
            }
            if ( 'building' !== ( $state['status'] ?? '' ) ) {
                return new WP_Error( 'cvr2_local_not_building', 'Não há catálogo em preparação.' );
            }
            if ( 'csv' === ( $state['source'] ?? '' ) ) {
                return CVR2_Local_CSV::prepare_step( $state );
            }
            if ( 'rest' !== ( $state['source'] ?? '' ) ) {
                return new WP_Error( 'cvr2_local_source', 'Origem desconhecida.' );
            }

            // Server-side pacing: multiple browser tabs cannot bypass this limit.
            $remaining = (float) ( $state['next_request_at'] ?? 0 ) - microtime( true );
            if ( $remaining > 0 ) {
                $state['wait_ms'] = (int) ceil( $remaining * 1000 );
                return $state;
            }

            $page = max( 1, (int) ( $state['page'] ?? 1 ) );
            // Existing 2.5.0 snapshots do not carry page_size: retain their original 40
            // so their already-written records and future pages stay aligned.
            $page_size = max( 1, min( 40, (int) ( $state['page_size'] ?? self::PAGE_SIZE ) ) );
            $result = CVR2_REST_Client::request( 'products', array(
                'page' => $page, 'per_page' => $page_size,
                'orderby' => 'id', 'order' => 'asc', 'status' => 'any',
            ), 90 );
            if ( is_wp_error( $result ) ) {
                return $result; // Retry same page without losing checkpoint.
            }
            $rows = array_values( (array) $result['data'] );
            // Download variable product children now, not during the local import.
            foreach ( $rows as &$row ) {
                $row = (array) $row;
                if ( 'variable' === ( $row['type'] ?? '' ) ) {
                    $source_id = absint( $row['id'] ?? 0 );
                    if ( ! $source_id ) {
                        return new WP_Error( 'cvr2_local_variable', 'Produto variável sem ID de origem.' );
                    }
                    $variations = CVR2_REST_Client::all_pages(
                        'products/' . $source_id . '/variations',
                        array( 'status' => 'any' ), 100
                    );
                    if ( is_wp_error( $variations ) ) {
                        return $variations;
                    }
                    $row['__cvr2_variations'] = $variations;
                }
            }
            unset( $row );

            $written = self::append_records( $rows, $state );
            if ( is_wp_error( $written ) ) {
                return $written;
            }
            $state['total']       = max( 0, (int) $result['total'] );
            $state['total_pages'] = max( 1, (int) $result['total_pages'] );
            $state['page']        = $page + 1;
            $state['updated_at']  = time();
            $state['wait_ms'] = max( 500, (int) ( $state['cooldown_ms'] ?? 2500 ) );
            $state['next_request_at'] = microtime( true ) + $state['wait_ms'] / 1000;
            if ( $page >= $state['total_pages'] || empty( $rows ) ) {
                $state['status'] = 'ready';
                $state['completed_at'] = time();
                $state['wait_ms'] = 0;
                $state['next_request_at'] = 0;
            } else {
                $latest_state = self::snapshot();
                if ( 'paused_building' === ( $latest_state['status'] ?? '' ) ) {
                    $state['status'] = 'paused_building';
                }
            }
            self::save_snapshot( $state );
            return $state;
        } ) );
    }

    public static function ajax_import_start(): void {
        self::guard();
        $phase = sanitize_key( (string) wp_unslash( $_POST['phase'] ?? '' ) );
        self::respond( self::with_lock( static function () use ( $phase ) {
            $snapshot = self::snapshot();
            if ( 'ready' !== ( $snapshot['status'] ?? '' ) ) {
                return new WP_Error( 'cvr2_local_not_ready', 'Preparar e concluir o catálogo local antes de importar.' );
            }
            if ( ! in_array( $phase, array( 'products', 'images' ), true ) ) {
                return new WP_Error( 'cvr2_local_phase', 'Fase inválida.' );
            }
            $legacy = (array) get_option( CVR2_STATE_OPTION, array() );
            if ( 'running' === ( $legacy['status'] ?? '' ) ) {
                return new WP_Error( 'cvr2_local_legacy_busy', 'O importador antigo está em execução.' );
            }
            $previous = self::import();
            if ( 'running' === ( $previous['status'] ?? '' ) ) {
                return new WP_Error( 'cvr2_local_running', 'Já existe uma fase de importação em execução.' );
            }
            if ( 'images' === $phase && get_option( 'cvr2_local_products_done' ) !== ( $snapshot['id'] ?? '' ) ) {
                return new WP_Error( 'cvr2_local_first_phase', 'Concluir primeiro a importação dos produtos deste catálogo.' );
            }
            if ( 'images' === $phase && 'csv' === $snapshot['source'] &&
                 empty( $snapshot['mapping']['images'] ) ) {
                return new WP_Error( 'cvr2_local_csv_no_images', 'CSV sem coluna de imagens selecionada.' );
            }
            $state = array(
                'run_id'   => wp_generate_uuid4(),
                'snapshot_id' => $snapshot['id'],
                'phase'    => $phase,
                'status'   => 'running',
                'offset'   => 0,
                'subphase' => 'rows',
                'relations_page' => 1,
                'processed'=> 0,
                'success'  => 0,
                'skipped'  => 0,
                'failed'   => 0,
                'total'    => (int) $snapshot['records'],
                'next_batch_at' => 0.0,
                'wait_ms' => 0,
                'recent'   => array(),
                'report_bytes' => 0,
                'updated_at' => time(),
            );
            $report = self::report_path( $state );
            if ( is_wp_error( $report ) ) { return $report; }
            $fp = @fopen( $report, 'wb' );
            if ( ! $fp || false === fputcsv( $fp, array( 'Fase', 'ID de origem', 'SKU', 'Resultado', 'Mensagem' ), ';', '"', '' ) ) {
                if ( $fp ) { fclose( $fp ); }
                return new WP_Error( 'cvr2_local_report_open', 'Não foi possível criar o relatório CSV privado.' );
            }
            fflush( $fp );
            $state['report_bytes'] = ftell( $fp );
            fclose( $fp );
            @chmod( $report, 0600 );
            update_option( self::IMPORT_OPTION, $state, false );
            $previous_report = self::report_path( $previous );
            if ( ! is_wp_error( $previous_report ) && $previous_report !== $report && is_file( $previous_report ) ) {
                @unlink( $previous_report );
            }
            return $state;
        } ) );
    }

    public static function ajax_import_step(): void {
        self::guard();
        $requested_run = sanitize_text_field( (string) wp_unslash( $_POST['run_id'] ?? '' ) );
        self::respond( self::with_lock( static function () use ( $requested_run ) {
            $state = self::import();
            if ( empty( $state['run_id'] ) || ! $requested_run ||
                 ! hash_equals( (string) $state['run_id'], $requested_run ) ) {
                return new WP_Error( 'cvr2_local_stale', 'Execução antiga. Recuperar o estado atualizado.' );
            }
            if ( 'running' !== ( $state['status'] ?? '' ) ) {
                return $state;
            }
            $remaining = (float) ( $state['next_batch_at'] ?? 0 ) - microtime( true );
            if ( $remaining > 0 ) {
                $state['wait_ms'] = (int) ceil( $remaining * 1000 );
                return $state;
            }
            $snapshot = self::snapshot();
            if ( 'ready' !== ( $snapshot['status'] ?? '' ) ||
                 ( $state['snapshot_id'] ?? '' ) !== ( $snapshot['id'] ?? '' ) ) {
                return new WP_Error( 'cvr2_local_snapshot_changed', 'O ficheiro local mudou; importação bloqueada.' );
            }
            if ( 'products' === $state['phase'] && 'relations' === ( $state['subphase'] ?? '' ) ) {
                // Resolve upsells, cross-sells and grouped children only after all source
                // products exist. Scan only records touched by this run.
                $page = max( 1, (int) ( $state['relations_page'] ?? 1 ) );
                $relations = CVR2_Product_Importer::resolve_relations_page( $page, 50, (string) $state['run_id'] );
                $state['relations_page'] = $page + 1;
                $state['relations_total_pages'] = (int) $relations['total_pages'];
                $state['updated_at'] = time();
                if ( $page >= (int) $relations['total_pages'] ) {
                    $state['status'] = 'done';
                    $state['completed_at'] = time();
                    update_option( 'cvr2_local_products_done', (string) $snapshot['id'], false );
                }
                update_option( self::IMPORT_OPTION, $state, false );
                return $state;
            }

            $path = self::file_path( $snapshot );
            if ( is_wp_error( $path ) ) {
                return $path;
            }
            $fp = @fopen( $path, 'rb' );
            if ( $fp && ( fstat( $fp )['size'] ?? -1 ) !== (int) $snapshot['bytes'] ) {
                fclose( $fp );
                return new WP_Error( 'cvr2_local_changed', 'O ficheiro local mudou desde que foi preparado. Importação bloqueada.' );
            }
            if ( ! $fp || fseek( $fp, (int) $state['offset'] ) !== 0 ) {
                if ( $fp ) { fclose( $fp ); }
                return new WP_Error( 'cvr2_local_read', 'Erro a ler o catálogo privado.' );
            }

            $started = microtime( true );
            for ( $count = 0; $count < self::STEP_SIZE && microtime( true ) - $started < 20; $count++ ) {
                if ( feof( $fp ) ) {
                    break;
                }
                $line = fgets( $fp );
                if ( false === $line ) {
                    break;
                }
                $source = json_decode( $line, true );
                if ( ! is_array( $source ) ) {
                    $outcome = new WP_Error( 'cvr2_local_json', 'Registo JSONL inválido.' );
                } elseif ( 'products' === $state['phase'] ) {
                    $outcome = 'csv' === $snapshot['source']
                        ? CVR2_Local_CSV::import_product( $source )
                        : CVR2_Product_Importer::import_source_product( $source, false, true );
                } elseif ( empty( $source['images'] ) &&
                           empty( $source['__cvr2_variations'] ) ) {
                    $outcome = array( 'skipped' => true );
                } elseif ( 'csv' === $snapshot['source'] &&
                           is_wp_error( CVR2_Local_CSV::validate_images( $source ) ) ) {
                    $outcome = CVR2_Local_CSV::validate_images( $source );
                } else {
                    $outcome = CVR2_Product_Importer::update_source_product_images( $source );
                }

                $state['processed']++;
                if ( is_wp_error( $outcome ) ) {
                    $state['failed']++;
                    $message = $outcome->get_error_message();
                    $kind = 'erro';
                } elseif ( ! empty( $outcome['skipped'] ) ) {
                    $state['skipped']++;
                    $message = 'Sem imagens para associar.';
                    $kind = 'ignorado';
                } else {
                    $state['success']++;
                    $message = 'Concluído.';
                    $kind = 'ok';
                    if ( 'products' === $state['phase'] &&
                         'rest' === $snapshot['source'] && ! empty( $outcome['id'] ) ) {
                        update_post_meta( (int) $outcome['id'], '_cvr2_import_run', (string) $state['run_id'] );
                    }
                }
                array_unshift( $state['recent'], array(
                    'sku' => sanitize_text_field( (string) ( $source['sku'] ?? '' ) ),
                    'name' => sanitize_text_field( (string) ( $source['name'] ?? '' ) ),
                    'result' => $kind,
                    'message' => $message,
                ) );
                $state['recent'] = array_slice( $state['recent'], 0, 30 );
                // Report and checkpoint only after WooCommerce has completed the record.
                $reported = self::report_row( $state, (array) $source, $kind, $message );
                if ( is_wp_error( $reported ) ) {
                    fclose( $fp );
                    return $reported;
                }
                $state['offset'] = ftell( $fp );
                $state['updated_at'] = time();
                $latest = self::import();
                if ( 'paused' === ( $latest['status'] ?? '' ) ) {
                    $state['status'] = 'paused';
                }
                update_option( self::IMPORT_OPTION, $state, false );
                if ( 'paused' === $state['status'] ) {
                    break;
                }
            }
            if ( feof( $fp ) || $state['processed'] >= $state['total'] ) {
                if ( 'products' === $state['phase'] && 'rest' === $snapshot['source'] ) {
                    $state['subphase'] = 'relations';
                } else {
                    $state['status'] = 'done';
                    $state['completed_at'] = time();
                    if ( 'products' === $state['phase'] ) {
                        update_option( 'cvr2_local_products_done', (string) $snapshot['id'], false );
                    }
                }
                update_option( self::IMPORT_OPTION, $state, false );
            }
            fclose( $fp );
            if ( 'running' === $state['status'] ) {
                // Importing from disk still consumes Woo/DB workers: share capacity.
                $state['wait_ms'] = 900;
                $state['next_batch_at'] = microtime( true ) + 0.9;
                update_option( self::IMPORT_OPTION, $state, false );
            } else {
                $state['wait_ms'] = 0;
                $state['next_batch_at'] = 0.0;
                update_option( self::IMPORT_OPTION, $state, false );
            }
            return $state;
        } ) );
    }

    public static function ajax_pause(): void {
        self::guard();
        $state = self::import();
        if ( 'running' === ( $state['status'] ?? '' ) ) {
            $state['status'] = 'paused';
            update_option( self::IMPORT_OPTION, $state, false );
        } elseif ( 'paused' === ( $state['status'] ?? '' ) ) {
            $legacy = (array) get_option( CVR2_STATE_OPTION, array() );
            if ( 'running' === ( $legacy['status'] ?? '' ) ) {
                self::respond( new WP_Error(
                    'cvr2_local_legacy_busy',
                    'A importação REST antiga está ativa; não é seguro retomar esta importação em paralelo.'
                ) );
            }
            $state['status'] = 'running';
            update_option( self::IMPORT_OPTION, $state, false );
        }
        self::respond( $state );
    }

    public static function ajax_delete(): void {
        self::guard();
        self::respond( self::with_lock( static function () {
            $current = self::import();
            if ( 'running' === ( $current['status'] ?? '' ) ) {
                return new WP_Error( 'cvr2_local_running', 'Pausar a importação antes de eliminar o catálogo.' );
            }
            $snapshot = self::snapshot();
            $path = self::file_path( $snapshot );
            if ( ! is_wp_error( $path ) && is_file( $path ) ) {
                @unlink( $path );
            }
            $report = self::report_path( $current );
            if ( ! is_wp_error( $report ) && is_file( $report ) ) {
                @unlink( $report );
            }
            delete_option( self::SNAPSHOT_OPTION );
            delete_option( self::IMPORT_OPTION );
            delete_option( 'cvr2_local_products_done' );
            CVR2_Local_CSV::remove_uploaded_file();
            return array( 'deleted' => true );
        } ) );
    }
}
