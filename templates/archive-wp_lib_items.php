<?php
/*
 * Template Name: Library Item Archive
 */

wp_enqueue_style( 'wp_lib_frontend' );

get_header();

?>
<div id="primary">
	<div id="content" role="main">
		<?php
		// Fetches items, referred to as posts (because they are)
		$loop = new WP_Query( array('post_type'=>'wp_lib_items') );
		
		// Loops posts
		while ( $loop->have_posts() ) : $loop->the_post();
		
		// If item is set not to be publicly displayed, skip displaying item
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
						<?php the_post_thumbnail( 'medium' ); ?>
					</div>
				</div>
			</div>
		</article>
		
		<hr />
		
		<?php endwhile; ?>
	</div>
</div>
<?php get_footer(); ?>