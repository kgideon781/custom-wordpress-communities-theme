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

if (!is_user_logged_in()) {
    echo '<p>You need to <a href="' . wp_login_url() . '">log in</a> to view profiles.</p>';
    get_footer();
    exit;
}

$current_user_id = get_current_user_id();
$viewed_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : $current_user_id;
$user_info = get_userdata($viewed_user_id);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile_picture'])) {
    if (isset($_FILES['profile_picture']) && !empty($_FILES['profile_picture']['name'])) {
        $upload = wp_handle_upload($_FILES['profile_picture'], array('test_form' => false));

        if (isset($upload['file'])) {
            $file_path = $upload['file'];
            $file_name = basename($file_path);
            $wp_upload_dir = wp_upload_dir();
            $url = $wp_upload_dir['url'] . '/' . $file_name;

            update_user_meta($current_user_id, 'profile_picture', $url);
        }
    }

    if (isset($_POST['display_name'])) {
        wp_update_user(array(
            'ID' => $current_user_id,
            'display_name' => sanitize_text_field($_POST['display_name']),
        ));
    }

    if (isset($_POST['user_email'])) {
        wp_update_user(array(
            'ID' => $current_user_id,
            'user_email' => sanitize_email($_POST['user_email']),
        ));
    }
}

$profile_picture = get_user_meta($viewed_user_id, 'profile_picture', true);

?>

<div class="profile-container">
    <h2>Profile of <?php echo esc_html($user_info->display_name); ?></h2>

    <div class="profile-details">
        <p>Email: <?php echo esc_html($user_info->user_email); ?></p>
        <p>Username: <?php echo esc_html($user_info->user_login); ?></p>
        <p>Profile Picture: </p>
        <?php if ($profile_picture) : ?>
            <img src="<?php echo esc_url($profile_picture); ?>" alt="Profile Picture" width="150" height="150">
        <?php else : ?>
            <p>No profile picture set.</p>
        <?php endif; ?>
    </div>

    <?php if ($current_user_id == $viewed_user_id) : ?>
        <div class="profile-edit">
            <h3>Edit Profile</h3>
            <form id="profile-edit-form" method="post" enctype="multipart/form-data">
                <label for="display_name">Display Name:</label>
                <input type="text" id="display_name" name="display_name" value="<?php echo esc_attr($user_info->display_name); ?>">

                <label for="user_email">Email:</label>
                <input type="email" id="user_email" name="user_email" value="<?php echo esc_attr($user_info->user_email); ?>">

                <label for="profile_picture">Profile Picture:</label>
                <input type="file" id="profile_picture" name="profile_picture">

                <button type="submit" name="update_profile_picture">Update Profile</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="profile-activity">
        <h3><?php echo ($current_user_id == $viewed_user_id) ? 'Your' : esc_html($user_info->display_name) . "'s"; ?> Activity</h3>
        <div id="user-posts">
            <h4><?php echo ($current_user_id == $viewed_user_id) ? 'Your' : 'Their'; ?> Posts:</h4>
            <ul>
                <?php
                $args = array(
                    'author' => $viewed_user_id,
                    'post_type' => 'topic',
                );
                $user_posts = new WP_Query($args);
                if ($user_posts->have_posts()) :
                    while ($user_posts->have_posts()) : $user_posts->the_post(); ?>
                        <li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
                    <?php endwhile;
                else : ?>
                    <li>No posts found</li>
                <?php endif;
                wp_reset_postdata();
                ?>
            </ul>
        </div>

        <div id="user-replies">
            <h4><?php echo ($current_user_id == $viewed_user_id) ? 'Your' : 'Their'; ?> Replies:</h4>
            <ul>
                <?php
                $args = array(
                    'author' => $viewed_user_id,
                    'post_type' => 'reply',
                );
                $user_replies = new WP_Query($args);
                if ($user_replies->have_posts()) :
                    while ($user_replies->have_posts()) : $user_replies->the_post(); ?>
                        <li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
                    <?php endwhile;
                else : ?>
                    <li>No replies found</li>
                <?php endif;
                wp_reset_postdata();
                ?>
            </ul>
        </div>
    </div>
</div>

<?php
get_footer();
?>
