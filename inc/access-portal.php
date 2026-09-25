<?php
/** Routes front, connexion et lectures agrégées du portail UEB. */
if ( ! defined( 'ABSPATH' ) ) exit;

function ueb_portal_route() {
    return sanitize_key( $_GET['ueb_portal'] ?? '' );
}
/**
 * Adresse d'une vue du portail.
 *
 * S'ancre sur la vraie page WordPress si la direction en a créé une avec le
 * modèle « Espace de gestion » (Pages > Ajouter) : tout le monde reçoit alors
 * une adresse normale, listée dans les pages du site, plutôt qu'un lien
 * construit à la main. Sans cette page, on retombe sur l'adresse du site
 * suivie de « ?ueb_portal=… », exactement comme avant — rien ne casse pour
 * une installation qui n'a pas encore créé la page.
 */
function ueb_portal_url( $view = '', $args = array() ) {
    // Ne PAS tronquer une éventuelle chaîne de requête sur $base : en
    // permaliens simples (réglage actuel de ce site), get_permalink() rend
    // déjà « ?page_id=25 ». add_query_arg() sait fusionner proprement avec
    // une chaîne de requête existante ; la tronquer perdrait la page ciblée.
    $base = ueb_access_template_url( 'templates/access-portal.php' ) ?: home_url( '/' );
    return add_query_arg( array_merge( array( 'ueb_portal' => $view ?: 'home' ), $args ), $base );
}

/**
 * Adresse de la page WordPress qui utilise ce modèle, si une existe.
 *
 * Mémoïsée par requête : appelée depuis ueb_portal_url() elle-même, donc
 * potentiellement plusieurs dizaines de fois sur une seule page (chaque lien
 * de la barre latérale, chaque carte d'établissement…). Sans ce cache,
 * chacun de ces appels relancerait une requête get_posts().
 *
 * @param string $template Chemin du fichier, relatif à la racine du thème
 *                          (ex. 'page-administration.php', ou
 *                          'templates/access-portal.php' pour un modèle
 *                          rangé dans un sous-dossier).
 * @return string URL de la page, ou repli sur la route interne.
 */
function ueb_access_template_url( $template ) {
    static $cache = array();
    if ( array_key_exists( $template, $cache ) ) return $cache[ $template ];
    $pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'meta_key' => '_wp_page_template', 'meta_value' => $template, 'numberposts' => 1 ) );
    if ( $pages ) return $cache[ $template ] = get_permalink( $pages[0] );
    $routes = array( 'page-administration.php' => 'administration', 'page-references.php' => 'references' );
    // Pas d'entrée pour templates/access-portal.php ici : sans page créée,
    // ueb_portal_url() applique déjà son propre repli sur home_url( '/' ).
    return $cache[ $template ] = ( isset( $routes[ $template ] ) ? add_query_arg( 'ueb_portal', $routes[ $template ], home_url( '/' ) ) : '' );
}
function ueb_access_is_admin_page() {
    return is_page_template( 'page-administration.php' ) || 'administration' === ueb_portal_route();
}
function ueb_access_is_ref_page() {
    return is_page_template( 'page-references.php' ) || 'references' === ueb_portal_route();
}
function ueb_portal_home() {
    if ( ueb_access_has( 'ueb_view_overview' ) ) return ueb_portal_url( 'overview' );
    if ( ueb_access_has( 'ueb_view_stats' ) ) return ueb_portal_url( 'establishment' );
    if ( ueb_access_has_admin() ) return ueb_access_template_url( 'page-administration.php' ) ?: ueb_portal_url( 'students' );
    foreach ( array( 'ueb_view_students' => 'students', 'ueb_export_students' => 'students', 'ueb_manage_roles' => 'roles', 'ueb_manage_users' => 'users', 'ueb_manage_establishments' => 'establishments' ) as $cap => $view ) if ( ueb_access_has( $cap ) ) return ueb_portal_url( $view );
    if ( ueb_access_has_refs() ) return ueb_access_template_url( 'page-references.php' ) ?: ueb_portal_url( 'empty' );
    return ueb_portal_url( 'empty' );
}

/** Limitation commune au login front et au login WordPress. L'adresse du
 * pair réseau est utilisée, jamais un X-Forwarded-For fourni par le client. */
function ueb_auth_keys( $login ) {
    return array( 'ueb_auth_ip_' . hash( 'sha256', $_SERVER['REMOTE_ADDR'] ?? 'local' ), 'ueb_auth_user_' . hash( 'sha256', strtolower( trim( $login ) ) ) );
}
add_filter( 'nonce_user_logged_out', function( $id, $action ) {
    if ( 'ueb_front_auth' !== $action ) return $id;
    if ( empty( $_SESSION['ueb_auth_nonce_id'] ) ) $_SESSION['ueb_auth_nonce_id'] = random_int( 1, PHP_INT_MAX );
    return $_SESSION['ueb_auth_nonce_id'];
}, 10, 2 );

// Les liens WordPress de connexion et de récupération restent dans le front.
add_action( 'login_init', function() {
    $action = sanitize_key( $_REQUEST['action'] ?? 'login' );
    if ( in_array( $action, array( 'login', 'lostpassword', 'retrievepassword', 'rp', 'resetpass' ), true ) ) {
        $view = in_array( $action, array( 'rp', 'resetpass' ), true ) ? 'reset' : ( 'login' === $action ? 'login' : 'forgot' );
        $args = 'reset' === $view ? array( 'key' => sanitize_text_field( wp_unslash( $_GET['key'] ?? '' ) ), 'login' => sanitize_text_field( wp_unslash( $_GET['login'] ?? '' ) ) ) : array();
        wp_safe_redirect( ueb_portal_url( $view, $args ) ); exit;
    }
} );
add_filter( 'authenticate', function( $user, $login, $password ) {
    if ( '' === $login || '' === $password ) return $user;
    $keys = ueb_auth_keys( $login );
    foreach ( $keys as $key ) if ( (int) get_transient( $key ) >= 5 ) return new WP_Error( 'ueb_locked', 'Connexion impossible. Réessayez plus tard.' );
    if ( is_wp_error( $user ) || ! $user ) {
        foreach ( $keys as $key ) set_transient( $key, (int) get_transient( $key ) + 1, 15 * MINUTE_IN_SECONDS );
        return new WP_Error( 'ueb_login', 'Connexion impossible. Vérifiez vos informations ou réessayez plus tard.' );
    }
    delete_transient( $keys[1] );
    return $user;
}, 100, 3 );

add_filter( 'retrieve_password_message', function( $message, $key, $login ) {
    return "Une demande de nouveau mot de passe a été reçue pour votre compte UEB.\n\n" . ueb_portal_url( 'reset', array( 'key' => $key, 'login' => $login ) ) . "\n\nSi vous n’êtes pas à l’origine de cette demande, ignorez ce message.";
}, 10, 3 );
add_filter( 'lostpassword_url', function() { return ueb_portal_url( 'forgot' ); } );
add_filter( 'login_redirect', function( $redirect, $requested, $user ) {
    if ( $user instanceof WP_User && ! user_can( $user, 'manage_options' ) ) {
        wp_set_current_user( $user->ID );
        return ueb_portal_home();
    }
    return $redirect;
}, 10, 3 );

add_filter( 'show_admin_bar', function( $show ) {
    return current_user_can( 'manage_options' ) && ! ueb_portal_route() ? $show : false;
} );
add_action( 'admin_init', function() {
    if ( ! wp_doing_ajax() && is_user_logged_in() && ! current_user_can( 'manage_options' ) ) {
        wp_safe_redirect( ueb_portal_home() ); exit;
    }
}, 1 );

add_action( 'template_redirect', function() {
    $route = ueb_portal_route();
    if ( ! $route && ( is_page_template( 'page-administration.php' ) || is_page_template( 'page-references.php' ) ) && ! is_user_logged_in() ) {
        wp_safe_redirect( ueb_portal_url( 'login' ) ); exit;
    }
    // Visite directe de la page « Espace de gestion » (Pages > Ajouter, modèle
    // du même nom), sans vue précisée : ce modèle ne sait rien afficher seul,
    // il faut toujours l'envoyer vers la connexion ou vers l'accueil qui
    // convient à son rôle — jamais rendre la coque du portail à vide.
    if ( ! $route && is_page_template( 'templates/access-portal.php' ) ) {
        wp_safe_redirect( is_user_logged_in() ? ueb_portal_home() : ueb_portal_url( 'login' ) ); exit;
    }
    if ( ! $route ) return;
    nocache_headers();
    header( 'X-Robots-Tag: noindex, nofollow', true );
    header( 'Referrer-Policy: no-referrer', true );
    if ( 'logout' === $route ) {
        if ( ! wp_verify_nonce( $_GET['nonce'] ?? '', 'ueb_logout' ) ) wp_die( 'Accès refusé.', '', array( 'response' => 403 ) );
        wp_logout(); wp_safe_redirect( ueb_portal_url( 'login' ) ); exit;
    }
    $public = in_array( $route, array( 'login', 'forgot', 'reset' ), true );
    if ( ! $public && ! is_user_logged_in() ) { wp_safe_redirect( ueb_portal_url( 'login' ) ); exit; }
    if ( 'home' === $route || ( 'login' === $route && is_user_logged_in() ) ) { wp_safe_redirect( ueb_portal_home() ); exit; }
    if ( $public && 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
        $message = '';
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'ueb_front_auth' ) ) $message = 'La session a expiré. Rechargez cette page.';
        elseif ( 'login' === $route ) {
            $user = wp_signon( array( 'user_login' => sanitize_text_field( wp_unslash( $_POST['login'] ?? '' ) ), 'user_password' => wp_unslash( $_POST['password'] ?? '' ), 'remember' => ! empty( $_POST['remember'] ) ), is_ssl() );
            if ( is_wp_error( $user ) ) $message = 'Connexion impossible. Vérifiez vos informations ou réessayez dans 15 minutes.';
            else { session_regenerate_id( true ); wp_set_current_user( $user->ID ); wp_safe_redirect( ueb_portal_home() ); exit; }
        } elseif ( 'forgot' === $route ) {
            $key = 'ueb_forgot_' . hash( 'sha256', $_SERVER['REMOTE_ADDR'] ?? 'local' );
            if ( (int) get_transient( $key ) < 3 ) {
                set_transient( $key, (int) get_transient( $key ) + 1, 15 * MINUTE_IN_SECONDS );
                retrieve_password( sanitize_text_field( wp_unslash( $_POST['login'] ?? '' ) ) );
            }
            $message = 'Si un compte correspond, un lien vous sera envoyé. Vérifiez votre boîte de réception.';
        } elseif ( 'reset' === $route ) {
            $user = check_password_reset_key( sanitize_text_field( wp_unslash( $_GET['key'] ?? '' ) ), sanitize_text_field( wp_unslash( $_GET['login'] ?? '' ) ) );
            $password = wp_unslash( $_POST['password'] ?? '' );
            if ( is_wp_error( $user ) ) $message = 'Ce lien est invalide ou a expiré. Demandez un nouveau lien.';
            elseif ( strlen( $password ) < 12 || $password !== wp_unslash( $_POST['confirm'] ?? '' ) ) $message = 'Saisissez deux mots de passe identiques d’au moins 12 caractères.';
            else { reset_password( $user, $password ); wp_safe_redirect( ueb_portal_url( 'login', array( 'changed' => 1 ) ) ); exit; }
        }
        $GLOBALS['ueb_auth_message'] = $message;
    }
    $map = array( 'overview' => 'ueb_view_overview', 'establishment' => 'ueb_view_stats', 'roles' => 'ueb_manage_roles', 'users' => 'ueb_manage_users', 'establishments' => 'ueb_manage_establishments' );
    if ( isset( $map[ $route ] ) && ! ueb_access_has( $map[ $route ] ) ) wp_die( 'Accès refusé.', '', array( 'response' => 403 ) );
    if ( 'students' === $route && ! ueb_access_has( 'ueb_view_students' ) && ! ueb_access_has( 'ueb_export_students' ) ) wp_die( 'Accès refusé.', '', array( 'response' => 403 ) );
    if ( 'references' === $route && ! ueb_access_has_refs() ) wp_die( 'Accès refusé.', '', array( 'response' => 403 ) );
    if ( 'administration' === $route && ! ueb_access_has_admin() ) wp_die( 'Accès refusé.', '', array( 'response' => 403 ) );
    if ( 'establishment' === $route && ! empty( $_GET['establishment'] ) && ! ueb_access_contains( 'ueb_view_stats', absint( $_GET['establishment'] ) ) ) wp_die( 'Accès refusé.', '', array( 'response' => 403 ) );
    if ( ! $public && ! isset( $map[ $route ] ) && ! in_array( $route, array( 'students', 'empty', 'references', 'administration' ), true ) ) wp_die( 'Page introuvable.', '', array( 'response' => 404 ) );
}, 0 );

add_filter( 'template_include', function( $template ) {
    if ( 'references' === ueb_portal_route() ) return get_template_directory() . '/page-references.php';
    if ( 'administration' === ueb_portal_route() ) return get_template_directory() . '/page-administration.php';
    return ueb_portal_route() ? get_template_directory() . '/templates/access-portal.php' : $template;
} );
add_filter( 'document_title_parts', function( $parts ) {
    if ( ueb_portal_route() ) { $parts['title'] = 'Espace de gestion'; $parts['tagline'] = 'Université d’Ébolowa'; }
    return $parts;
}, 20 );
add_action( 'wp_enqueue_scripts', function() {
    if ( ! ueb_portal_route() || in_array( ueb_portal_route(), array( 'references', 'administration' ), true ) ) return;
    wp_enqueue_style( 'preinscriptions-admin', get_template_directory_uri() . '/assets/css/admin-dashboard.css', array( 'preinscriptions-style' ), filemtime( get_template_directory() . '/assets/css/admin-dashboard.css' ) );
    wp_enqueue_style( 'ueb-portal', get_template_directory_uri() . '/assets/css/access-portal.css', array( 'preinscriptions-admin' ), filemtime( get_template_directory() . '/assets/css/access-portal.css' ) );
    wp_enqueue_script( 'chartjs', get_template_directory_uri() . '/assets/js/vendor/chart.umd.min.js', array(), '4.4.0', true );
    wp_enqueue_script( 'ueb-portal', get_template_directory_uri() . '/assets/js/access-portal.js', array( 'chartjs' ), filemtime( get_template_directory() . '/assets/js/access-portal.js' ), true );
    $data = array( 'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'ueb_access' ), 'dataNonce' => wp_create_nonce( 'ueb_admin_dashboard' ), 'route' => ueb_portal_route(), 'home' => home_url( '/' ) );
    wp_localize_script( 'ueb-portal', 'uebPortal', $data );
}, 30 );

/**
 * Sigles et noms des établissements ouverts aux candidatures.
 *
 * Sert uniquement de décor à l'écran de connexion. Ce sont les mêmes
 * établissements que ceux proposés au public dans le formulaire de
 * préinscription : aucune information non publique n'est exposée avant
 * authentification, et surtout aucun effectif.
 *
 * @param int $limite Nombre maximum d'établissements affichés.
 * @return array[] { code, nom_fr }
 */
function ueb_portal_public_network( $limite = 8 ) {
    global $wpdb;
    return $wpdb->get_results( $wpdb->prepare(
        "SELECT code, nom_fr FROM ueb_facultes WHERE actif = 1 ORDER BY nom_fr LIMIT %d",
        max( 1, (int) $limite )
    ), ARRAY_A ) ?: array();
}

/**
 * Établissement consulté, tel qu'affiché dans l'en-tête.
 *
 * @param int $id Identifiant d'établissement, 0 pour la portée globale.
 * @return array|null Ligne de ueb_facultes limitée à la portée, ou null.
 */
function ueb_portal_establishment_row( $id ) {
    foreach ( ueb_access_establishments( 'ueb_view_stats' ) as $row ) {
        if ( (int) $row['id'] === (int) $id ) return $row;
    }
    return null;
}

/**
 * Nom lisible de l'établissement consulté, pour l'en-tête.
 *
 * @param int $id Identifiant d'établissement, 0 pour la portée globale.
 * @return string
 */
function ueb_portal_establishment_name( $id ) {
    $row = ueb_portal_establishment_row( $id );
    return $row ? $row['nom_fr'] : '';
}

function ueb_portal_stats( $overview, $id = 0 ) {
    global $wpdb;
    $caps = array( $overview ? 'ueb_view_overview' : 'ueb_view_stats' );
    if ( ! ueb_access_has( $caps ) || ( $id && ! ueb_access_contains( $caps, $id ) ) ) return new WP_Error( 'denied', 'Accès refusé.' );
    $establishments = ueb_access_establishments( $caps );
    if ( ! $overview ) {
        if ( ! $id && $establishments ) $id = (int) $establishments[0]['id'];
        $establishments = array_values( array_filter( $establishments, function( $row ) use ( $id ) { return (int) $row['id'] === $id; } ) );
        if ( ! $establishments ) return new WP_Error( 'missing', 'Établissement introuvable.' );
    }
    $scope = ueb_stats_population_sql() . ' AND ' . ueb_access_sql( 'p.faculte_id', $caps );
    if ( ! $overview ) $scope .= $wpdb->prepare( ' AND p.faculte_id = %d', $id );
    $today = current_datetime()->setTime( 0, 0 );
    $week = $today->modify( 'monday this week' );
    $previous = $week->modify( '-7 days' );
    // Compare les mêmes jours écoulés de la semaine précédente.
    $previous_end = current_datetime()->modify( '-7 days' );
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT p.faculte_id, COUNT(*) total,
        SUM(p.date_creation >= %s) today, SUM(p.date_creation >= %s) week,
        SUM(p.date_creation >= %s) month,
        SUM(p.date_creation >= %s AND p.date_creation <= %s) previous
        FROM ueb_preinscriptions p WHERE {$scope} GROUP BY p.faculte_id",
        $today->format( 'Y-m-d H:i:s' ), $week->format( 'Y-m-d H:i:s' ), $today->format( 'Y-m-01 00:00:00' ), $previous->format( 'Y-m-d H:i:s' ), $previous_end->format( 'Y-m-d H:i:s' )
    ), ARRAY_A );
    // 'previous' alimente la variation globale affichée en tête : sans lui,
    // le JS devrait la recomposer établissement par établissement et manquerait
    // les dossiers sans établissement renseigné.
    $totals = array( 'total' => 0, 'today' => 0, 'week' => 0, 'month' => 0, 'previous' => 0 );
    $indexed = array();
    foreach ( $rows as $row ) { $indexed[ (int) $row['faculte_id'] ] = $row; foreach ( $totals as $key => $value ) $totals[ $key ] += (int) $row[ $key ]; }
    $trends = array();
    $trend_caps = array_merge( $caps, array( 'ueb_view_trends' ) );
    $can_trend = ueb_access_has( $trend_caps ) && ( $overview || ueb_access_contains( $trend_caps, $id ) );
    if ( $can_trend ) {
        $trend_scope = ueb_access_sql( 'p.faculte_id', $trend_caps );
        $trends = $wpdb->get_results( $wpdb->prepare( "SELECT p.faculte_id, DATE(p.date_creation) day, COUNT(*) total FROM ueb_preinscriptions p WHERE {$scope} AND {$trend_scope} AND p.date_creation >= %s GROUP BY p.faculte_id, DATE(p.date_creation)", $today->modify( '-29 days' )->format( 'Y-m-d H:i:s' ) ), ARRAY_A );
    }
    $labels = array();
    for ( $i = 29; $i >= 0; $i-- ) $labels[] = $today->modify( "-{$i} days" )->format( 'Y-m-d' );
    $series = array();
    foreach ( $trends as $row ) $series[ (int) $row['faculte_id'] ][ $row['day'] ] = (int) $row['total'];
    $global = array_fill( 0, 30, 0 );
    foreach ( $series as $days ) foreach ( $labels as $i => $day ) $global[ $i ] += $days[ $day ] ?? 0;
    foreach ( $establishments as &$row ) {
        $count = $indexed[ (int) $row['id'] ] ?? array();
        foreach ( array( 'total', 'today', 'week', 'month', 'previous' ) as $key ) $row[ $key ] = (int) ( $count[ $key ] ?? 0 );
        $row['variation'] = $row['previous'] ? round( 100 * ( $row['week'] - $row['previous'] ) / $row['previous'] ) : null;
        $row['series'] = ueb_access_contains( 'ueb_view_trends', $row['id'] ) ? array_map( function( $day ) use ( $series, $row ) { return $series[ (int) $row['id'] ][ $day ] ?? 0; }, $labels ) : null;
        $row['url'] = ueb_access_has( 'ueb_view_stats' ) && ueb_access_contains( 'ueb_view_stats', $row['id'] ) ? ueb_portal_url( 'establishment', array( 'establishment' => $row['id'] ) ) : '';
        // Le nom de fichier brut ne sort pas de PHP : seule l'URL résolue part
        // au navigateur, et elle est vide si le fichier n'existe pas.
        $row['logo_url'] = preinscriptions_logo_etablissement( $row['logo'] ?? '' );
        unset( $row['logo'] );
    }
    unset( $row );
    $breakdown = array();
    if ( ! $overview ) {
        $breakdown['filieres'] = $wpdb->get_results( "SELECT COALESCE(f.libelle, 'Non renseignée') label, COUNT(*) total FROM ueb_preinscriptions p LEFT JOIN ueb_filieres f ON f.id = p.filiere_1_id AND f.faculte_id = p.faculte_id WHERE {$scope} GROUP BY f.id, f.libelle ORDER BY total DESC", ARRAY_A );
        $breakdown['statuts'] = $wpdb->get_results( "SELECT p.statut label, COUNT(*) total FROM ueb_preinscriptions p WHERE {$scope} GROUP BY p.statut", ARRAY_A );
    }
    return array( 'totals' => $totals, 'establishments' => $establishments, 'labels' => $labels, 'evolution' => $can_trend ? $global : null, 'breakdown' => $breakdown, 'unassigned' => (int) ( $indexed[0]['total'] ?? 0 ) );
}
add_action( 'wp_ajax_ueb_portal_stats', function() {
    $overview = 'overview' === ( $_POST['view'] ?? '' );
    ueb_access_require( $overview ? 'ueb_view_overview' : 'ueb_view_stats' );
    $result = ueb_portal_stats( $overview, absint( $_POST['establishment'] ?? 0 ) );
    if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 403 );
    wp_send_json_success( $result );
} );

add_action( 'wp_ajax_ueb_portal_establishment_save', function() {
    ueb_access_require( 'ueb_manage_establishments' );
    $id = absint( $_POST['id'] ?? 0 );
    $old = $id ? ueb_admin_ref_get( 'facultes', $id ) : array();
    if ( $id && ! $old ) wp_send_json_error( array( 'message' => 'Établissement inaccessible.' ), 403 );
    $raw = array_merge( $old ?: array(), array_intersect_key( $_POST, array_flip( array( 'nom_fr', 'nom_en', 'code', 'slug', 'actif' ) ) ) );
    if ( empty( $raw['nom_en'] ) ) $raw['nom_en'] = $raw['nom_fr'] ?? '';
    $result = $id ? ueb_admin_ref_update( 'facultes', $id, $raw ) : ueb_admin_ref_create( 'facultes', $raw );
    if ( ! $result['success'] ) wp_send_json_error( array( 'message' => $result['message'] ), 400 );
    wp_send_json_success( array( 'message' => 'Établissement enregistré.' ) );
} );
