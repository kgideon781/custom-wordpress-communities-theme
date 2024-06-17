<?php
/* Template Name: Registration Page */

get_header();
//check if user is already logged in
if (is_user_logged_in()) {
	echo 'Already logged in.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_registration'])) {
	// Sanitize and validate input fields
	$first_name = sanitize_text_field($_POST['first_name']);
	$last_name = sanitize_text_field($_POST['last_name']);
	$email = sanitize_email($_POST['email']);
	$areas_of_interest = sanitize_text_field($_POST['areas_of_interest']);
	$reason_for_signup = sanitize_text_field($_POST['reason_for_signup']);
	$institution = sanitize_text_field($_POST['institution']);
	$password = sanitize_text_field($_POST['password']);
	$confirm_password = sanitize_text_field($_POST['confirm_password']);

	// Check if passwords match
	if ($password !== $confirm_password) {
		echo '<p>Passwords do not match.</p>';
	} else {
		// Create a new user with a pending role
		$userdata = array(
			'user_login' => $email,
			'user_email' => $email,
			'user_pass' => $password,
			'first_name' => $first_name,
			'last_name' => $last_name,
			'role' => 'pending'
		);
		$user_id = wp_insert_user($userdata);

		// Check for errors
		if (is_wp_error($user_id)) {
			echo '<p>Error: ' . $user_id->get_error_message() . '</p>';
		} else {
			// Save additional user meta
			update_user_meta($user_id, 'areas_of_interest', $areas_of_interest);
			update_user_meta($user_id, 'reason_for_signup', $reason_for_signup);
			update_user_meta($user_id, 'institution', $institution);

			// Notify admin of new registration
			$admin_email = get_option('admin_email');
			$subject = 'New User Registration Pending Approval';
			$message = "A new user has registered and is pending approval:\n\n";
			$message .= "Name: $first_name $last_name\n";
			$message .= "Email: $email\n";
			$message .= "Areas of Interest: $areas_of_interest\n";
			$message .= "Reason for Signup: $reason_for_signup\n";
			$message .= "Institution: $institution\n";

			wp_mail($admin_email, $subject, $message);

			echo '<p>Registration successful. Please wait for admin approval.</p>';
		}
	}
}
?>
<?php if (!is_user_logged_in()) : ?>



<form id="sign-up-form-custom" method="post" action="">
    <h1 style="width: 100%; margin-bottom: 2%; ">Registration Form</h1>
	<label class="lbl-sign-up-fname" for="first_name">First Name</label>
	<input type="text" id="first_name" name="first_name" required>

	<label class="lbl-sign-up-lname" for="last_name">Last Name</label>
	<input type="text" id="last_name" name="last_name" required>

	<label class="lbl-sign-up-email" for="email">Email</label>
	<input type="email" id="email" name="email" required>

	<label class="lbl-sign-up-institution" for="institution">Institution</label>
	<input type="text" id="institution" name="institution" required>

	<label class="lbl-sign-up-aoi" for="areas_of_interest">Areas of Interest</label>
	<input type="text" id="areas_of_interest" name="areas_of_interest" required>

	<label class="lbl-sign-up-reason" for="reason_for_signup">Reason for Signup</label>
	<input type="text" id="reason_for_signup" name="reason_for_signup" required>

	<label class="lbl-sign-up-pwd" for="password">Password</label>
	<input type="password" id="password" name="password" required>

	<label class="lbl-sign-up-cnf-pwd" for="confirm_password">Confirm Password</label>
	<input type="password" id="confirm_password" name="confirm_password" required>

	<input class="sign-up-btn" type="submit" name="user_registration" value="Register">
</form>
<?php endif; ?>

<?php get_footer(); ?>
