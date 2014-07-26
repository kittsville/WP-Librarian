<?php
/*
Template Name: Library Item Single
*/

wp_enqueue_style( 'wp_lib_template' );

get_header(); ?>
<div id="primary">
	<div id="content" role="main">
	<?php
	if (have_posts()) : while (have_posts()) : the_post(); ?>
			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<div class="wp-lib-item-left">
					<header class="entry-header">
						<!-- Item title and meta -->
						<strong>Title:</strong> <?php the_title(); ?><br />
						<?php echo apply_filters( 'wp_lib_fetch_meta', get_the_ID() ); ?>
					</header>
					<!-- Item description -->
					<div class="entry-content"><?php the_content(); ?></div>
				</div>
				<div class="wp-lib-item-right">
					<!-- Item cover image -->
					<div class="wp-lib-item-cover">
						<?= get_the_post_thumbnail( get_the_ID(), 'medium' ); ?>
					</div>
				</div>
			</article>
	<?php endwhile; else: endif; ?>
	</div>
</div>
<?php get_footer(); ?>