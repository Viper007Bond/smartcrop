<?php

require_once __DIR__ . '/trait-smartcrop-image-analysis.php';

trait SmartCrop_WP_Image_Editor_Common {
	use SmartCrop_Image_Analysis;

	/**
	 * @var SmartCrop_WP_Image_Editor_GD|SmartCrop_WP_Image_Editor_Imagick
	 */
	public $sample;

	public static function test( $args = array() ) {
		if ( empty( $args['smartcrop'] ) || ! apply_filters( 'smartcrop_enabled', $args['smartcrop'] ) ) {
			return false;
		}

		return parent::test( $args );
	}

	public function resize( $max_w, $max_h, $crop = false ) {
		// If not cropping or the thumbnail is the same aspect ratio as the original image, then normal resizing is needed.
		if ( ! $crop || ( $max_w / $max_h ) === ( $this->size['width'] / $this->size['height'] ) ) {
			return parent::resize( $max_w, $max_h, $crop );
		}

		// Make a copy of the image that will be used for analysis.
		$this->sample = clone $this;

		// To make the cropping analysis process faster, shrink the sampling image.
		list( $presize_w, $presize_h ) = $this->sample->smartcrop_contrain_dimensions_outside_box( $this->size['width'], $this->size['height'], $max_w, $max_h );

		$presize_result = $this->sample->smartcrop_normal_resize( $presize_w, $presize_h, true );

		if ( is_wp_error( $presize_result ) ) {
			unset( $this->sample );

			return parent::resize( $max_w, $max_h, $crop );
		}

		add_filter( 'image_resize_dimensions', array( $this, 'smartcrop_calculate_image_resize_coordinates' ), 10, 6 );

		$resize_result = parent::resize( $max_w, $max_h, $crop );

		remove_filter( 'image_resize_dimensions', array( $this, 'smartcrop_calculate_image_resize_coordinates' ) );

		unset( $this->sample );

		return $resize_result;
	}

	public function smartcrop_normal_resize( $max_w, $max_h, $crop = false ) {
		return parent::resize( $max_w, $max_h, $crop );
	}

	public function smartcrop_calculate_image_resize_coordinates( $input, $orig_w, $orig_h, $dest_w, $dest_h, $crop ) {
		if ( ! $crop || ! $this->sample ) {
			return $input;
		}

		list( $sample_x, $sample_y ) = $this->sample->smartcrop_get_crop_coordinates( $dest_w, $dest_h );

		// The crop coordinates are for the smaller sample image, so they need to be scaled back up to match the original.
		$sample_scale = ( $this->size['width'] / $this->sample->size['width'] );
		$orig_x       = (int) round( $sample_x * $sample_scale );
		$orig_y       = (int) round( $sample_y * $sample_scale );

		$aspect_ratio = $orig_w / $orig_h;
		$new_w        = min( $dest_w, $orig_w );
		$new_h        = min( $dest_h, $orig_h );

		if ( ! $new_w ) {
			$new_w = (int) round( $new_h * $aspect_ratio );
		}

		if ( ! $new_h ) {
			$new_h = (int) round( $new_w / $aspect_ratio );
		}

		$size_ratio = max( $new_w / $orig_w, $new_h / $orig_h );

		$crop_w = (int) round( $new_w / $size_ratio );
		$crop_h = (int) round( $new_h / $size_ratio );
		
		return array( 0, 0, $orig_x, $orig_y, $new_w, $new_h, $crop_w, $crop_h );
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
