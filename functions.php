<?php
ob_start();
// Theme setup function
function my_community_theme_setup() {
	add_theme_support('post-thumbnails');
}
add_action('after_setup_theme', 'my_community_theme_setup');

// Create custom post types
function create_custom_post_types() {
	register_post_type('community', array(
		'labels'      => array('name' => __('Communities'), 'singular_name' => __('Community')),
		'public'      => true,
		'has_archive' => true,
		'rewrite'     => array('slug' => 'communities'),
		'supports'    => array('title', 'editor', 'thumbnail'),
	));

	register_post_type('topic', array(
		'labels'      => array('name' => __('Topics'), 'singular_name' => __('Topic')),
		'public'      => true,
		'has_archive' => true,
		'rewrite'     => array('slug' => 'topics'),
		'supports'    => array('title', 'editor', 'author'),
	));

	register_post_type('reply', array(
		'labels'      => array('name' => __('Replies'), 'singular_name' => __('Reply')),
		'public'      => true,
		'has_archive' => false,
		'supports'    => array('editor', 'author'),
	));
}
add_action('init', 'create_custom_post_types');

// Create custom taxonomies
function create_custom_taxonomies() {
	register_taxonomy('community_category', 'community', array(
		'label'        => __('Community Categories'),
		'rewrite'      => array('slug' => 'community-category'),
		'hierarchical' => true,
	));

	register_taxonomy('topic_category', 'topic', array(
		'label'        => __('Topic Categories'),
		'rewrite'      => array('slug' => 'topic-category'),
		'hierarchical' => true,
	));

	register_taxonomy('reply_category', 'reply', array(
		'label'        => __('Reply Categories'),
		'rewrite'      => array('slug' => 'reply-category'),
		'hierarchical' => true,
	));
}
add_action('init', 'create_custom_taxonomies');

// Enqueue styles and scripts
function custom_theme_styles() {
wp_enqueue_style('main-style', get_stylesheet_uri() . '/style.css');
	wp_enqueue_style('custom-style', get_template_directory_uri() . '/css/custom-style.css');
	wp_enqueue_script('custom-script', get_template_directory_uri() . '/js/custom-script.js', array('jquery'), null, true);
}
add_action('wp_enqueue_scripts', 'custom_theme_styles');

// Create custom user roles
function create_community_roles() {
	add_role('community_admin', 'Community Admin', array(
		'read' => true, 'edit_posts' => true, 'delete_posts' => false, 'manage_community' => true,
	));

	add_role('community_mod', 'Community Mod', array(
		'read' => true, 'edit_posts' => true, 'delete_posts' => false, 'manage_replies' => true,
	));

	add_role('community_participant', 'Community Participant', array(
		'read' => true, 'edit_posts' => false, 'delete_posts' => false,
	));
}
add_action('init', 'create_community_roles');

// Notify members on new topic or reply
function notify_on_new_topic_or_reply($post_id, $post, $update) {
	if ($update) return;

	$post_type = get_post_type($post_id);
	if ($post_type === 'topic' || $post_type === 'reply') {
		$community_id = get_post_meta($post_id, 'community_id', true);
		if (!$community_id) return;

		$members_emails = get_community_member_emails($community_id);
		if (empty($members_emails)) return;

		$subject = ($post_type === 'topic') ? "New Topic: " . get_the_title($post_id) : "New Reply in Topic: " . get_the_title($post_id);
		$message = "Hello,\n\nA new $post_type has been posted in your community.\n\n";
		$message .= "Title: " . get_the_title($post_id) . "\n\n";
		$message .= "Follow this link to read more: " . get_permalink($post_id) . "\n\n";
		$message .= "Best regards,\nYour Community Team";

		foreach ($members_emails as $email) {
			wp_mail($email, $subject, $message);
		}
	}
}
add_action('wp_insert_post', 'notify_on_new_topic_or_reply', 10, 3);

// Start session on init
function start_session_on_init() {
	if (!session_id()) {
		session_start();
	}
}
add_action('init', 'start_session_on_init');

// Capture intended URL
function capture_intended_url() {
	if (!is_user_logged_in() && !is_admin()) {
		if (!session_id()) {
			session_start();
		}
		if (!isset($_SESSION['redirect_to'])) {
			$_SESSION['redirect_to'] = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		}
	}
}
add_action('template_redirect', 'capture_intended_url');

/*// Redirect after login
function custom_login_redirect($redirect_to, $request, $user) {
	if (isset($user->roles) && is_array($user->roles)) {
		if (!empty($_SESSION['redirect_to'])) {
			$redirect_to = $_SESSION['redirect_to'];
			unset($_SESSION['redirect_to']);
		} else {
			$redirect_to = home_url();
		}
	}
	return $redirect_to;
}
add_filter('login_redirect', 'custom_login_redirect', 10, 3);*/

// Redirect new users to landing group
function redirect_new_users_to_landing_group() {
	if (is_user_logged_in()) {
		$user_id = get_current_user_id();
		$is_new_user = get_user_meta($user_id, 'is_new_user', true);

		// Check if the current URL is not the welcome page
		if (!is_page('welcome-to-the-cop')) {
			//if is_new_user is not set, redirect to the landing group page
			if ($is_new_user === '') {
				wp_redirect(home_url('/welcome-to-the-cop/'));
				update_user_meta($user_id, 'is_new_user', 'no');
				exit;
			}

			if ($is_new_user === 'yes') {
				// Update the user meta to indicate the user is no longer new
				update_user_meta($user_id, 'is_new_user', 'no');

				// Redirect to the landing group page
				wp_redirect(home_url('/welcome-to-the-cop/'));
				exit;
			}
		}
	}
}
add_action('template_redirect', 'redirect_new_users_to_landing_group');


// Add custom user meta on registration
function add_custom_user_meta($user_id) {
	add_user_meta($user_id, 'is_new_user', 'yes');
}
add_action('user_register', 'add_custom_user_meta');

// Membership management
function handle_membership($community_id, $action) {
	$user_id = get_current_user_id();
	$members = get_post_meta($community_id, 'community_members', true);
	if (!is_array($members)) {
		$members = array();
	}

	if ($action == 'join' && !in_array($user_id, $members)) {
		$members[] = $user_id;
		update_post_meta($community_id, 'community_members', $members);
	} elseif ($action == 'leave' && in_array($user_id, $members)) {
		$members = array_diff($members, array($user_id));
		update_post_meta($community_id, 'community_members', $members);
	}
}

if (isset($_GET['community_action']) && isset($_GET['community_id'])) {
	handle_membership(intval($_GET['community_id']), sanitize_text_field($_GET['community_action']));
	wp_redirect(get_permalink($_GET['community_id']));
	exit;
}
function ajax_update_profile() {
	// Check if user is logged in
	if (!is_user_logged_in()) {
		wp_send_json_error('You are not logged in.');
	}

	$user_id = get_current_user_id();
	$display_name = sanitize_text_field($_POST['display_name']);
	$user_email = sanitize_email($_POST['user_email']);

	$user_data = array(
		'ID' => $user_id,
		'display_name' => $display_name,
		'user_email' => $user_email,
	);

	$user_id = wp_update_user($user_data);

	if (is_wp_error($user_id)) {
		wp_send_json_error('Error updating profile.');
	} else {
		wp_send_json_success('Profile updated successfully.');
	}
}
add_action('wp_ajax_update_profile', 'ajax_update_profile');

function enqueue_profile_scripts() {
	if (is_page_template('page-profile.php')) {
		wp_enqueue_script('profile-edit', get_template_directory_uri() . '/js/profile-edit.js', array('jquery'), null, true);
		wp_localize_script('profile-edit', 'ajax_object', array(
			'ajax_url' => admin_url('admin-ajax.php'),
		));
	}
}
add_action('wp_enqueue_scripts', 'enqueue_profile_scripts');

// Add custom roles
function custom_add_pending_role() {
	add_role('pending', __('Pending'), array('read' => true));
}
add_action('init', 'custom_add_pending_role');

// Handle user approval and rejection
function custom_user_approval_admin_menu() {
	add_menu_page('User Approvals', 'User Approvals', 'manage_options', 'user-approvals', 'custom_user_approval_page');
}
add_action('admin_menu', 'custom_user_approval_admin_menu');

function custom_user_approval_page() {
	if (isset($_GET['action']) && isset($_GET['user_id']) && check_admin_referer('approve_reject_user_' . $_GET['user_id'])) {
		$user_id = intval($_GET['user_id']);
		$action = sanitize_text_field($_GET['action']);
		$user = get_userdata($user_id);

		if ($user) {
			if ($action == 'approve') {
				$user->set_role('subscriber');

				// Notify user of approval
				wp_mail($user->user_email, 'Your account has been approved', 'Your account on our website has been approved. You can now log in.');

				wp_redirect(admin_url('admin.php?page=user-approvals&message=approved'));
				exit;
			} elseif ($action == 'reject') {
				wp_delete_user($user_id);

				// Notify user of rejection
				wp_mail($user->user_email, 'Your account has been rejected', 'Your account on our website has been rejected.');

				wp_redirect(admin_url('admin.php?page=user-approvals&message=rejected'));
				exit;
			}
		}
	}

	if (isset($_GET['message']) && $_GET['message'] == 'approved') {
		echo '<div class="notice notice-success is-dismissible"><p>User approved.</p></div>';
	} elseif (isset($_GET['message']) && $_GET['message'] == 'rejected') {
		echo '<div class="notice notice-warning is-dismissible"><p>User rejected.</p></div>';
	}

	$args = array(
		'role' => 'pending',
	);
	$pending_users = get_users($args);

	echo '<div class="wrap">';
	echo '<h1>User Approvals</h1>';
	echo '<table class="widefat fixed">';
	echo '<thead><tr><th>Name</th><th>Email</th><th>Actions</th></tr></thead>';
	echo '<tbody>';

	foreach ($pending_users as $user) {
		echo '<tr>';
		echo '<td>' . esc_html($user->first_name) . ' ' . esc_html($user->last_name) . '</td>';
		echo '<td>' . esc_html($user->user_email) . '</td>';
		echo '<td>';
		echo '<a href="' . wp_nonce_url(admin_url('admin.php?page=user-approvals&action=approve&user_id=' . $user->ID), 'approve_reject_user_' . $user->ID) . '">Approve</a> | ';
		echo '<a href="' . wp_nonce_url(admin_url('admin.php?page=user-approvals&action=reject&user_id=' . $user->ID), 'approve_reject_user_' . $user->ID) . '">Reject</a>';
		echo '</td>';
		echo '</tr>';
	}

	echo '</tbody>';
	echo '</table>';
	echo '</div>';
}

// Notify admin of pending user registrations
function custom_notify_admin_pending_users($user_id) {
	$user = get_userdata($user_id);
	if ($user && in_array('pending', $user->roles)) {
		$admin_email = get_option('admin_email');
		$subject = 'New User Registration Pending Approval';
		$message = "A new user has registered and is pending approval:\n\n";
		$message .= "Name: {$user->first_name} {$user->last_name}\n";
		$message .= "Email: {$user->user_email}\n";

		wp_mail($admin_email, $subject, $message);
	}
}
add_action('user_register', 'custom_notify_admin_pending_users');

function custom_login_redirect($redirect_to, $request, $user) {
	// Ensure session is started
	if (!session_id()) {
		session_start();
	}

	// Check if the user has roles and if they are an array
	if (isset($user->roles) && is_array($user->roles)) {
		// Handle redirection for 'pending' users
		if (in_array('pending', $user->roles)) {
			wp_logout(); // Log out the user
			// Show an error message
			return home_url('/pending-approval/');
		}

		// Handle redirection for 'subscriber' users
		if (in_array('subscriber', $user->roles)) {
			// Redirect new users to the landing group page
			redirect_new_users_to_landing_group();
			return home_url('/dashboard/');
		}

		// Handle other roles
		if (in_array('community_admin', $user->roles)) {
			return home_url('/admin-dashboard/');
		} elseif (in_array('community_mod', $user->roles)) {
			return home_url('/moderator-dashboard/');
		} elseif (in_array('community_participant', $user->roles)) {
			return home_url('/community/');
		}

		// Default redirection: If a specific redirection URL was set in session
		if (!empty($_SESSION['redirect_to'])) {
			$redirect_to = $_SESSION['redirect_to'];
			unset($_SESSION['redirect_to']);
		} else {
			$redirect_to = home_url(); // Fallback to home URL
		}
	}

	return $redirect_to;
}
add_filter('login_redirect', 'custom_login_redirect', 10, 3);


function custom_check_user_approval_status($user, $username, $password) {
	// Check if user exists
	if (!$user) {
		return new WP_Error('denied', __('Invalid username or password.'));
	}

	// Check if user role is 'pending' or any other role that indicates pending status
	if (in_array('pending', $user->roles)) {
		return new WP_Error('denied', __('Your account is pending approval. Please wait for approval.'));
	}

	// User is approved, return the user object
	return $user;
}
add_filter('authenticate', 'custom_check_user_approval_status', 10, 3);

/*// Redirect logged-in users to /home
function redirect_logged_in_users() {
	// Check if the user is logged in and the request is not for an admin page
	if (is_user_logged_in() && !is_admin() && !is_page('home')) {
		wp_redirect(home_url('/home'));
		exit;
	}
}
add_action('template_redirect', 'redirect_logged_in_users');*/

// Hide the admin bar for non-admin users
function hide_admin_bar_for_non_admins($show_admin_bar) {
	if (!current_user_can('administrator')) {
		return false;
	}
	return $show_admin_bar;
}
add_filter('show_admin_bar', 'hide_admin_bar_for_non_admins');


ob_end_clean();
?>
