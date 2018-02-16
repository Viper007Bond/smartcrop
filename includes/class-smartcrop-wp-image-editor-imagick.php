<?php
/**
 * Imagick implementation of the SmartCrop Image Editor.
 *
 * @package SmartCrop
 * @since   1.0.0
 */

require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
require_once ABSPATH . WPINC . '/class-wp-image-editor-imagick.php';
require_once __DIR__ . '/trait-smartcrop-wp-image-editor-common.php';

/**
 * Imagick-specific methods for SmartCrop functionality.
 *
 * @see  WP_Image_Editor_GD
 * @uses SmartCrop_WP_Image_Editor_Common
 * @uses SmartCrop_Image_Analysis
 */
class SmartCrop_WP_Image_Editor_Imagick extends WP_Image_Editor_Imagick {
	use SmartCrop_WP_Image_Editor_Common;

	/**
	 * Makes a separate, distinct copy of the `image` Imagick object for when this class is cloned.
	 */
	public function __clone() {
		$this->image = clone $this->image;
	}

	/**
	 * @see SmartCrop_Image_Analysis::smartcrop_filter_smooth()
	 */
	public function smartcrop_filter_smooth( $smoothness ) {
		$this->image->statisticImage( Imagick::STATISTIC_MEDIAN, $smoothness, $smoothness );
	}

	/**
	 * @see SmartCrop_Image_Analysis::smartcrop_get_average_rgb_color_for_region()
	 */
	public function smartcrop_get_average_rgb_color_for_region( $src_x, $src_y, $src_w, $src_h ) {
		$region = clone $this->image;
		$region->cropImage( $src_w, $src_h, $src_x, $src_y );
		$region->scaleImage( 1, 1 );

		$pixel = $region->getImagePixelColor( 0, 0 );

		$color = $pixel->getColor();

		$pixel->clear();
		$region->clear();

		return array(
			'red'   => $color['r'],
			'green' => $color['g'],
			'blue'  => $color['b'],
			'alpha' => $color['a'],
		);
	}

	/**
	 * @see SmartCrop_Image_Analysis::smartcrop_get_entropy_for_region()
	 */
	public function smartcrop_get_entropy_for_region( $src_x, $src_y, $src_w, $src_h ) {
		$region = clone $this->image;
		$region->cropImage( $src_w, $src_h, $src_x, $src_y );

		// Make the region a greyscale set of detected edges.
		$region->edgeImage( 1 );
		$region->modulateImage( 100, 0, 100 );

		$histogram = $region->getImageHistogram();

		$levels = array();
		foreach ( $histogram as $color ) {
			$level[ $color->getColor()['r'] ] = $color->getColorCount();
			$color->clear();
		}

		// Get the entropy value from the histogram.
		$entropy = 0;
		foreach ( $levels as $level ) {
			$pl = $level / ( $src_w * $src_h );
			$pl = $pl * log( $pl );

			$entropy -= $pl;
		}

		$region->clear();

		return $entropy;
	}
}
