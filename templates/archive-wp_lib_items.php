<?php
/**
 * Template Name: Library Item Archive
 */

// No direct loading
defined('ABSPATH') OR die('No');

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
				<a href="<?php the_permalink(); ?>"><?php the_post_thumbnail(array(200, 500)); ?></a>
			</div>
			<!-- Item title, taxonomy terms and meta -->
			<div class="item-meta-wrap">
				<?php do_action('wp_lib_display_item_meta'); ?>
			</div>
			<!-- Item description -->
			<div class="wp-lib-item-description entry-content">
				<?php the_content(); ?>
			</div>
		</article>
		<?php endwhile; else: endif; ?>
		<div class="navigation"><p><?php posts_nav_link(); ?></p></div>
	</div>
</div>
<?php get_footer(); ?>
