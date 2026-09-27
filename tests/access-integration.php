<?php
/** Tests réels WordPress/MySQL, sans dépendance. Les données sont rollbackées.
 * Usage : php tests/access-integration.php [chemin/vers/wp-load.php]
 * --fixtures crée des comptes temporaires pour le navigateur ; --cleanup les retire.
 */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
define( 'DOING_AJAX', true );
require $argv[1] ?? dirname( __DIR__, 4 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
// Les cookies de la reprise sont testés sans envoyer de sortie avant eux.
ob_start();
global $wpdb;
$mode = $argv[2] ?? '';
$fixture_path = '/tmp/ueb-access-fixtures.json';
if ( '--cleanup' === $mode ) {
    $fixtures = json_decode( file_get_contents( $fixture_path ), true );
    foreach ( $fixtures['users'] as $user ) wp_delete_user( $user['id'] );
    $roles = ueb_access_roles();
    foreach ( $fixtures['roles'] as $key ) { remove_role( $key ); unset( $roles[ $key ] ); }
    update_option( 'ueb_access_roles', $roles, false );
    foreach ( $fixtures['dossiers'] as $number ) $wpdb->delete( 'ueb_preinscriptions', array( 'numero_dossier' => $number ) );
    foreach ( $fixtures['filieres'] as $id ) $wpdb->delete( 'ueb_filieres', array( 'id' => $id ) );
    foreach ( $fixtures['establishments'] as $id ) $wpdb->delete( 'ueb_facultes', array( 'id' => $id ) );
    unlink( $fixture_path ); echo "Fixtures supprimées.\n"; exit;
}
if ( '--fixtures' !== $mode ) {
    $wpdb->query( 'START TRANSACTION' );
    register_shutdown_function( function() use ( $wpdb ) { $wpdb->query( 'ROLLBACK' ); wp_cache_flush(); } );
}
elseif ( file_exists( $fixture_path ) ) {
    throw new RuntimeException( 'Des fixtures existent déjà. Exécutez --cleanup avant de les recréer.' );
}
$prefix = 'zt' . substr( wp_generate_uuid4(), 0, 6 );
$fixtures = array( 'users' => array(), 'roles' => array(), 'establishments' => array(), 'dossiers' => array(), 'filieres' => array(), 'base' => home_url( '/' ) );
function ensure( $condition, $label ) {
    if ( ! $condition ) throw new RuntimeException( 'ÉCHEC : ' . $label );
    echo 'OK : ' . $label . "\n";
}
// Un compte technique temporaire ; aucun compte existant n'est modifié.
$admin_id = wp_insert_user( array( 'user_login' => $prefix . '_root', 'user_pass' => wp_generate_password( 32 ), 'role' => 'administrator' ) );
if ( is_wp_error( $admin_id ) ) throw new RuntimeException( 'Création de fixture impossible.' );
wp_set_current_user( $admin_id );
$fixtures['users']['root'] = array( 'id' => $admin_id );
foreach ( array( 'A', 'B', 'C' ) as $letter ) {
    $wpdb->insert( 'ueb_facultes', array( 'code' => $prefix . $letter, 'nom_fr' => 'Établissement test ' . $letter, 'nom_en' => 'Test ' . $letter, 'slug' => $prefix . '-' . strtolower( $letter ), 'actif' => 1 ) );
    $id = (int) $wpdb->insert_id;
    if ( ! $id ) throw new RuntimeException( $wpdb->last_error );
    $fixtures['establishments'][] = $id;
    $wpdb->insert( 'ueb_filieres', array( 'code' => $prefix . $letter, 'libelle' => 'Filière test ' . $letter, 'faculte_id' => $id ) );
    $fid = (int) $wpdb->insert_id; $fixtures['filieres'][] = $fid;
    for ( $i = 0; $i < 2; $i++ ) {
        $numero = $prefix . $letter . $i;
        $wpdb->insert( 'ueb_preinscriptions', array( 'numero_dossier' => $numero, 'faculte_id' => $id, 'filiere_1_id' => $fid, 'nom' => 'Candidat test ' . $letter, 'prenom' => 'Exemple', 'date_creation' => current_time( 'mysql' ), 'statut' => $i ? 'soumis' : 'brouillon' ) );
        $fixtures['dossiers'][] = $numero;
    }
}
[$a,$b,$c] = $fixtures['establishments'];
$data_caps = array( 'ueb_view_stats', 'ueb_view_trends', 'ueb_view_students', 'ueb_export_students', 'ueb_section_dossiers', 'ueb_section_effectifs', 'ueb_section_stats', 'ueb_ref_filieres' );
$definitions = array(
    'single' => array( 'single', array( $a ), $data_caps ),
    'multiple' => array( 'multiple', array( $a, $b ), $data_caps ),
    'all' => array( 'all', array(), array_merge( $data_caps, array( 'ueb_view_overview' ) ) ),
    'limited' => array( 'single', array( $a ), array( 'ueb_view_stats' ) ),
    'director' => array( 'all', array(), array_keys( ueb_access_catalogue() ) ),
    'manager' => array( 'single', array( $a ), array_merge( $data_caps, array( 'ueb_manage_roles', 'ueb_manage_users', 'ueb_manage_establishments' ) ) ),
    'otherstats' => array( 'single', array( $b ), array( 'ueb_view_stats' ) ),
    'reader' => array( 'single', array( $a ), array( 'ueb_view_students' ) ),
    'filterer' => array( 'single', array( $a ), array( 'ueb_view_students', 'ueb_filter_students' ) ),
);
foreach ( $definitions as $kind => $definition ) {
    $result = ueb_access_save_role( array( 'name' => $prefix . ' ' . $kind, 'scope' => $definition[0], 'establishments' => $definition[1], 'permissions' => $definition[2] ) );
    if ( is_wp_error( $result ) ) throw new RuntimeException( $result->get_error_message() );
    $key = $result['key']; $fixtures['roles'][] = $key;
    $password = wp_generate_password( 28, true, true );
    $login = $prefix . '_' . $kind;
    $id = wp_insert_user( array( 'user_login' => $login, 'user_pass' => $password, 'display_name' => 'Test ' . $kind, 'role' => $key ) );
    $fixtures['users'][ $kind ] = array( 'id' => $id, 'login' => $login, 'password' => $password, 'role' => $key );
}
if ( '--fixtures' === $mode ) {
    file_put_contents( $fixture_path, wp_json_encode( $fixtures ) ); chmod( $fixture_path, 0600 );
    echo "Fixtures temporaires préparées pour le navigateur (identifiants non affichés).\n"; exit;
}
function as_user( $kind ) {
    global $fixtures;
    wp_set_current_user( 0 ); wp_set_current_user( $fixtures['users'][ $kind ]['id'] );
    $_REQUEST = $_POST = array();
}
as_user( 'single' );
ensure( ueb_access_scope( 'ueb_view_students' ) === array( $a ), 'Portée un seul établissement' );
ensure( 2 === ueb_admin_get_dossiers_filtres( array() )['total'], 'Liste limitée côté SQL' );
ensure( 0 === ueb_admin_get_dossiers_filtres( array( 'faculte' => $b ) )['total'], 'Filtre forgé sans fuite' );
ensure( null === ueb_admin_get_dossier_detail( $fixtures['dossiers'][2] ), 'Détail IDOR refusé' );
ensure( 2 === count( ueb_export_get_rows( array() ) ), 'Export limité au périmètre' );
// Export d'un chef d'établissement : exactement ce que montre sa liste.
$_REQUEST['action'] = 'ueb_admin_export';
ensure( 2 === count( ueb_export_get_rows( array() ) ), 'Export (action réelle) limité à son établissement' );
ensure( 0 === count( ueb_export_get_rows( array( 'faculte' => $b ) ) ), 'Export : établissement forgé sans fuite' );
ensure( 1 === count( ueb_export_get_rows( array( 'statut' => 'soumis' ) ) ), 'Export : même filtre de statut que la liste' );
ensure( 1 === count( ueb_export_get_rows( array(), $prefix . 'A1' ) ), 'Export : même recherche que la liste' );
ensure( 'Établissement : ' . $prefix . 'A' === ueb_export_perimetre( array(), array() ), 'Export : en-tête nomme son établissement' );
$_REQUEST = array();
ensure( 2 === ueb_portal_stats( false, $a )['totals']['total'], 'Statistiques établissement' );
ensure( is_wp_error( ueb_portal_stats( false, $b ) ), 'Statistiques IDOR refusées' );
ensure( is_wp_error( ueb_portal_stats( true ) ), 'Vue globale interdite sans permission' );
ensure( 1 === count( ueb_effectifs_vue_universite()['lignes'] ), 'Ancien organigramme limité' );
ensure( null === ueb_effectifs_vue_etablissement( $b ), 'Ancien détail établissement refusé' );
ensure( null === ueb_effectifs_vue_filiere( $fixtures['filieres'][1] ), 'Ancien détail filière refusé' );
ensure( 2 === ueb_effectifs_vue_filiere( $fixtures['filieres'][0] )['total'], 'Ancienne filière autorisée conservée' );
ensure( 1 === ueb_admin_ref_list( 'filieres' )['total'], 'Référentiel limité' );
ensure( null === ueb_admin_ref_get( 'filieres', $fixtures['filieres'][1] ), 'Référence hors portée inaccessible' );
$raw = array( 'code' => $prefix . 'X', 'libelle' => 'Test', 'faculte_id' => $b, 'type_formation' => 'classique', 'cycle' => 'tous', 'actif' => 1 );
ensure( ! ueb_admin_ref_create( 'filieres', $raw )['success'], 'Création de filière hors portée refusée' );
ensure( ! ueb_admin_ref_update( 'filieres', $fixtures['filieres'][0], $raw )['success'], 'Déplacement hors portée refusé' );
// Privilège « Filtrer les dossiers » : sans lui, le serveur ignore les
// filtres détaillés, même envoyés à la main ; avec lui, ils s'appliquent,
// toujours dans la seule portée du compte.
as_user( 'reader' );
$_REQUEST = array( 'action' => 'ueb_admin_get_dossiers', 'sexe' => 'F', 'niveau_lmd' => '1', 'statut' => 'soumis' );
$lus = ueb_admin_ajax_extract_filters();
ensure( ! ueb_access_peut_filtrer() && '' === $lus['sexe'] && '' === $lus['niveau_lmd'], 'Filtres détaillés ignorés sans le privilège' );
ensure( 'soumis' === $lus['statut'], 'Filtre de statut conservé sans le privilège' );
as_user( 'filterer' );
$_REQUEST = array( 'action' => 'ueb_admin_get_dossiers', 'sexe' => 'F', 'date_from' => '2000-01-01' );
$lus = ueb_admin_ajax_extract_filters();
ensure( ueb_access_peut_filtrer() && 'F' === $lus['sexe'], 'Filtres détaillés appliqués avec le privilège' );
ensure( 2 === ueb_admin_get_dossiers_filtres( array( 'date_from' => '2000-01-01' ) )['total'], 'Filtre détaillé limité à son établissement' );
$_REQUEST = array();
as_user( 'multiple' );
ensure( 4 === ueb_admin_get_dossiers_filtres( array() )['total'], 'Portée plusieurs établissements' );
ensure( ! ueb_access_contains( 'ueb_view_students', $c ), 'Troisième établissement exclu' );
as_user( 'all' );
ensure( null === ueb_access_scope( 'ueb_view_students' ), 'Portée tous dynamique' );
ensure( 'Tous les établissements' === ueb_export_perimetre( array(), array() ), 'Export global : en-tête sans établissement imposé' );
ensure( count( ueb_portal_stats( true )['establishments'] ) >= 3, 'Vue globale des établissements autorisés' );
as_user( 'limited' );
ensure( ! current_user_can( 'ueb_view_students' ) && ! current_user_can( 'ueb_export_students' ), 'Permissions absentes' );
ensure( 0 === ueb_admin_get_dossiers_filtres( array() )['total'], 'Aucun dossier sans permission même en appel direct' );
ensure( null === ueb_portal_stats( false, $a )['evolution'], 'Courbes absentes sans permission' );
as_user( 'manager' );
$input = array( 'name' => $prefix . ' escalation', 'scope' => 'all', 'permissions' => array( 'ueb_view_stats' ) );
ensure( is_wp_error( ueb_access_save_role( $input ) ), 'Élargissement vers tous refusé' );
$input['scope'] = 'single'; $input['establishments'] = array( $b );
ensure( is_wp_error( ueb_access_save_role( $input ) ), 'Délégation établissement hors portée refusée' );
$input['establishments'] = array( $a ); $input['permissions'] = array( 'manage_options' );
ensure( is_wp_error( ueb_access_save_role( $input ) ), 'Capability système refusée' );
$input['permissions'] = array( 'ueb_view_stats' ); $input['key'] = $fixtures['users']['manager']['role']; $input['revision'] = 1;
ensure( is_wp_error( ueb_access_save_role( $input ) ), 'Modification de son propre rôle refusée' );
$input['key'] = 'administrator';
ensure( is_wp_error( ueb_access_save_role( $input ) ), 'Rôle administrator protégé' );
ensure( is_wp_error( ueb_access_save_user( array( 'id' => $fixtures['users']['all']['id'], 'role' => $fixtures['users']['single']['role'] ) ) ), 'Compte hors portée protégé' );
as_user( 'director' );
$key = $fixtures['users']['single']['role'];
ensure( is_wp_error( ueb_access_delete_role( array( 'key' => $key, 'revision' => 1, 'confirmed' => 1 ) ) ), 'Suppression avec comptes exige réaffectation' );
ensure( ! is_wp_error( ueb_access_delete_role( array( 'key' => $key, 'revision' => 1, 'confirmed' => 1, 'replacement' => $fixtures['users']['multiple']['role'] ) ) ), 'Suppression avec réaffectation autorisée' );
ensure( in_array( $fixtures['users']['multiple']['role'], get_user_by( 'id', $fixtures['users']['single']['id'] )->roles, true ), 'Compte effectivement réaffecté' );
as_user( 'limited' );
wp_get_current_user()->add_role( $fixtures['users']['otherstats']['role'] );
ensure( ! ueb_access_contains( 'ueb_view_students', $b ), 'Plusieurs rôles : aucun croisement de permission' );
ensure( count( ueb_access_scope( 'ueb_view_stats' ) ) === 2, 'Plusieurs rôles : union pour une même permission' );
as_user( 'manager' );
$input = array( 'name' => $prefix . ' journal', 'scope' => 'single', 'establishments' => array( $a ), 'permissions' => array( 'ueb_view_stats' ) );
$created = ueb_access_save_role( $input );
ensure( ! is_wp_error( $created ), 'Création par une direction limitée à un établissement' );
$input['key'] = $created['key']; $input['revision'] = 1;
ensure( ! is_wp_error( ueb_access_save_role( $input ) ), 'Modification autorisée' );
ensure( is_wp_error( ueb_access_save_role( $input ) ), 'Écriture concurrente obsolète refusée' );
$input['key'] = ''; $input['name'] .= ' capacités'; $input['permissions'][] = 'ueb_view_overview';
ensure( is_wp_error( ueb_access_save_role( $input ) ), 'Permission supplémentaire non détenue refusée' );
ensure( ! ueb_access_ref_allowed( 'nationalites' ), 'Référentiel global non attribué refusé' );
ensure( ! ueb_admin_ref_create( 'facultes', array() )['success'], 'Direction limitée ne crée pas un établissement hors portée' );

// Authentification : limitation commune, erreurs génériques, reset natif.
as_user( 'limited' );
$_SERVER['REMOTE_ADDR'] = '127.0.0.254';
$account = $fixtures['users']['limited'];
foreach ( ueb_auth_keys( $account['login'] ) as $key ) delete_transient( $key );
for ( $i = 0; $i < 5; $i++ ) wp_authenticate( $account['login'], 'incorrect-password' );
ensure( is_wp_error( wp_authenticate( $account['login'], $account['password'] ) ), 'Login limité après cinq échecs' );
foreach ( ueb_auth_keys( $account['login'] ) as $key ) delete_transient( $key );
ensure( ! is_wp_error( wp_authenticate( $account['login'], $account['password'] ) ), 'Connexion de nouveau possible après déblocage' );
$reset_key = get_password_reset_key( get_user_by( 'id', $account['id'] ) );
ensure( ! is_wp_error( check_password_reset_key( $reset_key, $account['login'] ) ), 'Clé de récupération native valide' );
ensure( is_wp_error( check_password_reset_key( 'incorrect', $account['login'] ) ), 'Clé de récupération invalide refusée' );

// Parcours public : création, sauvegarde et reprise par numéro de dossier.
wp_set_current_user( 0 );
$draft = ueb_initialiser_dossier();
ensure( (bool) $draft, 'Création publique d\'un dossier' );
ensure( ueb_sauvegarder_progression( $draft, 2, array( 'nom' => 'Test brouillon' ) ), 'Sauvegarde du brouillon' );
ensure( 'Test brouillon' === ueb_recuperer_progression( $draft )['donnees']['nom'], 'Reprise par numéro de dossier' );
ensure( "'=1+1" === ueb_export_csv_cell( '=1+1' ), 'Formule CSV neutralisée' );
ensure( ! $wpdb->last_error, 'Aucune erreur SQL' );
echo "Tous les tests ont réussi. Transaction annulée à la sortie.\n";
