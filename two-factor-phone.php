<?php
/**
 * Plugin Name: Two Factor Phone
 * Plugin URI: https://github.com/shield-9/two-factor-phone
 * Description: Add Phone Call support to "Two Factor" feature as a plugin
 * Author: Daisuke Takahashi (Extend Wings)
 * Version: 0.1
 * Author URI: https://www.extendwings.com
 * Text Domain: two-factor-phone
 * Domain Path: /languages
 */

define( 'TWO_FACTOR_PHONE_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Return path to Two Factor Phone plugin.
 *
 * @since 0.1-dev
 *
 * @param string[] $providers Array of providers.
 * @return string[] Array of providers.
 */
function two_factor_phone_init( $providers ) {
	$providers['Two_Factor_Phone'] = TWO_FACTOR_PHONE_DIR . 'class.two-factor-phone.php';

	return $providers;
}

add_filter( 'two_factor_providers', 'two_factor_phone_init' );
