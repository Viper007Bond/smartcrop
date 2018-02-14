<?php

class SmartCrop_Test_Image_Editor extends WP_UnitTestCase {
	public static $directory;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$directory = __DIR__ . '/smartcrop-samples/smartcropjs/';
	}

	public function test_calculate_image_resize_coordinates() {
		$editor = wp_get_image_editor(
			self::$directory . '65210163.jpg',
			array(
				'smartcrop' => true,
				'methods'   => array( 'resize' ),
			)
		);

		// Verify that a SmartCrop image editor was selected.
		$this->assertTrue( method_exists( $editor, 'smartcrop_calculate_image_resize_coordinates' ) );

		$verify_coordinates = function ( $coordinates ) {
			$this->assertSame( $coordinates, array( 0, 0, 0, 250, 150, 150, 646, 646 ) );

			return $coordinates;
		};

		add_filter( 'smartcrop_calculate_image_resize_coordinates', $verify_coordinates );

		$editor->resize( 150, 150, true );

		remove_filter( 'smartcrop_calculate_image_resize_coordinates', $verify_coordinates );

		$this->assertSame( $editor->get_size()['width'], 150 );
		$this->assertSame( $editor->get_size()['height'], 150 );

		unset( $editor );
	}
}