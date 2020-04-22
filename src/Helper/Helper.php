<?php
namespace Src\Helper;

/**
 * Helper
 */
class Helper {

	/**
	 * var_dump
	 * 
	 * @ignore
	 * @param mixed
	 */
	public static function dump($var_dump) {
		echo '<pre style="margin-left:200px;">';
		var_dump($var_dump);
		echo '</pre>';
	}

	/**
	 * Helper function to escape quotes in strings for use in Javascript
	 *
	 * @ignore
	 * @param string #message
	 * @return string
	 */
	public static function esc_quotes($string) {
		return str_replace('"', '\"', $string);
	}

	/**
	 * Display admin error message.
	 *
	 * @ignore
	 */
	public static function wp_internal_notice() {
		printf( '<div class="error"><p>%s</p></div>', esc_html( self::wp_internal_error() ) );
	}

	/**
	 * Error message holder.
	 *
	 * @ignore
	 * @param string $message Message string which will be saved if not empty.
	 * @return string
	 */
	public static function wp_internal_error( $message = '' ) {
		static $store = '';
		if ( $message ) {
			$store = $message;
		}
		return $store;
	}
}