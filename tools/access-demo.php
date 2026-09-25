<?php
/** Initialisation explicite de profils modifiables, réservée à la CLI.
 * php tools/access-demo.php /chemin/wp-load.php ID_ADMIN --install
 * Le nom des exemples n'est jamais utilisé pour autoriser un accès.
 */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
if ( count( $argv ) < 4 || '--install' !== $argv[3] ) {
    exit( "Usage : php tools/access-demo.php /chemin/wp-load.php ID_ADMIN --install\n" );
}
require $argv[1];
wp_set_current_user( absint( $argv[2] ) );
if ( ! current_user_can( 'manage_options' ) ) exit( "Un administrateur technique existant doit initialiser ces exemples.\n" );
if ( ! ueb_access_lock() ) exit( "Une modification est en cours. Réessayez.\n" );
try {
    $state = (array) get_option( 'ueb_access_demo', array() );
    $roles = ueb_access_roles();
    if ( empty( $state['establishment'] ) ) {
        $created = ueb_admin_ref_create( 'facultes', array( 'code' => 'FS-TEST', 'nom_fr' => 'Faculté des Sciences — Test', 'nom_en' => 'Faculty of Science — Test', 'slug' => 'faculte-sciences-test', 'actif' => '0' ) );
        if ( ! $created['success'] ) throw new RuntimeException( $created['message'] );
        $state['establishment'] = $created['id'];
        update_option( 'ueb_access_demo', $state, false );
    }
    $examples = array(
        'direction' => array( 'name' => 'Direction', 'scope' => 'all', 'permissions' => array_keys( ueb_access_catalogue() ) ),
        'local' => array( 'name' => 'Admin FS', 'scope' => 'single', 'establishments' => array( $state['establishment'] ), 'permissions' => array( 'ueb_view_stats', 'ueb_view_trends', 'ueb_view_students', 'ueb_export_students', 'ueb_section_effectifs', 'ueb_section_dossiers' ) ),
    );
    foreach ( $examples as $example => $input ) {
        if ( ! empty( $state[ $example ] ) ) continue; // Ne réécrit jamais un rôle modifié par la direction.
        $created = ueb_access_save_role( $input );
        if ( is_wp_error( $created ) ) throw new RuntimeException( $created->get_error_message() );
        $state[ $example ] = $created['key'];
        update_option( 'ueb_access_demo', $state, false );
    }
    echo "Exemples prêts : Direction, Admin FS, Faculté des Sciences — Test (inactif).\n";
    echo "Créez les comptes et choisissez leurs rôles depuis : " . ueb_portal_url( 'users' ) . "\n";
} catch ( Throwable $error ) {
    fwrite( STDERR, $error->getMessage() . "\n" );
    exit( 1 );
} finally {
    ueb_access_unlock();
}
