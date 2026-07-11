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
    define( 'PREINSCRIPTIONS_VERSION', '1.1' );
}

/**
 * Démarre la session PHP tôt, pour pouvoir stocker l'ID du post créé
 * (preinscription_post_id) entre la sauvegarde en BD et la génération du PDF.
 */
function ueb_start_session() {
    if ( ! session_id() ) {
        session_start();
    }
}
add_action( 'init', 'ueb_start_session', 1 );

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

    // Header + footer : charges sur TOUTES les pages (nav.bar + site-footer).
    $hf_css = get_template_directory() . '/assets/css/header-footer.css';
    $hf_js  = get_template_directory() . '/assets/js/header-footer.js';

    wp_enqueue_style(
        'preinscriptions-header-footer',
        get_template_directory_uri() . '/assets/css/header-footer.css',
        array( 'preinscriptions-style' ),
        file_exists( $hf_css ) ? filemtime( $hf_css ) : PREINSCRIPTIONS_VERSION
    );

    wp_enqueue_script(
        'preinscriptions-header-footer',
        get_template_directory_uri() . '/assets/js/header-footer.js',
        array(),
        file_exists( $hf_js ) ? filemtime( $hf_js ) : PREINSCRIPTIONS_VERSION,
        true
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

/* ==================
FONCTIONS DU FORMULAIRE
=======================*/

/* ── 1. Enqueue CSS + JS du formulaire sur la page de préinscription */
function preinscriptions_form_assets() {
    if ( is_page_template( 'page-preinscription.php' ) ) {
        wp_enqueue_style(
            'preinscriptions-form',
            get_template_directory_uri() . '/assets/css/form-preinscription.css',
            array( 'preinscriptions-style' ),
            '1.0'
        );
        wp_enqueue_script(
            'preinscriptions-form',
            get_template_directory_uri() . '/assets/js/form-preinscription.js',
            array(),
            '3.1',
            true  // chargé en footer
        );
    }
}
add_action( 'wp_enqueue_scripts', 'preinscriptions_form_assets' );


/* ── 2. Custom Post Type : preinscription ── */
function preinscriptions_register_cpt() {
    register_post_type( 'preinscription', array(
        'labels'        => array(
            'name'          => 'Préinscriptions',
            'singular_name' => 'Préinscription',
            'menu_name'     => 'Préinscriptions',
            'all_items'     => 'Toutes les demandes',
            'view_item'     => 'Voir la demande',
            'search_items'  => 'Rechercher',
        ),
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => true,
        'menu_icon'     => 'dashicons-welcome-write-blog',
        'supports'      => array( 'title' ),
        'capability_type' => 'post',
        'map_meta_cap'  => true,
    ) );
}
add_action( 'init', 'preinscriptions_register_cpt' );

require_once( get_template_directory() . '/inc/db-functions.php' );
require_once( get_template_directory() . '/inc/pdf-functions.php' );
require_once( get_template_directory() . '/inc/db-schema.php' );
require_once( get_template_directory() . '/inc/dossier-functions.php' );
require_once( get_template_directory() . '/inc/db-schema.php' );
require_once( get_template_directory() . '/inc/db-seed.php' );
