<?php
use LearnPress\Models\CourseModel;
use LearnPress\Models\UserModel;
use LearnPress\Helpers\Template;
use LearnPress\Models\UserItems\UserCourseModel;

// Temporaray incluse fils
require_once COURSE_REVIEW_PATH . '/inc/plugin.php';

add_filter('learn-press/single-course/modern/section-instructor', function( array $section, CourseModel $courseModel, $userModel ) {
	if ( ! Course_Review_Addon::is_enable( $courseModel ) ) {
			return $section;
	}
	ob_start();
	do_action( 'learn-press/course-review/rating-reviews', $courseModel, $userModel );
	$html = ob_get_clean();
	return apply_filters(
		'learn-press/course/rating-reviews/position',
		Template::insert_value_to_position_array( $section, 'after', 'wrapper_end', 'review', $html ),
		$html,
		$section,
		$courseModel,
		$userModel
	);
}, 8, 3);


add_action('learn-press/course-review/rating-reviews', function($courseModel, $userModel) {
	$reviews = learn_press_get_course_review($courseModel->get_id());
	echo '<h3 class="item-title">' . esc_html__( 'Reviews', 'course-review' ) . '</h3>';
	echo course_rate($courseModel);
	echo html_list_reviews($reviews);
	echo html_btn_review( $courseModel, $userModel );
}, 10, 2);

/*================= Review List =================*/
function html_list_reviews($reviews){
	if ( empty($reviews) ) {
		return '';
	}

	$html = '<ul class="course-review-list">';

	foreach ($reviews as $review) {

		$rating = get_comment_meta($review->comment_ID, '_lpr_rating', true);

		$html .= '<li class="course-review-item">';

		// Avatar
		$html .= '<div class="course-review-author">';
		$html .= get_avatar($review->user_id ?? $review->comment_author_email, 96);
		$html .= '</div>';

		// Right content
		$html .= '<div class="course-review-content">';

		// Info row
		$html .= '<div class="course-review-info">';
		$html .= '<div class="course-review-author-rated">';

		$stars = '<div class="course-review-stars" style="display:flex; gap:2px;">';
		for ($i = 1; $i <= 5; $i++) {
			$color = ($i <= $rating) ? '#f59e0b' : '#fff';
			$stars .= '
			<svg width="20" height="20" viewBox="0 0 24 24"
				fill="' . esc_attr($color) . '"
				stroke="#f59e0b"
				stroke-width="1.5"
				xmlns="http://www.w3.org/2000/svg">
				<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
			</svg>';
		}
		$stars .= '</div>';

		$html .= $stars;
		$html .= '</div>'; // author-rated

		// Date
		$html .= '<div class="course-review-date">';
		$html .= esc_html(get_date_from_gmt($review->comment_date_gmt, 'F j, Y'));
		$html .= '</div>';

		$html .= '</div>'; // info

		// Username
		$html .= '<h4 class="course-review-user-name">';
		$html .= get_userdata($review->user_id)->display_name;
		$html .= '</h4>';

		// Title
		$html .= '<h5 class="course-review-title">';
		$html .= esc_html(get_comment_meta($review->comment_ID, '_lpr_review_title', true));
		$html .= '</h5>';

		// Content
		$html .= '<div class="course-review-text">';
		$html .= esc_html($review->comment_content);
		$html .= '</div>';

		$html .= '</div>'; // content
		$html .= '</li>';
	}

	$html .= '</ul>';

	return $html;
}

/*================= Average Rating UI =================*/
function course_rate($courseModel): string {

	$course_id     = $courseModel->get_id();
	$courseRatings = count_rating_of_course($course_id);

	$ratingPercentage = average_calculation_rating($courseRatings);
	$averageStars     = round($ratingPercentage, 1);
	$total_reviews    = $courseRatings->total;

	$five_percent  = $total_reviews ? ($courseRatings->five  / $total_reviews) * 100 : 0;
	$four_percent  = $total_reviews ? ($courseRatings->four  / $total_reviews) * 100 : 0;
	$three_percent = $total_reviews ? ($courseRatings->three / $total_reviews) * 100 : 0;
	$two_percent   = $total_reviews ? ($courseRatings->two   / $total_reviews) * 100 : 0;
	$one_percent   = $total_reviews ? ($courseRatings->one   / $total_reviews) * 100 : 0;

	$html = '<div class="course-rate">';

	/* ========================= SUMMARY ========================= */
	$html .= '<div class="course-rate__summary">';
	$html .= '<div class="course-rate__summary-value">' . $averageStars . '</div>';
	$html .= '<div class="course-rate__summary-stars"><div class="review-stars-rated">';
	for ($i = 1; $i <= 5; $i++) {
		$html .= ($i <= floor($averageStars))
			? '<div class="review-star">★</div>'
			: '<div class="review-star">☆</div>';
	}
	$html .= '</div></div>';
	$html .= '<div class="course-rate__summary-text"><span>' . $total_reviews . '</span> rating</div>';
	$html .= '</div>'; // summary

	/* ========================= DETAILS ========================= */
	$html .= '<div class="course-rate__details">';

	$rows = [
		5 => [$five_percent,  $courseRatings->five],
		4 => [$four_percent,  $courseRatings->four],
		3 => [$three_percent, $courseRatings->three],
		2 => [$two_percent,   $courseRatings->two],
		1 => [$one_percent,   $courseRatings->one],
	];

	foreach ($rows as $star => [$percent, $count]) {
		$html .= '
		<div class="course-rate__details-row">
			<span class="course-rate__details-row-star">' . $star . '</span>
			<div class="review-star">★</div>
			<div class="course-rate__details-row-value">
				<div class="rating-gray"></div>
				<div class="rating" style="width:' . $percent . '%;"></div>
			</div>
			<span class="rating-count">' . $count . '</span>
		</div>';
	}

	$html .= '</div>'; // details
	$html .= '</div>'; // course-rate

	return $html;
}

/*================= Average Calculation Rating =================*/
function average_calculation_rating( $courseRatings ) {

	if ( empty($courseRatings) || empty($courseRatings->total) ) {
		return 0;
	}

	$total = (int) $courseRatings->total;

	$sum =
		(5 * (int) $courseRatings->five)  +
		(4 * (int) $courseRatings->four)  +
		(3 * (int) $courseRatings->three) +
		(2 * (int) $courseRatings->two)   +
		(1 * (int) $courseRatings->one);

	return round($sum / $total, 1);
}

/*================= Review Button + Form =================*/
function html_btn_review( CourseModel $courseModel, $userModel ) {

	// Not logged in
	if ( ! $userModel ) {
		return '';
	}

	$user_id   = $userModel->get_id();
	$course_id = $courseModel->get_id();

	// Check if user is enrolled or finished
	$userCourse = UserCourseModel::find( $user_id, $course_id, true );

	if ( ! $userCourse ) {
		return '';
	}

	$can_review = ( $userCourse->has_enrolled_or_finished() ||
				  ( $courseModel->is_offline() && $userCourse->has_purchased() ) )
				  && ! learn_press_get_user_rate( $course_id, $user_id );

	if ( ! $can_review ) {
		return '';
	}

	ob_start(); ?>

	<!-- BUTTON -->
	<div class="write-review">
		<button type="button" class="review-button lp-button">
			Write Review
		</button>
	</div>

	<!-- FORM (hidden by default) -->
	<section class="course-review-wrapper">
		<div class="review-form">
			<form>
				<input type="hidden" name="rating" value="0">

				<h4>
					Write a review
					<a href="#" class="close">×</a>
				</h4>

				<ul class="review-fields">

					<li>
						<label>Title *</label>
						<input type="text" name="review_title" required />
					</li>

					<li>
						<label>Content *</label>
						<textarea name="review_content" required></textarea>
					</li>

					<li>
						<label>Rating *</label>
						<ul class="review-stars">
							<?php for ($i = 1; $i <= 5; $i++) { ?>
								<li data-star="<?php echo $i; ?>">★</li>
							<?php } ?>
						</ul>
					</li>

					<li class="review-actions">
						<button type="submit" class="lp-button submit-review"
								data-id="<?php echo esc_attr( $course_id ); ?>">
							Submit Review
						</button>
					</li>

				</ul>

				<?php wp_nonce_field('lp_review_nonce', 'lp_review_nonce_field'); ?>

			</form>
		</div>
	</section>

	<?php return ob_get_clean();
}