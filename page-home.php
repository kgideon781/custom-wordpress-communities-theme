<?php
/*
Template Name: Home Page
*/

get_header();

// Handle join/leave actions
$current_user = wp_get_current_user();
$current_user_id = $current_user->ID;

if (isset($_GET['community_action']) && isset($_GET['community_id'])) {
	$community_id = intval($_GET['community_id']);
	$community_members = get_post_meta($community_id, 'community_members', true);
	if (!is_array($community_members)) {
		$community_members = array();
	}

	if ($_GET['community_action'] === 'join' && !in_array($current_user_id, $community_members)) {
		$community_members[] = $current_user_id;
		update_post_meta($community_id, 'community_members', $community_members);
	} elseif ($_GET['community_action'] === 'leave' && in_array($current_user_id, $community_members)) {
		$community_members = array_diff($community_members, array($current_user_id));
		update_post_meta($community_id, 'community_members', $community_members);
	}

	// Redirect to avoid resubmission of the action on page reload
	wp_redirect(remove_query_arg(array('community_action', 'community_id')));
	exit;
}

?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">

        <h1>Welcome to the Community Website</h1>

		<?php
		$args = array(
			'post_type' => 'community',
			'posts_per_page' => 10,
		);
		$communities = new WP_Query($args);

		if ($communities->have_posts()) :
			while ($communities->have_posts()) : $communities->the_post();
				$community_id = get_the_ID();
				$community_members = get_post_meta($community_id, 'community_members', true);
				$is_member = is_array($community_members) && in_array($current_user_id, $community_members);
				$members = get_post_meta($community_id, 'community_members', true);
				$members = maybe_unserialize($members); // Ensure it's an array if serialized
				$members = is_array($members) ? $members : []; // Ensure it's an array if not already

				$member_count = count($members);

				// Count topics for this community
				$topic_args = array(
					'post_type' => 'topic',
					'meta_query' => array(
						array(
							'key' => 'community_id',
							'value' => $community_id,
							'compare' => '='
						)
					),
					'posts_per_page' => -1, // Get all topics
				);
				$topics = new WP_Query($topic_args);
				$topic_count = $topics->found_posts;

				// Count replies for this community
				$reply_args = array(
					'post_type' => 'replies',
					'meta_query' => array(
						array(
							'key' => 'community_id',
							'value' => $community_id,
							'compare' => '='
						)
					),
					'posts_per_page' => -1, // Get all replies
				);
				$replies = new WP_Query($reply_args);
				$reply_count = $replies->found_posts;
				?>
                <div class="communities-container" id="communities-container">
                    <div class="communities-container-left" style="max-width: 85%;">
                        <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                        <p><?php the_excerpt(); ?></p>
                        <div style="color: #656464; display: flex; gap: 2%;">
                            <div style="display: flex; gap: 4px;">
                                <i class="fa-solid fa-user-group"></i>
                                <span><?php echo $member_count; ?></span>
                            </div>

                            <div style="display: flex; gap: 4px;">
                                <i class="fa-solid fa-comments"></i>
                                <span><?php echo $topic_count; ?></span>
                            </div>
                            <div style="display: flex; gap: 4px;">
                                <i class="fa-solid fa-reply-all"></i>
                                <span><?php echo $reply_count; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="communities-container-right" style="padding-right: 10px; max-width: 15%;" >
						<?php if ($is_member) : ?>
                            <a href="<?php echo add_query_arg(array('community_action' => 'leave', 'community_id' => $community_id)); ?>">Leave Community</a>
						<?php else : ?>
                            <a href="<?php echo add_query_arg(array('community_action' => 'join', 'community_id' => $community_id)); ?>">Join Community</a>
						<?php endif; ?>
                    </div>
                </div>
			<?php
			endwhile;
			wp_reset_postdata();
		else :
			echo '<p>No community posts found.</p>';
		endif;
		?>

    </main>
</div>

<?php get_footer(); ?>
