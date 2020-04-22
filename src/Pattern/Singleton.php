<?php

namespace Src\Pattern;

/**
 * Singleton
 */
abstract class Singleton {
	/**
	 * @var NULL
	 */
	protected static $instance;

	/**
	 * Constructor
	 * 
	 * @ignore
	 */
	private function __construct() {}

	/**
	 * Get instance
	 * 
	 * @ignore
	 */
	final public static function get_instance() {
		if ( static::$instance === NULL ) {
			static::$instance = new static();
		}
		return static::$instance;
	}
}