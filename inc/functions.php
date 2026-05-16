<?php

/**
 * Get all approved course reviews.
 *
 * @param int $course_id
 * @return array
 */
if ( ! function_exists( 'learn_press_get_course_review' ) ) {

	function learn_press_get_course_review( $course_id ) {

		return get_comments( [
			'post_id' => $course_id,
			'type'    => 'review',
			'status'  => 'approve',
			'orderby' => 'comment_date',
			'order'   => 'DESC',
		] );
	}
}


/**
 * Count course ratings.
 *
 * @param int $course_id
 * @return object
 */
if ( ! function_exists( 'count_rating_of_course' ) ) {

	function count_rating_of_course( $course_id = 0 ) {

		global $wpdb;

		$query = $wpdb->prepare(
			"
			SELECT
				COUNT(DISTINCT c.comment_ID) AS total,
				SUM(CASE WHEN cm.meta_value = '5' THEN 1 ELSE 0 END) AS five,
				SUM(CASE WHEN cm.meta_value = '4' THEN 1 ELSE 0 END) AS four,
				SUM(CASE WHEN cm.meta_value = '3' THEN 1 ELSE 0 END) AS three,
				SUM(CASE WHEN cm.meta_value = '2' THEN 1 ELSE 0 END) AS two,
				SUM(CASE WHEN cm.meta_value = '1' THEN 1 ELSE 0 END) AS one
			FROM {$wpdb->comments} AS c
			INNER JOIN {$wpdb->commentmeta} AS cm
				ON c.comment_ID = cm.comment_id
			WHERE c.comment_post_ID = %d
				AND c.comment_approved = 1
				AND c.comment_type = 'review'
				AND cm.meta_key = '_lpr_rating'
			",
			$course_id
		);

		return $wpdb->get_row( $query );
	}
}


/**
 * Get the rating user has posted for a course.
 *
 * @param int $course_id
 * @param int $user_id
 * @param bool $force
 *
 * @return mixed
 */
if ( ! function_exists( 'learn_press_get_user_rate' ) ) {

    function learn_press_get_user_rate( int $course_id = 0, int $user_id = 0, $force = false ) {
        /*-- Check if a specific user already submitted a
         review for a course — to prevent duplicate reviews. -- */
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        if ( ! $course_id ) {
            $course_id = get_the_ID();
        }

        global $wpdb;
        $query = $wpdb->prepare(
            "
            SELECT *
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->comments} c ON c.comment_post_ID = p.ID
            WHERE c.comment_post_ID = %d
            AND c.user_id = %d
            AND c.comment_type = %s
            ",
            $course_id,
            $user_id,
            'review'
        );

        $comment = $wpdb->get_row( $query );
        if ( $comment ) {
            $comment->comment_title = get_comment_meta( $comment->comment_ID, '_lpr_review_title', true );
            $comment->rating        = get_comment_meta( $comment->comment_ID, '_lpr_rating', true );
        }

        return $comment;
    }
}
