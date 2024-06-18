<?php
/*
Template Name: Profile Page
*/

if (!is_user_logged_in()) {
	$current_url = esc_url(home_url(add_query_arg(null, null)));
	$login_url = wp_login_url($current_url); // Pass the current URL as a parameter to the login URL
	wp_redirect($login_url);
	exit;
}

get_header();

$current_user_id = get_current_user_id();
$viewed_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : $current_user_id;
$user_info = get_userdata($viewed_user_id);

// Ensure wp_handle_upload is available
if (!function_exists('wp_handle_upload')) {
	require_once(ABSPATH . 'wp-admin/includes/file.php');
}

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $current_user_id == $viewed_user_id) {
	// Update display name
	if (isset($_POST['display_name'])) {
		wp_update_user(array(
			'ID' => $current_user_id,
			'display_name' => sanitize_text_field($_POST['display_name']),
		));
	}

	// Update bio, tagline, areas of interest, and institution
	update_user_meta($current_user_id, 'bio', sanitize_text_field($_POST['bio']));
	update_user_meta($current_user_id, 'tagline', sanitize_text_field($_POST['tagline']));
	update_user_meta($current_user_id, 'areas_of_interest', sanitize_text_field($_POST['areas_of_interest']));
	update_user_meta($current_user_id, 'institution', sanitize_text_field($_POST['institution']));

	// Handle profile picture upload
	if (isset($_FILES['profile_picture']) && !empty($_FILES['profile_picture']['name'])) {
		$upload = wp_handle_upload($_FILES['profile_picture'], array('test_form' => false));

		if (isset($upload['file'])) {
			$file_path = $upload['file'];
			$file_name = basename($file_path);

			// Prepare an array of post data for the attachment.
			$attachment = array(
				'guid' => $upload['url'],
				'post_mime_type' => $upload['type'],
				'post_title' => preg_replace('/\.[^.]+$/', '', $file_name),
				'post_content' => '',
				'post_status' => 'inherit'
			);

			// Insert the attachment into the database.
			$attach_id = wp_insert_attachment($attachment, $file_path);

			// Generate the metadata for the attachment, and update the database record.
			require_once(ABSPATH . 'wp-admin/includes/image.php');
			$attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
			wp_update_attachment_metadata($attach_id, $attach_data);

			// Update the user meta to use the attachment ID as profile picture.
			update_user_meta($current_user_id, 'profile_picture', $attach_id);
		}
	}
}

// Fetch user meta fields
$profile_picture_id = get_user_meta($viewed_user_id, 'profile_picture', true);
$profile_picture_url = $profile_picture_id ? wp_get_attachment_url($profile_picture_id) : get_avatar_url($viewed_user_id); // Use get_avatar_url() for avatar if no custom one is set
$bio = get_user_meta($viewed_user_id, 'bio', true);
$tagline = get_user_meta($viewed_user_id, 'tagline', true);
$areas_of_interest = get_user_meta($viewed_user_id, 'areas_of_interest', true);
$institution = get_user_meta($viewed_user_id, 'institution', true);

// Query communities and check membership
$args = array(
	'post_type' => 'community',
	'posts_per_page' => -1, // Fetch all communities
);
$communities = new WP_Query($args);

$user_communities = [];

if ($communities->have_posts()) {
	while ($communities->have_posts()) {
		$communities->the_post();
		$community_id = get_the_ID();
		$community_members = get_post_meta($community_id, 'community_members', true);
		$community_members = maybe_unserialize($community_members);

		if (is_array($community_members) && in_array($viewed_user_id, $community_members)) {
			$user_communities[] = array(
				'id' => $community_id,
				'title' => get_the_title(),
				'permalink' => get_permalink(),
			);
		}
	}
	wp_reset_postdata();
}

?>

<div class="profile-container">
    <div class="profile-details">
        <div class="user-profile-img-container">
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #ccc; padding: 5px 0;">
                <strong>About <?php echo esc_html($user_info->display_name); ?> </strong>
	            <?php if ($current_user_id == $viewed_user_id) : ?>
                    <button id="edit-profile-button" onclick="openModal()">
                        <i class="fa-solid fa-user-pen" style="padding: 0 5px"></i>

                    </button>

                    <div id="edit-profile-modal" class="modal">
                        <div class="modal-content">
                            <span class="close" onclick="closeModal()">&times;</span>
                            <h3>Edit Profile</h3>
                            <form method="post" enctype="multipart/form-data">
                                <label for="display_name">Display Name:</label>
                                <input type="text" id="display_name" name="display_name" value="<?php echo esc_attr($user_info->display_name); ?>">

                                <p>Email: <?php echo esc_html($user_info->user_email); ?> (non-editable)</p>

                                <label for="bio">Bio:</label>
                                <textarea id="bio" name="bio"><?php echo esc_textarea($bio); ?></textarea>

                                <label for="tagline">Tagline:</label>
                                <input type="text" id="tagline" name="tagline" value="<?php echo esc_attr($tagline); ?>">

                                <label for="areas_of_interest">Areas of Interest:</label>
                                <input type="text" id="areas_of_interest" name="areas_of_interest" value="<?php echo esc_attr($areas_of_interest); ?>">

                                <label for="institution">Institution:</label>
                                <input type="text" id="institution" name="institution" value="<?php echo esc_attr($institution); ?>">

                                <label for="profile_picture">Profile Picture:</label>
                                <input type="file" id="profile_picture" name="profile_picture">

                                <button type="submit">Update Profile</button>
                            </form>
                        </div>
                    </div>
	            <?php endif; ?>
            </div>

	        <?php if ($profile_picture_url) : ?>
                <img src="<?php echo esc_url($profile_picture_url); ?>" alt="Profile Picture" width="150" height="150">
	        <?php else : ?>
                <p>No profile picture set.</p>
	        <?php endif; ?>
            <div class="user-profile-name">
                <h4><?php echo esc_html($user_info->display_name); ?></h4>
                <p><?php echo esc_html($tagline); ?></p>
            </div>
            <div class="user-profile-meta">
                <p><strong>Email: </strong><?php echo esc_html($user_info->user_email); ?></p>
                <p><strong>Username: </strong><?php echo esc_html($user_info->user_login); ?></p>
                <p><strong>Areas of Interest: </strong><?php echo esc_html($areas_of_interest); ?></p>
                <p><strong>Institution: </strong><?php echo esc_html($institution); ?></p>
            </div>

            <div class="user-profile-bio">
                <h4>Bio: </h4>
                <p><?php echo esc_html($bio); ?></p>
            </div>

        </div>
    </div>



    <div class="profile-communities">
        <h3>Communities</h3>
        <ul>
			<?php if (!empty($user_communities)) :
				foreach ($user_communities as $community) : ?>
                    <li style="border-bottom: 1px solid #ccc;">
                        <div style="display:flex; gap: 2%; align-items: center; padding: 10px;">
                            <div style="display: flex; justify-content: center; align-items: center; width: 50px; height: 50px; border-radius: 50%; border: 1px solid #ccc;">
                                <i class="fa-solid fa-user-group" style="font-size: 24px; color: #ccc;"></i>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 5px;">
                                <a href="<?php echo esc_url($community['permalink']); ?>"><?php echo esc_html($community['title']); ?></a>
                                <span>Public forum</span>
                            </div>
                        </div>
                    </li>

				<?php endforeach;
			else : ?>
                <li>No communities found</li>
			<?php endif; ?>
        </ul>
    </div>
</div>

<script>
    function openModal() {
        document.getElementById('edit-profile-modal').style.display = 'block';
    }

    function closeModal() {
        document.getElementById('edit-profile-modal').style.display = 'none';
    }

    // Close the modal when clicking outside of the modal content
    window.onclick = function(event) {
        var modal = document.getElementById('edit-profile-modal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
</script>

<style>
    /* Modal container */
    .modal {
        display: none; /* Hidden by default */
        position: fixed; /* Stay in place */
        z-index: 1; /* Sit on top */
        left: 0;
        top: 0;
        width: 100%; /* Full width */
        height: 100%; /* Full height */
        overflow: auto; /* Enable scroll if needed */
        background-color: rgb(0,0,0); /* Fallback color */
        background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
        padding-top: 60px;
    }

    /* Modal content box */
    .modal-content {
        background-color: #fefefe;
        margin: 5% auto; /* 15% from the top and centered */
        padding: 20px;
        border: 1px solid #888;
        width: 80%; /* Could be more or less, depending on screen size */
    }

    /* Close button */
    .close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
    }

    .close:hover,
    .close:focus {
        color: black;
        text-decoration: none;
        cursor: pointer;
    }

    /* Profile container */
    .profile-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
    }

    .profile-details p {
        margin: 5px 0;
    }

    .profile-edit button {
        margin-top: 10px;
    }

    .profile-communities ul {
        list-style-type: none;
        padding: 0;
    }

    .profile-communities ul li {
        margin: 5px 0;
    }
</style>

<?php
//get_footer();
?>
