<?php
/**
 * Template Name: Custom Login Page
 */

get_header();

if (is_user_logged_in()) {
	echo '<p>You are already logged in.</p>';
	wp_redirect(home_url());
	exit;
} else {
	// Display the default WordPress login form
	$args = array(
		'redirect' => home_url(), // Redirect to home after login
		'form_id' => 'loginform-custom',
		'label_username' => __('Email'),
		'label_password' => __('Password'),
		'label_remember' => __('Remember Me'),
		'label_log_in' => __('Log In'),
		'remember' => true
	);
	wp_login_form($args);
}

// Handle login submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_login'])) {
	$creds = array(
		'user_login'    => sanitize_text_field($_POST['log']),
		'user_password' => sanitize_text_field($_POST['pwd']),
		'remember'      => true
	);

	// Authenticate the user
	$user = wp_signon($creds, false);

	if (is_wp_error($user)) {
		// Display error message if authentication fails
		echo '<p class="error">Error: ' . $user->get_error_message() . '</p>';
	} else {
		// Check if the user object is a WP_User
		if ($user instanceof WP_User) {
			// Check user role after successful login
			if (in_array('Pending', $user->roles)) {
				// Redirect pending users to a specific URL or show an error message
				wp_logout(); // Log out the user
				echo '<p class="error">Login failed: Your account is pending approval. Please wait for approval.</p>';
			} else {
				// Redirect approved users to the intended URL or homepage
				$redirect_to = home_url();
				wp_safe_redirect($redirect_to);
				exit;
			}
		} else {
			// Handle unexpected case where $user is not a WP_User object
			echo '<p class="error">Unexpected error: Unable to log in. Please try again later.</p>';
		}
	}
}

// Display error message if login failed due to pending approval
if (isset($_GET['login']) && $_GET['login'] === 'failed') {
	echo '<p class="error">Login failed: Your account is pending approval. Please wait for approval.</p>';
}
?>




