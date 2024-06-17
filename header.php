<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?php bloginfo('stylesheet_url'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<header>
    <h1><a href="<?php echo home_url(); ?>"><?php bloginfo('name'); ?></a></h1>
    <nav>
		<?php wp_nav_menu(array('theme_location' => 'primary')); ?>
    </nav>

	<?php if (is_user_logged_in()) : ?>
        <div class="logout-button">
            <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    <?php else : ?>
        <div class="logout-button">
            <a href="/login" class="btn-logout"><i class="fas fa-sign-in-alt"></i> Login</a>
        </div>
	<?php endif; ?>

</header>
