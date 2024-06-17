<?php
/* Template Name: Updates Template */
get_header(); ?>

<div id="updates">
	<?php
	$args = array(
		'post_type' => 'updates',
		'posts_per_page' => 10,
	);
	$updates_query = new WP_Query($args);
	if ($updates_query->have_posts()) :
		while ($updates_query->have_posts()) : $updates_query->the_post(); ?>
			<div class="update-item">
				<h2><?php the_title(); ?></h2>
				<div><?php the_content(); ?></div>
			</div>
		<?php endwhile;
		wp_reset_postdata();
	else :
		echo '<p>No updates found.</p>';
	endif;
	?>
</div>

<?php get_footer(); ?>
