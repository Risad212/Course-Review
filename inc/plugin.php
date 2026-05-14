<?php

use LearnPress\Models\CourseModel;
use LearnPress\Models\UserItems\UserCourseModel;
use LearnPress\Models\UserModel;

defined('ABSPATH') || exit;


class Course_Review_Addon extends LP_Addon
{

	public static $instance = null;

	// meta key enable constant
	const META_KEY_ENABLE = '_lp_course_review_enable';

	/**
	 * Get instance (Singleton)
	 */
	public static function instance()
	{

		if (is_null(self::$instance)) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();
		$this->initHook();
	}

	/**
	 * Define constants
	 */
	public function _define_constants()
	{

		if (! defined('COURSE_REVIEW_PATH')) {
			define('COURSE_REVIEW_PATH', dirname(COURSE_REVIEW_FILE));
		}

		if (! defined('COURSE_REVIEW_URL')) {
			define(
				'COURSE_REVIEW_URL',
				untrailingslashit(plugins_url('/', __DIR__))
			);
		}
	}

	/**
	 * Includes
	 */
	public function _includes()
	{
		include_once COURSE_REVIEW_PATH . '/inc/functions.php';
		include_once COURSE_REVIEW_PATH . '/inc/TemplateHooks/TemplateHooks.php';
	}

	/**
	 * Assets
	 */
	public function enqueue_assets()
	{

		wp_enqueue_style(
			'toastify-css',
			COURSE_REVIEW_URL . '/assets/css/toastify.min.css',
			[],
			'1.12.0'
		);

		wp_enqueue_style(
			'course-review-style',
			COURSE_REVIEW_URL . '/assets/css/course-review.css',
			[],
			'1.0.0'
		);

		wp_enqueue_script(
			'toastify-script',
			COURSE_REVIEW_URL . '/assets/js/toastify.min.js',
			['jquery'],
			'1.12.0',
			true
		);

		wp_enqueue_script(
			'course-review-script',
			COURSE_REVIEW_URL . '/assets/js/course-review.js',
			['jquery'],
			'1.0.0',
			true
		);

		wp_localize_script(
			'course-review-script',
			'lp_ajax',
			[
				'url' => admin_url('admin-ajax.php'),
			]
		);
	}

	/**
	 * inialize Hooks
	 */
	public function initHook()
	{

		// Asset loading
		add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

		// AJAX hooks
		add_action('wp_ajax_lp_save_review', [$this, 'lp_save_review']);
		add_action('wp_ajax_nopriv_lp_save_review', [$this, 'lp_save_review']);

		// Show rating in admin comment
		add_filter(
			'comment_text',
			[$this, 'add_rating_to_admin_comment'],
			10,
			2
		);

		// Show menu link in LearnPress admin menu
		add_action(
			'admin_menu',
			function () {

				add_submenu_page(
					'learn_press',
					__('Course Reviews', 'course-review'),
					__('Course Reviews', 'course-review'),
					'manage_options',
					home_url('/wp-admin/edit-comments.php?comment_type=review')
				);
			}
		);

		// temporary include CourseReviewWidget.php file
		include_once COURSE_REVIEW_PATH . '/inc/CourseReviewWidget.php';

		// Widget register
		add_action(
			'learn-press/widgets/register',
			function ($widgets) {
				$widgets[] = CourseReviewWidget::instance();
				return $widgets;
			}
		);

		// Add setting field to every course.
		add_filter(
			'lp/course/meta-box/fields/general',
			function ($fields, $post_id) {
				$fields[self::META_KEY_ENABLE] = new LP_Meta_Box_Checkbox_Field(
					__('Enable reviews', 'course-review'),
					__('Show reviews for this course', 'course-review'),
					'yes'
				);

				return $fields;
			},
			10,
			2
		);
	}

	/**
	 * AJAX Review Save In Comment
	 */
	public function lp_save_review()
	{

		$post_id = isset($_POST['post_id'])
			? intval($_POST['post_id'])
			: 0;

		$rating = isset($_POST['rating'])
			? intval($_POST['rating'])
			: 0;

		$title = isset($_POST['title'])
			? sanitize_text_field($_POST['title'])
			: '';

		$content = isset($_POST['content'])
			? sanitize_textarea_field($_POST['content'])
			: '';

		// Validation
		if (! $post_id) {

			wp_send_json_error(
				[
					'message' => __('Missing post ID', 'course-review'),
				]
			);
		}

		if (! $rating) {

			wp_send_json_error(
				[
					'message' => __('Please select rating', 'course-review'),
				]
			);
		}

		if (empty($title)) {

			wp_send_json_error(
				[
					'message' => __('Title is required', 'course-review'),
				]
			);
		}

		if (empty($content)) {

			wp_send_json_error(
				[
					'message' => __('Content is required', 'course-review'),
				]
			);
		}

		// Insert comment
		$comment_id = wp_insert_comment(
			[
				'comment_post_ID' => $post_id,
				'comment_content' => $content,
				'comment_type'    => 'review',
				'comment_approved' => 1,
				'user_id'         => get_current_user_id(),
			]
		);

		// Meta
		update_comment_meta($comment_id, '_lpr_rating', $rating);
		update_comment_meta($comment_id, '_lpr_review_title', $title);

		wp_send_json_success(
			[
				'message'    => __('Review saved and awaiting approval', 'course-review'),
				'comment_id' => $comment_id,
			]
		);
	}

	/**
	 * Show rating in admin comments
	 */
	public function add_rating_to_admin_comment($comment_text, $comment)
	{

		if (is_admin() && $comment->comment_type === 'review') {

			$rating = get_comment_meta(
				$comment->comment_ID,
				'_lpr_rating',
				true
			);

			if (! $rating) {
				return $comment_text;
			}

			$stars = '<div class="lp-admin-stars" style="display:flex; gap:2px;">';

			for ($i = 1; $i <= 5; $i++) {

				$color = ($i <= $rating) ? '#f59e0b' : '#fff';

				$stars .= '
				<svg
					width="20"
					height="20"
					viewBox="0 0 24 24"
					fill="' . esc_attr($color) . '"
					stroke="#f59e0b"
					stroke-width="1.5"
					xmlns="http://www.w3.org/2000/svg"
				>
					<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
				</svg>';
			}

			$stars .= '</div>';

			$comment_text .= $stars;
		}

		return $comment_text;
	}

	/**
	 * Check user can review course.
	 */
	public function check_user_can_review_course(UserModel $user, CourseModel $course): bool
	{
		$can_review = false;

		$userCourse = UserCourseModel::find($user->get_id(), $course->get_id(), true);
		if (
			$userCourse &&
			($userCourse->has_enrolled_or_finished() || ($course->is_offline() && $userCourse->has_purchased()))
			&& ! learn_press_get_user_rate($course->get_id(), $user->get_id())
		) {
			$can_review = true;
		}

		return $can_review;
	}

	/**
	 * Check Course Review Enable
	 */
	public function is_enable(CourseModel $course): bool
	{
		$enable = $course->get_meta_value_by_key(self::META_KEY_ENABLE, 'yes');
		return 'yes' === $enable;
	}
}
