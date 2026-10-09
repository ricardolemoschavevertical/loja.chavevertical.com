<?php
defined( 'ABSPATH' ) || exit;

/** CSV upload, field mapping and partial WooCommerce upsert. */
final class CVR2_Local_CSV {
    private const UPLOAD_OPTION = 'cvr2_local_csv_upload_v1';

    public static function init(): void {
        foreach ( array(
            'cvr2_local_csv_upload' => 'ajax_upload',
            'cvr2_local_csv_start'  => 'ajax_start',
        ) as $action => $method ) {
            add_action( 'wp_ajax_' . $action, array( __CLASS__, $method ) );
        }
    }

    public static function fields(): array {
        return array(
            'sku' => 'SKU (obrigatório)', 'name' => 'Nome', 'slug' => 'Slug',
            'type' => 'Tipo (simple/variable)', 'status' => 'Estado', 'description' => 'Descrição',
            'short_description' => 'Descrição curta', 'regular_price' => 'Preço normal',
            'sale_price' => 'Preço promocional', 'manage_stock' => 'Gerir stock (1/0)',
            'stock_quantity' => 'Quantidade em stock', 'stock_status' => 'Disponibilidade',
            'weight' => 'Peso', 'length' => 'Comprimento', 'width' => 'Largura',
            'height' => 'Altura', 'categories' => 'Categorias existentes (slug/nome/caminho; separador | ou vírgula)',
            'images' => 'Imagens (URLs HTTPS; separador | ou vírgula)',
            'seo_title' => 'Rank Math - Título SEO', 'seo_description' => 'Rank Math - Meta description',
            'focus_keyword' => 'Rank Math - Palavra-chave principal',
        );
    }

    private static function csv_path( string $id ) {
        if ( ! preg_match( '/^[a-f0-9]{32}$/D', $id ) ) {
            return new WP_Error( 'cvr2_csv_id', 'Identificador CSV inválido.' );
        }
        $dir = CVR2_Local_Catalog::directory();
        return is_wp_error( $dir ) ? $dir : $dir . '/csv-' . $id . '.csv';
    }

    public static function remove_uploaded_file(): void {
        $item = (array) get_option( self::UPLOAD_OPTION, array() );
        $path = self::csv_path( (string) ( $item['id'] ?? '' ) );
        if ( ! is_wp_error( $path ) && is_file( $path ) ) {
            @unlink( $path );
        }
        delete_option( self::UPLOAD_OPTION );
    }

    private static function delimiter_and_headers( string $path ) {
        $fp = fopen( $path, 'rb' );
        if ( ! $fp ) {
            return new WP_Error( 'cvr2_csv_read', 'Não foi possível ler o CSV.' );
        }
        $first = fgets( $fp, 65536 );
        if ( ! is_string( $first ) ) {
            fclose( $fp );
            return new WP_Error( 'cvr2_csv_empty', 'CSV vazio.' );
        }
        $first = preg_replace( '/^\xEF\xBB\xBF/', '', $first );
        $best = ','; $best_count = 0;
        foreach ( array( ';', ',', "\t" ) as $candidate ) {
            $parts = str_getcsv( $first, $candidate, '"', '' );
            if ( count( $parts ) > $best_count ) {
                $best_count = count( $parts );
                $best = $candidate;
            }
        }
        if ( $best_count < 2 ) {
            fclose( $fp );
            return new WP_Error( 'cvr2_csv_columns', 'O CSV deve ter pelo menos duas colunas.' );
        }
        rewind( $fp );
        $headers = fgetcsv( $fp, 0, $best, '"', '' );
        if ( ! is_array( $headers ) ) {
            fclose( $fp );
            return new WP_Error( 'cvr2_csv_header', 'Cabeçalho CSV inválido.' );
        }
        $headers = array_map( static function ( $value ) {
            return trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $value ) );
        }, $headers );
        if ( count( array_unique( $headers ) ) !== count( $headers ) || in_array( '', $headers, true ) || count( $headers ) > 250 ) {
            fclose( $fp );
            return new WP_Error( 'cvr2_csv_duplicate', 'Cabeçalhos vazios, repetidos ou demasiadas colunas.' );
        }
        $preview = array();
        for ( $i = 0; $i < 5 && ! feof( $fp ); $i++ ) {
            $row = fgetcsv( $fp, 0, $best, '"', '' );
            if ( is_array( $row ) ) {
                $preview[] = array_slice( array_map( 'sanitize_text_field', $row ), 0, 20 );
            }
        }
        // Rewind: retain only the offset after the first header record.
        rewind( $fp );
        fgetcsv( $fp, 0, $best, '"', '' );
        $data_start = ftell( $fp );
        fclose( $fp );
        return array( 'headers' => $headers, 'delimiter' => $best, 'preview' => $preview, 'data_start' => $data_start );
    }

    public static function ajax_upload(): void {
        CVR2_Local_Catalog::guard();
        CVR2_Local_Catalog::respond( CVR2_Local_Catalog::with_lock( static function () {
            $snapshot = CVR2_Local_Catalog::snapshot();
            $running = CVR2_Local_Catalog::import();
            if ( 'building' === ( $snapshot['status'] ?? '' ) || 'running' === ( $running['status'] ?? '' ) ) {
                return new WP_Error( 'cvr2_csv_busy', 'Concluir ou interromper a operação em curso antes de carregar o CSV.' );
            }
            $file = $_FILES['file'] ?? null;
            if ( ! is_array( $file ) || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? -1 ) ||
                 ! is_uploaded_file( (string) ( $file['tmp_name'] ?? '' ) ) ) {
                return new WP_Error( 'cvr2_csv_upload', 'Ficheiro não enviado corretamente.' );
            }
            $filename = sanitize_file_name( (string) ( $file['name'] ?? '' ) );
            if ( strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) !== 'csv' ) {
                return new WP_Error( 'cvr2_csv_extension', 'Enviar um ficheiro .csv.' );
            }
            $size = (int) ( $file['size'] ?? 0 );
            if ( $size < 5 || $size > 250 * 1024 * 1024 ) {
                return new WP_Error( 'cvr2_csv_size', 'O CSV deve ter entre 5 bytes e 250 MB.' );
            }
            $id = bin2hex( random_bytes( 16 ) );
            $path = self::csv_path( $id );
            if ( is_wp_error( $path ) ) {
                return $path;
            }
            if ( ! move_uploaded_file( $file['tmp_name'], $path ) ) {
                return new WP_Error( 'cvr2_csv_move', 'Não foi possível guardar o CSV no diretório privado.' );
            }
            @chmod( $path, 0600 );
            $parsed = self::delimiter_and_headers( $path );
            if ( is_wp_error( $parsed ) ) {
                @unlink( $path );
                return $parsed;
            }
            self::remove_uploaded_file();
            $upload = array_merge( $parsed, array( 'id' => $id, 'filename' => $filename, 'size' => $size ) );
            update_option( self::UPLOAD_OPTION, $upload, false );
            return $upload;
        } ) );
    }

    public static function ajax_start(): void {
        CVR2_Local_Catalog::guard();
        $posted_id = sanitize_text_field( (string) wp_unslash( $_POST['csv_id'] ?? '' ) );
        $mapping = json_decode( (string) wp_unslash( $_POST['mapping'] ?? '{}' ), true );
        CVR2_Local_Catalog::respond( CVR2_Local_Catalog::with_lock( static function () use ( $posted_id, $mapping ) {
            $uploaded = (array) get_option( self::UPLOAD_OPTION, array() );
            if ( ! $posted_id || ( $uploaded['id'] ?? '' ) !== $posted_id || ! is_array( $mapping ) ) {
                return new WP_Error( 'cvr2_csv_mapping', 'CSV ou mapeamento inválido.' );
            }
            $headers = (array) ( $uploaded['headers'] ?? array() );
            $allowed = self::fields();
            $clean = array();
            foreach ( $mapping as $field => $header ) {
                if ( ! isset( $allowed[$field] ) || ! is_string( $header ) || ! in_array( $header, $headers, true ) ) {
                    return new WP_Error( 'cvr2_csv_field', 'Existe uma coluna selecionada inválida.' );
                }
                $clean[$field] = $header;
            }
            if ( empty( $clean['sku'] ) ) {
                return new WP_Error( 'cvr2_csv_sku', 'Selecionar uma coluna SKU.' );
            }
            $path = self::csv_path( $posted_id );
            if ( is_wp_error( $path ) || ! is_file( $path ) ) {
                return new WP_Error( 'cvr2_csv_missing', 'CSV local não encontrado.' );
            }
            return CVR2_Local_Catalog::new_snapshot( 'csv', array(
                'csv_id' => $posted_id, 'csv_offset' => (int) $uploaded['data_start'],
                'headers' => $headers, 'delimiter' => (string) $uploaded['delimiter'],
                'mapping' => $clean,
            ) );
        } ) );
    }

    public static function prepare_step( array $state ) {
        $path = self::csv_path( (string) ( $state['csv_id'] ?? '' ) );
        if ( is_wp_error( $path ) ) {
            return $path;
        }
        $fp = @fopen( $path, 'rb' );
        if ( ! $fp || fseek( $fp, (int) ( $state['csv_offset'] ?? 0 ) ) !== 0 ) {
            if ( $fp ) { fclose( $fp ); }
            return new WP_Error( 'cvr2_csv_seek', 'Não foi possível retomar a leitura do CSV.' );
        }
        $headers = (array) ( $state['headers'] ?? array() );
        $delimiter = (string) ( $state['delimiter'] ?? ';' );
        $mapping = (array) ( $state['mapping'] ?? array() );
        $rows = array(); $read = 0;
        while ( $read < 300 && ! feof( $fp ) ) {
            $parts = fgetcsv( $fp, 0, $delimiter, '"', '' );
            if ( ! is_array( $parts ) || ( count( $parts ) === 1 && null === $parts[0] ) ) {
                continue;
            }
            $read++;
            if ( count( $parts ) !== count( $headers ) ) {
                fclose( $fp );
                return new WP_Error( 'cvr2_csv_width', sprintf( 'Linha CSV perto do registo %d: número de colunas diferente do cabeçalho.', (int) $state['records'] + $read ) );
            }
            $values = array_combine( $headers, $parts );
            $data = array( '__cvr2_csv' => true, 'id' => 0 );
            foreach ( $mapping as $field => $column ) {
                $value = trim( (string) ( $values[$column] ?? '' ) );
                // Empty cells do not overwrite existing data; "0" is intentionally retained.
                if ( '' === $value ) { continue; }
                if ( 'images' === $field ) {
                    $sources = array_map( 'trim', preg_split( '/\\s*[|,]\\s*/u', $value ) );
                    $data['images'] = array_values( array_map(
                        static fn( $src ) => array( 'src' => $src ),
                        array_filter( $sources, 'strlen' )
                    ) );
                } elseif ( 'categories' === $field ) {
                    $data['categories'] = array_values( array_filter( array_map( 'trim', preg_split( '/\\s*[|,]\\s*/u', $value ) ), 'strlen' ) );
                } else {
                    $data[$field] = $value;
                }
            }
            $rows[] = $data;
        }
        $next_offset = ftell( $fp );
        $complete = feof( $fp );
        fclose( $fp );
        $written = CVR2_Local_Catalog::append_records( $rows, $state );
        if ( is_wp_error( $written ) ) {
            return $written;
        }
        $state['csv_offset'] = $next_offset;
        $state['updated_at'] = time();
        if ( $complete ) {
            $state['status'] = 'ready';
            $state['completed_at'] = time();
            $state['total'] = $state['records'];
        }
        CVR2_Local_Catalog::save_snapshot( $state );
        return $state;
    }

    /**
     * User-supplied CSV image URLs must point to the configured source or R2 origin.
     * This prevents the existing media fallback from downloading an arbitrary intranet URL.
     */
    public static function validate_images( array $row ) {
        $origins = array( CVR2_REST_Client::source_url(), CVR2_REST_Client::r2_base_url() );
        $hosts = array_filter( array_map( static fn( $u ) => strtolower( (string) wp_parse_url( $u, PHP_URL_HOST ) ), $origins ) );
        foreach ( (array) ( $row['images'] ?? array() ) as $item ) {
            $src = (string) ( $item['src'] ?? '' );
            $parts = wp_parse_url( $src );
            if ( ! is_array( $parts ) || 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) )
                 || ! in_array( strtolower( (string) ( $parts['host'] ?? '' ) ), $hosts, true )
                 || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
                return new WP_Error( 'cvr2_csv_unsafe_image', 'Imagem CSV fora da origem HTTPS autorizada: ' . esc_url_raw( $src ) );
            }
        }
        return true;
    }

    private static function category_id( string $raw ) {
        $segments = array_values( array_filter( array_map( 'trim', explode( '>', $raw ) ), 'strlen' ) );
        $leaf = end( $segments );
        $slug = sanitize_title( (string) $leaf );
        $matches = get_terms( array(
            'taxonomy' => 'product_cat', 'slug' => $slug,
            'hide_empty' => false, 'number' => 10, 'fields' => 'all',
        ) );
        if ( is_wp_error( $matches ) || count( $matches ) !== 1 ) {
            return new WP_Error( 'cvr2_csv_category_missing', 'Categoria não existente ou ambígua no destino: ' . $raw );
        }
        $term = $matches[0];
        if ( count( $segments ) > 1 ) {
            for ( $i = count( $segments ) - 2; $i >= 0; $i-- ) {
                $term = $term->parent ? get_term( $term->parent, 'product_cat' ) : null;
                if ( ! $term instanceof WP_Term || ! in_array(
                    sanitize_title( $segments[$i] ),
                    array( $term->slug, sanitize_title( $term->name ) ),
                    true
                ) ) {
                    return new WP_Error( 'cvr2_csv_category_path', 'Caminho de categoria não existe no destino: ' . $raw );
                }
            }
        }
        return (int) $matches[0]->term_id;
    }

    /** Partial upsert: unselected columns are untouched on existing Woo products. */
    public static function import_product( array $row ) {
        $sku = wc_clean( (string) ( $row['sku'] ?? '' ) );
        if ( '' === $sku ) {
            return new WP_Error( 'cvr2_csv_missing_sku', 'SKU vazio.' );
        }
        $id = (int) wc_get_product_id_by_sku( $sku );
        if ( $id && 'product' !== get_post_type( $id ) ) {
            return new WP_Error( 'cvr2_csv_variation', 'SKU de variação: utilizar a exportação REST para importar variações.' );
        }
        $existing = $id ? wc_get_product( $id ) : false;
        $type = sanitize_key( (string) ( $row['type'] ?? ( $existing ? $existing->get_type() : 'simple' ) ) );
        if ( ! in_array( $type, array( 'simple', 'variable' ), true ) ) {
            return new WP_Error( 'cvr2_csv_type', 'CSV suporta produtos simples e produtos-pai variáveis.' );
        }
        if ( $existing && $existing->get_type() !== $type ) {
            return new WP_Error( 'cvr2_csv_type_change', 'Não altera automaticamente o tipo de um produto existente.' );
        }
        if ( ! $existing && ( empty( $row['name'] ) || empty( $row['slug'] ) ) ) {
            return new WP_Error( 'cvr2_csv_new_required', 'Novos produtos requerem SKU, Nome e Slug.' );
        }
        $slug = trim( (string) ( $row['slug'] ?? '' ) );
        if ( $slug ) {
            if ( $slug !== sanitize_title( $slug ) ) {
                return new WP_Error( 'cvr2_csv_slug', 'Slug não normalizado: ' . $slug );
            }
            $owner = get_page_by_path( $slug, OBJECT, 'product' );
            if ( $owner instanceof WP_Post && (int) $owner->ID !== $id ) {
                return new WP_Error( 'cvr2_csv_slug_collision', 'Slug já utilizado por outro produto: ' . $slug );
            }
        }
        $category_ids = null;
        if ( isset( $row['categories'] ) ) {
            $category_ids = array();
            foreach ( (array) $row['categories'] as $raw ) {
                $cat = self::category_id( (string) $raw );
                if ( is_wp_error( $cat ) ) { return $cat; }
                $category_ids[] = $cat;
            }
        }
        $product = $existing ?: ( 'variable' === $type ? new WC_Product_Variable() : new WC_Product_Simple() );

        try {
            if ( ! $existing ) {
                $product->set_sku( $sku );
                $product->set_status( 'draft' ); // First-time CSV products default to draft unless mapped.
            }
            $text_fields = array(
                'name' => 'set_name', 'slug' => 'set_slug', 'status' => 'set_status',
                'description' => 'set_description', 'short_description' => 'set_short_description',
                'regular_price' => 'set_regular_price', 'sale_price' => 'set_sale_price',
                'stock_status' => 'set_stock_status', 'weight' => 'set_weight',
                'length' => 'set_length', 'width' => 'set_width', 'height' => 'set_height',
            );
            foreach ( $text_fields as $key => $setter ) {
                if ( array_key_exists( $key, $row ) ) {
                    $product->$setter( (string) $row[$key] );
                }
            }
            if ( array_key_exists( 'manage_stock', $row ) ) {
                $product->set_manage_stock( in_array( strtolower( (string) $row['manage_stock'] ), array( '1', 'yes', 'sim', 'true' ), true ) );
            }
            if ( array_key_exists( 'stock_quantity', $row ) ) {
                if ( ! is_numeric( $row['stock_quantity'] ) ) {
                    return new WP_Error( 'cvr2_csv_stock', 'Stock deve ser numérico.' );
                }
                $product->set_stock_quantity( (int) $row['stock_quantity'] );
            }
            if ( null !== $category_ids ) {
                $product->set_category_ids( array_values( array_unique( $category_ids ) ) );
            }
            $saved_id = $product->save();
            if ( ! $saved_id ) {
                return new WP_Error( 'cvr2_csv_save', 'Falha na gravação do produto.' );
            }
            if ( $slug && (string) get_post_field( 'post_name', $saved_id ) !== $slug ) {
                return new WP_Error( 'cvr2_csv_slug_verify', 'WooCommerce modificou o slug. Produto requer revisão: #' . $saved_id );
            }
            foreach ( array(
                'seo_title' => 'rank_math_title',
                'seo_description' => 'rank_math_description',
                'focus_keyword' => 'rank_math_focus_keyword',
            ) as $from => $meta_key ) {
                if ( array_key_exists( $from, $row ) ) {
                    update_post_meta( $saved_id, $meta_key, sanitize_text_field( (string) $row[$from] ) );
                }
            }
            return array( 'id' => (int) $saved_id, 'sku' => $sku );
        } catch ( Throwable $error ) {
            return new WP_Error( 'cvr2_csv_exception', $error->getMessage() );
        }
    }
}
