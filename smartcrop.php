<?php /*

**************************************************************************

Plugin Name:  SmartCrop
Description:
Plugin URI:   https://alex.blog/wordpress-plugins/smartcrop/
Version:      1.0.0
Author:       Alex Mills (Viper007Bond)
Author URI:   https://alex.blog/
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

require_once __DIR__ . '/includes/class-smartcrop-wp-image-editor-imagick.php';
require_once __DIR__ . '/includes/class-smartcrop-wp-image-editor-gd.php';

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
		add_filter( 'wp_image_editors', array( $this, 'register_image_editors' ) );
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'queue_regeneration_of_cropped_thumbnails' ), 1, 2 );
		add_action( 'smartcrop_process_thumbnail', array( $this, 'process_thumbnail' ), 10, 3 );
	}

	/**
	 * Registers this plugin's cropping image editor classes with WordPress.
	 *
	 * @since 1.0.0
	 *
	 * @param array $implementations List of available image editors.
	 *
	 * @return array Modified list of available image editors.
	 */
	public function register_image_editors( $implementations ) {
		return array_merge(
			array(
				'SmartCrop_WP_Image_Editor_Imagick',
				'SmartCrop_WP_Image_Editor_GD',
			),
			$implementations
		);
	}

	/**
	 * Schedules WP Cron events for each cropped thumbnail image.
	 * This is because the smart cropping functionality can be slow.
	 *
	 * @since 1.0.0
	 *
	 * @param array $metadata      An array of attachment meta data.
	 * @param int   $attachment_id The attachment ID.
	 *
	 * @return array The unmodified attachment meta data.
	 */
	public function queue_regeneration_of_cropped_thumbnails( $metadata, $attachment_id ) {
		if ( ! is_array( $metadata['sizes'] ) ) {
			return $metadata;
		}

		$thumbnail_sizes = $this->get_thumbnail_sizes();

		foreach ( $metadata['sizes'] as $thumbnail_label => $thumbnail_details ) {
			if ( empty( $thumbnail_sizes[ $thumbnail_label ] ) || ! $thumbnail_sizes[ $thumbnail_label ]['crop'] ) {
				continue;
			}

			// This process can take a while, so offload it to the cron to be done asynchronously.
			wp_schedule_single_event( time() - 1, 'smartcrop_process_thumbnail', array( $attachment_id, $thumbnail_details ) );
		}

		return $metadata;
	}

	/**
	 * Processes a single cropped thumbnail size for an attachment.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $attachment_id     The attachment ID.
	 * @param array $thumbnail_details An array of thumbnail details.
	 *
	 * @return array|WP_Error Array of saved file details, or a WP_Error object.
	 */
	public function process_thumbnail( $attachment_id, $thumbnail_details ) {
		$fullsize = get_attached_file( $attachment_id );

		if ( false === $fullsize || ! file_exists( $fullsize ) ) {
			return new WP_Error(
				'smartcrop_fullsize_not_found',
				'The fullsize image file could not be found.',
				array(
					'fullsizepath'  => _wp_relative_upload_path( $fullsize ),
					'attachment_id' => $attachment_id,
				)
			);
		}

		@set_time_limit( 300 );

		$editor = wp_get_image_editor(
			$fullsize,
			array(
				'smartcrop' => true,
				'methods'   => array( 'resize' ),
			)
		);

		if ( is_wp_error( $editor ) ) {
			return $editor;
		}

		$resize = $editor->resize( $thumbnail_details['width'], $thumbnail_details['height'], true );

		if ( is_wp_error( $resize ) ) {
			return $resize;
		}

		$filename = dirname( $fullsize ) . DIRECTORY_SEPARATOR . $thumbnail_details['file'];

		return $editor->save( $filename );
	}

	/**
	 * Returns an array of all thumbnail sizes, including their label, size, and crop setting.
	 *
	 * @since 1.0.0
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
