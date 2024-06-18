<?php
ob_start();
get_header();

// Redirect to login if not logged in
if (!is_user_logged_in()) {
	$current_url = esc_url(home_url(add_query_arg(null, null)));
	$login_url = home_url("/login"); // Pass the current URL as a parameter to the login URL
	$redirect_url = add_query_arg('redirect_to', urlencode($current_url), $login_url); // Append current URL as a parameter

	wp_redirect($redirect_url);
	exit;
}

function get_community_member_emails($community_id) {
	$members = get_post_meta($community_id, 'community_members', true);
	if (is_array($members) && !empty($members)) {
		$emails = [];
		foreach ($members as $member_id) {
			$user_info = get_userdata($member_id);
			if ($user_info && !empty($user_info->user_email)) {
				$emails[] = $user_info->user_email;
			}
		}
		return $emails;
	}
	return [];
}

if (have_posts()) : while (have_posts()) : the_post();
	$community_id = get_the_ID();

	$members = get_post_meta($community_id, 'community_members', true);
	$members = maybe_unserialize($members); // Ensure it's an array if serialized
	$members = is_array($members) ? $members : []; // Ensure it's an array if not already

	$member_count = count($members);
	$moderators = get_post_meta($community_id, 'community_moderators', true) ?: [];
	$group_admins = get_post_meta($community_id, 'community_group_admins', true) ?: [];
	// Pagination settings
	$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
	$users_per_page = 10;
	$offset = ($paged - 1) * $users_per_page;
	$total_pages = ceil($member_count / $users_per_page);

	// Slice the members array for current page
	$members_to_display = array_slice($members, $offset, $users_per_page);

	// Check if the current user is a member
	$current_user = wp_get_current_user();
	$is_member = is_array($members) && in_array($current_user->ID, $members);

	// Check if the current user is a moderator or group admin
	$is_moderator = in_array($current_user->ID, $moderators);
	$is_group_admin = in_array($current_user->ID, $group_admins);

	// Handle form submission for bulk actions
	if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_action']) && isset($_POST['member_ids'])) {
		$action = sanitize_text_field($_POST['bulk_action']);
		$member_ids = array_map('intval', $_POST['member_ids']);

		foreach ($member_ids as $member_id) {
			if ($action === 'assign_moderator' && !in_array($member_id, $moderators)) {
				$moderators[] = $member_id;
			} elseif ($action === 'demote_moderator' && in_array($member_id, $moderators)) {
				$moderators = array_diff($moderators, [$member_id]);
			} elseif ($action === 'assign_group_admin' && !in_array($member_id, $group_admins)) {
				$group_admins[] = $member_id;
			} elseif ($action === 'demote_group_admin' && in_array($member_id, $group_admins)) {
				$group_admins = array_diff($group_admins, [$member_id]);
			}
		}

		// Update the metadata with the modified lists
		update_post_meta($community_id, 'community_moderators', $moderators);
		update_post_meta($community_id, 'community_group_admins', $group_admins);

		// Refresh the page to reflect changes
		wp_redirect(get_permalink($community_id));
		exit;
	}

	// Fetch topics
	$args = array(
		'post_type' => 'topic',
		'meta_query' => array(
			array(
				'key' => 'community_id',
				'value' => $community_id,
				'compare' => '='
			)
		),
		'posts_per_page' => -1  // Fetch all topics
	);

	$topics = new WP_Query($args);
	wp_reset_postdata();

	/*// Fetch site-wide popular topics
	$site_wide_args = array(
		'post_type' => 'topic',
		'posts_per_page' => -1  // Fetch all topics
	);

	$site_wide_topics = new WP_Query($site_wide_args);

	// Prepare an array to store topic data with reply counts
	$topics_data = [];

	if ($site_wide_topics->have_posts()) {
		while ($site_wide_topics->have_posts()) : $site_wide_topics->the_post();
			$topic_id = get_the_ID();
			$replies_count_args = array(
				'post_type' => 'reply',
				'meta_query' => array(
					array(
						'key' => 'topic_id',
						'value' => $topic_id,
						'compare' => '='
					)
				),
				'fields' => 'ids', // Only get post IDs to optimize the query
			);
			$replies_query = new WP_Query($replies_count_args);
			$replies_count = $replies_query->found_posts;

			// Fetch metadata for the topic
			$author_id = get_the_author_meta('ID');
			$author_name = get_the_author_meta('display_name', $author_id);
			$date_posted = get_the_date();
			$community_id = get_post_meta($topic_id, 'community_id', true);
			$community_name = get_the_title($community_id);

			// Store topic data
			$topics_data[] = [
				'id' => $topic_id,
				'title' => get_the_title(),
				'replies_count' => $replies_count,
				'author_name' => $author_name,
				'date_posted' => $date_posted,
				'community_name' => $community_name
			];
		endwhile;
		wp_reset_postdata();

		// Sort topics data by replies count in descending order
		usort($topics_data, function ($a, $b) {
			return $b['replies_count'] - $a['replies_count'];
		});
	}
*/

	?>

    <div id="primary" class="content-area">
        <main id="main" class="site-main">
            <div class="single-community-container">
                <div class="single-group-hero-section">
                    <div class="community-single-inner">
                        <div id="title-desc-container">
                            <h1><?php the_title(); ?></h1>
                            <span>Members: <?php echo $member_count; ?></span>
                        </div>
                        <div>
							<?php if ($is_member) : ?>
                                <a href="<?php echo add_query_arg(array('community_action' => 'leave', 'community_id' => $community_id)); ?>">Leave Community</a>
							<?php else : ?>
                                <a href="<?php echo add_query_arg(array('community_action' => 'join', 'community_id' => $community_id)); ?>">Join Community</a>
							<?php endif; ?>
                        </div>
                    </div>

                    <div><?php the_content(); ?></div>

                    <!-- Display list of members and role management options for admins -->
					<?php if (current_user_can('administrator')) : ?>
                        <div class="community-member-management">
                            <h2>Manage Members</h2>
                            <form id="bulk-actions-form" method="post">
                                <select name="bulk_action" id="bulk_action">
                                    <option value="">Select an action</option>
                                    <option value="assign_moderator">Assign Moderator</option>
                                    <option value="demote_moderator">Demote Moderator</option>
                                    <option value="assign_group_admin">Assign Group Admin</option>
                                    <option value="demote_group_admin">Demote Group Admin</option>
                                </select>
                                <input type="submit" value="Apply" class="button-primary" />

                                <ul class="community-member-list">
									<?php
									if (is_array($members_to_display)) {
										foreach ($members_to_display as $member_id) {
											$user_info = get_userdata($member_id);
											if ($user_info) {
												$user_roles = [];
												if (in_array($member_id, $moderators)) {
													$user_roles[] = 'Moderator';
												}
												if (in_array($member_id, $group_admins)) {
													$user_roles[] = 'Group Admin';
												}
												?>
                                                <li>
                                                    <input type="checkbox" name="member_ids[]" value="<?php echo $member_id; ?>">
                                                    <span><?php echo esc_html($user_info->display_name); ?> (<?php echo implode(', ', $user_roles); ?>)</span>
                                                </li>
												<?php
											}
										}
									}
									?>
                                </ul>

                                <!-- Pagination controls -->
                                <div class="pagination">
									<?php
									$pagination_base = add_query_arg('paged', '%#%');
									echo paginate_links(array(
										'base' => $pagination_base,
										'format' => '?paged=%#%',
										'current' => $paged,
										'total' => $total_pages,
									));
									?>
                                </div>
                            </form>
                        </div>
					<?php endif; ?>

                    <div class="community-mods-and-admins">
                        <h3>Admins</h3>
                        <ul class="community-member-list">
			                <?php
			                if (is_array($group_admins)) {
				                foreach ($group_admins as $admin_id) {
					                $user_info = get_userdata($admin_id);
					                if ($user_info) {
						                ?>
                                        <li>
                                            <span><?php echo esc_html($user_info->display_name); ?></span>
                                        </li>
						                <?php
					                }
				                }
			                }
			                ?>
                        </ul>

                        <h3>Moderators</h3>
                        <ul class="community-member-list">
			                <?php
			                if (is_array($moderators)) {
				                foreach ($moderators as $moderator_id) {
					                $user_info = get_userdata($moderator_id);
					                if ($user_info) {
						                ?>
                                        <li>
                                            <span><?php echo esc_html($user_info->display_name); ?></span>
                                        </li>
						                <?php
					                }
				                }
			                }
			                ?>
                        </ul>
                    </div>
                </div>
                <h2>Topics</h2>
				<?php if ($topics && $topics->have_posts()) : ?>
                <div class="topic-list-container" style="display: flex; flex-wrap: wrap">
                    <div class="topics-left" style="flex: 0 0 70%;">
                        <div class="community-topics-list">
                            <ul class="topics-list">
			                    <?php while ($topics->have_posts()) : $topics->the_post();
				                    $topic_id = get_the_ID();
				                    // Query to count replies for the current topic
				                    $replies_count_args = array(
					                    'post_type' => 'reply',
					                    'meta_query' => array(
						                    array(
							                    'key' => 'topic_id',
							                    'value' => $topic_id,
							                    'compare' => '='
						                    )
					                    ),
					                    'fields' => 'ids', // Only get post IDs to optimize the query
				                    );
				                    $replies_query = new WP_Query($replies_count_args);
				                    $replies_count = $replies_query->found_posts;

				                    // Query to get the last reply for the current topic
				                    $last_reply_args = array(
					                    'post_type' => 'reply',
					                    'posts_per_page' => 1,
					                    'orderby' => 'date',
					                    'order' => 'DESC',
					                    'meta_query' => array(
						                    array(
							                    'key' => 'topic_id',
							                    'value' => $topic_id,
							                    'compare' => '='
						                    )
					                    )
				                    );
				                    $last_reply_query = new WP_Query($last_reply_args);
				                    $last_reply_author = '';
				                    $last_reply_date = '';

				                    if ($last_reply_query->have_posts()) {
					                    $last_reply_query->the_post();
					                    $last_reply_author_id = get_the_author_meta('ID');
					                    $last_reply_author = get_the_author_meta('display_name', $last_reply_author_id);
					                    $profile_picture_id = get_user_meta($last_reply_author_id, 'profile_picture', true);
					                    $profile_picture_url = $profile_picture_id ? wp_get_attachment_url($profile_picture_id) : get_avatar_url($last_reply_author_id);
					                    $last_reply_avatar = get_avatar_url($last_reply_author_id, 50);
					                    $last_reply_date = get_the_date('F j, Y \a\t g:i a');
					                    wp_reset_postdata();
				                    }
				                    ?>
                                    <li>
                                        <div class="topics-list-author-container">
                                            <!--Get the Author's profile image-->
                                            <div>
                                                <h5 style="margin: 0; font-size: 15px; color: #313131; "><a href="<?php echo esc_html(get_permalink($topic_id)); ?>"><?php echo esc_html(get_the_title($topic_id)); ?></a></h5>
                                                <!--<p>--><?php //the_excerpt(); ?><!--</p>-->
                                                <div style="display: flex; align-items: center; color: #353C41; font-size: 13px; margin: 0;">
                                                    <p>By <?php the_author(); ?></p>, <p style="margin-left: 5px;"><?php echo get_the_date('F j, Y \a\t g:i a'); ?></p>
                                                </div>
                                            </div>
                                            <div class="topics-list-right-inner">
                                                <!-- Replies count -->
							                    <?php if (!empty($last_reply_author) && !empty($last_reply_date)) : ?>
                                                    <p style="margin-right: 15px; font-size: 15px;"><?php echo esc_html($replies_count); ?> replies</p>
                                                    <img style="margin-right: 10px;" src="<?php echo esc_html($profile_picture_url); ?>" alt="Author's profile image" class="topic-author-thumb" />
                                                    <div style="margin-right: 10px;">
                                                        <div style="display: flex; flex-direction: column; font-size: 13px;">
                                                            <a href="<?php echo site_url('/user-profile/?user_id=' . $last_reply_author_id); ?>"><?php echo get_the_author_meta('display_name', $last_reply_author_id); ?></a>

                                                            <p style="margin-bottom: 0; color: #353C41"><?php echo esc_html($last_reply_author); ?></p>
                                                            <p style="margin-top: 0; color: #718906"><?php echo esc_html($last_reply_date) ?></p>
                                                        </div>
                                                    </div>
							                    <?php endif; ?>
                                            </div>
                                        </div>
                                    </li>
			                    <?php endwhile; ?>
                            </ul>
                        </div>
	                    <?php wp_reset_postdata(); ?>
	                    <?php else : ?>
                            <p>No topics yet.</p>
	                    <?php endif; ?>

	                    <?php if (/*current_user_can('edit_posts') ||*/ $is_moderator || $is_group_admin) : ?>
                            <div id="new-topic-container" style="width: 100%;">
                                <h2>Create a New Topic</h2>
                                <form id="new-topic" method="post" action="">
                                    <label class="lbl-topic-title" for="topic_title">Title</label>
                                    <input class="input-topic-title" type="text" id="topic_title" name="topic_title" required />

                                    <label class="lbl-topic-content" for="topic_content">Content</label>

				                    <?php
				                    // Prepare content for the editor
				                    $content = '';
				                    $editor_id = 'topic_content';
				                    $settings = array(
					                    'media_buttons' => true, // Show the "Add Media" button
					                    'textarea_name' => 'topic_content', // Name of the `textarea` field
					                    'textarea_rows' => 10, // Height of the editor
					                    'tinymce' => array(
						                    'toolbar1' => 'bold,italic,underline,|,bullist,numlist,|,link,unlink,|,wp_adv',
						                    'toolbar2' => 'formatselect,alignleft,aligncenter,alignright,alignjustify,|,forecolor,|,pastetext,removeformat,charmap,|,undo,redo,|,fullscreen'
					                    ),
				                    );
				                    wp_editor($content, $editor_id, $settings);
				                    ?>

                                    <input class="btn-submit-topic" type="submit" value="Create Topic" />
                                </form>

			                    <?php
			                    // Process the form submission
			                    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['topic_title']) && isset($_POST['topic_content'])) {
				                    // Sanitize and validate the input
				                    $new_topic = array(
					                    'post_title' => sanitize_text_field($_POST['topic_title']),
					                    'post_content' => wp_kses_post($_POST['topic_content']), // Sanitize content for allowed HTML
					                    'post_status' => 'publish',
					                    'post_type' => 'topic',
					                    'meta_input' => array(
						                    'community_id' => $community_id
					                    )
				                    );
				                    wp_insert_post($new_topic);

				                    // Redirect to the same page to avoid form resubmission
				                    wp_redirect(esc_url(add_query_arg('status', 'success', get_permalink())));
				                    echo '<p>Topic created.</p>';
			                    }
			                    ?>
                            </div>
	                    <?php endif; ?>
                    </div>
                    <div class="topics-right" style="flex: 0 0 25%">
                        <div class="community-sidebar">
                            <h3>Community Members</h3>
                            <ul class="community-member-list">
                                <?php
                                if (is_array($members_to_display)) {
                                    foreach ($members_to_display as $member_id) {
                                        $user_info = get_userdata($member_id);
                                        if ($user_info) {
                                            ?>
                                            <li>
                                                <span><?php echo esc_html($user_info->display_name); ?></span>
                                            </li>
                                            <?php
                                        }
                                    }
                                }
                                ?>
                            </ul>
                        </div>
                        <div class="community-popular-topics">
                            <h3>Popular Topics</h3>
                            <ul class="popular-topics-list">
	                            <?php
	                            if (!empty($topics_data)) {
		                            $counter = 0;
		                            foreach ($topics_data as $topic) {
			                            if ($counter >= 5) {
				                            break;
			                            }
			                            ?>
                                        <li>
                                            <a href="<?php echo esc_url(get_permalink($topic['id'])); ?>"><?php echo esc_html($topic['title']); ?></a>
                                            <div class="topic-meta">
                                                <span>Replies: <?php echo $topic['replies_count']; ?></span>
                                                <span>Posted by <?php echo $topic['author_name']; ?> on <?php echo $topic['date_posted']; ?></span>
                                                <span>In <?php echo $topic['community_name']; ?></span>
                                            </div>
                                        </li>
			                            <?php
			                            $counter++;
		                            }
	                            } else {
		                            echo '<li>No topics found.</li>';
	                            }
	                            ?>
                            </ul>

                        </div>
                    </div>

                </div>
            </div>
        </main>
    </div>

<?php
endwhile; else : ?>
    <p>No content found.</p>
<?php endif;

ob_end_flush();
get_footer();
?>
