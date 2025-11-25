<?php

class WP_Test_Edit_Flow_Story_Budget extends WP_UnitTestCase {

	protected static $admin_user_id;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_user_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$admin_user_id );
	}

	/**
	 * Test that the story budget date filter handles valid date
	 */
	public function test_story_budget_set_start_date_filter() {
    global $edit_flow;

    $user = get_user_by( 'id', self::$admin_user_id );

    wp_set_current_user( self::$admin_user_id );

    // Users filters need to be set (they're set by default)
    $edit_flow->story_budget->update_user_filters();

    $new_filters['start_date'] = '2019-12-01';

    $users_filters = $edit_flow->story_budget->update_user_filters_from_form_date_range_change( $user, $new_filters );

    $this->assertEquals( '2019-12-01', $users_filters['start_date'] );
  }
    
    /**
	 * Test that the story budget date filter handles invalid date
	 */
	public function test_story_budget_set_start_date_filter_invalid() {
    global $edit_flow;

    $user = get_user_by( 'id', self::$admin_user_id );

    wp_set_current_user( self::$admin_user_id );

    // Users filters need to be set (they're set by default)
    $edit_flow->story_budget->update_user_filters();

    $new_filters['start_date'] = 'not a date';

    $users_filters = $edit_flow->story_budget->update_user_filters_from_form_date_range_change( $user, $new_filters );

    $this->assertEquals( date( 'Y-m-d' ), $users_filters['start_date'] );
  }
    
    /**
	 * Test that the story budget number of days filter handles valid number of days
	 */
	public function test_story_budget_set_number_days_filter() {
    global $edit_flow;

    $user = get_user_by( 'id', self::$admin_user_id );

    wp_set_current_user( self::$admin_user_id );

    // Users filters need to be set (they're set by default)
    $edit_flow->story_budget->update_user_filters();

    $new_filters['number_days'] = 10;

    $users_filters = $edit_flow->story_budget->update_user_filters_from_form_date_range_change( $user, $new_filters );

    $this->assertEquals( 10, $users_filters['number_days'] );
  }
    
    /**
	 * Test that the story budget number of days filter handles invalid number of days
	 */
	public function test_story_budget_set_number_days_filter_invalid() {
    global $edit_flow;

    $user = get_user_by( 'id', self::$admin_user_id );

    wp_set_current_user( self::$admin_user_id );

    // Users filters need to be set (they're set by default)
    $edit_flow->story_budget->update_user_filters();

    $new_filters['number_days'] = 'not days';

    $users_filters = $edit_flow->story_budget->update_user_filters_from_form_date_range_change( $user, $new_filters );

    $this->assertEquals( 1, $users_filters['number_days'] );
  }
    
    /**
	 * Test that the story budget handles both valid date and number of days filters
	 */
	public function test_story_budget_set_date_and_number_days_filters() {
    global $edit_flow;

    $user = get_user_by( 'id', self::$admin_user_id );

    wp_set_current_user( self::$admin_user_id );

    // Users filters need to be set (they're set by default)
    $edit_flow->story_budget->update_user_filters();

    $new_filters['start_date'] = '2019-12-01';
    $new_filters['number_days'] = 10;

    $users_filters = $edit_flow->story_budget->update_user_filters_from_form_date_range_change( $user, $new_filters );

    $this->assertEquals( 10, $users_filters['number_days'] );
    $this->assertEquals( '2019-12-01', $users_filters['start_date'] );
  }
  
  /**
	* Test that calendar has default custom statuses
	*/
	public function test_calendar_custom_statuses() {
		global $edit_flow;

        $statuses = array_map( 
            function( $status ) {
                return $status->name;
            }, 
            $edit_flow->calendar->get_calendar_post_stati() 
        );

		$this->assertContains( 'future', $statuses );
		$this->assertContains( 'pitch', $statuses );
	}

	/**
	 * Test that calendar can show registered status
	 */
	public function test_calendar_custom_statuses_registered() {
		global $edit_flow;

		$new_custom_status = array(
			'term' => __( 'New Custom Status' ),
			'args' => array(
				'slug'        => 'new-custom-status',
				'description' => 'description',
				'position'    => 6,
			),
        );
        
        $edit_flow->custom_status->add_custom_status( $new_custom_status['term'], $new_custom_status['args'] );

        $statuses = array_map( 
            function( $status ) {
                return $status->name;
            }, 
            $edit_flow->calendar->get_calendar_post_stati() 
        );
        
        $this->assertContains( 'future', $statuses );
	}
}
