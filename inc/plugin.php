<?php

use LearnPress\Models\CourseModel;
use LearnPress\Models\UserItems\UserCourseModel;
use LearnPress\Models\UserModel;
use LearnPress\CourseReview\CourseReviewWidget;

defined('ABSPATH') || exit;

class Course_Review_Addon extends LP_Addon
{
	public static $instance = null;

	const META_KEY_ENABLE = '_lp_course_review_enable';

	/**
	 * Get single instance (Singleton pattern)
	 */
	public static function instance()
	{
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor - initialize hooks
	 */
	public function __construct()
	{
		parent::__construct();
		$this->initHook();
	}

	/**
	 * Define plugin constants (path & URL)
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
	 * Include required files
	 */
	public function _includes()
	{
		include_once COURSE_REVIEW_PATH . '/inc/functions.php';
		include_once COURSE_REVIEW_PATH . '/inc/TemplateHooks/TemplateHooks.php';
	}

	/**
	 * Load CSS & JS assets
	 */
	public function enqueue_assets()
	{
		wp_enqueue_style('toastify-css', COURSE_REVIEW_URL . '/assets/css/toastify.min.css', [], '1.12.0');
		wp_enqueue_style('course-review-style', COURSE_REVIEW_URL . '/assets/css/course-review.css', [], '1.0.0');

		wp_enqueue_script('toastify-script', COURSE_REVIEW_URL . '/assets/js/toastify.min.js', ['jquery'], '1.12.0', true);
		wp_enqueue_script('course-review-script', COURSE_REVIEW_URL . '/assets/js/course-review.js', ['jquery'], '1.0.0', true);

		wp_localize_script('course-review-script', 'lp_ajax', [
			'url' => admin_url('admin-ajax.php'),
		]);
	}

	/**
	 * Register hooks, filters, AJAX, widgets
	 */
	public function initHook()
	{
		add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

		add_action('wp_ajax_lp_save_review', [$this, 'lp_save_review']);
		add_action('wp_ajax_nopriv_lp_save_review', [$this, 'lp_save_review']);

		add_filter('comment_text', [$this, 'add_rating_to_admin_comment'], 10, 2);

		add_action('admin_menu', function () {
			add_submenu_page(
				'learn_press',
				__('Course Reviews', 'course-review'),
				__('Course Reviews', 'course-review'),
				'manage_options',
				home_url('/wp-admin/edit-comments.php?comment_type=review')
			);
		});

		/**
		 * Register LearnPress review widget
		 */
		add_action('learn-press/widgets/register', function ($widgets) {
			$widgets[] = CourseReviewWidget::instance();
			return $widgets;
		});

		/**
		 * Add "Enable Reviews" field in course settings
		 */
		add_filter('lp/course/meta-box/fields/general', function ($fields, $post_id) {

			$fields[self::META_KEY_ENABLE] = new LP_Meta_Box_Checkbox_Field(
				__('Enable reviews', 'course-review'),
				__('Show reviews for this course', 'course-review'),
				'yes'
			);

			return $fields;

		}, 10, 2);
	}

	/**
	 * Save review via AJAX
	 */
	public function lp_save_review()
	{
		$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
		$rating  = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
		$title   = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
		$content = isset($_POST['content']) ? sanitize_textarea_field($_POST['content']) : '';

		if (! $post_id) {
			wp_send_json_error(['message' => __('Missing post ID', 'course-review')]);
		}

		if (! $rating) {
			wp_send_json_error(['message' => __('Please select rating', 'course-review')]);
		}

		if (empty($title)) {
			wp_send_json_error(['message' => __('Title is required', 'course-review')]);
		}

		if (empty($content)) {
			wp_send_json_error(['message' => __('Content is required', 'course-review')]);
		}

		$comment_id = wp_insert_comment([
			'comment_post_ID' => $post_id,
			'comment_content' => $content,
			'comment_type'    => 'review',
			'comment_approved'=> 1,
			'user_id'         => get_current_user_id(),
		]);

		update_comment_meta($comment_id, '_lpr_rating', $rating);
		update_comment_meta($comment_id, '_lpr_review_title', $title);

		wp_send_json_success([
			'message'    => __('Review saved and awaiting approval', 'course-review'),
			'comment_id' => $comment_id,
		]);
	}

	/**
	 * Add star rating in admin comment list
	 */
	public function add_rating_to_admin_comment($comment_text, $comment)
	{
		if (is_admin() && $comment->comment_type === 'review') {

			$rating = get_comment_meta($comment->comment_ID, '_lpr_rating', true);

			if (! $rating) return $comment_text;

			$stars = '<div style="display:flex; gap:2px;">';

				for ($i = 1; $i <= 5; $i++) {

					$filled = ($i <= $rating);

					$stars .= '<div class="star">';

					$stars .= '<svg width="18" height="18" viewBox="0 0 24 24" 
						fill="' . ($filled ? '#f59e0b' : '#fff') . '" 
						stroke="#f59e0b" stroke-width="1.5">
						<polygon points="12 2 15 8 22 9 17 14 18 21 12 18 6 21 7 14 2 9 9 8"/>
					</svg>';

					$stars .= '</div>';
				}

				$stars .= '</div>';

			$comment_text .= $stars;
		}

		return $comment_text;
	}

	/**
	 * Check if user can review a course
	 */
	public function check_user_can_review_course(UserModel $user, CourseModel $course): bool
	{
		$userCourse = UserCourseModel::find($user->get_id(), $course->get_id(), true);

		if (
			$userCourse &&
			($userCourse->has_enrolled_or_finished() ||
			($course->is_offline() && $userCourse->has_purchased())) &&
			! learn_press_get_user_rate($course->get_id(), $user->get_id())
		) {
			return true;
		}

		return false;
	}

	/**
	 * Check if reviews are enabled for course
	 */
	public function is_enable(CourseModel $course): bool
	{
		$enable = $course->get_meta_value_by_key(self::META_KEY_ENABLE, 'yes');
		return 'yes' === $enable;
	}
}
