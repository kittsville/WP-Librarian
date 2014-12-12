<?php
/*
 * Template Name: Library Item Archive
 */

get_header();

?>
<div id="primary">
	<div id="content" role="main">
		<?php
			// If there are items, loop through them
			if (have_posts()) : while (have_posts()) : the_post();
		?>
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
		<?php if ( $wp_query->current_post + 1 !== $wp_query->post_count ): ?>
		<hr />
		<?php endif; endwhile; else: endif; ?>
	</div>
</div>
<?php get_footer(); ?>