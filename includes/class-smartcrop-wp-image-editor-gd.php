<?php

require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
require_once ABSPATH . WPINC . '/class-wp-image-editor-gd.php';
require_once __DIR__ . '/trait-smartcrop-wp-image-editor-common.php';

class SmartCrop_WP_Image_Editor_GD extends WP_Image_Editor_GD {
	use SmartCrop_WP_Image_Editor_Common;

	public function __clone() {
		$image_copy = imagecreatetruecolor( $this->size['width'], $this->size['height'] );
		imagecopy( $image_copy, $this->image, 0, 0, 0, 0, $this->size['width'], $this->size['height'] );

		$this->image = $image_copy;
	}

	public function smartcrop_filter_smooth( $smoothness ) {
		imagefilter( $this->image, IMG_FILTER_SMOOTH, $smoothness );
	}

	public function smartcrop_get_average_rgb_color_for_region( $src_x, $src_y, $src_w, $src_h ) {
		$pixel = imagecreatetruecolor( 1, 1 );

		imagecopyresampled( $pixel, $this->image, 0, 0, $src_x, $src_y, 1, 1, $src_w, $src_h );

		$average_color = imagecolorsforindex( $pixel, imagecolorat( $pixel, 0, 0 ) );

		imagedestroy( $pixel );

		return $average_color;
	}

	public function smartcrop_get_entropy_for_region( $src_x, $src_y, $src_w, $src_h ) {
		$region = imagecreatetruecolor( $src_w, $src_h );
		imagecopy( $region, $this->image, 0, 0, $src_x, $src_y, $src_w, $src_h );

		// Make the region a greyscale set of detected edges.
		imagefilter( $region, IMG_FILTER_EDGEDETECT );
		imagefilter( $region, IMG_FILTER_GRAYSCALE );

		// Create a histogram of the edge image.
		$levels = array();
		for ( $x = 0; $x < $src_w; $x ++ ) {
			for ( $y = 0; $y < $src_h; $y ++ ) {
				$color = imagecolorsforindex( $region, imagecolorat( $region, $x, $y ) );

				// Red, green, and blue are all equal in a grayscale image so we can just use the red value.
				if ( ! isset( $levels[ $color['red'] ] ) ) {
					$levels[ $color['red'] ] = 0;
				}

				$levels[ $color['red'] ] ++;
			}
		}

		// Get the entropy value from the histogram.
		$entropy = 0;
		foreach ( $levels as $level ) {
			$pl = $level / ( $src_w * $src_h );
			$pl = $pl * log( $pl );

			$entropy -= $pl;
		}

		imagedestroy( $region );

		return $entropy;
	}
}
