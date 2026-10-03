<?php
defined( 'ABSPATH' ) || exit;
get_header();
?>
<div class="cvl-shell cvl-content">
<?php
while ( have_posts() ) :
    the_post();
    ?>
    <article <?php post_class( 'cvl-entry' ); ?>>
        <h1><?php the_title(); ?></h1>
        <div class="cvl-entry-content"><?php the_content(); ?></div>
    </article>
    <?php
endwhile;
?>
</div>
<?php get_footer(); ?>
