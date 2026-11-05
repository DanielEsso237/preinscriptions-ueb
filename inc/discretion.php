<?php
/**
 * Discrétion et durcissement : le site ne laisse pas voir qu'il tourne sous
 * WordPress, et ferme ce que WordPress ouvre par défaut.
 *
 *   - adresses neutres des fichiers : /ressources/… pour le thème, /fichiers/…
 *     pour les téléversements, dès que le .htaccess racine porte le bloc
 *     « UEB discretion » (docs/serveur-discretion.md) ;
 *   - en-tête de page sans générateur, liens de l'API, oEmbed, RSD, émojis,
 *     styles de blocs ni classes WordPress ;
 *   - API REST, XML-RPC, flux, sitemap et fichiers publics du cœur : page 404
 *     pour un visiteur non connecté ;
 *   - identifiants des comptes cachés (API users, ?author=, /author/…) ;
 *   - éditeur de fichiers de wp-admin coupé (comme DISALLOW_FILE_EDIT) ;
 *   - cookie de session et en-têtes de sécurité.
 *
 * Chargé en premier par functions.php : le filtre des adresses du thème doit
 * être en place avant tout appel à get_template_directory_uri().
 * ueb_chemin_demande() et ueb_repondre_introuvable() viennent de
 * inc/connexion-wp.php ; elles ne servent qu'à l'exécution des crochets.
 *
 * @package Preinscriptions_UEB
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ---------- Adresses neutres des fichiers ---------- */

/** Repère du bloc à placer dans le .htaccess racine (docs/serveur-discretion.md). */
const UEB_REPERE_HTACCESS = '# BEGIN UEB discretion';

/**
 * Vrai si le .htaccess racine porte le bloc qui sert /ressources/ et /fichiers/.
 * Sans lui, les adresses restent celles de WordPress : rien ne casse si le
 * thème est déployé avant le fichier du serveur.
 */
function ueb_adresses_neutres() {
    static $actif = null;
    if ( null === $actif ) {
        $fichier = ABSPATH . '.htaccess';
        $actif   = is_readable( $fichier ) && false !== strpos( (string) file_get_contents( $fichier ), UEB_REPERE_HTACCESS ); // phpcs:ignore WordPress.WP.AlternativeFunctions
    }
    return $actif;
}

/* /wp-content/themes/preinscriptions-ueb/… → /ressources/… */
add_filter( 'template_directory_uri', function ( $url ) {
    return ueb_adresses_neutres() ? home_url( '/ressources' ) : $url;
} );
add_filter( 'stylesheet_directory_uri', function ( $url ) {
    return ueb_adresses_neutres() ? home_url( '/ressources' ) : $url;
} );

/* /wp-content/uploads/… → /fichiers/… */
add_filter( 'upload_dir', function ( $dossiers ) {
    if ( ueb_adresses_neutres() && ! empty( $dossiers['baseurl'] ) ) {
        $ancienne            = $dossiers['baseurl'];
        $dossiers['baseurl'] = home_url( '/fichiers' );
        $dossiers['url']     = str_replace( $ancienne, $dossiers['baseurl'], $dossiers['url'] );
    }
    return $dossiers;
} );

/* ---------- En-tête des pages ---------- */

/* Générateur (« WordPress 7.1.4 ») : ni dans les pages, ni dans les flux. */
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

/* Liens qui annoncent WordPress : API REST, oEmbed, RSD, Windows Live Writer, lien court, flux. */
remove_action( 'wp_head', 'rest_output_link_wp_head' );
remove_action( 'template_redirect', 'rest_output_link_header', 11 );
remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
remove_action( 'wp_head', 'wp_oembed_add_host_js' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
remove_action( 'wp_head', 'feed_links', 2 );
remove_action( 'wp_head', 'feed_links_extra', 3 );

/* Émojis : script et styles de WordPress. */
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

/* Styles des blocs : le thème n'utilise pas l'éditeur de blocs. */
add_action( 'wp_enqueue_scripts', function () {
    foreach ( array( 'wp-block-library', 'wp-block-library-theme', 'classic-theme-styles', 'global-styles', 'core-block-supports' ) as $poignee ) {
        wp_dequeue_style( $poignee );
        wp_deregister_style( $poignee );
    }
}, 100 );
remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
add_filter( 'wp_img_tag_add_auto_sizes', '__return_false' ); // style « wp-img-auto-sizes-contain »
add_filter( 'should_load_separate_core_block_assets', '__return_false' );

/* Règles de préchargement (<script type="speculationrules">), qui listent
   wp-admin, wp-content et wp-*.php. */
add_filter( 'wp_speculation_rules_configuration', '__return_null' );

/* Balises <link> et <script> : sans l'attribut data-wp-strategy ni les
   identifiants « …-css » / « …-js » typiques de WordPress. */
add_filter( 'style_loader_tag', function ( $balise, $poignee ) {
    return str_replace( " id='" . $poignee . "-css'", '', str_replace( ' id="' . $poignee . '-css"', '', $balise ) );
}, 10, 2 );
add_filter( 'script_loader_tag', function ( $balise, $poignee ) {
    $balise = preg_replace( '/\sdata-wp-strategy="[^"]*"/', '', $balise );
    return str_replace( array( ' id="' . $poignee . '-js"', " id='" . $poignee . "-js'" ), '', $balise );
}, 10, 2 );

/* Numéro de version de WordPress sur les fichiers du cœur : retiré (ceux du
   thème portent leur date de modification, sans rapport avec WordPress). */
$ueb_sans_version = function ( $src ) {
    return is_string( $src ) && str_contains( $src, 'ver=' . get_bloginfo( 'version' ) ) ? remove_query_arg( 'ver', $src ) : $src;
};
add_filter( 'style_loader_src', $ueb_sans_version, 100 );
add_filter( 'script_loader_src', $ueb_sans_version, 100 );
unset( $ueb_sans_version );

/* Classes de <body> : seulement celles du thème, plus « home » dont la
   landing a besoin (assets/css/landing-page.css) ; sans « page-template… »,
   « page-id-… », « wp-theme-… », « logged-in »… */
add_filter( 'body_class', function ( $classes, $propres ) {
    $gardees = array_values( array_filter( (array) $propres ) );
    if ( in_array( 'home', (array) $classes, true ) ) {
        $gardees[] = 'home';
    }
    if ( in_array( 'error404', (array) $classes, true ) ) {
        $gardees[] = 'page-introuvable';
    }
    return $gardees;
}, 100, 2 );

/* ---------- Portes de WordPress fermées aux visiteurs ---------- */

/* Flux RSS / Atom : le site n'en publie pas. */
foreach ( array( 'do_feed', 'do_feed_rdf', 'do_feed_rss', 'do_feed_rss2', 'do_feed_atom', 'do_feed_rss2_comments', 'do_feed_atom_comments' ) as $ueb_flux ) {
    add_action( $ueb_flux, 'ueb_repondre_introuvable', 1 );
}
unset( $ueb_flux );

/* Sitemap de WordPress (/wp-sitemap.xml) : coupé, l'adresse tombe en 404. */
add_filter( 'wp_sitemaps_enabled', '__return_false' );

/* XML-RPC : le site n'en a pas l'usage, et il permet d'essayer des milliers
   de mots de passe en une requête. */
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', '__return_empty_array' );
add_filter( 'wp_headers', function ( $entetes ) {
    unset( $entetes['X-Pingback'] );
    return $entetes;
} );

/* API REST, XML-RPC, fichiers publics du cœur : 404 pour un visiteur non
   connecté. Connecté (wp-admin, éditeur), l'API reste disponible. Le thème
   n'utilise pas l'API REST (formulaire et portail passent par admin-ajax.php). */
add_action( 'wp_loaded', function () {
    if ( 'cli' === PHP_SAPI || wp_doing_cron() || wp_doing_ajax() || is_user_logged_in() ) {
        return;
    }
    $chemin = ueb_chemin_demande();
    $rest   = 'wp-json' === $chemin || str_starts_with( $chemin, 'wp-json/' ) || isset( $_GET['rest_route'] ); // phpcs:ignore WordPress.Security.NonceVerification
    $coeur  = ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST )
        || (bool) preg_match( '#(^|/)(xmlrpc|wp-links-opml|wp-trackback|wp-signup|wp-activate|wp-mail|wp-comments-post)\.php$#', $chemin );
    if ( $rest || $coeur ) {
        ueb_repondre_introuvable();
    }
}, 2 );

/* ---------- Identifiants des comptes ---------- */

/* La liste des comptes WordPress (/wp-json/wp/v2/users) n'est lisible que connecté. */
add_filter( 'rest_endpoints', function ( $routes ) {
    if ( ! is_user_logged_in() ) {
        foreach ( array_keys( $routes ) as $route ) {
            if ( str_starts_with( $route, '/wp/v2/users' ) ) {
                unset( $routes[ $route ] );
            }
        }
    }
    return $routes;
} );

/* ?author=1 et /author/… donnent l'identifiant de connexion : page 404. */
add_action( 'template_redirect', function () {
    if ( is_author() || isset( $_GET['author'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
        ueb_repondre_introuvable();
    }
}, 1 );
add_filter( 'redirect_canonical', function ( $url ) {
    return isset( $_GET['author'] ) ? false : $url; // phpcs:ignore WordPress.Security.NonceVerification
} );

add_filter( 'oembed_response_data', function ( $donnees ) {
    unset( $donnees['author_name'], $donnees['author_url'] );
    return $donnees;
} );

add_filter( 'wp_sitemaps_add_provider', function ( $fournisseur, $nom ) {
    return 'users' === $nom ? false : $fournisseur;
}, 10, 2 );

/* ---------- wp-admin ---------- */

/* Éditeur de fichiers (thèmes, extensions) coupé, même sans DISALLOW_FILE_EDIT
   dans wp-config.php : un compte administrateur volé ne peut pas écrire de PHP
   sur le serveur depuis le navigateur. */
add_filter( 'map_meta_cap', function ( $caps, $cap ) {
    return in_array( $cap, array( 'edit_themes', 'edit_plugins', 'edit_files' ), true ) ? array( 'do_not_allow' ) : $caps;
}, 10, 2 );

/* ---------- Session et en-têtes ---------- */

/* Cookie de session PHP (numéro de dossier en cours) : illisible par le
   JavaScript, envoyé seulement en HTTPS quand le site l'est, pas aux sites tiers. */
if ( PHP_SESSION_NONE === session_status() && ! headers_sent() ) {
    session_set_cookie_params( array(
        'lifetime' => 0,
        'path'     => COOKIEPATH ?: '/',
        'secure'   => is_ssl(),
        'httponly' => true,
        'samesite' => 'Lax',
    ) );
}

/* En-têtes de sécurité : pas d'affichage du site dans un cadre d'un autre
   site, pas de devinette du type des fichiers, adresse d'origine limitée. */
add_action( 'send_headers', function () {
    header( 'X-Content-Type-Options: nosniff' );
    header( 'X-Frame-Options: SAMEORIGIN' );
    header( 'Referrer-Policy: strict-origin-when-cross-origin' );
    header_remove( 'X-Powered-By' );
    if ( is_ssl() ) {
        header( 'Strict-Transport-Security: max-age=15552000' );
    }
} );
