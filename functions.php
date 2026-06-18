<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function preinscriptions_theme_assets() {
    wp_enqueue_style(
        'preinscriptions-style',
        get_stylesheet_uri(),
        array(),
        '1.0'
    );
}
add_action( 'wp_enqueue_scripts', 'preinscriptions_theme_assets' );