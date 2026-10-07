<?php
defined( 'ABSPATH' ) || exit;
get_header();

$cvl_is_cart_page  = function_exists( 'is_cart' ) && is_cart();
$cvl_cart_is_empty = $cvl_is_cart_page && function_exists( 'WC' ) && WC()->cart && WC()->cart->is_empty();
?>
<div class="cvl-shell cvl-content">
<?php
while ( have_posts() ) :
    the_post();
    ?>
    <article <?php post_class( $cvl_is_cart_page ? 'cvl-entry cvl-cart-entry' : 'cvl-entry' ); ?>>
        <?php if ( $cvl_is_cart_page ) : ?>
            <header class="cvl-cart-page-head">
                <a class="cvl-cart-back" href="<?php echo esc_url( function_exists( 'cvl_shop_url' ) ? cvl_shop_url() : home_url( '/shop/' ) ); ?>">
                    <span aria-hidden="true">←</span>
                    <span><?php esc_html_e( 'CONTINUAR A COMPRAR', 'chavevertical-lite' ); ?></span>
                </a>

                <div class="cvl-cart-page-title-row">
                    <div>
                        <p class="cvl-cart-page-eyebrow"><?php esc_html_e( 'A SUA ENCOMENDA', 'chavevertical-lite' ); ?></p>
                        <h1><?php esc_html_e( 'Carrinho', 'chavevertical-lite' ); ?></h1>
                        <p class="cvl-cart-page-subtitle">
                            <?php
                            echo esc_html(
                                $cvl_cart_is_empty
                                    ? __( 'O seu carrinho está vazio. Veja abaixo os produtos em promoção ou continue a explorar a loja.', 'chavevertical-lite' )
                                    : __( 'Revise os produtos, quantidades e valores antes de avançar para os dados de faturação e entrega.', 'chavevertical-lite' )
                            );
                            ?>
                        </p>
                    </div>

                    <ol class="cvl-cart-steps" aria-label="<?php esc_attr_e( 'Etapas da compra', 'chavevertical-lite' ); ?>">
                        <li class="cvl-cart-step is-active" aria-current="step"><span class="cvl-cart-step-number">1</span><span><?php esc_html_e( 'Carrinho', 'chavevertical-lite' ); ?></span></li>
                        <li class="cvl-cart-step"><span class="cvl-cart-step-number">2</span><span><?php esc_html_e( 'Dados', 'chavevertical-lite' ); ?></span></li>
                        <li class="cvl-cart-step"><span class="cvl-cart-step-number">3</span><span><?php esc_html_e( 'Pagamento', 'chavevertical-lite' ); ?></span></li>
                    </ol>
                </div>
            </header>

            <div class="cvl-entry-content"><?php the_content(); ?></div>

            <div class="cvl-cart-trust" aria-label="<?php esc_attr_e( 'Vantagens da compra', 'chavevertical-lite' ); ?>">
                <div class="cvl-cart-trust-item"><span class="cvl-cart-trust-icon" aria-hidden="true">✓</span><span><?php esc_html_e( 'Pagamento seguro e processo protegido', 'chavevertical-lite' ); ?></span></div>
                <div class="cvl-cart-trust-item"><span class="cvl-cart-trust-icon" aria-hidden="true">🚚</span><span><?php esc_html_e( 'Envios para todo o país', 'chavevertical-lite' ); ?></span></div>
                <div class="cvl-cart-trust-item"><span class="cvl-cart-trust-icon" aria-hidden="true">☎</span><span><?php esc_html_e( 'Apoio da equipa Chave Vertical', 'chavevertical-lite' ); ?></span></div>
            </div>
        <?php else : ?>
            <h1><?php the_title(); ?></h1>
            <div class="cvl-entry-content"><?php the_content(); ?></div>
        <?php endif; ?>
    </article>
    <?php
endwhile;
?>
</div>
<?php get_footer(); ?>
