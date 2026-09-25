<?php
/** Permissions métier et portées. Aucun intitulé de rôle ne décide d'un accès. */
if ( ! defined( 'ABSPATH' ) ) exit;

function ueb_access_catalogue() {
    $caps = array(
        'ueb_view_overview' => array( 'Vues et statistiques', "Voir la vue d’ensemble" ),
        'ueb_view_stats' => array( 'Vues et statistiques', 'Voir les statistiques d’un établissement' ),
        'ueb_view_trends' => array( 'Vues et statistiques', 'Voir les courbes d’évolution' ),
        'ueb_view_students' => array( 'Données', 'Consulter les dossiers' ),
        'ueb_export_students' => array( 'Données', 'Exporter les dossiers' ),
        'ueb_view_duplicates' => array( 'Doublons', 'Voir les doublons' ),
        'ueb_manage_duplicates' => array( 'Doublons', 'Désactiver / réactiver les doublons' ),
        'ueb_configure_duplicates' => array( 'Doublons', 'Configurer la détection' ),
        'ueb_section_stats' => array( 'Espace admin existant', 'Ouvrir les statistiques détaillées' ),
        'ueb_section_effectifs' => array( 'Espace admin existant', 'Ouvrir les effectifs' ),
        'ueb_section_dossiers' => array( 'Espace admin existant', 'Ouvrir la section dossiers' ),
        'ueb_manage_establishments' => array( 'Administration', 'Gérer les établissements' ),
        'ueb_manage_roles' => array( 'Administration', 'Gérer les rôles' ),
        'ueb_manage_users' => array( 'Administration', 'Gérer les comptes utilisateurs' ),
    );
    foreach ( ueb_admin_ref_registry() as $key => $cfg ) {
        if ( 'facultes' === $key ) continue;
        $global = empty( $cfg['columns']['faculte_id'] );
        $caps[ 'ueb_ref_' . $key ] = array( 'Référentiels', $cfg['label'] . ( $global ? ' · portée tous requise' : '' ) );
    }
    return $caps;
}

function ueb_access_roles() {
    return (array) get_option( 'ueb_access_roles', array() );
}

/** Frontière technique WP : l'administrateur existant conserve ses accès,
 * sans modifier son rôle ni attribuer manage_options à un rôle métier. */
add_filter( 'user_has_cap', function( $allcaps ) {
    if ( ! empty( $allcaps['manage_options'] ) ) {
        foreach ( ueb_access_catalogue() as $cap => $label ) $allcaps[ $cap ] = true;
    }
    return $allcaps;
} );

/** Génération du cache de portées. Incrémentée après toute écriture de
 * rôle : dans la même requête, un scope déjà calculé deviendrait faux. */
function ueb_access_cache_generation( $invalider = false ) {
    static $generation = 0;
    if ( $invalider ) $generation++;
    return $generation;
}

/** null = tous ; [] = aucun. Union PAR capability, puis intersection
 * des capabilities requises : deux rôles ne peuvent croiser leurs droits.
 *
 * Mémoïsé par requête : le portail interroge la portée des dizaines de fois
 * (une par entrée de menu, une par établissement affiché, une par colonne de
 * tableau). Sans cache, chaque appel rejoue autant de user_can() qu'il y a
 * de rôles métier déclarés. */
function ueb_access_scope( $caps, $user = null ) {
    $user = $user ?: wp_get_current_user();
    static $cache = array();
    $signature = ueb_access_cache_generation() . '|' . $user->ID . '|' . implode( ',', (array) $caps );
    if ( array_key_exists( $signature, $cache ) ) return $cache[ $signature ];
    if ( ! $user->exists() ) return $cache[ $signature ] = array();
    if ( user_can( $user, 'manage_options' ) ) return $cache[ $signature ] = null;
    $result = null;
    foreach ( (array) $caps as $cap ) {
        if ( ! user_can( $user, $cap ) ) return $cache[ $signature ] = array();
        $scope = array();
        foreach ( ueb_access_roles() as $key => $role ) {
            if ( ! in_array( $key, $user->roles, true ) || ! in_array( $cap, $role['permissions'], true ) ) continue;
            if ( 'all' === $role['scope'] ) { $scope = null; break; }
            $scope = array_merge( $scope, array_map( 'intval', $role['establishments'] ) );
        }
        if ( null !== $scope ) $result = null === $result ? array_unique( $scope ) : array_intersect( $result, $scope );
    }
    return $cache[ $signature ] = ( null === $result ? null : array_values( $result ) );
}

function ueb_access_has( $caps ) {
    foreach ( (array) $caps as $cap ) if ( ! current_user_can( $cap ) ) return false;
    // Un seul calcul de portée : « aucun établissement » vaut refus.
    $scope = ueb_access_scope( $caps );
    return null === $scope || count( $scope ) > 0;
}

function ueb_access_contains( $caps, $id ) {
    $scope = ueb_access_scope( $caps );
    return null === $scope || in_array( (int) $id, $scope, true );
}

/** Colonne fournie par le code exclusivement ; valeurs préparées. */
function ueb_access_sql( $column, $caps ) {
    global $wpdb;
    $scope = ueb_access_scope( $caps );
    if ( null === $scope ) return '1=1';
    if ( ! $scope ) return '1=0';
    return $wpdb->prepare( $column . ' IN (' . implode( ',', array_fill( 0, count( $scope ), '%d' ) ) . ')', $scope );
}

function ueb_access_endpoint_caps( $action = null ) {
    $action = $action ?? sanitize_key( $_REQUEST['action'] ?? '' );
    $map = array(
        'ueb_admin_get_stats' => array( 'ueb_section_stats', 'ueb_view_stats' ),
        'ueb_admin_get_effectifs' => array( 'ueb_section_effectifs', 'ueb_view_stats' ),
        'ueb_admin_get_dossiers' => array( 'ueb_view_students' ),
        'ueb_admin_get_dossier_detail' => array( 'ueb_view_students' ),
        'ueb_admin_export' => array( 'ueb_export_students' ),
        'ueb_admin_export_csv' => array( 'ueb_export_students' ),
    );
    return $map[ $action ] ?? array( 'ueb_view_students' );
}

/** Options communes des filtres : union des portées auxquelles le compte
 * a déjà accès, sans donner accès à un dossier ou à une statistique. */
function ueb_access_filter_sql( $column ) {
    $clauses = array();
    foreach ( array( 'ueb_view_stats', 'ueb_view_students', 'ueb_export_students' ) as $cap ) {
        if ( current_user_can( $cap ) ) $clauses[] = '(' . ueb_access_sql( $column, $cap ) . ')';
    }
    return $clauses ? '(' . implode( ' OR ', $clauses ) . ')' : '1=0';
}

function ueb_access_require( $caps, $nonce = 'ueb_access' ) {
    if ( ! is_user_logged_in() || ! ueb_access_has( $caps ) ) wp_send_json_error( array( 'message' => 'Accès refusé.' ), 403 );
    check_ajax_referer( $nonce, 'nonce' );
    nocache_headers();
}

function ueb_access_establishments( $caps ) {
    global $wpdb;
    $where = ueb_access_sql( 'id', $caps );
    return $wpdb->get_results( "SELECT id, code, nom_fr, nom_en, slug, actif, logo FROM ueb_facultes WHERE {$where} ORDER BY nom_fr", ARRAY_A );
}

function ueb_access_ref_cap( $key ) {
    return 'facultes' === $key ? 'ueb_manage_establishments' : 'ueb_ref_' . $key;
}

function ueb_access_ref_allowed( $key ) {
    $registry = ueb_admin_ref_registry();
    if ( ! isset( $registry[ $key ] ) ) return false;
    $cap = ueb_access_ref_cap( $key );
    if ( ! ueb_access_has( $cap ) ) return false;
    // Les dictionnaires communs n'ont pas de propriétaire établissement.
    return 'facultes' === $key || isset( $registry[ $key ]['columns']['faculte_id'] ) || null === ueb_access_scope( $cap );
}

function ueb_access_has_refs() {
    foreach ( ueb_admin_ref_registry() as $key => $cfg ) if ( ueb_access_ref_allowed( $key ) ) return true;
    return false;
}

function ueb_access_ref_where( $key, $prefix = '' ) {
    if ( ! ueb_access_ref_allowed( $key ) ) return '1=0';
    $cfg = ueb_admin_ref_registry()[ $key ];
    if ( 'facultes' === $key ) return ueb_access_sql( $prefix . 'id', ueb_access_ref_cap( $key ) );
    if ( isset( $cfg['columns']['faculte_id'] ) ) return ueb_access_sql( $prefix . 'faculte_id', ueb_access_ref_cap( $key ) );
    return '1=1';
}

function ueb_access_has_admin() {
    return ueb_access_has( array( 'ueb_section_stats', 'ueb_view_stats' ) ) || ueb_access_has( array( 'ueb_section_effectifs', 'ueb_view_stats' ) ) || ueb_access_has( array( 'ueb_section_dossiers', 'ueb_view_students' ) );
}

function ueb_access_role_label() {
    $labels = array();
    foreach ( wp_get_current_user()->roles as $key ) {
        if ( isset( wp_roles()->roles[ $key ] ) ) $labels[] = translate_user_role( wp_roles()->roles[ $key ]['name'] );
    }
    return implode( ' · ', $labels );
}

/** Migration additive, rejouable ; les rôles métier existants sont détectés
 * par leur capability historique, pas par un nom de rôle particulier. */
function ueb_access_migrate() {
    if ( '2' === get_option( 'ueb_access_version' ) ) return;
    global $wpdb;
    if ( ! $wpdb->get_var( "SHOW TABLES LIKE 'ueb_facultes'" ) ) return;
    if ( ! ueb_add_column_if_missing( 'ueb_facultes', 'slug', "VARCHAR(180) NOT NULL DEFAULT ''" ) ||
         ! ueb_add_column_if_missing( 'ueb_facultes', 'actif', 'TINYINT(1) NOT NULL DEFAULT 1' ) ) return;
    foreach ( $wpdb->get_results( "SELECT id, code FROM ueb_facultes WHERE slug = ''" ) as $row ) {
        $wpdb->update( 'ueb_facultes', array( 'slug' => sanitize_title( $row->code ) . '-' . $row->id ), array( 'id' => $row->id ) );
    }
    if ( ! $wpdb->get_var( "SHOW INDEX FROM ueb_facultes WHERE Key_name = 'ueb_slug'" ) ) {
        if ( false === $wpdb->query( 'ALTER TABLE ueb_facultes ADD UNIQUE KEY ueb_slug (slug)' ) ) return;
    }
    if ( ! $wpdb->get_var( "SHOW INDEX FROM ueb_preinscriptions WHERE Key_name = 'ueb_scope_date'" ) ) {
        if ( false === $wpdb->query( 'ALTER TABLE ueb_preinscriptions ADD INDEX ueb_scope_date (faculte_id, date_creation)' ) ) return;
    }
    $roles = ueb_access_roles();
    $legacy_caps = array( 'ueb_view_overview', 'ueb_view_stats', 'ueb_view_trends', 'ueb_view_students', 'ueb_export_students', 'ueb_section_stats', 'ueb_section_effectifs', 'ueb_section_dossiers' );
    foreach ( wp_roles()->roles as $key => $role ) {
        if ( empty( $role['capabilities']['voir_preinscriptions'] ) || ! empty( $role['capabilities']['manage_options'] ) || isset( $roles[ $key ] ) ) continue;
        // Ne jamais rendre un rôle système éditable par la direction.
        $extra = array_diff( array_keys( array_filter( $role['capabilities'] ) ), array( 'read', 'voir_preinscriptions' ) );
        if ( $extra ) continue;
        $roles[ $key ] = array( 'name' => $role['name'], 'scope' => 'all', 'establishments' => array(), 'permissions' => $legacy_caps, 'revision' => 1 );
        foreach ( $legacy_caps as $cap ) get_role( $key )->add_cap( $cap );
    }
    update_option( 'ueb_access_roles', $roles, false );
    update_option( 'ueb_access_version', '2', false );
}
add_action( 'init', 'ueb_access_migrate', 20 );

/** Préréglages éditables : aucune création automatique de rôle. */
function ueb_access_presets() {
    return array(
        array( 'name' => 'Lecture seule', 'permissions' => array( 'ueb_view_stats', 'ueb_view_trends', 'ueb_view_students' ) ),
        array( 'name' => 'Chef d’établissement', 'permissions' => array( 'ueb_view_stats', 'ueb_view_trends', 'ueb_view_students', 'ueb_export_students', 'ueb_section_effectifs', 'ueb_section_dossiers' ) ),
        array( 'name' => 'Supervision globale / Recteur', 'permissions' => array( 'ueb_view_overview', 'ueb_view_stats', 'ueb_view_trends' ) ),
        array( 'name' => 'Suivi des admissions / DAAS', 'permissions' => array( 'ueb_view_overview', 'ueb_view_stats', 'ueb_view_trends', 'ueb_view_students', 'ueb_export_students', 'ueb_section_stats', 'ueb_section_effectifs', 'ueb_section_dossiers' ) ),
        array( 'name' => 'Direction / Super gestionnaire', 'permissions' => array_keys( ueb_access_catalogue() ) ),
    );
}

/** Détecte toute extension de droits OU de portée, y compris par permission. */
function ueb_access_can_delegate( $role, $management_cap ) {
    $caps = array_merge( array( $management_cap ), $role['permissions'] );
    foreach ( $caps as $cap ) {
        if ( ! current_user_can( $cap ) ) return false;
        $scope = ueb_access_scope( $cap );
        if ( null !== $scope && ( 'all' === $role['scope'] || array_diff( $role['establishments'], $scope ) ) ) return false;
    }
    return true;
}

function ueb_access_role_is_safe( $key ) {
    $role = get_role( $key );
    return 'administrator' !== $key && $role && ! array_diff( array_keys( array_filter( $role->capabilities ) ), array_merge( array( 'read', 'voir_preinscriptions' ), array_keys( ueb_access_catalogue() ) ) );
}

/** Un verrou SQL par site sérialise les écritures de rôles et affectations. */
function ueb_access_lock() {
    global $wpdb;
    return 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', 'ueb_access_' . get_current_blog_id() ) );
}
function ueb_access_unlock() {
    global $wpdb;
    $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', 'ueb_access_' . get_current_blog_id() ) );
}

function ueb_access_save_role( $input ) {
    $key = sanitize_key( $input['key'] ?? '' );
    $roles = ueb_access_roles();
    if ( ! current_user_can( 'ueb_manage_roles' ) ) return new WP_Error( 'denied', 'Accès refusé.' );
    if ( $key && ( ! isset( $roles[ $key ] ) || ! ueb_access_role_is_safe( $key ) || in_array( $key, wp_get_current_user()->roles, true ) || ! ueb_access_can_delegate( $roles[ $key ], 'ueb_manage_roles' ) ) ) return new WP_Error( 'protected', 'Ce rôle est protégé ou dépasse vos accès.' );
    if ( $key && (int) ( $input['revision'] ?? 0 ) !== (int) $roles[ $key ]['revision'] ) return new WP_Error( 'conflict', 'Ce rôle a changé. Rechargez la page avant de le modifier.' );
    $role = array(
        'name' => mb_substr( sanitize_text_field( $input['name'] ?? '' ), 0, 100 ),
        'scope' => sanitize_key( $input['scope'] ?? '' ),
        'establishments' => array_values( array_unique( array_map( 'absint', (array) ( $input['establishments'] ?? array() ) ) ) ),
        'permissions' => array_values( array_unique( array_map( 'sanitize_key', (array) ( $input['permissions'] ?? array() ) ) ) ),
        'revision' => $key ? (int) $roles[ $key ]['revision'] + 1 : 1,
    );
    if ( ! $role['name'] || ! in_array( $role['scope'], array( 'single', 'multiple', 'all' ), true ) || ! $role['permissions'] || array_diff( $role['permissions'], array_keys( ueb_access_catalogue() ) ) ) return new WP_Error( 'invalid', 'Renseignez un nom, une portée et des permissions autorisées.' );
    if ( 'all' === $role['scope'] ) $role['establishments'] = array();
    elseif ( ( 'single' === $role['scope'] && 1 !== count( $role['establishments'] ) ) || ( 'multiple' === $role['scope'] && count( $role['establishments'] ) < 2 ) ) return new WP_Error( 'scope', 'Sélectionnez le nombre d’établissements correspondant à la portée.' );
    $valid_ids = array_map( 'intval', wp_list_pluck( ueb_access_establishments( 'ueb_manage_roles' ), 'id' ) );
    if ( array_diff( $role['establishments'], $valid_ids ) || ! ueb_access_can_delegate( $role, 'ueb_manage_roles' ) ) return new WP_Error( 'escalation', 'Ces permissions ou établissements dépassent vos accès.' );
    foreach ( $roles as $other => $entry ) if ( $other !== $key && mb_strtolower( $entry['name'] ) === mb_strtolower( $role['name'] ) ) return new WP_Error( 'duplicate', 'Ce nom de rôle existe déjà.' );
    if ( ! $key ) $key = 'ueb_role_' . str_replace( '-', '', wp_generate_uuid4() );
    $caps = array_fill_keys( array_merge( array( 'read' ), $role['permissions'] ), true );
    // WordPress persiste les capabilities ; l'option persiste la portée.
    if ( get_role( $key ) ) remove_role( $key );
    add_role( $key, $role['name'], $caps );
    $roles[ $key ] = $role;
    update_option( 'ueb_access_roles', $roles, false );
    ueb_access_cache_generation( true );
    return array( 'key' => $key, 'message' => 'Rôle enregistré.' );
}

function ueb_access_user_manageable( $user ) {
    if ( ! $user->exists() || $user->ID === get_current_user_id() || ! $user->roles || user_can( $user, 'manage_options' ) || is_super_admin( $user->ID ) ) return false;
    $roles = ueb_access_roles();
    foreach ( $user->roles as $key ) if ( ! isset( $roles[ $key ] ) || ! ueb_access_role_is_safe( $key ) || ! ueb_access_can_delegate( $roles[ $key ], 'ueb_manage_users' ) ) return false;
    // Capabilities individuelles : ne pas prendre le contrôle d'un compte privilégié.
    foreach ( $user->caps as $cap => $enabled ) if ( $enabled && ! in_array( $cap, $user->roles, true ) ) return false;
    return true;
}

function ueb_access_delete_role( $input ) {
    $key = sanitize_key( $input['key'] ?? '' );
    $replacement = sanitize_key( $input['replacement'] ?? '' );
    $roles = ueb_access_roles();
    if ( ! current_user_can( 'ueb_manage_roles' ) || '1' !== (string) ( $input['confirmed'] ?? '' ) || ! isset( $roles[ $key ] ) || ! ueb_access_role_is_safe( $key ) || in_array( $key, wp_get_current_user()->roles, true ) || ! ueb_access_can_delegate( $roles[ $key ], 'ueb_manage_roles' ) ) return new WP_Error( 'denied', 'Suppression non autorisée.' );
    if ( (int) ( $input['revision'] ?? 0 ) !== (int) $roles[ $key ]['revision'] ) return new WP_Error( 'conflict', 'Ce rôle a changé. Rechargez la page.' );
    $users = get_users( array( 'role' => $key ) );
    if ( $users ) {
        if ( $replacement === $key || ! isset( $roles[ $replacement ] ) || ! ueb_access_role_is_safe( $replacement ) || ! ueb_access_can_delegate( $roles[ $replacement ], 'ueb_manage_users' ) ) return new WP_Error( 'reassign', 'Choisissez un rôle de réaffectation autorisé pour les comptes rattachés.' );
        foreach ( $users as $user ) if ( ! ueb_access_user_manageable( $user ) ) return new WP_Error( 'protected', 'Un compte rattaché est protégé. La suppression est refusée.' );
        foreach ( $users as $user ) { $user->remove_role( $key ); $user->add_role( $replacement ); }
    }
    remove_role( $key );
    unset( $roles[ $key ] );
    update_option( 'ueb_access_roles', $roles, false );
    ueb_access_cache_generation( true );
    return array( 'message' => 'Rôle supprimé. Les comptes ont été réaffectés si nécessaire.' );
}

function ueb_access_save_user( $input ) {
    $roles = ueb_access_roles();
    $key = sanitize_key( $input['role'] ?? '' );
    if ( ! isset( $roles[ $key ] ) || ! ueb_access_role_is_safe( $key ) || ! ueb_access_can_delegate( $roles[ $key ], 'ueb_manage_users' ) ) return new WP_Error( 'denied', 'Affectation non autorisée.' );
    $id = absint( $input['id'] ?? 0 );
    if ( $id ) {
        $user = get_user_by( 'id', $id );
        if ( ! $user || ! ueb_access_user_manageable( $user ) ) return new WP_Error( 'denied', 'Ce compte est protégé ou hors de votre portée.' );
        $user->set_role( $key );
    } else {
        $password = (string) ( $input['password'] ?? '' );
        if ( ! is_email( $input['email'] ?? '' ) || ! trim( $input['name'] ?? '' ) ) return new WP_Error( 'profile', 'Renseignez un nom et une adresse e-mail valide.' );
        if ( strlen( $password ) < 12 ) return new WP_Error( 'password', 'Utilisez un mot de passe d’au moins 12 caractères.' );
        $id = wp_insert_user( array( 'user_login' => sanitize_user( $input['login'] ?? '', true ), 'user_email' => sanitize_email( $input['email'] ?? '' ), 'display_name' => sanitize_text_field( $input['name'] ?? '' ), 'user_pass' => $password, 'role' => $key ) );
        if ( is_wp_error( $id ) ) return new WP_Error( 'user', 'Création impossible. Vérifiez l’identifiant et l’adresse e-mail, qui doivent être uniques.' );
    }
    return array( 'message' => 'Compte enregistré.', 'id' => $id );
}

function ueb_access_mutation_endpoint() {
    $operation = sanitize_key( $_POST['operation'] ?? '' );
    ueb_access_require( 'user_save' === $operation ? 'ueb_manage_users' : 'ueb_manage_roles' );
    if ( ! ueb_access_lock() ) wp_send_json_error( array( 'message' => 'Une modification est en cours. Réessayez.' ), 409 );
    try {
        $input = wp_unslash( $_POST );
        if ( 'role_save' === $operation ) $result = ueb_access_save_role( $input );
        elseif ( 'role_delete' === $operation ) $result = ueb_access_delete_role( $input );
        elseif ( 'user_save' === $operation ) $result = ueb_access_save_user( $input );
        else $result = new WP_Error( 'invalid', 'Action inconnue.' );
    } finally { ueb_access_unlock(); }
    if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
    wp_send_json_success( $result );
}
add_action( 'wp_ajax_ueb_access_mutate', 'ueb_access_mutation_endpoint' );
