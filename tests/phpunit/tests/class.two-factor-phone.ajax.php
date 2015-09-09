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
	 * Mimic the ajax handling of admin-ajax.php
	 * Capture the output via output buffering, and if there is any, store
	 * it in $this->_last_message.
	 * @param string $action
	 */
	protected function _handleAjax( $action, $nopriv = false ) {
		// Start output buffering
		ini_set( 'implicit_flush', false );
		ob_start();

		// Build the request
		$_POST['action'] = $action;
		$_GET['action']  = $action;
		$_REQUEST	= array_merge( $_POST, $_GET );

		// Call the hooks
		do_action( 'admin_init' );
		if( ! $nopriv ) {
			do_action( 'wp_ajax_' . $_REQUEST['action'], null );
		} else {
			do_action( 'wp_ajax_nopriv_' . $_REQUEST['action'], null );
		}

		// Save the output
		$buffer = ob_get_clean();
		if ( !empty( $buffer ) )
			$this->_last_response = $buffer;
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

		$_GET['nonce'] = wp_create_nonce( 'two-factor-phone-twiml' );
		$_GET['user']  = $current_user->ID;

		$this->logout();

		$this->_handleAjax( 'two-factor-phone-twiml', true );

		var_dump( $this->_last_response );
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

		$this->_handleAjax( 'two-factor-phone-twiml', true );
	}

	/**
	 * Verify that TwiML is not displayed without user ID.
	 * @covers Two_Factor_Phone::show_twiml_page
	 */
	function test_show_twiml_page_no_uid() {
		$this->_clear_action();

		$current_user = wp_get_current_user();

		$_GET['nonce'] = wp_create_nonce( 'two-factor-phone-twiml' );

		$this->logout();

		$this->_handleAjax( 'two-factor-phone-twiml', true );

		$this->assertEquals( '', $this->_last_response );
	}
}
