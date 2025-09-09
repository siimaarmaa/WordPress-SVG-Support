<?php
/*
Plugin Name: WordPress SVG Support (Secure)
Plugin URI: https://www.aarmaa.ee
Description: Allows secure SVG format for WordPress
Author: Siim Aarmaa | Aarmaa
Version: 1.2-secure
Author URI: https://www.aarmaa.ee
*/

// Allow SVG file type (restricted to Admins + Editors)
add_filter('upload_mimes', function ($mimes) {
    if (current_user_can('manage_options') || current_user_can('edit_others_posts')) {
        $mimes['svg'] = 'image/svg+xml';
    }
    return $mimes;
});

// Ensure correct filetype check
add_filter('wp_check_filetype_and_ext', function ($data, $file, $filename, $mimes) {
    $filetype = wp_check_filetype($filename, $mimes);
    return [
        'ext'             => $filetype['ext'],
        'type'            => $filetype['type'],
        'proper_filename' => $data['proper_filename']
    ];
}, 10, 4);

// Add CSS fix for thumbnails
add_action('admin_head', function () {
    echo '<style type="text/css">
        .attachment-266x266, .thumbnail img {
            width: 100% !important;
            height: auto !important;
        }
    </style>';
});

// Global flag for sanitizer availability
$GLOBALS['svg_support_sanitizer_ok'] = false;

// Secure SVG sanitization with fallback protection
function secure_sanitize_svg($file) {
    if ($file['type'] === 'image/svg+xml') {
        $autoload = ABSPATH . 'vendor/autoload.php';

        if (!file_exists($autoload)) {
            $file['error'] = 'SVG uploads are disabled: missing sanitizer library.';
            return $file;
        }

        require_once $autoload;

        if (!class_exists(\enshrined\svgSanitize\Sanitizer::class)) {
            $file['error'] = 'SVG uploads are disabled: sanitizer not available.';
            return $file;
        }

        // Mark sanitizer as available
        $GLOBALS['svg_support_sanitizer_ok'] = true;

        $sanitizer = new \enshrined\svgSanitize\Sanitizer();

        $dirtySVG = file_get_contents($file['tmp_name']);
        $cleanSVG = $sanitizer->sanitize($dirtySVG);

        if ($cleanSVG === false) {
            $file['error'] = 'Invalid or unsafe SVG file.';
        } else {
            file_put_contents($file['tmp_name'], $cleanSVG);
        }
    }
    return $file;
}
add_filter('wp_handle_upload_prefilter', 'secure_sanitize_svg');

// Show admin notice if sanitizer is missing (dismissible)
add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) {
        return; // Only admins need this notice
    }

    if (!$GLOBALS['svg_support_sanitizer_ok'] && !get_user_meta(get_current_user_id(), 'dismiss_svg_notice', true)) {
        $dismiss_url = wp_nonce_url(add_query_arg('dismiss_svg_notice', '1'), 'dismiss_svg_notice');
        echo '<div class="notice notice-error is-dismissible">
            <p><strong>WordPress SVG Support (Secure):</strong> 
            SVG uploads are currently <span style="color:red;">disabled</span> because the sanitizer library is missing. 
            Please run <code>composer require enshrined/svg-sanitize</code> in your WordPress root. 
            <a href="' . esc_url($dismiss_url) . '">Dismiss this notice</a></p>
        </div>';
    }
});

// Handle dismiss action
add_action('admin_init', function () {
    if (isset($_GET['dismiss_svg_notice']) && current_user_can('manage_options')) {
        check_admin_referer('dismiss_svg_notice');
        update_user_meta(get_current_user_id(), 'dismiss_svg_notice', 1);
        wp_safe_redirect(remove_query_arg(['dismiss_svg_notice', '_wpnonce']));
        exit;
    }
});
