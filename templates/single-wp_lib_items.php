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
				<header class="entry-header">
					<!-- Display Item Meta and Thumbnail -->
					<strong>Title: </strong><?php the_title(); ?><?php echo '<div class="wp-lib-item-cover">' . get_the_post_thumbnail( get_the_ID(), 'medium' ) . '</div>'; ?><br />
					<?php echo apply_filters( 'wp_lib_fetch_meta', get_the_ID() ); ?>
				</header>
				<!-- Display library item content -->
				<div class="entry-content"><?php the_content(); ?></div>
			</article>
	<?php endwhile; else: endif; ?>
	</div>
</div>
<?php get_footer(); ?>