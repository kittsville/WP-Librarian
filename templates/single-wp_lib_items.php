<?php
/*
 * Template Name: Library Single Item
 */

// No direct loading
defined( 'ABSPATH' ) OR die('No');

get_header();

?>
<div id="primary">
	<div id="content" role="main">
	<?php
	if (have_posts()) : while (have_posts()) : the_post(); ?>
		<a href="<?php echo wp_lib_item_archive_url(); ?>" class="wp-lib-back-nav">&laquo; All Items</a>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<!-- Item cover image -->
			<div class="wp-lib-item-cover">
				<?php the_post_thumbnail( array( 200, 500 ) ); ?>
			</div>
			<!-- Item title, taxonomy terms and meta -->
			<div class="item-meta-wrap">
				<?php wp_lib_display_item_meta( get_the_ID(), false ); ?>
			</div>
			<!-- Item description -->
			<div class="wp-lib-item-description entry-content">
				<?php the_content(); ?>
			</div>
		</article>
	<?php endwhile; else: endif; ?>
	</div>
</div>
<?php get_footer(); ?>