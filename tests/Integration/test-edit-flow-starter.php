<?php
/**
 * Tests to test that that testing framework is testing tests. Meta, huh?
 * @package wordpress-plugins-tests
 * @author mbijon
 */
class WP_Test_Edit_Flow_Starter_Tests extends WP_UnitTestCase {
	
	/**
	 * Run a simple test to ensure that the tests are running
	 */
	function test_editflow_exists() {
		 
		$this->assertTrue( class_exists( 'edit_flow' ) );
		 
	}
	
	/**
	 * Verify a minimum version of WordPress is installed
	 */
	function test_wp_version() {
		
		$minimum_version = '3.4.0';
		$running_version = get_bloginfo( 'version' );
		
		//trunk is always "master" in github terms, but WordPress has a specific way of describing it
		//grab the exact version number to verify that we're on trunk
		if ( $running_version == 'master' || $running_version == 'trunk' ) {
			$file = file_get_contents( 'https://raw.github.com/WordPress/WordPress/master/wp-includes/version.php' );
			preg_match( '#\$wp_version = \'([^\']+)\';#', $file, $matches );
			$running_version = $matches[1];
		}
		
		$this->assertTrue( version_compare( $running_version, $minimum_version, '>=' ) );
	
	}
	
	/**
	 * Test modules loading
	 */
	function test_editflow_register_module() {
		
		$EditFlow = EditFlow();
		
		$module_real = strtolower( 'calendar' );
		$module_args = array ( 'title' => $module_real );
		$module_return = $EditFlow->register_module( $module_real, $module_args );
		$this->assertTrue( $module_real == $module_return->name );
		
	}
	
}