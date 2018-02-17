# SmartCrop

[![Travis CI Build Status](https://travis-ci.org/Viper007Bond/smartcrop.svg)](https://travis-ci.org/Viper007Bond/smartcrop)
[![WordPress Plugin Downloads](https://img.shields.io/wordpress/plugin/dt/smartcrop.svg)](https://wordpress.org/plugins/smartcrop/)

WordPress plugin that adjusts the crop location of cropped thumbnail images so that the theoretically most interesting part of the image is shown instead of only the center.

Analysis is done asynchronously after upload to prevent slow upload processing. This means that your browser may cache the centered verson before it's later overwritten with the smartly cropped version.

Image analysis is based on code by @gschoppe.