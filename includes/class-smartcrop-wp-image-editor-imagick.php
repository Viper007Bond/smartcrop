<?php

require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
require_once ABSPATH . WPINC . '/class-wp-image-editor-imagick.php';
require_once __DIR__ . '/trait-smartcrop-wp-image-editor-common.php';

class SmartCrop_WP_Image_Editor_Imagick extends WP_Image_Editor_Imagick {
	use SmartCrop_WP_Image_Editor_Common;
}