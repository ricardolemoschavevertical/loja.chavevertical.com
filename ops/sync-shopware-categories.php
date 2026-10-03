<?php
declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "CLI only\n" );
    exit( 2 );
}

$wpRoot = '/home/chavevertical-loja/htdocs/loja.chavevertical.com';
require_once $wpRoot . '/wp-load.php';

if ( ! taxonomy_exists( 'product_cat' ) ) {
    fwrite( STDERR, "WooCommerce product_cat taxonomy is not available.\n" );
    exit( 3 );
}

$manifestPath = $argv[1] ?? '';
$apply        = in_array( '--apply', $argv, true );
$images       = in_array( '--images', $argv, true );

if ( $manifestPath === '' || ! is_file( $manifestPath ) ) {
    $existing = get_terms( array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
        'fields'     => 'all',
    ) );

    if ( is_wp_error( $existing ) ) {
        fwrite( STDERR, $existing->get_error_message() . "\n" );
        exit( 4 );
    }

    echo 'Woo categories: ' . count( $existing ) . "\n";
    foreach ( array_slice( $existing, 0, 40 ) as $term ) {
        echo sprintf( "- %d | parent=%d | %s | %s\n", $term->term_id, $term->parent, $term->slug, $term->name );
    }
    exit( 0 );
}

$data = json_decode( (string) file_get_contents( $manifestPath ), true );
if ( ! is_array( $data ) || ! isset( $data['categories'] ) || ! is_array( $data['categories'] ) ) {
    fwrite( STDERR, "Invalid category manifest.\n" );
    exit( 5 );
}

$sourceRoot = (string) ( $data['navigation_root_id'] ?? '' );
$categories = array_values( array_filter(
    $data['categories'],
    static function ( $item ): bool {
        if ( ! is_array( $item ) || empty( $item['shopware_id'] ) || empty( $item['name'] ) ) {
            return false;
        }
        if ( isset( $item['active'] ) && ! $item['active'] ) {
            return false;
        }
        return strtolower( trim( (string) $item['name'] ) ) !== 'predefinido';
    }
) );

$byId = array();
foreach ( $categories as $category ) {
    $byId[ (string) $category['shopware_id'] ] = $category;
}

$depthCache = array();
$getDepth = static function ( string $id ) use ( &$getDepth, &$depthCache, $byId, $sourceRoot ): int {
    if ( isset( $depthCache[ $id ] ) ) {
        return $depthCache[ $id ];
    }

    $depth  = 0;
    $seen   = array();
    $cursor = $id;

    while ( isset( $byId[ $cursor ] ) ) {
        $parent = (string) ( $byId[ $cursor ]['parent_shopware_id'] ?? '' );
        if ( $parent === '' || $parent === $sourceRoot || ! isset( $byId[ $parent ] ) || isset( $seen[ $parent ] ) ) {
            break;
        }
        $seen[ $parent ] = true;
        $depth++;
        $cursor = $parent;
    }

    return $depthCache[ $id ] = $depth;
};

usort(
    $categories,
    static function ( array $a, array $b ) use ( $getDepth ): int {
        $da = $getDepth( (string) $a['shopware_id'] );
        $db = $getDepth( (string) $b['shopware_id'] );
        if ( $da === $db ) {
            return strcasecmp( (string) $a['name'], (string) $b['name'] );
        }
        return $da <=> $db;
    }
);

$existingTerms = get_terms( array(
    'taxonomy'   => 'product_cat',
    'hide_empty' => false,
) );

if ( is_wp_error( $existingTerms ) ) {
    fwrite( STDERR, $existingTerms->get_error_message() . "\n" );
    exit( 6 );
}

$sourceToTerm = array();
$slugToTerm   = array();
$nameParentToTerm = array();

foreach ( $existingTerms as $term ) {
    $sourceId = (string) get_term_meta( $term->term_id, '_cvl_shopware_category_id', true );
    if ( $sourceId !== '' ) {
        $sourceToTerm[ $sourceId ] = (int) $term->term_id;
    }
    $slugToTerm[ $term->slug ] = (int) $term->term_id;
    $nameParentToTerm[ strtolower( $term->name ) . '|' . (int) $term->parent ] = (int) $term->term_id;
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
}

foreach ( $categories as $category ) {
    $sourceId = (string) $category['shopware_id'];
    $parentSourceId = (string) ( $category['parent_shopware_id'] ?? '' );
    $parentTermId = 0;

    if ( $parentSourceId !== '' && $parentSourceId !== $sourceRoot && isset( $sourceToTerm[ $parentSourceId ] ) ) {
        $parentTermId = (int) $sourceToTerm[ $parentSourceId ];
    }

    $name = trim( html_entity_decode( wp_strip_all_tags( (string) $category['name'] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
    $slug = sanitize_title( (string) ( $category['slug'] ?? '' ) );
    if ( $slug === '' ) {
        $slug = sanitize_title( $name );
    }

    $description = isset( $category['description'] ) ? wp_kses_post( (string) $category['description'] ) : '';
    $termId = $sourceToTerm[ $sourceId ] ?? 0;

    if ( ! $termId ) {
        $candidate = $nameParentToTerm[ strtolower( $name ) . '|' . $parentTermId ] ?? 0;
        if ( $candidate ) {
            $termId = $candidate;
            $reused++;
        } elseif ( isset( $slugToTerm[ $slug ] ) ) {
            $candidateTerm = get_term( $slugToTerm[ $slug ], 'product_cat' );
            if ( $candidateTerm && ! is_wp_error( $candidateTerm ) && strtolower( $candidateTerm->name ) === strtolower( $name ) ) {
                $termId = (int) $candidateTerm->term_id;
                $reused++;
            }
        }
    }

    if ( ! $apply ) {
        if ( $termId ) {
            echo "UPDATE/REUSE: {$name} -> term {$termId}\n";
        } else {
            echo "CREATE: {$name} | parent={$parentTermId} | slug={$slug}\n";
        }
        if ( $termId ) {
            $sourceToTerm[ $sourceId ] = $termId;
        }
        continue;
    }

    if ( $termId ) {
        $result = wp_update_term(
            $termId,
            'product_cat',
            array(
                'name'        => $name,
                'slug'        => $slug,
                'parent'      => $parentTermId,
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
            'product_cat',
            array(
                'slug'        => $slug,
                'parent'      => $parentTermId,
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

    update_term_meta( $termId, '_cvl_shopware_category_id', $sourceId );
    update_term_meta( $termId, '_cvl_shopware_seo_path', (string) ( $category['seo_path'] ?? '' ) );
    $sourceToTerm[ $sourceId ] = $termId;
    $slugToTerm[ $slug ] = $termId;
    $nameParentToTerm[ strtolower( $name ) . '|' . $parentTermId ] = $termId;

    $imageUrl = esc_url_raw( (string) ( $category['image'] ?? '' ) );
    if ( $images && $imageUrl !== '' && ! (int) get_term_meta( $termId, 'thumbnail_id', true ) ) {
        $attachmentId = media_sideload_image( $imageUrl, 0, $name, 'id' );
        if ( ! is_wp_error( $attachmentId ) ) {
            update_term_meta( $termId, 'thumbnail_id', (int) $attachmentId );
            $imagesImported++;
        }
    }
}

if ( $apply ) {
    update_option( 'cvl_shopware_category_map', $sourceToTerm, false );
    clean_term_cache( array_values( $sourceToTerm ), 'product_cat' );
}

echo 'Mode: ' . ( $apply ? 'APPLY' : 'DRY-RUN' ) . "\n";
echo 'Source categories: ' . count( $categories ) . "\n";
echo 'Existing Woo categories before sync: ' . count( $existingTerms ) . "\n";
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
