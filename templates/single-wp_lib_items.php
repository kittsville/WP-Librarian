<?php
/*
 * Template Name: Library Single Item
 */

get_header();

?>
<div id="primary">
	<div id="content" role="main">
	<?php
	if (have_posts()) : while (have_posts()) : the_post(); ?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<!-- Item cover image -->
			<div class="wp-lib-item-cover">
				<a href="<?php the_permalink(); ?>"><?php the_post_thumbnail( array( 200, 500 ) ); ?></a>
			</div>
			<!-- Item title, taxonomy terms and meta -->
			<div class="item-meta-wrap">
				<?php wp_lib_display_item_meta( get_the_ID() ); ?>
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