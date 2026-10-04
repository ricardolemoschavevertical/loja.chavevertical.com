<?php
defined( 'ABSPATH' ) || exit;

final class CVOS_Indexer {
    private CVOS_Settings $settings;
    private CVOS_Client $client;
    private CVOS_Serializer $serializer;

    public function __construct( CVOS_Settings $settings, CVOS_Client $client, CVOS_Serializer $serializer ) {
        $this->settings   = $settings;
        $this->client     = $client;
        $this->serializer = $serializer;

        add_action( 'cvos_index_product', array( $this, 'index_product' ) );
        add_action( 'cvos_delete_product', array( $this, 'delete_product' ) );
        add_action( 'cvos_reindex_batch', array( $this, 'reindex_batch' ), 10, 2 );
        add_action( 'cvos_scheduled_reindex', array( $this, 'scheduled_reindex' ) );

        add_action( 'woocommerce_new_product', array( $this, 'queue_product' ) );
        add_action( 'woocommerce_update_product', array( $this, 'queue_product' ) );
        add_action( 'woocommerce_product_set_stock', array( $this, 'queue_product_object' ) );
        add_action( 'woocommerce_variation_set_stock', array( $this, 'queue_product_object' ) );
        add_action( 'woocommerce_product_set_stock_status', array( $this, 'queue_product' ) );
        add_action( 'before_delete_post', array( $this, 'before_delete_post' ) );
    }

    public function queue_product_object( $product ): void {
        if ( ! $product instanceof WC_Product ) {
            return;
        }

        $id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
        if ( $id ) {
            $this->queue_product( (int) $id );
        }
    }

    public function queue_product( int $product_id ): void {
        if ( ! $this->settings->is_yes( 'enabled' ) || ! $this->settings->configured() ) {
            return;
        }

        $product = wc_get_product( $product_id );
        if ( $product instanceof WC_Product && $product->is_type( 'variation' ) ) {
            $product_id = $product->get_parent_id();
        }

        if ( ! $product_id ) {
            return;
        }

        if ( function_exists( 'as_enqueue_async_action' ) ) {
            as_enqueue_async_action( 'cvos_index_product', array( $product_id ), 'cv-opensearch', true );
        } else {
            wp_schedule_single_event( time() + 5, 'cvos_index_product', array( $product_id ) );
        }
    }

    public function before_delete_post( int $post_id ): void {
        if ( 'product' !== get_post_type( $post_id ) ) {
            return;
        }

        if ( function_exists( 'as_enqueue_async_action' ) ) {
            as_enqueue_async_action( 'cvos_delete_product', array( $post_id ), 'cv-opensearch', true );
        } else {
            wp_schedule_single_event( time() + 5, 'cvos_delete_product', array( $post_id ) );
        }
    }

    public function index_product( int $product_id ) {
        $document = $this->serializer->product( $product_id );

        if ( ! $document ) {
            return $this->client->delete_document( $product_id );
        }

        $ready = $this->client->ensure_index();
        if ( is_wp_error( $ready ) ) {
            return $ready;
        }

        return $this->client->index_document( $product_id, $document );
    }

    public function delete_product( int $product_id ) {
        return $this->client->delete_document( $product_id );
    }

    public function start_full_reindex(): void {
        if ( ! $this->settings->configured() ) {
            update_option(
                'cvos_reindex_state',
                array(
                    'status'  => 'error',
                    'message' => 'OpenSearch não configurado.',
                    'updated' => time(),
                ),
                false
            );
            return;
        }

        $created = $this->client->recreate_index();

        if ( is_wp_error( $created ) ) {
            update_option(
                'cvos_reindex_state',
                array(
                    'status'  => 'error',
                    'message' => $created->get_error_message(),
                    'updated' => time(),
                ),
                false
            );
            return;
        }

        $counts = wp_count_posts( 'product' );
        $total  = isset( $counts->publish ) ? (int) $counts->publish : 0;

        update_option(
            'cvos_reindex_state',
            array(
                'status'    => 'running',
                'processed' => 0,
                'total'     => $total,
                'started'   => time(),
                'updated'   => time(),
                'message'   => '',
            ),
            false
        );

        $this->schedule_batch( 0, 50 );
    }

    public function reindex_batch( int $offset = 0, int $limit = 50 ): void {
        $limit = max( 10, min( 100, $limit ) );

        $ids = get_posts(
            array(
                'post_type'              => 'product',
                'post_status'            => 'publish',
                'posts_per_page'         => $limit,
                'offset'                 => max( 0, $offset ),
                'orderby'                => 'ID',
                'order'                  => 'ASC',
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            )
        );

        $documents = array();

        foreach ( $ids as $product_id ) {
            $document = $this->serializer->product( (int) $product_id );
            if ( $document ) {
                $documents[] = $document;
            }
        }

        if ( $documents ) {
            $result = $this->client->bulk( $documents );

            if ( is_wp_error( $result ) || ! empty( $result['errors'] ) ) {
                $message = is_wp_error( $result )
                    ? $result->get_error_message()
                    : 'O OpenSearch devolveu erros num lote de indexação.';

                update_option(
                    'cvos_reindex_state',
                    array(
                        'status'    => 'error',
                        'processed' => $offset,
                        'message'   => $message,
                        'updated'   => time(),
                    ),
                    false
                );
                return;
            }
        }

        $state = get_option( 'cvos_reindex_state', array() );
        $processed = $offset + count( $ids );

        $state['processed'] = $processed;
        $state['updated']   = time();

        update_option( 'cvos_reindex_state', $state, false );

        if ( count( $ids ) < $limit ) {
            $state['status']   = 'complete';
            $state['finished'] = time();
            $state['updated']  = time();
            update_option( 'cvos_reindex_state', $state, false );
            return;
        }

        $this->schedule_batch( $processed, $limit );
    }

    private function schedule_batch( int $offset, int $limit ): void {
        if ( function_exists( 'as_enqueue_async_action' ) ) {
            as_enqueue_async_action( 'cvos_reindex_batch', array( $offset, $limit ), 'cv-opensearch' );
        } else {
            wp_schedule_single_event( time() + 3, 'cvos_reindex_batch', array( $offset, $limit ) );
        }
    }

    public function scheduled_reindex(): void {
        $this->start_full_reindex();
        $this->update_schedule();
    }

    public function update_schedule(): void {
        wp_clear_scheduled_hook( 'cvos_scheduled_reindex' );

        if ( ! $this->settings->is_yes( 'schedule_enabled' ) ) {
            return;
        }

        $seconds = 'daily' === $this->settings->get( 'schedule_interval', 'weekly' )
            ? DAY_IN_SECONDS
            : WEEK_IN_SECONDS;

        wp_schedule_single_event( time() + $seconds, 'cvos_scheduled_reindex' );
    }
}
