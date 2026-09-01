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
    define( 'PREINSCRIPTIONS_VERSION', '1.3' );
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
    $fonts_css = get_template_directory() . '/assets/css/fonts.css';
    wp_enqueue_style(
        'preinscriptions-fonts',
        get_template_directory_uri() . '/assets/css/fonts.css',
        array(),
        file_exists( $fonts_css ) ? filemtime( $fonts_css ) : PREINSCRIPTIONS_VERSION
    );

    wp_enqueue_style(
        'preinscriptions-style',
        get_stylesheet_uri(),
        array( 'preinscriptions-fonts' ),
        PREINSCRIPTIONS_VERSION
    );

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
            '2.2'
        );
        wp_enqueue_script(
            'preinscriptions-form',
            get_template_directory_uri() . '/assets/js/form-preinscription.js',
            array(),
            '4.6',
            true
        );

        // Passe l'URL admin-ajax.php et le nonce partagé du formulaire au JS.
        wp_localize_script( 'preinscriptions-form', 'uebAjax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'preinscription_submit' ),
        ) );
    }
}
add_action( 'wp_enqueue_scripts', 'preinscriptions_form_assets' );

require_once( get_template_directory() . '/inc/analytics-functions.php' );

/**
 * Charge le CSS admin pour tout le monde (utile pour afficher proprement
 * l'écran de connexion), mais le JS + les données ne sont chargés que
 * pour les comptes ayant la capacité 'voir_preinscriptions'.
 */
function preinscriptions_admin_assets() {
    if ( ! is_page_template( 'page-administration.php' ) ) return;

    wp_enqueue_style( 'preinscriptions-admin', get_template_directory_uri() . '/assets/css/admin-dashboard.css', array( 'preinscriptions-style' ), PREINSCRIPTIONS_VERSION );

    if ( ! is_user_logged_in() || ! current_user_can( 'voir_preinscriptions' ) ) return;

    wp_enqueue_script( 'chartjs', get_template_directory_uri() . '/assets/js/vendor/chart.umd.min.js', array(), '4.4.0', true );
    wp_enqueue_script( 'preinscriptions-admin-analytics', get_template_directory_uri() . '/assets/js/admin-analytics.js', array( 'chartjs' ), PREINSCRIPTIONS_VERSION, true );
    wp_enqueue_script( 'preinscriptions-admin-dashboard', get_template_directory_uri() . '/assets/js/admin-dashboard.js', array( 'chartjs', 'preinscriptions-admin-analytics' ), PREINSCRIPTIONS_VERSION, true );

    wp_localize_script( 'preinscriptions-admin-dashboard', 'uebAdminDashboard', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'ueb_admin_dashboard' ),
        'refs'     => ueb_admin_get_reference_lists(),
    ) );
}

function preinscriptions_countdown_assets() {
    if ( ! is_page_template( 'page-compte-rebours.php' )
        && ! is_page_template( 'page-preinscription.php' )
        && ! is_page_template( 'page-maintenance.php' ) ) {
        return;
    }
    $countdown_css = get_template_directory() . '/assets/css/compte-rebours.css';
    $countdown_js  = get_template_directory() . '/assets/js/compte-rebours.js';

    wp_enqueue_style(
        'preinscriptions-countdown',
        get_template_directory_uri() . '/assets/css/compte-rebours.css',
        array( 'preinscriptions-style' ),
        file_exists( $countdown_css ) ? filemtime( $countdown_css ) : PREINSCRIPTIONS_VERSION
    );

    wp_enqueue_script(
        'preinscriptions-countdown',
        get_template_directory_uri() . '/assets/js/compte-rebours.js',
        array(),
        file_exists( $countdown_js ) ? filemtime( $countdown_js ) : PREINSCRIPTIONS_VERSION,
        true
    );
}
/**
 * Assets de la page « Guide de préinscription ».
 * Même schéma que preinscriptions_countdown_assets() : feuille autonome
 * préfixée « gp- », chargée sur ce seul template.
 */
function preinscriptions_guide_assets() {
    if ( ! is_page_template( 'page-guide-preinscription.php' ) ) {
        return;
    }

    $guide_css = get_template_directory() . '/assets/css/guide-preinscription.css';
    $guide_js  = get_template_directory() . '/assets/js/guide-preinscription.js';

    wp_enqueue_style(
        'preinscriptions-guide',
        get_template_directory_uri() . '/assets/css/guide-preinscription.css',
        array( 'preinscriptions-style' ),
        file_exists( $guide_css ) ? filemtime( $guide_css ) : PREINSCRIPTIONS_VERSION
    );

    wp_enqueue_script(
        'preinscriptions-guide',
        get_template_directory_uri() . '/assets/js/guide-preinscription.js',
        array(),
        file_exists( $guide_js ) ? filemtime( $guide_js ) : PREINSCRIPTIONS_VERSION,
        true
    );
}
add_action( 'wp_enqueue_scripts', 'preinscriptions_guide_assets' );

add_action( 'wp_enqueue_scripts', 'preinscriptions_countdown_assets' );
add_action( 'wp_enqueue_scripts', 'preinscriptions_admin_assets' );

/**
 * Assets de la page "Gestion des références" (page-references.php).
 * Complètement séparée de preinscriptions_admin_assets() ci-dessus :
 * autre template, autre capacité requise ('manage_options' — vrais
 * administrateurs WordPress — plutôt que 'voir_preinscriptions'), autre
 * nonce ('ueb_admin_references'). Réutilise la feuille de style du
 * dashboard (tokens de couleur + composants communs : boutons, tableau,
 * modale, états) pour ne pas dupliquer ~1600 lignes de CSS, et ajoute
 * juste le complément propre à cette page.
 */
function preinscriptions_references_assets() {
    if ( ! is_page_template( 'page-references.php' ) ) return;

    wp_enqueue_style( 'preinscriptions-admin', get_template_directory_uri() . '/assets/css/admin-dashboard.css', array( 'preinscriptions-style' ), PREINSCRIPTIONS_VERSION );
    wp_enqueue_style( 'preinscriptions-admin-references', get_template_directory_uri() . '/assets/css/admin-references.css', array( 'preinscriptions-admin' ), PREINSCRIPTIONS_VERSION );

    if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) return;

    wp_enqueue_script( 'preinscriptions-admin-references', get_template_directory_uri() . '/assets/js/admin-references.js', array(), PREINSCRIPTIONS_VERSION, true );

    wp_localize_script( 'preinscriptions-admin-references', 'uebAdminReferences', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'ueb_admin_references' ),
        'registry' => ueb_admin_ref_get_registry_for_js(),
    ) );
}
add_action( 'wp_enqueue_scripts', 'preinscriptions_references_assets' );

/**
 * Le back-office (dashboard des dossiers OU page de gestion des
 * références) occupe-t-il tout l'écran ?
 *
 * Vrai uniquement sur l'une de ces pages ET pour un compte autorisé sur
 * cette page précise : la navigation publique n'a pas de sens autour d'un
 * back-office (la sidebar/topbar porte déjà la navigation et la
 * déconnexion), mais l'écran de connexion, lui, reste une page du site et
 * garde son en-tête.
 *
 * Même principe que page-preinscription.php, qui masque déjà la barre.
 *
 * @return bool
 */
function ueb_est_dashboard_plein_ecran() {
    if ( is_page_template( 'page-administration.php' ) ) {
        return is_user_logged_in() && current_user_can( 'voir_preinscriptions' );
    }
    if ( is_page_template( 'page-references.php' ) ) {
        return is_user_logged_in() && current_user_can( 'manage_options' );
    }
    return false;
}

/**
 * Applique le thème clair/sombre mémorisé AVANT le premier rendu.
 *
 * Le thème CLAIR est le défaut du tableau de bord, quelle que soit la
 * préférence système : c'est un outil de gestion consulté en journée, dont
 * les documents et les listes se lisent comme du papier. Le thème sombre
 * reste disponible, mais uniquement sur choix explicite du bouton de
 * bascule — la préférence système n'est plus suivie.
 *
 * Doit rester un script bloquant inline dans le <head> : si l'attribut
 * data-ueb-theme n'est posé qu'au chargement de admin-dashboard.js (en
 * pied de page), la page s'affiche une fraction de seconde dans le mauvais
 * thème — un flash blanc très visible sur un dashboard sombre.
 *
 * Même clé localStorage ('ueb-admin-theme') que le dashboard des dossiers :
 * un même compte qui navigue entre les deux pages garde sa préférence.
 * thème.
 */
function preinscriptions_admin_theme_boot() {
    if ( ! is_page_template( 'page-administration.php' ) && ! is_page_template( 'page-references.php' ) ) return;
    ?>
    <script>
    (function () {
        var t = 'light';
        try {
            var memo = localStorage.getItem('ueb-admin-theme');
            if (memo === 'dark' || memo === 'light') t = memo;
        } catch (e) {
            /* localStorage indisponible (navigation privée stricte) :
               on reste sur le thème clair. */
        }
        document.documentElement.setAttribute('data-ueb-theme', t);
    }());
    </script>
    <?php
}
add_action( 'wp_head', 'preinscriptions_admin_theme_boot', 1 );

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

/* ── Création du rôle "Gestionnaire Préinscriptions" ── */
function ueb_register_roles() {
    add_role(
        'gestionnaire_preinscriptions',
        'Gestionnaire Préinscriptions',
        array(
            'read'                 => true,  // accès de base à l'admin WP
            'voir_preinscriptions' => true,
        )
    );

    // Donner aussi la capacité aux administrateurs
    $admin = get_role( 'administrator' );
    if ( $admin ) {
        $admin->add_cap( 'voir_preinscriptions' );
    }
}
add_action( 'after_switch_theme', 'ueb_register_roles' );

require_once( get_template_directory() . '/inc/db-functions.php' );
require_once( get_template_directory() . '/inc/pdf-functions.php' );
require_once( get_template_directory() . '/inc/guide-preinscription-content.php' );
require_once( get_template_directory() . '/inc/guide-pdf-functions.php' );
require_once( get_template_directory() . '/inc/db-schema.php' );
require_once( get_template_directory() . '/inc/dossier-functions.php' );
require_once( get_template_directory() . '/inc/ajax-functions.php' );
require_once( get_template_directory() . '/inc/db-seed.php' );
require_once( get_template_directory() . '/inc/admin-functions.php' );
require_once( get_template_directory() . '/inc/admin-ajax-functions.php' );
require_once( get_template_directory() . '/inc/admin-references-functions.php' );
require_once( get_template_directory() . '/inc/admin-references-ajax.php' );
require_once( get_template_directory() . '/inc/export-functions.php' );
require_once( get_template_directory() . '/inc/export-office.php' );
require_once( get_template_directory() . '/inc/social-medias-functions.php' );