<?php
/**
 * Settings module integration tests.
 *
 * @package Automattic\EditFlow\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\EditFlow\Tests\Integration;

use EF_Settings;
use Yoast\WPTestUtils\WPIntegration\TestCase;

class SettingsTest extends TestCase {

	protected static $ef_settings;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$ef_settings = new EF_Settings();
	}

	public static function wpTearDownAfterClass() {
		self::$ef_settings = null;
	}

	protected function tearDown(): void {
		self::$ef_settings->form_errors = array();
		unset( $_REQUEST['form-errors'] );

		parent::tearDown();
	}

	/**
	 * Render the error-or-description helper and capture its output.
	 *
	 * @param string $field       Field name to render.
	 * @param string $description Description shown when there is no error.
	 * @return string Captured HTML.
	 */
	private function render_field( string $field, string $description ): string {
		ob_start();
		self::$ef_settings->helper_print_error_or_description( $field, $description );
		return (string) ob_get_clean();
	}

	/**
	 * A server-set form error is displayed, escaped, in place of the field description.
	 */
	public function test_displays_form_error_when_set() {
		self::$ef_settings->form_errors['name'] = '<b>Name is required</b>';

		$html = $this->render_field( 'name', 'The field description.' );

		$this->assertStringContainsString( 'form-error', $html );
		$this->assertStringContainsString( esc_html( '<b>Name is required</b>' ), $html );
		$this->assertStringNotContainsString( '<b>Name is required</b>', $html, 'Raw markup must not survive into the output.' );
		$this->assertStringNotContainsString( 'The field description.', $html );
	}

	/**
	 * The field description shows when there is no error for that field.
	 */
	public function test_displays_description_when_no_error() {
		$html = $this->render_field( 'name', 'The field description.' );

		$this->assertStringContainsString( 'class="description"', $html );
		$this->assertStringContainsString( 'The field description.', $html );
		$this->assertStringNotContainsString( 'form-error', $html );
	}

	/**
	 * A form error injected via the request (e.g. a crafted query string) must not be rendered:
	 * errors are read from the module property, not $_REQUEST, so inline messages cannot be spoofed.
	 */
	public function test_ignores_form_error_injected_via_request() {
		$_REQUEST['form-errors']['name'] = 'Spoofed error from the URL';

		$html = $this->render_field( 'name', 'The field description.' );

		$this->assertStringNotContainsString( 'Spoofed error from the URL', $html );
		$this->assertStringContainsString( 'The field description.', $html );
	}
}
