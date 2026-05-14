<?php

use LearnPress\Shortcodes\AbstractShortcode;
use LearnPress\Helpers\Singleton;
use LearnPress\Models\CourseModel;
use LearnPress\Models\UserModel;
use LearnPress\Helpers\Template;
use Throwable;

defined('ABSPATH') || exit;


/**
 * ShortCode Class
 */

class CourseReviewShortCode extends AbstractShortcode
{
    use Singleton;

    protected $shortcode_name = 'review';

    public function render($attrs): string
    {
        $html = '';

        try {
            if (empty($attrs)) {
                $attrs = [];
            }

            $course_id = $attrs['course_id'] ?? 0;
            $courseModel = CourseModel::find($course_id, true);
            $userModel = UserModel::find(get_current_user_id(), true);

            if (! $courseModel) {
                $html = Template::print_message(__('Course is invalid', 'course-review'), 'warning', false);
            } else {
                ob_start();
                do_action('learn-press/course-review/rating-reviews', $courseModel, $userModel);
                $html = ob_get_clean();
            }
        } catch (Throwable $e) {
            $html = Template::print_message(
                $e->getMessage(),
                'error',
                false
            );
        }

        return $html;
    }
}
