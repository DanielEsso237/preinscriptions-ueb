<?php
/**
 * Fonctions et definitions du theme Preinscriptions UEB.
 *
 * @package Preinscriptions_UEB
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Version du theme, utilisee pour le versioning des assets (cache busting).
 */
if ( ! defined( 'PREINSCRIPTIONS_VERSION' ) ) {
    define( 'PREINSCRIPTIONS_VERSION', '1.0' );
}

/**
 * Chargement des fonctions specifiques a la landing page.
 */
require get_template_directory() . '/inc/landing-page-functions.php';

/**
 * Enregistre et charge les feuilles de style et scripts du theme.
 */
function preinscriptions_theme_assets() {
    // Polices auto-hebergees (Sora pour les titres, Inter pour le corps).
    // Hbergees localement dans assets/fonts (pas d'appel a Google Fonts en CDN : RGPD + perf).
    $fonts_css = get_template_directory() . '/assets/css/fonts.css';
    wp_enqueue_style(
        'preinscriptions-fonts',
        get_template_directory_uri() . '/assets/css/fonts.css',
        array(),
        file_exists( $fonts_css ) ? filemtime( $fonts_css ) : PREINSCRIPTIONS_VERSION
    );

    // Feuille de style principale du theme (style.css a la racine).
    wp_enqueue_style(
        'preinscriptions-style',
        get_stylesheet_uri(),
        array( 'preinscriptions-fonts' ),
        PREINSCRIPTIONS_VERSION
    );

    // Assets specifiques a la landing page : charges uniquement sur la page d'accueil.
    if ( is_front_page() ) {
        $landing_css = get_template_directory() . '/assets/css/landing-page.css';
        $landing_js  = get_template_directory() . '/assets/js/landing-page.js';

        wp_enqueue_style(
            'preinscriptions-landing',
            get_template_directory_uri() . '/assets/css/landing-page.css',
            array( 'preinscriptions-style' ),
            file_exists( $landing_css ) ? filemtime( $landing_css ) : PREINSCRIPTIONS_VERSION
        );

        wp_enqueue_script(
            'preinscriptions-landing',
            get_template_directory_uri() . '/assets/js/landing-page.js',
            array(),
            file_exists( $landing_js ) ? filemtime( $landing_js ) : PREINSCRIPTIONS_VERSION,
            true
        );
    }
}
add_action( 'wp_enqueue_scripts', 'preinscriptions_theme_assets' );
