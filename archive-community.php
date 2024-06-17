<?php
get_header();

// Check if the current user is a member
$current_user = wp_get_current_user();
$current_user_id = $current_user->ID;

// Handle join/leave actions
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

// Determine if the current user is a member
$is_member = false;
if (have_posts()) : while (have_posts()) : the_post();
$community_id = get_the_ID();
$community_members = get_post_meta($community_id, 'community_members', true);
if (is_array($community_members) && in_array($current_user_id, $community_members)) {
	$is_member = true;
}
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <h1>Community Posts</h1>

        <div class="communities-container">
            <div class="communities-container-left">
                <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                <p><?php the_excerpt(); ?></p>
            </div>
            <div class="communities-container-right">
				<?php if ($is_member) : ?>
                    <a href="<?php echo add_query_arg(array('community_action' => 'leave', 'community_id' => $community_id)); ?>">Leave Community</a>
				<?php else : ?>
                    <a href="<?php echo add_query_arg(array('community_action' => 'join', 'community_id' => $community_id)); ?>">Join Community</a>
				<?php endif; ?>
            </div>
        </div>

		<?php endwhile; else : ?>
            <p>No community posts found.</p>
		<?php endif; ?>
    </main>
</div>

<?php get_footer(); ?>
