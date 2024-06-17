<?php
ob_start(); // Start output buffering

get_header();

if (!is_user_logged_in()) {
	$current_url = esc_url(home_url(add_query_arg(null, null)));
	$login_url = home_url("/login"); // Pass the current URL as a parameter to the login URL
	$redirect_url = add_query_arg('redirect_to', urlencode($current_url), $login_url); // Append current URL as a parameter

	wp_redirect($redirect_url);
	exit;
}

$allowed_tags = array(
	'a' => array(
		'href' => array(),
		'title' => array(),
		'target' => array(),
	),
	'b' => array(),
	'strong' => array(),
	'i' => array(),
	'em' => array(),
	'p' => array(),
	'br' => array(),
	'ul' => array(),
	'ol' => array(),
	'li' => array(),
	'blockquote' => array(),
	'img' => array(
		'src' => array(),
		'alt' => array(),
		'width' => array(),
		'height' => array(),
		'class' => array(),
	),
	'span' => array(
		'style' => array(),
		'class' => array(),
	),
	'div' => array(
		'style' => array(),
		'class' => array(),
	),
	'iframe' => array(
		'src' => array(),
		'width' => array(),
		'height' => array(),
		'frameborder' => array(),
		'allowfullscreen' => array()
	),
	// Add more allowed tags as needed
);

$current_user = wp_get_current_user();
$topic_id = get_the_ID();
$community_id = get_post_meta($topic_id, 'community_id', true);
$members = get_post_meta($community_id, 'community_members', true);
if (!is_array($members)) {
	$members = array();
}
$is_member = in_array($current_user->ID, $members);

function custom_format_date($date) {
	$time = strtotime($date);
	$current_time = current_time('timestamp');
	$difference = $current_time - $time;

	if ($difference < DAY_IN_SECONDS) {
		return 'Today at ' . date('g:i a', $time);
	} elseif ($difference < 2 * DAY_IN_SECONDS) {
		return 'Yesterday at ' . date('g:i a', $time);
	} else {
		return date('M j \a\t g:i a', $time);
	}
}

// Get the membership level (role) of a user
function get_author_role($author_id, $community_id) {
	$moderators = get_post_meta($community_id, 'community_moderators', true);
	$admins = get_post_meta($community_id, 'community_group_admins', true);

	if (!is_array($moderators)) {
		$moderators = array();
	}
	if (!is_array($admins)) {
		$admins = array();
	}

	if (in_array($author_id, $admins)) {
		return 'Admin';
	} elseif (in_array($author_id, $moderators)) {
		return 'Moderator';
	} else {
		return 'Participant';
	}
}

?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">

		<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
        <div class="single-topic-container">
            <h1><?php the_title(); ?></h1>
            <div class="single-topic-header-meta">
                <span class="topic-single-author">By <?php the_author(); ?></span>
                <span class="topic-single-date"><?php echo custom_format_date(get_the_date('Y-m-d H:i:s')); ?></span>
            </div>

            <div><?php the_content(); ?></div>
        </div>

        <div class="topic-single-body">
            <h2>Replies</h2>
			<?php
			$args = array(
				'post_type' => 'reply',
				'meta_query' => array(
					array(
						'key' => 'topic_id',
						'value' => $topic_id,
						'compare' => '='
					)
				)
			);
			$replies = new WP_Query($args);

			if ($replies->have_posts()) : ?>
                <div class="replies">
					<?php while ($replies->have_posts()) : $replies->the_post(); ?>
						<?php
						$reply_id = get_the_ID();
						$reply = get_post($reply_id);

						if ($reply) {
							$reply_title = $reply->post_title;
							$reply_content = $reply->post_content;
							$reply_author_id = $reply->post_author;
							$reply_author_avatar_url = get_avatar_url($reply_author_id, array('size' => 50));
							$reply_date = $reply->post_date;
							$formatted_reply_date = custom_format_date($reply_date);
							$is_admin = current_user_can('manage_options');
							$is_author = ($current_user->ID == $reply_author_id);
							$can_edit = $is_author && (strtotime($reply_date) > strtotime('-5 minutes'));

							$author_role = get_author_role($reply_author_id, $community_id);
                            ?>
                            <div class="reply">
                                <div class="reply-author-container">
                                    <img src="<?php echo esc_url($reply_author_avatar_url); ?>" alt="<?php echo esc_attr(get_the_author_meta('display_name', $reply_author_id)); ?>" class="reply-author-avatar" />
                                    <div class="reply-author-container-inner">
                                        <span class="reply-author"><?php echo get_the_author_meta('display_name', $reply_author_id); ?></span>

                                        <p class="reply-author-role" style="padding: 0; margin: 5px 0 5px 0;"><?php echo esc_html($author_role); ?></p>
                                        <!--Posts count with icons left -->
                                        <div class="reply-author-posts">
                                            <i class="fa-solid fa-comment"></i>
                                            <span class="reply-author-posts-count"><?php echo count_user_posts($reply_author_id); ?></span>
                                            <span class="reply-author-posts-text">Posts</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="reply-side">
                                    <p class="reply-date">Replied <?php echo esc_html($formatted_reply_date); ?></p>
                                    <div class="reply-content"><?php echo wp_kses($reply_content, $allowed_tags); ?></div>

                                    <!-- Admin and Author Controls -->
                                    <div class="reply-meta">
										<?php if ($is_admin || $can_edit) : ?>
                                            <div class="reply-controls">
												<?php if ($can_edit) : ?>
                                                    <a href="<?php echo get_edit_post_link($reply_id); ?>" class="reply-edit-link">Edit</a>
                                                    <a href="<?php echo get_delete_post_link($reply_id); ?>" class="reply-delete-link">Delete</a>
												<?php endif; ?>
												<?php if ($is_admin) : ?>
                                                    <!-- Admin-only controls (Approve/Reject) -->
                                                    <a href="<?php echo add_query_arg(array('action' => 'approve', 'reply_id' => $reply_id)); ?>" class="reply-approve-link">Approve</a>
                                                    <a href="<?php echo add_query_arg(array('action' => 'reject', 'reply_id' => $reply_id)); ?>" class="reply-reject-link">Reject</a>
												<?php endif; ?>
                                            </div>
										<?php endif; ?>
                                    </div>
                                </div>
                            </div>
						<?php } else { ?>
                            <p class="no-reply">No reply found with the given ID.</p>
						<?php } ?>
					<?php endwhile; ?>
                </div>
				<?php wp_reset_postdata(); ?>
			<?php else : ?>
                <p class="no-replies">No replies found.</p>
			<?php endif; ?>

			<?php if ($is_member) : ?>
                <div class="new-reply-form-container">
                    <h2>Reply to this Topic</h2>
                    <form class="new-reply-form" id="new-reply" method="post" action="">
						<?php
						// Using wp_editor() for WYSIWYG functionality
						wp_editor('', 'reply_content', array(
							'media_buttons' => false,
							'textarea_rows' => 5,
							'teeny' => true
						));
						?>
                        <input class="btn-submit-reply" type="submit" value="Reply" />
                    </form>
                </div>

				<?php
				if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reply_content'])) {
					$new_reply = array(
						'post_title' => 'Reply to ' . get_the_title(),
						'post_content' => wp_kses_post($_POST['reply_content']),
						'post_status' => 'publish',
						'post_type' => 'reply',
						'meta_input' => array(
							'topic_id' => $topic_id
						)
					);
					wp_insert_post($new_reply);
					// Redirect to avoid form resubmission on page reload
					wp_redirect(add_query_arg('reply_posted', 'true', get_permalink()));
					exit;
				}

				if (isset($_GET['reply_posted']) && $_GET['reply_posted'] == 'true') {
					echo '<p>Reply posted.</p>';
				}

			endif;
			endwhile; else : ?>
                <p>No content found.</p>
			<?php endif; ?>
        </div>
    </main>
</div>

<?php get_footer(); ?>
<?php ob_end_flush(); // Flush the output buffer ?>
