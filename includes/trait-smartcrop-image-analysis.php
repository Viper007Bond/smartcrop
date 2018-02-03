<?php

trait SmartCrop_Image_Analysis {
	protected $size = null;

	abstract public function smartcrop_filter_smooth( $smoothness );

	abstract public function smartcrop_get_average_rgb_color_for_region( $src_x, $src_y, $src_w, $src_h );

	abstract public function smartcrop_get_entropy_for_region( $src_x, $src_y, $src_w, $src_h );

	public function smartcrop_get_crop_coordinates( $dest_w, $dest_h ) {
		list( $focus_x, $focus_y, $focus_x_weight, $focus_y_weight ) = $this->smartcrop_get_focal_point();

		// If the presize is wider than the desired width, then crop the side(s).
		if ( $this->size['width'] > $dest_w ) {
			$y = 0;

			// Which side of the focal point is more interesting?
			if ( $focus_x_weight > 0 ) {
				// Put the center point on the right rule of thirds line.
				$x = ( $focus_x * $this->size['width'] ) - ( ( 2 / 3 ) * $dest_w );
			} elseif ( $focus_x_weight < 0 ) {
				// Put the center point on the left rule of thirds line.
				$x = ( $focus_x * $this->size['width'] ) - ( ( 1 / 3 ) * $dest_w );
			} else {
				// Center the image on the focal point.
				$x = ( $focus_x * $this->size['width'] ) - ( 0.5 * $dest_w );
			}

			// Make sure the coordinate is not out of bounds.
			if ( $x >= $this->size['width'] - $dest_w ) {
				$x = $this->size['width'] - $dest_w - 1;
			}
		} else {
			$x = 0;

			if ( $focus_y_weight > 0 ) {
				// Put the center point on the top rule of thirds line.
				$y = ( $focus_y * $this->size['height'] ) - ( ( 2 / 3 ) * $dest_h );
			} elseif ( $focus_y_weight < 0 ) {
				// Put the center point on the bottom rule of thirds line.
				$y = ( $focus_y * $this->size['height'] ) - ( ( 1 / 3 ) * $dest_h );
			} else {
				// Center the image on the focal point.
				$y = ( $focus_y * $this->size['height'] ) - ( 0.5 * $dest_h );
			}

			// Make sure the coordinate is not out of bounds.
			if ( $y >= $this->size['height'] - $dest_h ) {
				$y = $this->size['height'] - $dest_h - 1;
			}
		}

		$x = max( 0, $x );
		$y = max( 0, $y );

		return array( $x, $y );
	}

	public function smartcrop_get_focal_point( $slice_count = 20, $weight = 0.5 ) {
		// @TODO: Should we further shrink the image like the original does? $sample

		// Smooth the image a little to help reduce the effects of noise.
		$this->smartcrop_filter_smooth( 7 );

		// Find the average color of the whole image.
		$average_color = $this->smartcrop_get_average_rgb_color_for_region( 0, 0, $this->size['width'], $this->size['height'] );
		$average_color = $this->smartcrop_color_rgb_to_lab( $average_color );

		list( $x, $x_weight ) = $this->smartcrop_find_best_slice( $slice_count, $weight, $average_color, 'vertical' );
		list( $y, $y_weight ) = $this->smartcrop_find_best_slice( $slice_count, $weight, $average_color, 'horizontal' );

		var_dump( 'focus', $average_color, $x, $x_weight, $y, $y_weight );

		return array( $x, $y, $x_weight, $y_weight );
	}

	public function smartcrop_find_best_slice( $slice_count, $weight, $average_color_lab, $slice_direction ) {
		if ( 'vertical' === $slice_direction ) {
			$slice_width  = floor( $this->size['width'] / $slice_count );
			$slice_height = $this->size['height'];

			$slice_size_primary   = $slice_width;
			$slice_size_secondary = $this->size['width'];

			$slice_y = 0;
		} else {
			$slice_width  = $this->size['width'];
			$slice_height = floor( $this->size['height'] / $slice_count );

			$slice_size_primary   = $slice_height;
			$slice_size_secondary = $this->size['height'];

			$slice_x = 0;
		}

		$slices = array();
		for ( $i = 0; $i < $slice_count; $i ++ ) {
			if ( 'vertical' === $slice_direction ) {
				$slice_x = $slice_width * $i;
			} else {
				$slice_y = $slice_height * $i;
			}

			// Color
			if ( 0 === $weight ) {
				// A weight of 0 means color is ignored.
				$slice_color = 0;
			} else {
				$slice_average_color_rgb = $this->smartcrop_get_average_rgb_color_for_region( $slice_x, $slice_y, $slice_width, $slice_height );
				$slice_color             = $this->smartcrop_get_color_difference_via_euclidean_distance( $average_color_lab, $this->smartcrop_color_rgb_to_lab( $slice_average_color_rgb ) );
			}

			// Entropy
			if ( 1 === $weight ) {
				// A weight of 1 means entropy is ignored.
				$slice_entropy = 0;
			} else {
				$slice_entropy = $this->smartcrop_get_entropy_for_region( $slice_x, $slice_y, $slice_width, $slice_height );
			}

			// Get a weighted average of the color and entropy.
			$slices[ $i ] = $slice_color * $weight + $slice_entropy * ( 1 - $weight );
		}

		// Find the best slice.
		$best_slice = array_search( max( $slices ), $slices, true );

		// Get the center of that slice.
		$center = ( $best_slice + 0.5 ) * $slice_size_primary / $slice_size_secondary;

		$slice_weight = $this->smartcrop_get_slice_weight( $slices, $best_slice );

		return array( $center, $slice_weight );
	}

	public function smartcrop_get_color_difference_via_euclidean_distance( $color_1, $color_2 ) {
		$sumOfSquares = 0;
		foreach ( $color_1 as $key => $val ) {
			$sumOfSquares += pow( ( $color_2[ $key ] - $val ), 2 );
		}

		$distance = sqrt( $sumOfSquares );

		// Divide by 10 to put it in similar range to entropy numbers.
		return $distance / 10;
	}

	public function smartcrop_get_slice_weight( $slices, $best_slice ) {
		$slice_count = count( $slices );

		if ( 0 === $best_slice ) {
			$a = 0;
			$b = 1;
		} elseif ( $slice_count - 1 === $best_slice ) {
			$a = 1;
			$b = 0;
		} else {
			$a = $b = 0;

			// Average of the slices to the left of the best slice.
			for ( $i = 0; $i < $best_slice; $i ++ ) {
				$a += $slices[ $i ];
			}
			$a = $a / $best_slice;

			// Average of the slices to the right of the best slice.
			for ( $i = $best_slice + 1; $i < $slice_count; $i ++ ) {
				$b += $slices[ $i ];
			}
			$b = $b / ( $slice_count - ( $best_slice + 1 ) );
		}

		if ( $a > $b ) {
			return 1;
		}

		if ( $a < $b ) {
			return - 1;
		}

		return 0;
	}

	public function smartcrop_color_rgb_to_lab( $color ) {
		list( $r, $g, $b ) = array_map( function ( $color ) {
			$color = $color / 255;

			if ( $color > 0.04045 ) {
				$color = pow( ( ( $color + 0.055 ) / 1.055 ), 2.4 );
			} else {
				$color = $color / 12.92;
			}

			return $color * 100;
		}, array_values( $color ) );

		$x = 0.4124 * $r + 0.3576 * $g + 0.1805 * $b;
		$y = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
		$z = 0.0193 * $r + 0.1192 * $g + 0.9505 * $b;

		$l = $a = $b = 0;

		if ( $y !== 0 ) {
			$l = 10 * sqrt( $y );
			$a = 17.5 * ( ( 1.02 * $x ) - $y ) / sqrt( $y );
			$b = 7 * ( $y - 0.847 * $z ) / sqrt( $y );
		}

		return compact( 'l', 'a', 'b' );
	}
}
