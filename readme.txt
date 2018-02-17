=== SmartCrop ===
Contributors: Viper007Bond
Tags: thumbnail, thumbnails, crop
Requires at least: 3.5
Tested up to: 4.9
Requires PHP: 5.4
Stable tag: trunk
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Instead of cropping to the center of an image, SmartCrop attempts to use the most interesting part of the image for the thumbnail.

== Description ==

By default, WordPress uses the center of an image when creating cropped thumbnails. SmartCrop attempts to find and use the most interesting part of an image and centers the cropping on that portion of the image.

This process is entirely automatic and cannot be manually controlled. There are other plugins out there if you wish to manually specify where you want thumbnails to be cropped.

Since this analysis process can be slow on large images, thumbnails are initially center cropped and later asynchronously regenerated to be smartly cropped. This avoids the uploading of new images from being slow. The one side effect of this however is that your browser may load and cache the centered thumbnail before it's re-cropped.

Want to smartly crop your past image uploads? You can use my [Regenerate Thumbnails](https://wordpress.org/plugins/regenerate-thumbnails/) plugin to trigger that. Make sure to uncheck skipping existing thumbnails.

= Need Help? Found A Bug? Want To Contribute Code? =

Support for this plugin is provided via the [WordPress.org forums](https://wordpress.org/support/plugin/smartcrop).

The source code for this plugin is available on [GitHub](https://github.com/Viper007Bond/smartcrop).

== Installation ==

1. Go to your admin area and select Plugins → Add New from the menu.
2. Search for "SmartCrop".
3. Click install.
4. Click activate.

That's it! The plugin will work automatically in the background on any new uploads.

Want to smartly crop your past image uploads? You can use my [Regenerate Thumbnails](https://wordpress.org/plugins/regenerate-thumbnails/) plugin to trigger that. Make sure to uncheck skipping existing thumbnails.

== Screenshots ==

1. Sample source image.
2. Default thumbnail cropping.
3. Cropped thumbnail with SmartCrop enabled.

== ChangeLog ==

= Version 1.0.0 =

* Initial release.