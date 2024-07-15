<?php

/*
Plugin Name: WordPress SVG Support
Plugin URI: https://www.aarmaa.ee
Description: Allows SVG format for WordPress
Author: Siim Aarmaa | Aarmaa
Version: 1.0
Author URI: https://www.aarmaa.ee
*/

// Include the SVG sanitization library
require_once plugin_dir_path(__FILE__) . 'svg-sanitizer.php';

// Allow SVG
add_filter('wp_check_filetype_and_ext', function($data, $file, $filename, $mimes) {
    global $wp_version;
    if (version_compare($wp_version, '6.0.0', '<')) {
        return $data;
    }

    $filetype = wp_check_filetype($filename, $mimes);

    return [
        'ext' => $filetype['ext'],
        'type' => $filetype['type'],
        'proper_filename' => $data['proper_filename']
    ];
}, 10, 4);

function cc_mime_types($mimes) {
    $mimes['svg'] = 'image/svg+xml';
    return $mimes;
}
add_filter('upload_mimes', 'cc_mime_types');

function fix_svg() {
    echo '<style type="text/css">
        .attachment-266x266, .thumbnail img {
            width: 100% !important;
            height: auto !important;
        }
    </style>';
}
add_action('admin_head', 'fix_svg');

function sanitize_svg($file) {
    if ($file['type'] === 'image/svg+xml') {
        $svg_sanitizer = new enshrined\svgSanitize\Sanitizer();
        $dirty_svg = file_get_contents($file['tmp_name']);
        $clean_svg = $svg_sanitizer->sanitize($dirty_svg);
        if ($clean_svg) {
            file_put_contents($file['tmp_name'], $clean_svg);
        } else {
            // If sanitization fails, treat it as an invalid file
            $file['error'] = 'SVG file sanitization failed.';
        }
    }
    return $file;
}
add_filter('wp_handle_upload_prefilter', 'sanitize_svg');

?>
