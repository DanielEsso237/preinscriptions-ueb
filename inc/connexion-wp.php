<?php
/**
 * Connexion WordPress déplacée à /pw-ueb et habillée aux couleurs de l'Université.
 *
 * La page de connexion de WordPress ne répond plus qu'à /pw-ueb ; wp-login.php
 * et wp-admin, pour un visiteur non connecté, se comportent comme une adresse
 * inconnue au lieu de mener à la connexion. Le dossier wp-admin ne peut pas
 * être renommé (WordPress y renvoie en dur) : une fois connecté,
 * l'administrateur y travaille comme avant. admin-ajax.php et admin-post.php
 * restent ouverts (formulaires publics, portail de gestion).
 *
 * Le personnel continue de se connecter par le portail (?ueb_portal=login) ;
 * mot de passe oublié et réinitialisation y restent aussi (inc/access-portal.php).
 *
 * @package Preinscriptions_UEB
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const UEB_SLUG_CONNEXION_WP = 'pw-ueb';
const UEB_TITRE_ADMIN_WP    = 'Administration de l’UEB';

/** Adresse de la connexion WordPress. */
function ueb_url_connexion_wp( $requete = '' ) {
    $url = home_url( '/' . UEB_SLUG_CONNEXION_WP . '/' );
    return '' === $requete ? $url : $url . '?' . $requete;
}

/** Chemin demandé, sans le dossier du site ni la requête, sans « / » aux bouts. */
function ueb_chemin_demande() {
    $chemin = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH ); // phpcs:ignore
    $base   = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
    if ( '' !== $base && 0 === strpos( $chemin, $base ) ) {
        $chemin = substr( $chemin, strlen( $base ) );
    }
    return trim( rawurldecode( $chemin ), '/' );
}

/** La requête en cours est-elle la page /pw-ueb ? */
function ueb_est_connexion_wp() {
    return UEB_SLUG_CONNEXION_WP === ueb_chemin_demande();
}

/** Réponse d'une adresse inconnue (gabarit de repli du thème), sans rien révéler de WordPress. */
function ueb_repondre_introuvable() {
    global $wp_query;
    status_header( 404 );
    nocache_headers();
    if ( $wp_query instanceof WP_Query ) {
        $wp_query->set_404();
    }
    $gabarit = get_404_template() ?: get_index_template();
    if ( $gabarit ) {
        include $gabarit;
    }
    exit;
}

/** Remplace wp-login.php par l'adresse propre au site dans une URL produite par WordPress. */
function ueb_url_sans_wp_login( $url ) {
    if ( ! is_string( $url ) || false === strpos( $url, 'wp-login.php' ) ) {
        return $url;
    }
    $requete = (string) wp_parse_url( $url, PHP_URL_QUERY );
    return ueb_url_connexion_wp( $requete );
}
foreach ( array( 'site_url', 'network_site_url', 'wp_redirect', 'login_url', 'logout_url', 'lostpassword_url', 'register_url' ) as $ueb_filtre ) {
    add_filter( $ueb_filtre, 'ueb_url_sans_wp_login', 100 );
}
unset( $ueb_filtre );

/* /login, /admin, /dashboard ne renvoient plus vers la connexion. */
remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );

add_action( 'wp_loaded', function () {
    if ( 'cli' === PHP_SAPI || wp_doing_cron() || wp_doing_ajax() ) {
        return;
    }
    global $pagenow;

    /* /pw-ueb : la page de connexion de WordPress, servie à cette adresse. */
    if ( ueb_est_connexion_wp() ) {
        global $error, $interim_login, $action, $user_login, $user, $redirect_to; // variables lues par wp-login.php
        $pagenow = 'wp-login.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
        require_once ABSPATH . 'wp-login.php';
        exit;
    }

    /* wp-login.php appelé directement : adresse inconnue. */
    if ( 'wp-login.php' === $pagenow || preg_match( '#(^|/)wp-login\.php$#', ueb_chemin_demande() ) ) {
        ueb_repondre_introuvable();
    }

    /* wp-admin pour un visiteur non connecté : adresse inconnue, plutôt qu'un renvoi vers la connexion. */
    if ( is_admin() && ! is_user_logged_in() && 'admin-post.php' !== $pagenow ) {
        ueb_repondre_introuvable();
    }
}, 1 );

/* ---------- Habillage de la page /pw-ueb ---------- */

add_action( 'login_enqueue_scripts', function () {
    $dir = get_template_directory();
    $uri = get_template_directory_uri();
    wp_enqueue_style( 'preinscriptions-fonts', $uri . '/assets/css/fonts.css', array(), filemtime( $dir . '/assets/css/fonts.css' ) );
    wp_enqueue_style( 'ueb-connexion-wp', $uri . '/assets/css/connexion-wp.css', array( 'login', 'preinscriptions-fonts' ), filemtime( $dir . '/assets/css/connexion-wp.css' ) );
} );

/* Onglet du navigateur : « Connexion | Administration de l'UEB », sans « WordPress ». */
add_filter( 'login_title', function () {
    return 'Connexion | ' . UEB_TITRE_ADMIN_WP;
} );

/* Logo : celui de l'Université, qui mène au site (et non à wordpress.org). */
add_filter( 'login_headerurl', function () {
    return home_url( '/' );
} );
add_filter( 'login_headertext', function () {
    return 'Université d’Ebolowa';
} );

/* Bandeau du haut, à la manière de l'administration de Django. */
add_action( 'login_header', function () {
    printf(
        '<header class="ueb-pw-bandeau"><a href="%s">%s</a><span>Université d’Ebolowa</span></header>',
        esc_url( home_url( '/' ) ),
        esc_html( UEB_TITRE_ADMIN_WP )
    );
} );

/* Ni sélecteur de langue ni « ← Aller sur … ». */
add_filter( 'login_display_language_dropdown', '__return_false' );
add_filter( 'login_site_html_link', function () {
    return sprintf( '<a href="%s">Retour au site des préinscriptions</a>', esc_url( home_url( '/' ) ) );
} );
