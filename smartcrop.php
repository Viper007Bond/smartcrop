<?php /*

**************************************************************************

Plugin Name:  SmartCrop
Description:
Plugin URI:   https://alex.blog/wordpress-plugins/smartcrop/
Version:      1.0.0
Author:       Alex Mills (Viper007Bond)
Author URI:   https://alex.blog/
Text Domain:  smartcrop
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html

**************************************************************************

SmartCrop is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

SmartCrop is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with SmartCrop. If not, see https://www.gnu.org/licenses/gpl-2.0.html.

**************************************************************************/

class SmartCrop {
	/**
	 * The single instance of this plugin.
	 *
	 * @since  1.0.0
	 *
	 * @see    SmartCrop()
	 *
	 * @access private
	 * @var    SmartCrop
	 */
	private static $instance;

	/**
	 * Constructor. Doesn't actually do anything as instance() creates the class instance.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
	}

	/**
	 * Prevents the class from being cloned.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {
		wp_die( "Please don't clone SmartCrop" );
	}

	/**
	 * Prints the class from being unserialized and woken up.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		wp_die( "Please don't unserialize/wakeup SmartCrop" );
	}

	/**
	 * Creates a new instance of this class if one hasn't already been made
	 * and then returns the single instance of this class.
	 *
	 * @since 1.0.0
	 *
	 * @return SmartCrop
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new SmartCrop;
			self::$instance->setup();
		}

		return self::$instance;
	}

	/**
	 * Register all of the needed hooks and actions.
	 *
	 * @since 1.0.0
	 */
	public function setup() {
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'queue_regeneration_of_cropped_thumbnails' ), 1, 2 );

		add_action( 'smartcrop_process_thumbnail', array( $this, 'process_thumbnail' ), 10, 3 );
	}

	public function queue_regeneration_of_cropped_thumbnails( $metadata, $attachment_id ) {
		if ( ! is_array( $metadata['sizes'] ) ) {
			return $metadata;
		}

		// The smartcrop.php library requires GD
		if ( ! WP_Image_Editor_GD::test() ) {
			return $metadata;
		}

		$thumbnail_sizes = $this->get_thumbnail_sizes();

		foreach ( $metadata['sizes'] as $thumbnail_label => $thumbnail_details ) {
			if ( empty( $thumbnail_sizes[ $thumbnail_label ] ) || ! $thumbnail_sizes[ $thumbnail_label ]['crop'] ) {
				continue;
			}

			// This process can take a while, so offload it to the cron to be done asynchronously.
			wp_schedule_single_event( time() - 1, 'smartcrop_process_thumbnail', array( $attachment_id, $thumbnail_label, $thumbnail_details ) );
		}

		return $metadata;
	}

	public function process_thumbnail( $attachment_id, $thumbnail_label, $thumbnail_details ) {
		@set_time_limit( 300 );

		$fullsize = get_attached_file( $attachment_id );
		if ( false === $fullsize || ! file_exists( $fullsize ) ) {
			return;
		}

		require_once __DIR__ . '/libraries/gschoppe/smart_crop.php';

		$image = imagecreatefromjpeg( $fullsize );

		$smart_crop = new smart_crop( $image );

		$thumbnail = $smart_crop->get_resized( $thumbnail_details['width'], $thumbnail_details['height'] );

		$filename = dirname( $fullsize ) . DIRECTORY_SEPARATOR . $thumbnail_details['file'];

		imagejpeg( $thumbnail, $filename, 82 );

		imagedestroy( $image );
		imagedestroy( $thumbnail );
	}

	/**
	 * Returns an array of all thumbnail sizes, including their label, size, and crop setting.
	 *
	 * @return array An array, with the thumbnail label as the key and an array of thumbnail properties (width, height, crop).
	 */
	public function get_thumbnail_sizes() {
		global $_wp_additional_image_sizes;

		$thumbnail_sizes = array();

		foreach ( get_intermediate_image_sizes() as $size ) {
			$thumbnail_sizes[ $size ]['label'] = $size;
			if ( in_array( $size, array( 'thumbnail', 'medium', 'medium_large', 'large' ) ) ) {
				$thumbnail_sizes[ $size ]['width']  = (int) get_option( $size . '_size_w' );
				$thumbnail_sizes[ $size ]['height'] = (int) get_option( $size . '_size_h' );
				$thumbnail_sizes[ $size ]['crop']   = ( 'thumbnail' == $size ) ? (bool) get_option( 'thumbnail_crop' ) : false;
			} elseif ( ! empty( $_wp_additional_image_sizes ) && ! empty( $_wp_additional_image_sizes[ $size ] ) ) {
				$thumbnail_sizes[ $size ]['width']  = (int) $_wp_additional_image_sizes[ $size ]['width'];
				$thumbnail_sizes[ $size ]['height'] = (int) $_wp_additional_image_sizes[ $size ]['height'];
				$thumbnail_sizes[ $size ]['crop']   = (bool) $_wp_additional_image_sizes[ $size ]['crop'];
			}
		}

		return $thumbnail_sizes;
	}
}

/**
 * Returns the single instance of this plugin, creating one if needed.
 *
 * @since 1.0.0
 *
 * @return SmartCrop
 */
function SmartCrop() {
	return SmartCrop::instance();
}

/**
 * Initialize this plugin once all other plugins have finished loading.
 */
add_action( 'init', 'SmartCrop' );
