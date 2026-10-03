<?php
get_header();
?>
<div class="cvl-shell cvl-content">
<?php
if ( have_posts() ) {
    while ( have_posts() ) {
        the_post();
        ?>
        <article <?php post_class( 'cvl-entry' ); ?>>
            <h1><?php the_title(); ?></h1>
            <div class="cvl-entry-content"><?php the_content(); ?></div>
        </article>
        <?php
    }
} else {
    echo '<p>' . esc_html__( 'Sem conteúdos disponíveis.', 'chavevertical-lite' ) . '</p>';
}
?>
</div>
<?php
get_footer();
