<?php
declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "CLI only\n" );
    exit( 2 );
}

require_once '/home/chavevertical-loja/htdocs/loja.chavevertical.com/wp-load.php';

$taxonomy = 'product_brand';

if ( ! taxonomy_exists( $taxonomy ) ) {
    fwrite( STDERR, "Taxonomy product_brand is not available.\n" );
    exit( 3 );
}

$manifestPath = $argv[1] ?? '';
$apply        = in_array( '--apply', $argv, true );
$images       = in_array( '--images', $argv, true );

if ( $manifestPath === '' || ! is_file( $manifestPath ) ) {
    $terms = get_terms( array(
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
    ) );

    if ( is_wp_error( $terms ) ) {
        fwrite( STDERR, $terms->get_error_message() . "\n" );
        exit( 4 );
    }

    echo 'Woo brands: ' . count( $terms ) . "\n";
    foreach ( array_slice( $terms, 0, 40 ) as $term ) {
        $source = (string) get_term_meta( $term->term_id, '_cvl_shopware_brand_id', true );
        $thumb  = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
        echo sprintf(
            "- %d | %s | %s | source=%s | image=%s\n",
            $term->term_id,
            $term->slug,
            $term->name,
            $source ?: '-',
            $thumb ? 'yes' : 'no'
        );
    }
    exit( 0 );
}

$data = json_decode( (string) file_get_contents( $manifestPath ), true );

if ( ! is_array( $data ) || ! isset( $data['brands'] ) || ! is_array( $data['brands'] ) ) {
    fwrite( STDERR, "Invalid brand manifest.\n" );
    exit( 5 );
}

$brands = array_values( array_filter(
    $data['brands'],
    static function ( $brand ): bool {
        return is_array( $brand )
            && ! empty( $brand['shopware_id'] )
            && trim( (string) ( $brand['name'] ?? '' ) ) !== '';
    }
) );

usort(
    $brands,
    static fn( array $a, array $b ): int => strcasecmp( (string) $a['name'], (string) $b['name'] )
);

$existing = get_terms( array(
    'taxonomy'   => $taxonomy,
    'hide_empty' => false,
) );

if ( is_wp_error( $existing ) ) {
    fwrite( STDERR, $existing->get_error_message() . "\n" );
    exit( 6 );
}

$sourceToTerm = array();
$nameToTerm   = array();
$slugToTerm   = array();

foreach ( $existing as $term ) {
    $sourceId = (string) get_term_meta( $term->term_id, '_cvl_shopware_brand_id', true );

    if ( $sourceId !== '' ) {
        $sourceToTerm[ $sourceId ] = (int) $term->term_id;
    }

    $nameToTerm[ strtolower( trim( $term->name ) ) ] = (int) $term->term_id;
    $slugToTerm[ $term->slug ] = (int) $term->term_id;
}

$created = 0;
$updated = 0;
$reused  = 0;
$imagesImported = 0;
$failures = array();

if ( $apply && $images ) {
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    add_filter(
        'image_sideload_extensions',
        static function ( array $extensions ): array {
            $extensions[] = 'avif';
            return array_values( array_unique( $extensions ) );
        }
    );
}

foreach ( $brands as $brand ) {
    $sourceId = (string) $brand['shopware_id'];
    $name = trim(
        html_entity_decode(
            wp_strip_all_tags( (string) $brand['name'] ),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        )
    );

    $slug = sanitize_title( $name );
    $description = isset( $brand['description'] )
        ? wp_kses_post( (string) $brand['description'] )
        : '';

    $termId = $sourceToTerm[ $sourceId ] ?? 0;

    if ( ! $termId ) {
        $termId = $nameToTerm[ strtolower( $name ) ] ?? 0;

        if ( $termId ) {
            $reused++;
        } elseif ( isset( $slugToTerm[ $slug ] ) ) {
            $candidate = get_term( $slugToTerm[ $slug ], $taxonomy );

            if ( $candidate && ! is_wp_error( $candidate ) && strtolower( trim( $candidate->name ) ) === strtolower( $name ) ) {
                $termId = (int) $candidate->term_id;
                $reused++;
            }
        }
    }

    if ( ! $apply ) {
        echo $termId
            ? "UPDATE/REUSE: {$name} -> term {$termId}\n"
            : "CREATE: {$name} | slug={$slug}\n";

        continue;
    }

    if ( $termId ) {
        $result = wp_update_term(
            $termId,
            $taxonomy,
            array(
                'name'        => $name,
                'slug'        => $slug,
                'description' => $description,
            )
        );

        if ( is_wp_error( $result ) ) {
            $failures[] = $name . ': ' . $result->get_error_message();
            continue;
        }

        $termId = (int) $result['term_id'];
        $updated++;
    } else {
        $result = wp_insert_term(
            $name,
            $taxonomy,
            array(
                'slug'        => $slug,
                'description' => $description,
            )
        );

        if ( is_wp_error( $result ) ) {
            $failures[] = $name . ': ' . $result->get_error_message();
            continue;
        }

        $termId = (int) $result['term_id'];
        $created++;
    }

    update_term_meta( $termId, '_cvl_shopware_brand_id', $sourceId );

    $link = esc_url_raw( (string) ( $brand['link'] ?? '' ) );
    if ( $link !== '' ) {
        update_term_meta( $termId, '_cvl_shopware_brand_link', $link );
    }

    $sourceToTerm[ $sourceId ] = $termId;
    $nameToTerm[ strtolower( $name ) ] = $termId;
    $slugToTerm[ $slug ] = $termId;

    $imageUrl = esc_url_raw( (string) ( $brand['image'] ?? '' ) );

    if ( $images && $imageUrl !== '' && ! (int) get_term_meta( $termId, 'thumbnail_id', true ) ) {
        $attachmentId = media_sideload_image( $imageUrl, 0, $name, 'id' );

        if ( ! is_wp_error( $attachmentId ) ) {
            update_term_meta( $termId, 'thumbnail_id', (int) $attachmentId );
            $imagesImported++;
        }
    }
}

if ( $apply ) {
    update_option( 'cvl_shopware_brand_map', $sourceToTerm, false );
    clean_term_cache( array_values( $sourceToTerm ), $taxonomy );
}

echo 'Mode: ' . ( $apply ? 'APPLY' : 'DRY-RUN' ) . "\n";
echo 'Source brands: ' . count( $brands ) . "\n";
echo 'Existing Woo brands before sync: ' . count( $existing ) . "\n";
echo "Created: {$created}\n";
echo "Updated: {$updated}\n";
echo "Reused: {$reused}\n";
echo "Images imported: {$imagesImported}\n";
echo 'Mapped Shopware IDs: ' . count( $sourceToTerm ) . "\n";

if ( $failures ) {
    echo 'Failures: ' . count( $failures ) . "\n";

    foreach ( array_slice( $failures, 0, 30 ) as $failure ) {
        echo 'ERROR: ' . $failure . "\n";
    }

    exit( 7 );
}
