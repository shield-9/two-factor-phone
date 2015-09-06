<?php
/**
 * Test Two Factor Phone Class.
 * @group ajax
 */

class Tests_Class_Two_Factor_Phone_Ajax extends WP_Ajax_UnitTestCase {

	protected $provider;

	/**
	 * Set up a test case.
	 *
	 * @see WP_UnitTestCase::setup()
	 */
	public function setUp() {
		parent::setUp();
	}

	/**
	 * Clear the actions in between requests
	 */
	protected function _clear_action() {
		$_POST = $_GET = $_REQUEST = array();
		$this->_last_response = '';
	}

	/**
	 * Verify that TwiML is displayed.
	 * @covers Two_Factor_Phone::show_twiml_page
	 */
	function test_show_twiml_page() {
		$this->_clear_action();

		$current_user = wp_get_current_user();

		$_REQUEST['nonce'] = wp_create_nonce( 'two-factor-phone-twiml' );
		$_REQUEST['user']  = $current_user->ID;

		$this->logout();

		try {
			$this->_handleAjax( 'two-factor-phone-twiml' );
		} catch ( WPAjaxDieStopException $e ) {
			unset( $e );
		}

		$this->assertRegExp( '/Your login confirmation code for .* is:/', $this->_last_response );
	}

	/**
	 * Verify that TwiML is not displayed without nonce.
	 * @covers Two_Factor_Phone::show_twiml_page
	 *
	 * @expectedException        WPAjaxDieStopException
	 * @expectedExceptionMessage -1
	 */
	function test_show_twiml_page_no_nonce() {
		$this->_clear_action();

		$current_user = wp_get_current_user();

		$this->logout();

		$this->_handleAjax( 'two-factor-phone-twiml' );
	}

	/**
	 * Verify that TwiML is not displayed without user ID.
	 * @covers Two_Factor_Phone::show_twiml_page
	 *
	 * @expectedException        WPAjaxDieStopException
	 * @expectedExceptionMessage -1
	 */
	function test_show_twiml_page_no_uid() {
		$this->_clear_action();

		$current_user = wp_get_current_user();

		$_REQUEST['nonce'] = wp_create_nonce( 'two-factor-phone-twiml' );

		$this->logout();

		$this->_handleAjax( 'two-factor-phone-twiml' );
	}
}
