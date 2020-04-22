<?php
/**
 * WP Mail Queue
 *
 * @package   WP_Mail_Queue
 * @author    Yo Yamamoto <cross_sphere@hotmail.com>
 * @license   GPL-2.0+
 * @link      http://pulltab.info
 * @copyright 2020 Yo Yamamoto
 *
 * @wordpress-plugin
 * Plugin Name: WP Mail Queue
 * Plugin URI:  -
 * Description: ワードプレスから一斉メールを独自テーブルのキューに入れて、時差で配信するプラグインです。
 * Version:     1.0.0
 * Author:      Yo Yamamoto
 * Author URI:  http://pulltab.info
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) || defined( 'WMQ_URL' ) || defined( 'WMQ_DIR' ) ) {
	die;
} // end if

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require __DIR__ . '/vendor/autoload.php';
}
define( 'WMQ_FILE', __FILE__ );
define( 'WMQ_DIR', plugin_dir_path( __FILE__ ) );
define( 'WMQ_URL', plugin_dir_url( __FILE__ ) );

Src\Mail_Queue::get_instance();