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
		$this->expectOutputRegex('/<p>An error occured while calling.<\/p>/');

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
		$this->expectOutputRegex('/<p>An error occured while calling.<\/p>/');

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
}
