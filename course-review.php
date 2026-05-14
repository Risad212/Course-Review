<?php
/**
 * Plugin Name: Course Review
 * Plugin URI: 
 * Description: course review addon for learnpress.
 * Author: hmrisad
 * Version: 1.0.0
 * Author URI: 
 * Tags: learnpress
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: course-review
 * Domain Path: /languages/
 * Require_LP_Version: 4.3.2.3
 * Requires Plugins: learnpress
 */


if (!defined('ABSPATH')) exit;

// point to root folder of plugin where anywhere
const COURSE_REVIEW_FILE = __FILE__;

function course_review_preload() {
	// Set Base name plugin plugin folder and main file
	define( 'COURSE_REVIEW_BASENAME', plugin_basename( __FILE__ ) );

    include_once ABSPATH . 'wp-admin/includes/plugin.php';

    $addon_info = get_file_data(
        __FILE__,
        array(
            'Name'               => 'Plugin Name',
            'Require_LP_Version' => 'Require_LP_Version',
            'Version'            => 'Version',
        )
    );

    define('COURSE_REVIEW_VER', $addon_info['Version']);
    define('COURSE_REVIEW_REQUIRE_VER', $addon_info['Require_LP_Version']);

    // Check LearnPress Activated
    if ( ! is_plugin_active('learnpress/learnpress.php') ) {

        add_action('admin_notices', function () use ($addon_info) {
            echo '<div class="notice notice-error"><p>';
            echo 'Please activate <strong>LearnPress version ' . COURSE_REVIEW_REQUIRE_VER . ' or later</strong> before activating <strong>' . $addon_info['Name'] . '</strong>';
            echo '</p></div>';
        });

        deactivate_plugins(COURSE_REVIEW_BASENAME);

        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }

        return;
    }

    // LearnPress loaded
    add_action('learn-press/ready', function () {
        include_once plugin_dir_path(__FILE__) . 'inc/plugin.php';
        include_once plugin_dir_path(__FILE__) . 'inc/CourseReviewShortcode.php';

        Course_Review_Addon::instance();
        CourseReviewShortCode::instance();
    });
}

add_action('plugins_loaded', 'course_review_preload');