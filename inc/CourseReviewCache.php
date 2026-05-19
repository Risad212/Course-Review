<?php

namespace LearnPress\CourseReview;

use Exception;
use LearnPress\Models\CourseModel;
use LearnPress\Models\CoursePostModel;
use Course_Review_Addon;
use Course_Review_Preload;
use LP_Cache;

defined( 'ABSPATH' ) || exit();

class CourseReviewCache extends LP_Cache {

    // key_group_child
	protected $key_group_child = 'course-rating';

	public function __construct( $has_thim_cache = false ) {
		parent::__construct( $has_thim_cache );
	}


	/**
	 * Set Ratting From Cache
	 */
	public function set_rating( $course_id, $rating ){
      $this->set_cache( $course_id, $rating );
	  $key_cache_first = "{$this->key_group}/{$course_id}";
	  LP_Cache::cache_load_first( 'set', $key_cache_first, $rating );
	}


	/**
	 * Get Ratting From Cache
	 */
	public function get_ratting( int $course_id ) {
	  $cache_key = "{$this->key_group}/$course_id";
	  $total = LP_Cache::cache_load_first('get', $cache_key);

	  if( false !== $total ){
		return $total;
	  }

	 $total = $this->get_cache( $course_id );
	 Lp_Cache::cache_load_first('set', $cache_key, $total);

	 return $total;
	 
	}

    /**
	 * Clean cache rating
	 * And calculate average rating for course
	 */
	public function clean_rating( int $course_id, int $user_id = 0 ) {

	    // clear key from thim cache table and ram
		$this->clear( $course_id );

		// create groupe for specefic course
		$key_cache_first = "{$this->key_group}/{$course_id}";

		// remove if this key has in array $first_set_value array
		LP_Cache::cache_load_first( 'clear', $key_cache_first );

		// Set average rating for course
		$rating = Course_Review_Preload::$addon->get_rating_of_course( $course_id );

		// store avarage ratting in table
		Course_Review_Addon::set_course_rating_average( $course_id, $rating['rated'] );
		
		$courseModel = CourseModel::find( $course_id, true );

		if ( $courseModel instanceof CourseModel ) {

			$courseModel->meta_data->{ Course_Review_Addon::META_KEY_RATING_AVERAGE } = $rating['rated'];

			$courseModel->save( true );
		}

		$key_cache_review = "user/{$user_id}/course/{$course_id}/review";
		$this->clear( $key_cache_review );
	}
}
