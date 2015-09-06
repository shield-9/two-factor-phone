<?php
/**
 * Test Two Factor Phone Class.
 */

class Tests_Class_Two_Factor_Phone extends WP_UnitTestCase {

	protected $provider;

	/**
	 * Set up a test case.
	 *
	 * @see WP_UnitTestCase::setup()
	 */
	public function setUp() {
		parent::setUp();

		$this->provider = Two_Factor_Phone::get_instance();
	}

	/**
	 * Verify an instance exists.
	 * @covers Two_Factor_Phone::get_instance
	 */
	function test_get_instance() {
		global $wp_actions;

		unset( $wp_actions['plugins_loaded'] );

		$this->assertInstanceOf( 'Two_Factor_Phone', $this->provider->get_instance() );
	}

	/**
	 * Verify an instance exists.
	 * @covers Two_Factor_Phone::get_instance
	 */
	function test_get_instance_did_action() {
		global $wp_actions;

		$wp_actions['plugins_loaded'] = 1;

		$this->assertInstanceOf( 'Two_Factor_Phone', $this->provider->get_instance() );
	}

	/**
	 * Verify the label value.
	 * @covers Two_Factor_Phone::get_label
	 */
	function test_get_label() {
		$this->assertContains( 'Phone Call (Twilio)', $this->provider->get_label() );
	}

	/**
	 * Verify that validate_token validates a generated token.
	 * @covers Two_Factor_Phone::generate_token
	 * @covers Two_Factor_Phone::validate_token
	 */
	function test_generate_token_and_validate_token() {
		$user_id = 1;

		$token = $this->provider->generate_token( $user_id );

		$this->assertTrue( $this->provider->validate_token( $user_id, $token ) );
	}

	/**
	 * Show that validate_token fails for a different user's token.
	 * @covers Two_Factor_Phone::generate_token
	 * @covers Two_Factor_Phone::validate_token
	 */
	function test_generate_token_and_validate_token_false_different_users() {
		$user_id = 1;

		$token = $this->provider->generate_token( $user_id );

		$this->assertFalse( $this->provider->validate_token( $user_id + 1, $token ) );
	}

	/**
	 * Show that a deleted token can't validate for a user.
	 * @covers Two_Factor_Phone::generate_token
	 * @covers Two_Factor_Phone::validate_token
	 * @covers Two_Factor_Phone::delete_token
	 */
	function test_generate_token_and_validate_token_false_deleted() {
		$user_id = 1;

		$token = $this->provider->generate_token( $user_id );
		$this->provider->delete_token( $user_id );

		$this->assertFalse( $this->provider->validate_token( $user_id, $token ) );
	}

	/**
	 * Verify called tokens can be validated.
	 * @covers Two_Factor_Phone::generate_and_call_token
	 */
	function test_generate_and_call_token() {
		$user = new WP_User( $this->factory->user->create() );

		update_user_meta( $user->ID, Two_Factor_Phone::ACCOUNT_SID_META_KEY,     'AC6de23fc078bf6a68766cb71396bd909f' );
		update_user_meta( $user->ID, Two_Factor_Phone::AUTH_TOKEN_META_KEY,      'e89ae308710c53982fad1d6795a6c75b' );
		update_user_meta( $user->ID, Two_Factor_Phone::SENDER_NUMBER_META_KEY,   '+15005550006' );
		update_user_meta( $user->ID, Two_Factor_Phone::RECEIVER_NUMBER_META_KEY, '+15005550005' );

		$this->assertTrue( $this->provider->generate_and_call_token( $user ) );
	}

	/**
	 * Verify called tokens can be validated.
	 * @covers Two_Factor_Phone::generate_and_call_token
	 */
	function test_generate_and_call_token_invalid_data() {
		$user = new WP_User( $this->factory->user->create() );

		update_user_meta( $user->ID, Two_Factor_Phone::ACCOUNT_SID_META_KEY,     'dummydummy' );
		update_user_meta( $user->ID, Two_Factor_Phone::AUTH_TOKEN_META_KEY,      'WordPress!' );
		update_user_meta( $user->ID, Two_Factor_Phone::SENDER_NUMBER_META_KEY,   '+100000000000' );
		update_user_meta( $user->ID, Two_Factor_Phone::RECEIVER_NUMBER_META_KEY, '+810000000000' );

		$this->assertFalse( $this->provider->generate_and_call_token( $user ) );
	}

	/**
	 * Verify the contents of the authentication page.
	 * @covers Two_Factor_Phone::authentication_page
	 */
	function test_authentication_page() {
		$this->expectOutputRegex('/^\s*<p>A verification code has been sent to the phone number associated with your account\.<\/p>/s');
		$this->expectOutputRegex('/<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Log In"  \/><\/p>\s*$/s');

		$user = new WP_User( $this->factory->user->create() );

		update_user_meta( $user->ID, Two_Factor_Phone::ACCOUNT_SID_META_KEY,     'AC6de23fc078bf6a68766cb71396bd909f' );
		update_user_meta( $user->ID, Two_Factor_Phone::AUTH_TOKEN_META_KEY,      'e89ae308710c53982fad1d6795a6c75b' );
		update_user_meta( $user->ID, Two_Factor_Phone::SENDER_NUMBER_META_KEY,   '+15005550006' );
		update_user_meta( $user->ID, Two_Factor_Phone::RECEIVER_NUMBER_META_KEY, '+15005550005' );

		$this->provider->authentication_page( $user );
	}

	/**
	 * Verify the contents of the authentication page when invalid data are provided.
	 * @covers Two_Factor_Phone::authentication_page
	 */
	function test_authentication_page_invalid_data() {
		$this->expectOutputRegex('/<p>An error occured while calling\.<\/p>/');

		$user = new WP_User( $this->factory->user->create() );

		update_user_meta( $user->ID, Two_Factor_Phone::ACCOUNT_SID_META_KEY,     'dummydummy' );
		update_user_meta( $user->ID, Two_Factor_Phone::AUTH_TOKEN_META_KEY,      'WordPress!' );
		update_user_meta( $user->ID, Two_Factor_Phone::SENDER_NUMBER_META_KEY,   '+100000000000' );
		update_user_meta( $user->ID, Two_Factor_Phone::RECEIVER_NUMBER_META_KEY, '+810000000000' );

		$this->provider->authentication_page( $user );
	}

	/**
	 * Verify the contents of the authentication page when no user is provided.
	 * @covers Two_Factor_Phone::authentication_page
	 */
	function test_authentication_page_no_user() {
		$this->expectOutputString('');

		$this->provider->authentication_page( false );
	}

	/**
	 * Verify that call validation with no user returns false.
	 * @covers Two_Factor_Phone::validate_authentication
	 */
	function test_validate_authentication_no_user_is_false() {
		$this->assertFalse( $this->provider->validate_authentication( false ) );
	}

	/**
	 * Verify that call validation with no user returns false.
	 * @covers Two_Factor_Phone::validate_authentication
	 */
	function test_validate_authentication() {
		$user = new WP_User( $this->factory->user->create() );

		$token = $this->provider->generate_token( $user->ID );
		$_REQUEST['two-factor-phone-code'] = $token;

		$this->assertTrue( $this->provider->validate_authentication( $user ) );

		unset( $_REQUEST['two-factor-phone-code'] );
	}

	/**
	 * Verify that availability returns true.
	 * @covers Two_Factor_Phone::is_available_for_user
	 */
	function test_is_available_for_user() {
		$user = new WP_User( $this->factory->user->create() );

		update_user_meta( $user->ID, Two_Factor_Phone::ACCOUNT_SID_META_KEY,     'AC6de23fc078bf6a68766cb71396bd909f' );
		update_user_meta( $user->ID, Two_Factor_Phone::AUTH_TOKEN_META_KEY,      'e89ae308710c53982fad1d6795a6c75b' );
		update_user_meta( $user->ID, Two_Factor_Phone::SENDER_NUMBER_META_KEY,   '+15005550000' );
		update_user_meta( $user->ID, Two_Factor_Phone::RECEIVER_NUMBER_META_KEY, '+15005550005' );

		$this->assertTrue( $this->provider->is_available_for_user( $user ) );
	}

	/**
	 * Verify that availability returns false when no user provided.
	 * @covers Two_Factor_Phone::is_available_for_user
	 */
	function test_is_available_for_user_no_user() {
		$this->assertFalse( $this->provider->is_available_for_user( false ) );
	}

	/**
	 * Verify that availability returns false when user is not configured.
	 * @covers Two_Factor_Phone::is_available_for_user
	 */
	function test_is_available_for_user_no_setup_user() {
		$user = new WP_User( $this->factory->user->create() );

		delete_user_meta( $user->ID, Two_Factor_Phone::ACCOUNT_SID_META_KEY );
		delete_user_meta( $user->ID, Two_Factor_Phone::AUTH_TOKEN_META_KEY );
		delete_user_meta( $user->ID, Two_Factor_Phone::SENDER_NUMBER_META_KEY );
		delete_user_meta( $user->ID, Two_Factor_Phone::RECEIVER_NUMBER_META_KEY );

		$this->assertFalse( $this->provider->is_available_for_user( $user ) );
	}

	/**
	 * Verify that user profile is displaying.
	 * @covers Two_Factor_Phone::show_user_profile
	 */
	function test_show_user_profile() {
		global $wp_actions;

		$this->expectOutputRegex('/^\s*<div class="twilio" id="twilio-section">\s*<h3>Twilio<\/h3>\s*<table class="form-table">/s');
		$this->expectOutputRegex('/<\/table>\s*<\/div>\s*$/s');

		unset( $wp_actions['user_profile_twilio'] );
		$user = new WP_User( $this->factory->user->create() );

		$this->assertNull( $this->provider->show_user_profile( $user ) );
	}

	/**
	 * Verify that user profile returns null.
	 * @covers Two_Factor_Phone::show_user_profile
	 */
	function test_show_user_profile_did_action() {
		global $wp_actions;

		$wp_actions['user_profile_twilio'] = 1;
		$user = new WP_User( $this->factory->user->create() );

		$this->assertNull( $this->provider->show_user_profile( $user ) );
	}

	/**
	 * Verify that twilio item at user profile is displaying.
	 * @covers Two_Factor_Phone::show_twilio_item
	 */
	function test_show_twilio_item() {
		$this->expectOutputRegex('/AC6de23fc078bf6a68766cb71396bd909f/s');
		$this->expectOutputRegex('/e89ae308710c53982fad1d6795a6c75b/s');
		$this->expectOutputRegex('/\+15005550006/s');
		$this->expectOutputRegex('/\+15005550005/s');

		$user = new WP_User( $this->factory->user->create() );

		update_user_meta( $user->ID, Two_Factor_Phone::ACCOUNT_SID_META_KEY,     'AC6de23fc078bf6a68766cb71396bd909f' );
		update_user_meta( $user->ID, Two_Factor_Phone::AUTH_TOKEN_META_KEY,      'e89ae308710c53982fad1d6795a6c75b' );
		update_user_meta( $user->ID, Two_Factor_Phone::SENDER_NUMBER_META_KEY,   '+15005550006' );
		update_user_meta( $user->ID, Two_Factor_Phone::RECEIVER_NUMBER_META_KEY, '+15005550005' );


		$this->provider->show_twilio_item( $user );
	}

	/**
	 * Verify that twilio item at user profile is updated.
	 * @covers Two_Factor_Phone::catch_submission
	 */
	function test_catch_submission() {
		$this->markTestIncomplete( 'This test is not implemented yet.' );

		$current_user = wp_get_current_user();
		$new_user = new WP_User( $this->factory->user->create() );
		$new_user->add_cap( 'edit_users' );

		wp_set_current_user( $new_user->ID );

		$_POST['twilio-phone-sid']      = 'dummydummy';
		$_POST['twilio-phone-token']    = 'WordPress!';
		$_POST['twilio-phone-sender']   = '+100000000000';
		$_POST['twilio-phone-receiver'] = '+810000000000';

		$this->provider->catch_submission( $current_user->ID );

		$this->assertSame( $_POST['twilio-phone-sid'],      get_user_meta( $current_user->ID, Two_Factor_Phone::ACCOUNT_SID_META_KEY, true ) );
		$this->assertSame( $_POST['twilio-phone-token'],    get_user_meta( $current_user->ID, Two_Factor_Phone::AUTH_TOKEN_META_KEY, true ) );
		$this->assertSame( $_POST['twilio-phone-sender'],   get_user_meta( $current_user->ID, Two_Factor_Phone::SENDER_NUMBER_META_KEY, true ) );
		$this->assertSame( $_POST['twilio-phone-receiver'], get_user_meta( $current_user->ID, Two_Factor_Phone::RECEIVER_NUMBER_META_KEY, true ) );

		wp_set_current_user( $current_user->ID );
	}

	/**
	 * Verify that submission catcher returns null.
	 * @covers Two_Factor_Phone::catch_submission
	 */
	function test_catch_submission_no_cap() {
		$current_user = wp_get_current_user();
		$new_user = new WP_User( $this->factory->user->create( array(
			'role' => 'subscriber',
		) ) );

		wp_set_current_user( $new_user->ID );

		$this->assertNull( $this->provider->catch_submission( $current_user->ID ) );

		wp_set_current_user( $current_user->ID );
	}
}
