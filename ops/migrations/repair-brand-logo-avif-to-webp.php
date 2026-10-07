<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'CV_Core_Brand_Logo_WebP' ) ) {
    fwrite( STDERR, "CV_Core_Brand_Logo_WebP indisponível.\n" );
    exit( 1 );
}

$result = CV_Core_Brand_Logo_WebP::convert_existing();

printf(
    "brand-logo-webp converted=%d reused=%d skipped=%d failed=%d\n",
    (int) $result['converted'],
    (int) $result['reused'],
    (int) $result['skipped'],
    (int) $result['failed']
);

foreach ( $result['errors'] as $error ) {
    fwrite( STDERR, "brand-logo-webp error: " . $error . "\n" );
}

if ( $result['failed'] > 0 ) {
    exit( 1 );
}
