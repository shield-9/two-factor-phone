<?php
/**
 * Test Two Factor Phone.
 */

class Tests_Two_Factor_Phone extends WP_UnitTestCase {

	/**
	 * Set up a test case.
	 *
	 * @see WP_UnitTestCase::setup()
	 */
	function setUp() {
		parent::setUp();
	}

	/**
	 * Check that the plugin is active.
	 */
	function test_is_plugin_active() {

		$this->assertTrue( is_plugin_active( 'two-factor-phone/two-factor-phone.php' ) );

	}

	/**
	 * Check that the TWO_FACTOR_PHONE_DIR constant is defined.
	 */
	function test_constant_defined() {

		$this->assertTrue( defined( 'TWO_FACTOR_PHONE_DIR' ) );

	}

	/**
	 * Check that the files were included.
	 */
	function test_classes_exist() {

		$this->assertTrue( class_exists( 'Two_Factor_Phone' ) );

	}
}