<?php
/*
 * Template Name: Library Item Archive
 */

get_header();

?>
<div id="primary">
	<div id="content" role="main">
		<?php
			// Loops posts
			if (have_posts()) : while (have_posts()) : the_post();
			
			// If item is set not to be publicly displayed, skips displaying item
			if ( get_post_meta( get_the_ID(), 'wp_lib_item_delist', true ) )
				continue;
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<div class="wp-lib-item">
				<div class="wp-lib-item-left">
					<!-- Item title and meta -->
					<strong>Title: </strong><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a><br />
					<?php echo wp_lib_fetch_meta( get_the_ID() ); ?>
					<!-- Item description -->
					<div class="entry-content">
						<?php the_content(); ?>
					</div>
				</div>
				<div class="wp-lib-item-right">
					<!-- Item cover image -->
					<div class="wp-lib-item-cover">
						<a href="<?php the_permalink(); ?>">
							<?php the_post_thumbnail( array( 200, 500 ) ); ?>
						</a>
					</div>
				</div>
			</div>
		</article>
		
		<?php if ( $wp_query->current_post + 1 !== $wp_query->post_count ): ?>
		<hr />
		<?php endif; endwhile; else: endif; ?>
	</div>
</div>
<?php get_footer(); ?>