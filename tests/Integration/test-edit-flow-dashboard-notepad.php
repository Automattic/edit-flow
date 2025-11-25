<?php

class WP_Test_Edit_Flow_Dashboard_Note extends WP_UnitTestCase {
	
	function test_register_dashboard_note_post_type() {
		//As part of the Edit Flow initialziation process
		//EF_Dashboard_Notepad_Widget should have already
		//created the dashboard-note post type
		$pobj = get_post_type_object( 'dashboard-note' );
		$this->assertEquals( 'dashboard-note', $pobj->name );

		//Testing EF_Dashboard_Notepad_Widget::init explicitly 
		_unregister_post_type( 'dashboard-note' );

		$EditFlowDashboardNote = new EF_Dashboard_Notepad_Widget();

		$this->assertNull( get_post_type_object( 'dashboard-note' ) );

		//Should create the post type 'dashboard-note'
		$EditFlowDashboardNote->init();

		$pobj = get_post_type_object( 'dashboard-note' );
		$this->assertEquals( 'dashboard-note', $pobj->name );
	}

}