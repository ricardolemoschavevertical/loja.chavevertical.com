<?php
defined( 'ABSPATH' ) || exit;

final class CVR2_Admin {
    public static function init(): void {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ), 30 );
        add_action( 'admin_post_cvr2_save_settings', array( __CLASS__, 'save_settings' ) );
        add_action( 'wp_ajax_cvr2_test_source', array( __CLASS__, 'ajax_test_source' ) );
        add_action( 'wp_ajax_cvr2_sync_structure', array( __CLASS__, 'ajax_sync_structure' ) );
        add_action( 'wp_ajax_cvr2_start_import', array( __CLASS__, 'ajax_start_import' ) );
        add_action( 'wp_ajax_cvr2_import_batch', array( __CLASS__, 'ajax_import_batch' ) );
        add_action( 'wp_ajax_cvr2_import_status', array( __CLASS__, 'ajax_import_status' ) );
        add_action( 'wp_ajax_cvr2_reset_import', array( __CLASS__, 'ajax_reset_import' ) );
        add_action( 'wp_ajax_cvr2_set_watchdog', array( __CLASS__, 'ajax_set_watchdog' ) );
    }

    public static function menu(): void {
        $parent = function_exists( 'cv_admin_parent_slug' ) ? cv_admin_parent_slug() : 'woocommerce';

        add_submenu_page(
            $parent,
            'Importação REST + R2',
            'Importação REST + R2',
            'manage_woocommerce',
            'cv-r2-rest-import',
            array( __CLASS__, 'render' )
        );
    }

    private static function guard_ajax(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Sem permissões.' ), 403 );
        }
        check_ajax_referer( 'cvr2_import', 'nonce' );
    }

    public static function watchdog_enabled(): bool {
        // Default OFF: do not restart an import automatically if an API is overloaded.
        return 'yes' === get_option( 'cvr2_watchdog_enabled', 'no' );
    }

    public static function ajax_set_watchdog(): void {
        self::guard_ajax();
        $enabled = '1' === (string) wp_unslash( $_POST['enabled'] ?? '0' );
        update_option( 'cvr2_watchdog_enabled', $enabled ? 'yes' : 'no', false );
        wp_send_json_success( array(
            'enabled' => $enabled,
            'message' => $enabled
                ? 'Watchdog ativado: permite retoma automática da importação REST após falhas.'
                : 'Watchdog desligado: a retoma passa a ser exclusivamente manual.',
        ) );
    }

    public static function save_settings(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Sem permissões.', 'cv-r2-media-linker' ) );
        }

        check_admin_referer( 'cvr2_save_settings' );

        $old = CVR2_REST_Client::settings();
        $new = array(
            'source_url'  => untrailingslashit( esc_url_raw( wp_unslash( $_POST['source_url'] ?? '' ) ) ),
            'r2_base_url' => untrailingslashit( esc_url_raw( wp_unslash( $_POST['r2_base_url'] ?? '' ) ) ),
            'batch_size'  => 1,
            'source_ck'   => (string) ( $old['source_ck'] ?? '' ),
            'source_cs'   => (string) ( $old['source_cs'] ?? '' ),
        );

        $ck = trim( (string) wp_unslash( $_POST['source_ck'] ?? '' ) );
        $cs = trim( (string) wp_unslash( $_POST['source_cs'] ?? '' ) );

        if ( '' !== $ck ) {
            $new['source_ck'] = CVR2_REST_Client::encrypt_secret( $ck );
        }
        if ( '' !== $cs ) {
            $new['source_cs'] = CVR2_REST_Client::encrypt_secret( $cs );
        }

        update_option( CVR2_OPTION, $new, false );

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'      => 'cv-r2-rest-import',
                    'cvr2_saved'=> 1,
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    public static function ajax_test_source(): void {
        self::guard_ajax();
        $result = CVR2_REST_Client::test_connection();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success(
            array(
                'message' => sprintf( 'Ligação REST OK. %s produtos encontrados na origem.', number_format_i18n( (int) $result['total'] ) ),
            )
        );
    }

    public static function ajax_sync_structure(): void {
        self::guard_ajax();
        @set_time_limit( 120 );

        $result = CVR2_Product_Importer::sync_structure();
        update_option( 'cvr2_structure_synced_at', time(), false );

        $message = sprintf(
            'Estrutura verificada: %d categorias existentes correspondentes (nenhuma criada), %d etiquetas, %d marcas e %d atributos.',
            (int) $result['categories'],
            (int) $result['tags'],
            (int) $result['brands'],
            (int) $result['attributes']
        );

        if ( ! empty( $result['warnings'] ) ) {
            $message .= ' Avisos: ' . implode( ' | ', array_map( 'sanitize_text_field', $result['warnings'] ) );
        }

        wp_send_json_success(
            array(
                'message' => $message,
                'result'  => $result,
            )
        );
    }

    public static function ajax_start_import(): void {
        self::guard_ajax();

        $import_mode = sanitize_key( (string) wp_unslash( $_POST['import_mode'] ?? 'update_existing' ) );
        if ( ! in_array( $import_mode, array( 'update_existing', 'create_only', 'products_no_images', 'images_only' ), true ) ) {
            $import_mode = 'update_existing';
        }

        $local_catalog = CVR2_Local_Catalog::snapshot();
        $local_import  = CVR2_Local_Catalog::import();
        if ( in_array( (string) ( $local_catalog['status'] ?? '' ), array( 'building', 'paused_building' ), true )
            || 'running' === ( $local_import['status'] ?? '' ) ) {
            wp_send_json_error( array(
                'message' => 'Está a preparar/importar um catálogo local. Não iniciar o importador REST antigo em paralelo.',
            ), 409 );
        }

        $requested_batch_size = absint( wp_unslash( $_POST['batch_size'] ?? 10 ) );
        $requested_batch_size = max( 1, min( 50, $requested_batch_size ) );
        $batch_size = $requested_batch_size;

        $state = array(
            'status'      => 'running',
            'phase'       => 'products',
            'page'        => 1,
            'total_pages' => 0,
            'total'       => 0,
            'processed'   => 0,
            'created'     => 0,
            'updated'     => 0,
            'images_updated' => 0,
            'unchanged'   => 0,
            'ignored'     => 0,
            'slug_errors' => 0,
            'batch_index' => 0,
            'batch_size'  => $batch_size,
            'recent_results' => array(),
            'current'     => array(),
            'import_mode' => $import_mode,
            'run_id'      => wp_generate_uuid4(),
            'errors'      => array(),
            'started_at'  => time(),
            'updated_at'  => time(),
        );

        update_option( CVR2_STATE_OPTION, $state, false );
        wp_send_json_success(
            array(
                'state'   => $state,
                'message' => 'images_only' === $import_mode
                    ? sprintf( '2.ª fase: associação de imagens iniciada — lote máximo de %d produto(s).', $batch_size )
                    : ( 'products_no_images' === $import_mode
                        ? sprintf( '1.ª fase: produtos sem imagens iniciada — lote máximo de %d produto(s).', $batch_size )
                        : sprintf( 'Importação iniciada — lote máximo de %d produto(s).', $batch_size ) ),
            )
        );
    }

    public static function ajax_import_status(): void {
        self::guard_ajax();

        $state = (array) get_option( CVR2_STATE_OPTION, array() );
        $request_run_id = sanitize_text_field( (string) wp_unslash( $_POST['run_id'] ?? '' ) );

        if (
            $request_run_id
            && ! empty( $state['run_id'] )
            && ! hash_equals( (string) $state['run_id'], $request_run_id )
        ) {
            wp_send_json_error(
                array(
                    'message' => 'A execução ativa foi substituída por outra.',
                    'state'   => $state,
                    'code'    => 'stale_run',
                ),
                409
            );
        }

        wp_send_json_success(
            array(
                'state' => $state,
                'done'  => ! empty( $state ) && 'done' === ( $state['status'] ?? '' ),
                'watchdog_enabled' => self::watchdog_enabled(),
            )
        );
    }

    public static function ajax_reset_import(): void {
        self::guard_ajax();
        delete_option( CVR2_STATE_OPTION );
        wp_send_json_success( array( 'message' => 'Estado da importação limpo.' ) );
    }

    public static function ajax_import_batch(): void {
        self::guard_ajax();

        $state = (array) get_option( CVR2_STATE_OPTION, array() );
        $local_catalog = CVR2_Local_Catalog::snapshot();
        $local_import  = CVR2_Local_Catalog::import();
        if ( in_array( (string) ( $local_catalog['status'] ?? '' ), array( 'building', 'paused_building' ), true )
            || 'running' === ( $local_import['status'] ?? '' ) ) {
            wp_send_json_error( array(
                'message' => 'Catálogo local em execução; importação REST antiga bloqueada para evitar sobrecarga.',
            ), 409 );
        }
        if ( empty( $state ) || 'done' === ( $state['status'] ?? '' ) ) {
            wp_send_json_success(
                array(
                    'done'  => true,
                    'state' => $state,
                )
            );
        }

        $request_run_id = sanitize_text_field( (string) wp_unslash( $_POST['run_id'] ?? '' ) );
        $request_mode   = sanitize_key( (string) wp_unslash( $_POST['import_mode'] ?? '' ) );
        $state_run_id   = sanitize_text_field( (string) ( $state['run_id'] ?? '' ) );
        $import_mode    = sanitize_key( (string) ( $state['import_mode'] ?? 'update_existing' ) );

        if ( ! $request_run_id || ! hash_equals( $state_run_id, $request_run_id ) ) {
            wp_send_json_error(
                array(
                    'message' => 'Execução antiga bloqueada. Este separador já não controla a importação ativa.',
                    'state'   => $state,
                    'code'    => 'stale_run',
                ),
                409
            );
        }

        if ( ! $request_mode || $request_mode !== $import_mode ) {
            wp_send_json_error(
                array(
                    'message' => 'Modo de importação diferente do modo ativo. Pedido bloqueado.',
                    'state'   => $state,
                    'code'    => 'mode_mismatch',
                ),
                409
            );
        }

        // Uma execução em erro pode ser retomada pelo watchdog sem criar um novo run_id.
        if ( 'error' === ( $state['status'] ?? '' ) ) {
            $state['status']     = 'running';
            $state['updated_at'] = time();
            update_option( CVR2_STATE_OPTION, $state, false );
        }

        $lock_key   = 'cvr2_batch_lock_' . md5( $state_run_id );
        $lock_token = wp_generate_uuid4();

        if ( get_transient( $lock_key ) ) {
            wp_send_json_success(
                array(
                    'done'    => false,
                    'state'   => $state,
                    'busy'    => true,
                    'message' => 'Já existe um lote desta execução em processamento. A aguardar.',
                )
            );
        }

        set_transient( $lock_key, $lock_token, 180 );
        register_shutdown_function(
            static function () use ( $lock_key, $lock_token ): void {
                if ( get_transient( $lock_key ) === $lock_token ) {
                    delete_transient( $lock_key );
                }
            }
        );

        if ( absint( $state['batch_size'] ?? 1 ) > 1 ) {
            @set_time_limit( 180 );
        }

        if ( 'structure' === ( $state['phase'] ?? 'products' ) ) {
            // Compatibilidade com estados guardados por versões anteriores:
            // não bloquear o arranque numa sincronização estrutural completa.
            $state['phase']       = 'products';
            $state['page']        = max( 1, absint( $state['page'] ?? 1 ) );
            $state['batch_index'] = 0;
            $state['updated_at']  = time();
            update_option( CVR2_STATE_OPTION, $state, false );

            wp_send_json_success(
                array(
                    'done'    => false,
                    'state'   => $state,
                    'message' => 'Importação iniciada. Os atributos são garantidos produto a produto.',
                )
            );
        }

        if ( 'relations' === ( $state['phase'] ?? 'products' ) ) {
            $relations_run_id = 'create_only' === $import_mode
                ? sanitize_text_field( (string) ( $state['run_id'] ?? '' ) )
                : '';
            $result = CVR2_Product_Importer::resolve_relations_page( max( 1, absint( $state['page'] ?? 1 ) ), 25, $relations_run_id );
            $state['page']        = (int) $result['page'] + 1;
            $state['total_pages'] = (int) $result['total_pages'];
            $state['updated_at']  = time();

            if ( (int) $result['page'] >= (int) $result['total_pages'] ) {
                $state['status']      = 'done';
                $state['phase']       = 'done';
                $state['finished_at'] = time();
            }

            update_option( CVR2_STATE_OPTION, $state, false );

            wp_send_json_success(
                array(
                    'done'    => 'done' === $state['status'],
                    'state'   => $state,
                    'message' => 'A resolver relações entre produtos.',
                )
            );
        }

        /*
         * Todos os modos aceitam lotes de 1 a 50 produtos.
         * O batch_index é persistido depois de CADA produto realmente concluído.
         * Se o pedido for interrompido, a retoma volta à mesma página REST e salta
         * apenas os itens que já têm checkpoint concluído.
         *
         * Além do max_execution_time, usamos um orçamento interno de tempo para
         * devolver controlo ao browser antes de um timeout duro. Isto permite
         * lotes grandes sem fingir que o lote inteiro terminou.
         */
        $per_page         = max( 1, min( 50, absint( $state['batch_size'] ?? 10 ) ) );
        $page             = max( 1, absint( $state['page'] ?? 1 ) );
        $batch_index      = max( 0, absint( $state['batch_index'] ?? 0 ) );
        $batch_started_at = microtime( true );
        $time_budget      = 105.0;

        $request_args = array(
            'per_page' => $per_page,
            'page'     => $page,
            'orderby'  => 'id',
            'order'    => 'asc',
            'status'   => 'any',
        );

        if ( 'images_only' === $import_mode ) {
            // Segunda fase: receber apenas os campos necessários para resolver imagens.
            $request_args['_fields'] = 'id,name,type,sku,slug,images';
        } elseif ( 'products_no_images' === $import_mode ) {
            // Primeira fase: conservar dados completos de produto, SEO, categoria e
            // relações, excluindo imagens e galerias do payload REST.
            $request_args['_fields'] = implode( ',', array(
                'id','name','type','sku','slug','status','featured','catalog_visibility',
                'description','short_description','global_unique_id','regular_price',
                'sale_price','date_on_sale_from_gmt','date_on_sale_to_gmt','virtual',
                'downloadable','download_limit','download_expiry','tax_status',
                'tax_class','manage_stock','stock_quantity','stock_status','backorders',
                'sold_individually','weight','dimensions','reviews_allowed',
                'purchase_note','menu_order','date_created_gmt','date_modified_gmt',
                'low_stock_amount','external_url','button_text','shipping_class',
                'downloads','categories','tags','attributes','default_attributes',
                'brands','meta_data','upsell_ids','cross_sell_ids','grouped_products',
            ) );
        }

        $result = CVR2_REST_Client::request( 'products', $request_args, 60 );

        if ( is_wp_error( $result ) ) {
            $state['status']     = 'error';
            $state['updated_at'] = time();
            $state['errors'][]   = $result->get_error_message();
            $state['errors']     = array_slice( $state['errors'], -20 );
            update_option( CVR2_STATE_OPTION, $state, false );

            wp_send_json_error(
                array(
                    'message' => $result->get_error_message(),
                    'state'   => $state,
                )
            );
        }

        $state['total']       = (int) $result['total'];
        $state['total_pages'] = max( 1, (int) $result['total_pages'] );
        $state['batch_size']  = $per_page;

        $items = array_values( (array) $result['data'] );

        foreach ( $items as $index => $source_product ) {
            if ( $index < $batch_index ) {
                continue;
            }

            $source_product = (array) $source_product;
            $source_id      = absint( $source_product['id'] ?? 0 );

            // Checkpoint antes do trabalho: se este item falhar a meio,
            // é repetido com segurança na retoma.
            $state['current'] = array(
                'source_id' => $source_id,
                'sku'       => wc_clean( (string) ( $source_product['sku'] ?? '' ) ),
                'name'      => sanitize_text_field( (string) ( $source_product['name'] ?? '' ) ),
                'status'    => 'processing',
            );
            $state['updated_at'] = time();
            update_option( CVR2_STATE_OPTION, $state, false );

            if ( 'images_only' === $import_mode ) {
                $imported = CVR2_Product_Importer::update_source_product_images( $source_product );

                if ( is_wp_error( $imported ) ) {
                    if ( 'cvr2_images_target_missing' === $imported->get_error_code() ) {
                        $state['ignored'] = absint( $state['ignored'] ?? 0 ) + 1;
                        self::push_recent_result( $state, $source_product, 'ignored', 'Produto não existe no destino.' );
                    } else {
                        $state['errors'][] = '#' . $source_id . ': ' . $imported->get_error_message();
                        $state['errors']   = array_slice( $state['errors'], -20 );
                        self::push_recent_result( $state, $source_product, 'error', $imported->get_error_message() );
                    }
                } elseif ( ! empty( $imported['changed'] ) ) {
                    $state['images_updated'] = absint( $state['images_updated'] ?? 0 ) + 1;
                    self::push_recent_result(
                        $state,
                        $source_product,
                        'updated',                        sprintf(
                            'Imagem/galeria atualizada%s.',
                            ! empty( $imported['variation_images_updated'] )
                                ? ' + ' . absint( $imported['variation_images_updated'] ) . ' variação(ões)'
                                : ''
                        )
                    );
                } else {
                    $state['unchanged'] = absint( $state['unchanged'] ?? 0 ) + 1;
                    self::push_recent_result( $state, $source_product, 'unchanged', 'Já estava atualizado; nenhuma gravação necessária.' );
                }

                $state['processed']   = absint( $state['processed'] ?? 0 ) + 1;
                $state['batch_index'] = $index + 1;
                $state['updated_at']  = time();

                // Checkpoint por produto — não esperar pelo fim do lote.
                update_option( CVR2_STATE_OPTION, $state, false );

                if ( microtime( true ) - $batch_started_at >= $time_budget && $state['batch_index'] < count( $items ) ) {
                    wp_send_json_success(
                        array(
                            'done'    => false,
                            'state'   => $state,
                            'message' => sprintf(
                                'Lote parcialmente concluído: %1$d/%2$d itens desta página. A continuar automaticamente.',
                                (int) $state['batch_index'],
                                count( $items )
                            ),
                        )
                    );
                }

                continue;
            }

            $existing_match = CVR2_Product_Importer::locate_existing_product( $source_product );
            $was_existing    = ! empty( $existing_match['id'] );

            if ( $was_existing && 'create_only' === $import_mode ) {
                $state['ignored']     = absint( $state['ignored'] ?? 0 ) + 1;
                $state['processed']   = absint( $state['processed'] ?? 0 ) + 1;
                $state['batch_index'] = $index + 1;
                $state['updated_at']  = time();
                $match_labels = array(
                    'source_id'   => 'ID de origem',
                    'source_slug' => 'slug de origem guardado',
                    'sku'         => 'SKU',
                    'slug'        => 'slug',
                    'title'       => 'título exato único',
                );
                $matched_by = (string) ( $existing_match['matched_by'] ?? '' );
                $reason = $match_labels[ $matched_by ] ?? 'correspondência existente';

                self::push_recent_result(
                    $state,
                    $source_product,
                    'ignored',
                    sprintf(
                        'Ignorado: já existe como produto #%1$d (%2$s).',
                        absint( $existing_match['id'] ?? 0 ),
                        $reason
                    )
                );
                update_option( CVR2_STATE_OPTION, $state, false );

                if ( microtime( true ) - $batch_started_at >= $time_budget && $state['batch_index'] < count( $items ) ) {
                    wp_send_json_success(
                        array(
                            'done'    => false,
                            'state'   => $state,
                            'message' => sprintf(
                                'Lote parcialmente concluído: %1$d/%2$d itens desta página. A continuar automaticamente.',
                                (int) $state['batch_index'],
                                count( $items )
                            ),
                        )
                    );
                }

                continue;
            }

            $imported = CVR2_Product_Importer::import_source_product(
                $source_product,
                'create_only' === $import_mode,
                'products_no_images' === $import_mode
            );

            if ( is_wp_error( $imported ) ) {
                if ( str_starts_with( (string) $imported->get_error_code(), 'cvr2_slug_' ) ) {
                    $state['slug_errors'] = absint( $state['slug_errors'] ?? 0 ) + 1;
                }
                $state['errors'][] = '#' . $source_id . ': ' . $imported->get_error_message();
                $state['errors']   = array_slice( $state['errors'], -20 );
                self::push_recent_result( $state, $source_product, 'error', $imported->get_error_message() );
            } elseif ( ! empty( $imported['ignored_existing'] ) ) {
                $state['ignored'] = absint( $state['ignored'] ?? 0 ) + 1;

                $match_labels = array(
                    'source_id'   => 'ID de origem',
                    'source_slug' => 'slug de origem guardado',
                    'sku'         => 'SKU',
                    'slug'        => 'slug',
                    'title'       => 'título exato único',
                );
                $matched_by = (string) ( $imported['matched_by'] ?? '' );
                $reason     = $match_labels[ $matched_by ] ?? 'correspondência existente';

                self::push_recent_result(
                    $state,
                    $source_product,
                    'ignored',
                    sprintf(
                        'Ignorado pela proteção interna: já existe como produto #%1$d (%2$s).',
                        absint( $imported['id'] ?? 0 ),
                        $reason
                    )
                );
            } elseif ( $was_existing ) {
                $state['updated']++;
                self::push_recent_result( $state, $source_product, 'updated', 'Produto atualizado.' );
            } else {
                $state['created']++;
                $target_id = absint( $imported['id'] ?? 0 );
                if ( $target_id && ! empty( $state['run_id'] ) ) {
                    update_post_meta( $target_id, '_cvr2_import_run', sanitize_text_field( (string) $state['run_id'] ) );
                }
                self::push_recent_result( $state, $source_product, 'created', 'Produto criado.' );
            }

            $state['processed']   = absint( $state['processed'] ?? 0 ) + 1;
            $state['batch_index'] = $index + 1;
            $state['updated_at']  = time();
            update_option( CVR2_STATE_OPTION, $state, false );

            if ( microtime( true ) - $batch_started_at >= $time_budget && $state['batch_index'] < count( $items ) ) {
                wp_send_json_success(
                    array(
                        'done'    => false,
                        'state'   => $state,
                        'message' => sprintf(
                            'Lote parcialmente concluído: %1$d/%2$d itens desta página. A continuar automaticamente.',
                            (int) $state['batch_index'],
                            count( $items )
                        ),
                    )
                );
            }
        }

        $state['updated_at'] = time();
        $state['batch_index'] = 0;
        $state['current']     = array();

        if ( 'images_only' === $import_mode ) {
            if ( $page >= (int) $state['total_pages'] || empty( $items ) ) {
                $state['status']      = 'done';
                $state['phase']       = 'done';
                $state['finished_at'] = time();
            } else {
                $state['page'] = $page + 1;
            }
        } elseif ( $page >= (int) $state['total_pages'] || empty( $items ) ) {
            $state['phase'] = 'relations';
            $state['page']  = 1;
        } else {
            $state['page'] = $page + 1;
        }

        update_option( CVR2_STATE_OPTION, $state, false );

        $message = 'images_only' === $import_mode
            ? sprintf(
                'Imagens verificadas: %1$s / %2$s — lote: %5$s; atualizadas: %3$s; sem alteração: %4$s.',
                number_format_i18n( (int) $state['processed'] ),
                number_format_i18n( (int) $state['total'] ),
                number_format_i18n( (int) ( $state['images_updated'] ?? 0 ) ),
                number_format_i18n( (int) ( $state['unchanged'] ?? 0 ) ),
                number_format_i18n( (int) ( $state['batch_size'] ?? $per_page ) )
            )
            : sprintf(
                'Produtos processados: %1$s / %2$s — lote: %3$s; criados: %4$s; atualizados: %5$s; ignorados: %6$s.',
                number_format_i18n( (int) $state['processed'] ),
                number_format_i18n( (int) $state['total'] ),
                number_format_i18n( (int) ( $state['batch_size'] ?? $per_page ) ),
                number_format_i18n( (int) ( $state['created'] ?? 0 ) ),
                number_format_i18n( (int) ( $state['updated'] ?? 0 ) ),
                number_format_i18n( (int) ( $state['ignored'] ?? 0 ) )
            );

        wp_send_json_success(
            array(
                'done'    => 'done' === ( $state['status'] ?? '' ),
                'state'   => $state,
                'message' => $message,
            )
        );
    }

    private static function push_recent_result( array &$state, array $source, string $status, string $message ): void {
        $row = array(
            'source_id' => absint( $source['id'] ?? 0 ),
            'sku'       => wc_clean( (string) ( $source['sku'] ?? '' ) ),
            'name'      => sanitize_text_field( (string) ( $source['name'] ?? '' ) ),
            'status'         => sanitize_key( $status ),
            'product_status' => sanitize_key( (string) ( $source['status'] ?? '' ) ),
            'message'        => sanitize_text_field( $message ),
            'time'      => wp_date( 'H:i:s' ),
        );

        $recent = (array) ( $state['recent_results'] ?? array() );
        array_unshift( $recent, $row );
        $state['recent_results'] = array_slice( $recent, 0, 30 );
        $state['last_result']    = $row;
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Sem permissões.', 'cv-r2-media-linker' ) );
        }

        $settings = CVR2_REST_Client::settings();
        $state    = (array) get_option( CVR2_STATE_OPTION, array() );
        $nonce    = wp_create_nonce( 'cvr2_import' );
        $tab      = sanitize_key( (string) wp_unslash( $_GET['tab'] ?? 'import' ) );
        if ( ! in_array( $tab, array( 'import', 'local', 'r2', 'slugs', 'seo' ), true ) ) {
            $tab = 'import';
        }

        $base_url = add_query_arg( 'page', 'cv-r2-rest-import', admin_url( 'admin.php' ) );
        ?>
        <div class="wrap cvr2-admin">
            <h1>CHAVE VERTICAL — Importação REST + R2</h1>

            <nav class="nav-tab-wrapper" style="margin-bottom:18px">
                <a class="nav-tab <?php echo 'import' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'import', $base_url ) ); ?>">Importação</a>
                <a class="nav-tab <?php echo 'local' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'local', $base_url ) ); ?>">Catálogo local / CSV</a>
                <a class="nav-tab <?php echo 'r2' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'r2', $base_url ) ); ?>">Ferramentas R2</a>
                <a class="nav-tab <?php echo 'slugs' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'slugs', $base_url ) ); ?>">Comparar slugs</a>
                <a class="nav-tab <?php echo 'seo' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'seo', $base_url ) ); ?>">SEO Backend</a>
            </nav>

            <style>
                .cvr2-card{padding:20px;border:1px solid #dcdcde;border-radius:10px;background:#fff}
                .cvr2-row{display:grid;grid-template-columns:180px minmax(0,1fr);gap:14px;align-items:center;margin:12px 0}
                .cvr2-row input{width:100%}
                .cvr2-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px}
                .cvr2-log{min-height:56px;margin-top:14px;padding:12px;border:1px solid #dcdcde;background:#f6f7f7;white-space:pre-wrap}
                .cvr2-progress-meta{margin:12px 0 5px;display:flex;justify-content:space-between;gap:12px;color:#50575e;font-size:12px;font-weight:600}
                .cvr2-progress{height:14px;margin:0 0 12px;overflow:hidden;border-radius:999px;background:#e5e5e5}
                .cvr2-progress>span{height:100%;display:block;width:0;background:#00a32a;transition:width .2s}
                .cvr2-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(112px,1fr));gap:8px}
                .cvr2-stat{min-width:0;padding:10px;background:#f6f7f7;border-radius:7px}
                .cvr2-stat strong{display:block;font-size:18px}
                .cvr2-results-wrap{margin-top:14px;overflow:auto;border:1px solid #dcdcde;border-radius:8px;background:#fff}
                .cvr2-results{width:100%;border-collapse:collapse;font-size:12px}
                .cvr2-results th,.cvr2-results td{padding:8px 10px;border-bottom:1px solid #f0f0f1;text-align:left;vertical-align:top}
                .cvr2-results th{position:sticky;top:0;background:#f6f7f7;z-index:1}
                @media(max-width:900px){.cvr2-row{grid-template-columns:1fr}}
            </style>

            <?php if ( 'local' === $tab ) : ?>
                <?php CVR2_Local_Admin::render(); ?>
            </div>
                <?php return; ?>
            <?php elseif ( 'r2' === $tab ) : ?>
                <?php CVR2_R2_Tools::render(); ?>
            </div>
                <?php return; ?>
            <?php elseif ( 'slugs' === $tab ) : ?>
                <?php CVR2_Slug_Audit::render(); ?>
            </div>
                <?php return; ?>
            <?php elseif ( 'seo' === $tab ) : ?>
                <?php CVR2_Backend_SEO::render(); ?>
            </div>
                <?php return; ?>
            <?php endif; ?>

            <p>Importa produtos do site original por WooCommerce REST API, preservando slugs, dados WooCommerce, variações, metadados Rank Math e associações de imagens.</p>

            <?php if ( isset( $_GET['cvr2_saved'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>Configuração guardada.</p></div>
            <?php endif; ?>

            <style>
                .cvr2-grid{display:grid;grid-template-columns:minmax(360px,.9fr) minmax(620px,1.35fr);gap:18px;max-width:1600px;align-items:start}
                .cvr2-card{padding:20px;border:1px solid #dcdcde;border-radius:10px;background:#fff}
                .cvr2-card h2{margin-top:0}.cvr2-row{display:grid;grid-template-columns:180px minmax(0,1fr);gap:14px;align-items:center;margin:12px 0}
                .cvr2-row input{width:100%}.cvr2-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px}
                .cvr2-log{min-height:72px;padding:12px;border:1px solid #dcdcde;background:#f6f7f7;white-space:pre-wrap}
                .cvr2-active-mode{margin:14px 0 8px;padding:9px 12px;border-radius:7px;background:#f0f6fc;border:1px solid #c6d9ee;font-weight:700;color:#1d4f7a}
                .cvr2-watchdog{margin:0 0 12px;padding:8px 11px;border-radius:7px;background:#f6f7f7;border:1px solid #dcdcde;color:#50575e;font-size:12px}
                .cvr2-progress-meta{margin:8px 0 5px;display:flex;justify-content:space-between;gap:12px;color:#50575e;font-size:12px;font-weight:600}
                .cvr2-progress{height:14px;margin:0 0 12px;overflow:hidden;border-radius:999px;background:#e5e5e5}
                .cvr2-progress>span{height:100%;display:block;width:0;background:#00a32a;transition:width .2s}
                .cvr2-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(112px,1fr));gap:8px}.cvr2-stat{min-width:0;padding:10px;background:#f6f7f7;border-radius:7px}
                .cvr2-current{margin:12px 0;padding:10px 12px;border-left:4px solid #2271b1;background:#f0f6fc}
                .cvr2-results-wrap{margin-top:14px;max-height:360px;overflow:auto;border:1px solid #dcdcde;border-radius:8px;background:#fff}
                .cvr2-results{width:100%;min-width:760px;border-collapse:collapse;font-size:12px}
                .cvr2-results th,.cvr2-results td{padding:8px 10px;border-bottom:1px solid #f0f0f1;text-align:left;vertical-align:top}
                .cvr2-results th{position:sticky;top:0;background:#f6f7f7;z-index:1}
                .cvr2-status{display:inline-block;padding:3px 7px;border-radius:999px;font-size:10px;font-weight:800;text-transform:uppercase}
                .cvr2-status--updated,.cvr2-status--created{background:#edfaef;color:#116329}
                .cvr2-status--unchanged{background:#f0f0f1;color:#50575e}
                .cvr2-status--ignored{background:#fff7e6;color:#7a4b00}
                .cvr2-status--error{background:#fcf0f1;color:#8a2424}
                .cvr2-mode{margin:0 0 16px;padding:14px;border:1px solid #dcdcde;border-radius:8px;background:#f6f7f7}
                .cvr2-mode label{display:block;margin:8px 0}
                .cvr2-batch-size{margin-top:14px;padding-top:12px;border-top:1px solid #dcdcde;display:grid;grid-template-columns:170px 90px minmax(220px,1fr);gap:10px;align-items:center}
                .cvr2-batch-size label{margin:0}
                .cvr2-batch-size input{width:90px}
                .cvr2-stat strong{display:block;font-size:18px}
                @media(max-width:1180px){.cvr2-grid{grid-template-columns:1fr}.cvr2-row{grid-template-columns:1fr}.cvr2-batch-size{grid-template-columns:160px 90px 1fr}}
                @media(max-width:700px){.cvr2-stats{grid-template-columns:1fr 1fr}.cvr2-batch-size{grid-template-columns:1fr}.cvr2-results-wrap{max-width:100%}}
            </style>

            <div class="cvr2-grid">
                <section class="cvr2-card">
                    <h2>Origem REST</h2>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="cvr2_save_settings">
                        <?php wp_nonce_field( 'cvr2_save_settings' ); ?>

                        <div class="cvr2-row">
                            <label for="cvr2-source-url"><strong>Site original</strong></label>
                            <input id="cvr2-source-url" name="source_url" type="url" value="<?php echo esc_attr( (string) $settings['source_url'] ); ?>" required>
                        </div>
                        <div class="cvr2-row">
                            <label for="cvr2-r2-url"><strong>URL pública R2</strong></label>
                            <input id="cvr2-r2-url" name="r2_base_url" type="url" value="<?php echo esc_attr( (string) $settings['r2_base_url'] ); ?>" required>
                        </div>
                        <div class="cvr2-row">
                            <label for="cvr2-ck"><strong>Consumer key</strong></label>
                            <input id="cvr2-ck" name="source_ck" type="password" value="" autocomplete="new-password" placeholder="<?php echo CVR2_REST_Client::consumer_key() ? 'Configurada — deixar vazio para manter' : 'ck_…'; ?>">
                        </div>
                        <div class="cvr2-row">
                            <label for="cvr2-cs"><strong>Consumer secret</strong></label>
                            <input id="cvr2-cs" name="source_cs" type="password" value="" autocomplete="new-password" placeholder="<?php echo CVR2_REST_Client::consumer_secret() ? 'Configurada — deixar vazio para manter' : 'cs_…'; ?>">
                        </div>
                        <div class="cvr2-row">
                            <strong>Modo de execução</strong>
                            <div>
                                <strong>Seguro com checkpoint automático</strong><br>
                                <small>Todos os modos aceitam lotes de 1 a 50 produtos. O estado é gravado depois de cada produto realmente concluído; se houver timeout, pausa ou limite de tempo do pedido, a retoma continua dentro do mesmo lote sem perder o progresso.</small>
                                <input type="hidden" name="batch_size" value="1">
                            </div>
                        </div>

                        <p><button class="button button-primary" type="submit">Guardar configuração</button></p>
                    </form>

                    <p><strong>Prioridade de imagem:</strong> WEBP existente no R2 → formato original existente no R2 (JPG/JPEG/PNG/GIF/AVIF/etc.) → imagem original. Se a origem estiver em disco local, S3 ou outro URL externo, a imagem entra pela Media Library com <code>media_handle_sideload()</code> e o offload para R2 é feito pelos hooks normais do WordPress/plugin de storage. Não existe escrita direta no bucket fora do WordPress.</p>
                    <p><strong>Slug obrigatório:</strong> o slug recebido da origem tem de ficar exatamente igual. Se o WordPress tentar criar <code>-2</code>, <code>-3</code> ou qualquer outra alteração, esse produto é marcado com erro e não é considerado importado.</p>
                    <p><strong>Identidade do produto:</strong> ID original → SKU → slug. Depois da importação o slug fica bloqueado contra alterações acidentais.</p>
                </section>

                <section class="cvr2-card">
                    <h2>Importação</h2>
                    <div class="cvr2-mode">
                        <strong>Produtos e estado WooCommerce</strong>
                        <label><input type="radio" name="cvr2_import_mode" value="update_existing" checked> Atualizar produtos existentes e criar produtos novos</label>
                        <label><input type="radio" name="cvr2_import_mode" value="create_only"> Ignorar produtos existentes e criar apenas produtos novos</label>
                        <label><input type="radio" name="cvr2_import_mode" value="products_no_images"> <strong>1.ª fase — importar produtos sem imagens</strong> (criar e atualizar, sem alterar imagens existentes)</label>
                        <label><input type="radio" name="cvr2_import_mode" value="images_only"> <strong>2.ª fase — associar as imagens</strong> dos produtos e variações já importados</label>
                        <small>Para acelerar a migração, executar primeiro a 1.ª fase e, depois de terminar, a 2.ª fase. A associação de imagens não é executada na 1.ª fase. Em todos os modos, só são utilizadas categorias existentes: as categorias em falta bloqueiam o produto, nunca são criadas automaticamente. Os estados Publicado, Rascunho, Pendente e Privado, atributos e metadados SEO mantêm-se.</small>

                        <div class="cvr2-batch-size">
                            <label for="cvr2-batch-size"><strong>Quantidade por lote</strong></label>
                            <input id="cvr2-batch-size" type="number" min="1" max="50" step="1" value="<?php echo esc_attr( (string) max( 1, min( 50, absint( $state['batch_size'] ?? 10 ) ) ) ); ?>" inputmode="numeric">
                            <small>Máximo 50. Recomendado: começar com 5–10 produtos por lote, aumentar progressivamente se o servidor responder bem. A 1.ª fase é normalmente mais rápida porque ignora o processamento de imagens.</small>
                        </div>
                    </div>
                    <div class="cvr2-actions">
                        <button class="button" type="button" data-cvr2-action="test">1. Testar REST</button>
                        <button class="button" type="button" data-cvr2-action="structure">2. Preparar termos e atributos (opcional)</button>
                        <button class="button button-primary" type="button" data-cvr2-action="start">3. Iniciar / recomeçar importação</button>
                        <button class="button" type="button" data-cvr2-action="resume">Retomar</button>
                        <button class="button" type="button" data-cvr2-action="pause">Pausar</button>
                        <button class="button" type="button" data-cvr2-action="reset">Limpar estado</button>
                    </div>

                    <div class="cvr2-active-mode" data-cvr2-active-mode>Modo ativo: nenhum</div>
                    <div class="cvr2-watchdog">
                        <label for="cvr2-watchdog-enabled">
                            <input type="checkbox" id="cvr2-watchdog-enabled" <?php checked( self::watchdog_enabled() ); ?>>
                            <strong>Ativar watchdog automático (retoma após falhas)</strong>
                        </label>
                        <small style="display:block;margin-top:5px">Por defeito desligado, para evitar sobrecarga. Mesmo desligado, os checkpoints continuam guardados e podes clicar em «Retomar».</small>
                        <div data-cvr2-watchdog style="margin-top:6px">Watchdog automático: desligado.</div>
                    </div>

                    <div class="cvr2-progress-meta">
                        <span data-cvr2-progress-text>0 / 0 produtos</span>
                        <span data-cvr2-progress-percent>0%</span>
                    </div>
                    <div class="cvr2-progress" aria-hidden="true"><span data-cvr2-progress></span></div>
                    <div class="cvr2-stats">
                        <div class="cvr2-stat"><span>Processados</span><strong data-stat="processed">0</strong></div>
                        <div class="cvr2-stat"><span>Total</span><strong data-stat="total">0</strong></div>
                        <div class="cvr2-stat"><span>Criados</span><strong data-stat="created">0</strong></div>
                        <div class="cvr2-stat"><span>Atualizados</span><strong data-stat="updated">0</strong></div>
                        <div class="cvr2-stat"><span>Imagens atualizadas</span><strong data-stat="images_updated">0</strong></div>
                        <div class="cvr2-stat"><span>Sem alteração</span><strong data-stat="unchanged">0</strong></div>
                        <div class="cvr2-stat"><span>Ignorados</span><strong data-stat="ignored">0</strong></div>
                        <div class="cvr2-stat"><span>Erros de slug</span><strong data-stat="slug_errors">0</strong></div>
                    </div>
                    <h3>Estado</h3>
                    <div class="cvr2-log" data-cvr2-log>Pronto.</div>
                    <div class="cvr2-current" data-cvr2-current hidden></div>

                    <h3>Últimos produtos processados</h3>
                    <div class="cvr2-results-wrap">
                        <table class="cvr2-results">
                            <thead>
                                <tr>
                                    <th>Hora</th>
                                    <th>Produto</th>                                    <th>SKU</th>
                                    <th>Estado Woo</th>
                                    <th>Resultado</th>
                                    <th>Detalhe</th>
                                </tr>
                            </thead>
                            <tbody data-cvr2-results>
                                <tr><td colspan="6">Ainda sem resultados.</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <?php if ( $state ) : ?>
                        <p class="description">
                            <strong>Estado guardado:</strong>
                            <?php echo esc_html( ucfirst( (string) ( $state['status'] ?? 'desconhecido' ) ) ); ?>
                            · Lote <?php echo esc_html( (string) absint( $state['batch_size'] ?? 1 ) ); ?>
                            · <?php echo esc_html( number_format_i18n( absint( $state['processed'] ?? 0 ) ) ); ?> processados
                        </p>
                    <?php endif; ?>
                </section>
            </div>
        </div>

        <script>
        (() => {
            const nonce = <?php echo wp_json_encode( $nonce ); ?>;
            const log = document.querySelector('[data-cvr2-log]');
            const progress = document.querySelector('[data-cvr2-progress]');
            const progressText = document.querySelector('[data-cvr2-progress-text]');
            const progressPercent = document.querySelector('[data-cvr2-progress-percent]');
            const current = document.querySelector('[data-cvr2-current]');
            const results = document.querySelector('[data-cvr2-results]');
            const activeMode = document.querySelector('[data-cvr2-active-mode]');
            const watchdogStatus = document.querySelector('[data-cvr2-watchdog]');
            const watchdogCheckbox = document.querySelector('#cvr2-watchdog-enabled');
            let watchdogEnabled = <?php echo wp_json_encode( self::watchdog_enabled() ); ?>;
            const buttons = document.querySelectorAll('[data-cvr2-action]');
            const modeInputs = document.querySelectorAll('input[name="cvr2_import_mode"]');
            const batchInput = document.querySelector('#cvr2-batch-size');
            const WATCHDOG_DELAY_MS = 60000;
            let running = false;
            let activeRunId = '';
            let activeImportMode = '';
            let statusTimer = null;
            let statusRequestActive = false;
            let watchdogTimer = null;
            let manualPause = false;
            let lastKnownState = {};

            const write = (message) => { if (log) log.textContent = String(message || ''); };
            const writeWatchdog = (message) => { if (watchdogStatus) watchdogStatus.textContent = String(message || ''); };
            const setBusy = (busy) => {
                buttons.forEach((button) => {
                    if (button.dataset.cvr2Action === 'pause') {
                        button.disabled = !running;
                        return;
                    }
                    button.disabled = busy;
                });

                modeInputs.forEach((input) => {
                    input.disabled = busy;
                });

                if (batchInput) {
                    batchInput.disabled = busy;
                }
            };
            // Ceder recursos a outras APIs entre cada lote.
            const yieldBrowser = () => new Promise((resolve) => window.setTimeout(resolve, 650));

            function renderState(state) {
                state = state || {};
                lastKnownState = state;

                if (state.run_id) activeRunId = String(state.run_id);
                if (state.import_mode) activeImportMode = String(state.import_mode);

                const modeLabels = {
                    update_existing: 'ATUALIZAR EXISTENTES + CRIAR NOVOS',
                    create_only: 'CRIAR APENAS NOVOS — EXISTENTES IGNORADOS',
                    products_no_images: '1.ª FASE — PRODUTOS SEM IMAGENS',
                    images_only: '2.ª FASE — APENAS ATUALIZAR IMAGENS'
                };

                if (activeMode) {
                    activeMode.textContent = 'Modo ativo: ' + (modeLabels[activeImportMode] || 'nenhum');
                }

                if (activeImportMode) {
                    modeInputs.forEach((input) => {
                        input.checked = input.value === activeImportMode;
                    });
                }

                ['processed','total','created','updated','images_updated','unchanged','ignored','slug_errors'].forEach((key) => {
                    const el = document.querySelector('[data-stat="' + key + '"]');
                    if (el) el.textContent = Number(state[key] || 0).toLocaleString('pt-PT');
                });

                const total = Number(state.total || 0);
                const processed = Number(state.processed || 0);
                const percent = total > 0 ? Math.min(100, (processed / total) * 100) : 0;
                if (progress) progress.style.width = percent + '%';
                if (progressText) progressText.textContent = processed.toLocaleString('pt-PT') + ' / ' + total.toLocaleString('pt-PT') + ' produtos';
                if (progressPercent) progressPercent.textContent = percent.toLocaleString('pt-PT', {maximumFractionDigits:1}) + '%';

                if (current) {
                    const item = state.current || {};
                    if (item.source_id || item.sku || item.name) {
                        current.hidden = false;
                        current.textContent = 'A processar: ' + [item.name || ('#' + (item.source_id || '')), item.sku ? 'SKU ' + item.sku : ''].filter(Boolean).join(' — ');
                    } else {
                        current.hidden = true;
                        current.textContent = '';
                    }
                }

                if (results) {
                    const rows = Array.isArray(state.recent_results) ? state.recent_results : [];
                    results.replaceChildren();

                    if (!rows.length) {
                        const tr = document.createElement('tr');
                        const td = document.createElement('td');
                        td.colSpan = 6;
                        td.textContent = 'Ainda sem resultados.';
                        tr.appendChild(td);
                        results.appendChild(tr);
                    } else {
                        rows.forEach((row) => {
                            const tr = document.createElement('tr');
                            const values = [
                                row.time || '',
                                row.name || (row.source_id ? '#' + row.source_id : ''),
                                row.sku || '',
                            ];

                            values.forEach((value) => {
                                const td = document.createElement('td');
                                td.textContent = String(value || '');
                                tr.appendChild(td);
                            });

                            const wooStatusTd = document.createElement('td');
                            const wooStatusLabels = {
                                publish: 'Publicado',
                                draft: 'Rascunho',
                                pending: 'Pendente',
                                private: 'Privado'
                            };
                            const wooStatus = String(row.product_status || '');
                            wooStatusTd.textContent = wooStatusLabels[wooStatus] || wooStatus || '—';
                            tr.appendChild(wooStatusTd);

                            const statusTd = document.createElement('td');
                            const badge = document.createElement('span');
                            const labels = {
                                updated: 'Atualizado',
                                created: 'Criado',
                                unchanged: 'Sem alteração',
                                ignored: 'Ignorado',
                                error: 'Erro'
                            };
                            const status = String(row.status || 'unchanged');
                            badge.className = 'cvr2-status cvr2-status--' + status;
                            badge.textContent = labels[status] || status;
                            statusTd.appendChild(badge);
                            tr.appendChild(statusTd);

                            const messageTd = document.createElement('td');
                            messageTd.textContent = String(row.message || '');
                            tr.appendChild(messageTd);

                            results.appendChild(tr);
                        });
                    }
                }
            }

            async function call(action, params = {}) {
                const body = new URLSearchParams({action, nonce, ...params});
                const response = await fetch(ajaxurl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
                    body: body.toString()
                });
                const json = await response.json();
                if (!json.success) {
                    const error = new Error(json.data?.message || 'Erro no pedido.');
                    error.code = String(json.data?.code || '');
                    error.state = json.data?.state || null;
                    throw error;
                }
                return json.data || {};
            }

            async function refreshLiveStatus(force = false) {
                if ((!running && !force) || statusRequestActive) return;
                statusRequestActive = true;

                try {
                    const data = await call('cvr2_import_status', activeRunId ? {run_id: activeRunId} : {});
                    renderState(data.state);
                    if (typeof data.watchdog_enabled === 'boolean') {
                        watchdogEnabled = data.watchdog_enabled;
                        if (watchdogCheckbox) watchdogCheckbox.checked = watchdogEnabled;
                        if (!watchdogEnabled) cancelWatchdog();
                    }
                } catch (error) {
                    // O pedido principal pode continuar a trabalhar mesmo que
                    // uma leitura de estado falhe momentaneamente.
                } finally {
                    statusRequestActive = false;
                }
            }

            function startStatusPolling() {
                stopStatusPolling();
                // A API de estado também consome trabalhadores PHP; 5 s é suficiente.
                statusTimer = window.setInterval(refreshLiveStatus, 5000);
                refreshLiveStatus();
            }

            function stopStatusPolling() {
                if (statusTimer) {
                    window.clearInterval(statusTimer);
                    statusTimer = null;
                }
                statusRequestActive = false;
            }

            function cancelWatchdog() {
                if (watchdogTimer) {
                    window.clearTimeout(watchdogTimer);
                    watchdogTimer = null;
                }
            }

            function canResume(state = lastKnownState) {
                state = state || {};
                return Boolean(
                    activeRunId
                    && activeImportMode
                    && String(state.status || '') !== 'done'
                );
            }

            function armWatchdog() {
                cancelWatchdog();

                if (!watchdogEnabled) {
                    writeWatchdog('Watchdog automático: DESLIGADO. Usa «Retomar» para continuar após uma interrupção.');
                    return;
                }
                if (manualPause) {
                    writeWatchdog('Watchdog automático: pausado manualmente.');
                    return;
                }

                if (!canResume()) {
                    writeWatchdog('Watchdog automático: sem execução pendente.');
                    return;
                }

                const checkpoint = {
                    runId: activeRunId,
                    processed: Number(lastKnownState.processed || 0),
                    updatedAt: Number(lastKnownState.updated_at || 0)
                };

                writeWatchdog('Watchdog automático: ativo — se parar, retoma após 1 minuto.');

                watchdogTimer = window.setTimeout(async () => {
                    watchdogTimer = null;

                    if (!watchdogEnabled || manualPause || running || !canResume()) return;

                    let state = lastKnownState;

                    try {
                        const data = await call('cvr2_import_status', checkpoint.runId ? {run_id: checkpoint.runId} : {});
                        state = data.state || {};
                        renderState(state);
                        if (typeof data.watchdog_enabled === 'boolean' && !data.watchdog_enabled) {
                            watchdogEnabled = false;
                            if (watchdogCheckbox) watchdogCheckbox.checked = false;
                            writeWatchdog('Watchdog automático: DESLIGADO.');
                            return;
                        }

                        if (data.done || !canResume(state)) {
                            writeWatchdog('Watchdog automático: execução concluída.');
                            return;
                        }
                    } catch (error) {
                        if (error.state) {
                            state = error.state;
                            renderState(state);
                        }

                        if (error.code === 'stale_run') {
                            writeWatchdog('Watchdog automático: foi detetada outra execução ativa; a vigiar a nova execução.');
                            armWatchdog();
                            return;
                        }
                    }

                    const sameRun = String(state.run_id || activeRunId) === checkpoint.runId;
                    const progressed = sameRun && (
                        Number(state.processed || 0) > checkpoint.processed
                        || Number(state.updated_at || 0) > checkpoint.updatedAt
                    );

                    if (progressed) {
                        writeWatchdog('Watchdog automático: houve progresso no último minuto; continua a vigiar.');
                        armWatchdog();
                        return;
                    }

                    if (!watchdogEnabled) return;
                    write('Watchdog: a importação esteve parada durante 1 minuto. A retomar automaticamente…');
                    writeWatchdog('Watchdog automático: a retomar agora…');
                    loop();
                }, WATCHDOG_DELAY_MS);
            }

            async function loop() {
                if (running) return;

                cancelWatchdog();
                manualPause = false;
                running = true;
                setBusy(true);
                writeWatchdog(watchdogEnabled
                    ? 'Watchdog automático: importação em curso; retoma se parar.'
                    : 'Watchdog automático: DESLIGADO; importação manual em curso.');
                startStatusPolling();

                try {
                    while (running) {
                        const data = await call('cvr2_import_batch', {
                            run_id: activeRunId,
                            import_mode: activeImportMode
                        });
                        renderState(data.state);

                        if (data.busy) {
                            write(data.message || 'Outro lote desta execução ainda está a terminar.');
                            await new Promise((resolve) => window.setTimeout(resolve, 600));
                            continue;
                        }
                        write(data.message || (data.done ? 'Importação concluída.' : 'A processar…'));

                        if (data.done) {
                            running = false;
                            cancelWatchdog();
                            writeWatchdog('Watchdog automático: execução concluída.');
                            write(data.message || 'Importação concluída. O estado ficou guardado.');
                            break;
                        }

                        // Entrega o controlo ao browser antes do próximo produto.
                        await yieldBrowser();
                    }
                } catch (error) {
                    running = false;

                    if (error.state) {
                        renderState(error.state);
                    }

                    write((error.message || error) + (watchdogEnabled
                        ? ' — o watchdog tentará retomar em 1 minuto.'
                        : ' — watchdog desligado; para continuar clica em «Retomar».'));
                } finally {
                    stopStatusPolling();
                    await refreshLiveStatus(true);
                    setBusy(false);

                    if (!manualPause && !running && canResume()) {
                        armWatchdog();
                    }
                }
            }

            watchdogCheckbox?.addEventListener('change', async () => {
                const desired = watchdogCheckbox.checked;
                watchdogCheckbox.disabled = true;
                try {
                    const result = await call('cvr2_set_watchdog', {enabled: desired ? '1' : '0'});
                    watchdogEnabled = Boolean(result.enabled);
                    watchdogCheckbox.checked = watchdogEnabled;
                    if (!watchdogEnabled) cancelWatchdog();
                    else if (!running && !manualPause) armWatchdog();
                    if (!watchdogEnabled) writeWatchdog('Watchdog automático: DESLIGADO; retoma manual.');
                    write(result.message || (watchdogEnabled ? 'Watchdog ligado.' : 'Watchdog desligado.'));
                } catch (error) {
                    watchdogCheckbox.checked = watchdogEnabled;
                    write('Não foi possível guardar a opção do watchdog: ' + error.message);
                } finally {
                    watchdogCheckbox.disabled = false;
                }
            });

            document.querySelector('[data-cvr2-action="test"]')?.addEventListener('click', async () => {
                setBusy(true);
                try { const data = await call('cvr2_test_source'); write(data.message); }
                catch (error) { write(error.message || error); }
                finally { setBusy(false); }
            });

            document.querySelector('[data-cvr2-action="structure"]')?.addEventListener('click', async () => {
                setBusy(true);
                write('A verificar categorias existentes e preparar etiquetas, marcas e atributos…');
                try { const data = await call('cvr2_sync_structure'); write(data.message); }
                catch (error) { write(error.message || error); }
                finally { setBusy(false); }
            });

            document.querySelector('[data-cvr2-action="start"]')?.addEventListener('click', async () => {
                manualPause = false;
                cancelWatchdog();
                setBusy(true);
                try {
                    const mode = document.querySelector('input[name="cvr2_import_mode"]:checked')?.value || 'update_existing';
                    const batchSize = Math.max(1, Math.min(50, Number(batchInput?.value || 10)));
                    if (batchInput) batchInput.value = String(batchSize);
                    const data = await call('cvr2_start_import', {import_mode: mode, batch_size: batchSize});
                    activeRunId = String(data.state?.run_id || '');
                    activeImportMode = String(data.state?.import_mode || mode);
                    renderState(data.state);
                    write(data.message);
                    setBusy(false);
                    loop();
                } catch (error) {
                    write(error.message || error);
                    setBusy(false);
                }
            });

            document.querySelector('[data-cvr2-action="resume"]')?.addEventListener('click', async () => {
                manualPause = false;
                cancelWatchdog();
                setBusy(true);
                try {
                    const data = await call('cvr2_import_status');
                    activeRunId = String(data.state?.run_id || '');
                    activeImportMode = String(data.state?.import_mode || '');
                    renderState(data.state);

                    if (!activeRunId || !activeImportMode) {
                        write('Não existe uma importação válida para retomar.');
                        setBusy(false);
                        return;
                    }

                    setBusy(false);
                    loop();
                } catch (error) {
                    write(error.message || error);
                    setBusy(false);
                }
            });

            document.querySelector('[data-cvr2-action="pause"]')?.addEventListener('click', () => {
                manualPause = true;
                running = false;
                cancelWatchdog();
                stopStatusPolling();
                setBusy(false);
                refreshLiveStatus(true);
                writeWatchdog('Watchdog automático: pausado manualmente.');
                write('Importação pausada no browser. O progresso ficou guardado e pode ser retomado.');
            });

            const initialState = <?php echo wp_json_encode( $state, JSON_UNESCAPED_UNICODE ); ?>;
            renderState(initialState);
            armWatchdog();

            document.querySelector('[data-cvr2-action="reset"]')?.addEventListener('click', async () => {
                manualPause = true;
                running = false;
                cancelWatchdog();
                stopStatusPolling();
                setBusy(true);
                try {
                    const data = await call('cvr2_reset_import');
                    activeRunId = '';
                    activeImportMode = '';
                    renderState({});
                    if (activeMode) activeMode.textContent = 'Modo ativo: nenhum';
                    writeWatchdog('Watchdog automático: sem execução ativa.');
                    write(data.message);
                } catch (error) {
                    write(error.message || error);
                } finally {
                    setBusy(false);
                }
            });
        })();
        </script>
        <?php
    }
}