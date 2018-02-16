<?php

require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

class SmartCrop_Test_Image_Editor extends WP_UnitTestCase {
	public $class_to_test;

	public static function wpTearDownAfterClass() {
		$upload_dir = wp_get_upload_dir();
		$upload_dir = $upload_dir['path'];

		if ( is_dir( $upload_dir ) ) {
			$filesystem = new WP_Filesystem_Direct( array() );
			$filesystem->rmdir( trailingslashit( $upload_dir ), true );
		}
	}

	public function helper_get_cron_event( $attachment_id, $size ) {
		$crons = _get_cron_array();

		if ( empty( $crons ) ) {
			return false;
		}

		foreach ( $crons as $time => $cron ) {
			foreach ( $cron as $hook => $dings ) {
				if ( $hook !== 'smartcrop_process_thumbnail' ) {
					continue;
				}

				foreach ( $dings as $sig => $data ) {
					if ( $data['args'][0] != $attachment_id || $data['args'][1]['label'] != $size ) {
						continue;
					}

					return $data['args'];
				}
			}
		}

		return false;
	}

	public function test_calculate_image_resize_coordinates() {
		$editor = wp_get_image_editor(
			DIR_TESTDATA . '/images/33772.jpg',
			array(
				'smartcrop' => true,
				'methods'   => array( 'resize' ),
			)
		);

		// Verify that a SmartCrop image editor was selected.
		$this->assertTrue( method_exists( $editor, 'smartcrop_calculate_image_resize_coordinates' ) );

		$verify_coordinates = function ( $coordinates ) {
			$this->assertSame( $coordinates, array( 0, 0, 834, 0, 150, 150, 1080, 1080 ) );

			return $coordinates;
		};

		add_filter( 'smartcrop_calculate_image_resize_coordinates', $verify_coordinates );

		$editor->resize( 150, 150, true );

		remove_filter( 'smartcrop_calculate_image_resize_coordinates', $verify_coordinates );

		$this->assertSame( $editor->get_size()['width'], 150 );
		$this->assertSame( $editor->get_size()['height'], 150 );

		unset( $editor );
	}

	public function test_cron_and_cropping_imagick() {
		$this->assertTrue( WP_Image_Editor_Imagick::test() );

		$this->helper_test_cron_and_cropping( 'SmartCrop_WP_Image_Editor_Imagick' );
	}

	public function test_cron_and_cropping_gd() {
		$this->assertTrue( WP_Image_Editor_GD::test() );

		$this->helper_test_cron_and_cropping( 'SmartCrop_WP_Image_Editor_GD' );
	}

	public function helper_test_cron_and_cropping( $class ) {
		$this->class_to_test = $class;

		$attachment_id = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/33772.jpg' );

		$args = $this->helper_get_cron_event( $attachment_id, 'thumbnail' );
		$this->assertNotFalse( $args );

		$metadata = wp_get_attachment_metadata( $attachment_id );

		$thumbnail = dirname( get_attached_file( $attachment_id ) ) . DIRECTORY_SEPARATOR . $metadata['sizes']['thumbnail']['file'];

		$mtime = filemtime( $thumbnail );

		// Ensure that enough time passes for filemtime() to change.
		sleep( 1 );

		$thumbnail_original = dirname( $thumbnail ) . '/thumbnail-original.jpg';
		copy( $thumbnail, $thumbnail_original );

		add_filter( 'wp_image_editors', array( $this, 'helper_set_image_editor' ), 99 );
		do_action_ref_array( 'smartcrop_process_thumbnail', $args );
		remove_filter( 'wp_image_editors', array( $this, 'helper_set_image_editor' ) );

		$this->assertNotSame( $mtime, filemtime( $thumbnail ) );
		$this->assertNotSame( file_get_contents( $thumbnail_original ), file_get_contents( $thumbnail ) );
	}

	public function helper_set_image_editor( $implementations ) {
		return array( $this->class_to_test );
	}
}