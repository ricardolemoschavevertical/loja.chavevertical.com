<?php
/** Run with: php ops/tests/cart-sections.php */
if ( 'cli' !== PHP_SAPI ) {
    exit;
}

define( 'ABSPATH', __DIR__ );
$filters = array();
$cart_context = array( 'cart' => true, 'loop' => true, 'main' => true );
function add_filter( $hook, $callback, $priority = 10 ) {
    $GLOBALS['filters'][ $hook ] = $callback;
}
function add_action( $hook, $callback, $priority = 10 ) {}
function is_cart() { return $GLOBALS['cart_context']['cart']; }
function in_the_loop() { return $GLOBALS['cart_context']['loop']; }
function is_main_query() { return $GLOBALS['cart_context']['main']; }
require dirname( __DIR__, 2 ) . '/wp-content/themes/chavevertical-lite/inc/cart-sections.php';

$checks = 0;
function check( $condition, $label ) {
    global $checks;
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: $label\n" );
        exit( 1 );
    }
    ++$checks;
    echo "PASS: $label\n";
}

$cards = '';
for ( $i = 1; $i <= 6; ++$i ) {
    $cards .= '<li class="product" data-product_id="' . $i . '"><a href="/produto/' . $i . '/">Product ' . $i . '</a><button type="button">Add</button></li>';
}
$promotions = '<section class="cvl-empty-cart-promotions" aria-labelledby="cvl-empty-cart-promotions-title"><h2 id="cvl-empty-cart-promotions-title">Promotions</h2><ul class="products">' . $cards . '</ul></section>';
$saved_row = '<li class="wc-block-shopper-list-item" data-wp-each-child data-wp-context=\'{"listItem":{"key":"abc","quantity":3}}\'><h3>Saved product</h3><button data-wp-on--click="actions.onClickMoveToCart">Move to cart</button></li>';
$saved_start = '<section class="wp-block-woocommerce-saved-for-later wc-block-saved-for-later" data-wp-interactive="woocommerce/saved-for-later"><div class="wc-block-saved-for-later__header"><h2>Saved for later</h2></div><div class="wc-block-saved-for-later__notices"></div><ul class="wc-block-saved-for-later__list"><template data-wp-each--list-item="state.currentItems"><li class="wc-block-shopper-list-item">Template only</li></template>';
$saved_end = '<li class="wc-block-saved-for-later__empty" hidden>Nothing saved yet</li></ul></section>';
$saved = $saved_start . $saved_row . $saved_end;
$empty_saved = $saved_start . $saved_end;
$cart_start = '<div class="wp-block-woocommerce-cart"><div class="wp-block-woocommerce-empty-cart-block cvl-empty-cart-state"><div class="cvl-empty-cart-message">Cart message</div>';
$cart_end = '</div></div>';
$input = $cart_start . $promotions . $cart_end . $saved;
$output = cvl_cart_promotions_last( $input );
check( $output === $cart_start . $cart_end . $saved . "\n" . $promotions, 'block cart: complete saved list precedes promotions' );
check( substr_count( $output, 'class="product"' ) === 6, 'six existing sale products, no clones or new query' );
check( substr_count( $output, 'id="cvl-empty-cart-promotions-title"' ) === 1, 'unique promotions heading ID' );
check( strpos( $output, $saved ) !== false, 'native interactivity directives and customer row unchanged' );
check( cvl_cart_promotions_last( $output ) === $output, 'idempotent ordering' );
check( cvl_cart_promotions_last( $cart_start . $promotions . $cart_end . $empty_saved ) === $cart_start . $cart_end . $empty_saved . "\n" . $promotions, 'empty saved list retains its native DOM' );
check( cvl_cart_promotions_last( $cart_start . $promotions . $cart_end ) === $cart_start . $cart_end . "\n" . $promotions, 'guest cart without saved list' );
$classic = '<div class="woocommerce"><div class="cart-empty">Empty</div>' . $promotions . '<p class="return-to-shop">Continue shopping</p></div>' . $saved;
check( cvl_cart_promotions_last( $classic ) === '<div class="woocommerce"><div class="cart-empty">Empty</div><p class="return-to-shop">Continue shopping</p></div>' . $saved . "\n" . $promotions, 'classic cart: keep return-to-shop before saved list and promotions' );
$plain = '<div class="woocommerce"><form>Populated cart</form></div>' . $saved;
check( cvl_cart_promotions_last( $plain ) === $plain, 'no promotions: leave content unchanged' );
check( cvl_cart_promotions_last( $promotions . "\n  " ) === $promotions . "\n  ", 'already-last section preserves trailing whitespace' );
$broken = '<div><section class="cvl-empty-cart-promotions"><p>Incomplete';
check( cvl_cart_promotions_last( $broken ) === $broken, 'incomplete HTML fails open without truncation' );
$nested = str_replace( '<ul class="products">', '<section data-note="a > b">Nested</section><ul class="products">', $promotions );
check( cvl_cart_promotions_last( '<div>' . $nested . '</div>' . $saved ) === '<div></div>' . $saved . "\n" . $nested, 'nested sections and quoted angle brackets remain intact' );
$raw = '<!-- </section> --><script>const markup = "</section>";</script><style>p::after{content:"</section>"}</style><template><section>Template</section></template>';
$with_raw = str_replace( '<ul class="products">', $raw . '<ul class="products">', $promotions );
check( cvl_cart_promotions_last( '<div>' . $with_raw . '</div>' . $saved ) === '<div></div>' . $saved . "\n" . $with_raw, 'comments, script, style and template content are not closing tags' );
check( $filters['the_content']( $input ) === $output, 'main cart content uses ordering filter' );
foreach ( array( 'cart', 'loop', 'main' ) as $key ) {
    $cart_context[ $key ] = false;
    check( $filters['the_content']( $input ) === $input, 'filter scope excludes ' . $key . '=false' );
    $cart_context[ $key ] = true;
}
if ( isset( $argv[1] ) ) {
    file_put_contents( $argv[1], '<main class="cvl-entry-content">' . $output . '</main>' );
}
echo "All $checks checks passed.\n";
