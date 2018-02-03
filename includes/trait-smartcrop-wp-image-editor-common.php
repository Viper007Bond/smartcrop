<?php

require_once __DIR__ . '/trait-smartcrop-image-analysis.php';

trait SmartCrop_WP_Image_Editor_Common {
	use SmartCrop_Image_Analysis;

	public static function test( $args = array() ) {
		if ( empty( $args['smartcrop'] ) || ! apply_filters( 'smartcrop_enabled', $args['smartcrop'] ) ) {
			return false;
		}

		return parent::test( $args );
	}

	public function resize( $max_w, $max_h, $crop = false ) {
		if ( ! $crop ) {
			return parent::resize( $max_w, $max_h, $crop );
		}

		add_filter( 'image_resize_dimensions', array( $this, 'smartcrop_calculate_image_resize_coordinates' ), 10, 6 );

		$resize = parent::resize( $max_w, $max_h, $crop );

		remove_filter( 'image_resize_dimensions', array( $this, 'smartcrop_calculate_image_resize_coordinates' ) );

		return $resize;
	}

	public function smartcrop_normal_resize( $max_w, $max_h, $crop = false ) {
		$has_filter = has_filter( 'image_resize_dimensions', array( $this, 'smartcrop_calculate_image_resize_coordinates' ) );

		if ( $has_filter ) {
			remove_filter( 'image_resize_dimensions', array( $this, 'smartcrop_calculate_image_resize_coordinates' ) );
		}

		$resize = parent::resize( $max_w, $max_h, $crop );

		if ( $has_filter ) {
			add_filter( 'image_resize_dimensions', array( $this, 'smartcrop_calculate_image_resize_coordinates' ), 10, 6 );
		}

		return $resize;
	}

	public function smartcrop_calculate_image_resize_coordinates( $input, $orig_w, $orig_h, $thumb_w, $thumb_h, $crop ) {
		if ( ! $crop ) {
			return $input;
		}

		// To make the process faster, shrink the original to as small as possible while still being bigger than the thumbnail.
		list( $presize_w, $presize_h ) = $this->smartcrop_contrain_dimensions_outside_box( $orig_w, $orig_h, $thumb_w, $thumb_h );

		/**
		 * Bail if the presize is going to the same size as requested.
		 * This happens when the source and thumbnail are the same aspect ratio.
		 */
		if ( $presize_w === $thumb_w && $presize_h === $thumb_h ) {
			return $input;
		}

		// Shrink the image.
		$presize = $this->smartcrop_normal_resize( $presize_w, $presize_h, false );

		if ( is_wp_error( $presize ) ) {
			return $input;
		}

		list( $x, $y ) = $this->smartcrop_get_crop_coordinates( $thumb_w, $thumb_h );

		return array( 0, 0, $x, $y, $thumb_w, $thumb_h, $thumb_w, $thumb_h );
	}

	public function smartcrop_contrain_dimensions_outside_box( $current_width, $current_height, $minimum_width, $minimum_height ) {
		$width_ratio = $height_ratio = 1.0;
		$did_width   = $did_height = false;

		if ( $current_width > 0 && $current_width > $minimum_width ) {
			$width_ratio = $minimum_width / $current_width;
			$did_width   = true;
		}

		if ( $current_height > 0 && $current_height > $minimum_height ) {
			$height_ratio = $minimum_height / $current_height;
			$did_height   = true;
		}

		// Calculate the larger/smaller ratios
		$smaller_ratio = min( $width_ratio, $height_ratio );
		$larger_ratio  = max( $width_ratio, $height_ratio );

		if ( (int) round( $current_width * $larger_ratio ) > $minimum_width || (int) round( $current_height * $larger_ratio ) > $minimum_height ) {
			// We want the larger ratio here so that it overflows outside of the box.
			$ratio = $larger_ratio;
		} else {
			$ratio = $smaller_ratio;
		}

		// Very small dimensions may result in 0, 1 should be the minimum.
		$w = max( 1, (int) round( $current_width * $ratio ) );
		$h = max( 1, (int) round( $current_height * $ratio ) );

		// Sometimes, due to rounding, we'll end up with a result like this: 465x700 in a 177x177 box is 117x176... a pixel short
		// We also have issues with recursive calls resulting in an ever-changing result. Constraining to the result of a constraint should yield the original result.
		// Thus we look for dimensions that are one pixel shy of the max value and bump them up

		if ( $did_width && $w === $minimum_width - 1 ) {
			$w = $minimum_width; // Round it up
		}

		if ( $did_height && $h === $minimum_height - 1 ) {
			$h = $minimum_height; // Round it up
		}

		return array( $w, $h );
	}
}
