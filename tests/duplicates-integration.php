<?php
/** MySQL réel, tables TEMPORARY homonymes : aucun candidat réel ni compte modifié.
 * /opt/lampp/bin/php tests/duplicates-integration.php [--volume]
 */
if ( PHP_SAPI !== 'cli' ) exit;
define( 'DOING_AJAX', true );
require dirname( __DIR__, 4 ) . '/wp-load.php';
global $wpdb;
function check_dup( $condition, $label ) { if ( ! $condition ) throw new RuntimeException( 'ÉCHEC : ' . $label ); echo "OK : {$label}\n"; }
function sql_dup( $sql ) { global $wpdb; if ( false === $wpdb->query( $sql ) ) throw new RuntimeException( $wpdb->last_error ); }
// Copie privée des options avant toute migration ; la fermeture de la connexion
// détruit uniquement les tables temporaires. Pas de nettoyage destructif.
$options = $wpdb->options;
sql_dup( "CREATE TEMPORARY TABLE dup_test_options LIKE {$options}" );
sql_dup( "INSERT INTO dup_test_options SELECT * FROM {$options}" );
sql_dup( "CREATE TEMPORARY TABLE {$options} LIKE dup_test_options" );
sql_dup( "INSERT INTO {$options} SELECT * FROM dup_test_options" );
foreach ( array( 'ueb_preinscriptions', 'ueb_preinscriptions_telephones' ) as $table ) {
    sql_dup( "CREATE TEMPORARY TABLE dup_template LIKE {$table}" );
    sql_dup( "CREATE TEMPORARY TABLE {$table} LIKE dup_template" );
    sql_dup( 'DROP TEMPORARY TABLE dup_template' );
}
// Création privée des tables de la migration sans modifier les tables réelles.
$source = file_get_contents( dirname( __DIR__ ) . '/inc/duplicates-functions.php' );
preg_match( '/\$schemas = array\((.*?)\n    \);/s', $source, $match );
eval( '$schemas = array(' . $match[1] . ');' );
foreach ( $schemas as $suffix => $definition ) sql_dup( "CREATE TEMPORARY TABLE ueb_duplicate_{$suffix} ({$definition}) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" );
update_option( 'ueb_duplicates_version', '' );
check_dup( ueb_duplicates_migrate(), 'Migration additive' );
check_dup( ueb_duplicates_migrate(), 'Migration rejouable' );
update_option( 'ueb_duplicates_settings', array( 'email'=>true, 'phone'=>true, 'identity'=>true, 'country'=>'237' ) );
function actor_dup( $permissions = null, $scope = null ) {
    global $current_user;
    $current_user = new WP_User(); $current_user->ID = 999990;
    $current_user->roles = array( 'dup_tester' );
    $current_user->allcaps = array_fill_keys( $permissions ?? array_keys( ueb_access_catalogue() ), true );
    $GLOBALS['dup_test_role'] = array( 'dup_tester' => array( 'permissions'=>array_keys( $current_user->allcaps ), 'scope'=>$scope === null ? 'all' : 'multiple', 'establishments'=>$scope ?? array() ) );
}
add_filter( 'pre_option_ueb_access_roles', function() { return $GLOBALS['dup_test_role']; } );
actor_dup();
$facultes = array_map( 'intval', $wpdb->get_col( 'SELECT id FROM ueb_facultes ORDER BY id LIMIT 2' ) );
check_dup( count( $facultes ) === 2, 'Références établissements disponibles' );
[$fa,$fb] = $facultes;
function add_dup( $data = array(), $phone = '' ) {
    global $wpdb, $fa;
    static $number = 0;
    $defaults = array( 'numero_dossier'=>'TEST-DUP-' . ++$number, 'nom'=>'Isolé' . $number, 'prenom'=>'Test', 'faculte_id'=>$fa, 'statut'=>'soumis', 'date_creation'=>'2026-01-01 10:00:00', 'date_modification'=>'2026-01-01 10:00:00' );
    if ( false === $wpdb->insert( 'ueb_preinscriptions', array_merge( $defaults, $data ) ) ) throw new RuntimeException( $wpdb->last_error );
    $id = (int) $wpdb->insert_id;
    if ( $phone ) $wpdb->insert( 'ueb_preinscriptions_telephones', array( 'preinscription_id'=>$id, 'type'=>'candidat', 'numero'=>$phone ) );
    return $id;
}
function scan_dup() { while ( ueb_duplicates_index_batch() ) {} ueb_duplicates_rebuild(); }
function act_dup( $input ) {
    $preview = ueb_duplicates_preview( $input );
    check_dup( ! is_wp_error( $preview ), 'Prévisualisation ' . $input['operation'] );
    $result = ueb_duplicates_commit( $preview['token'], 'Test automatisé' );
    check_dup( ! is_wp_error( $result ), 'Confirmation ' . $input['operation'] . ( is_wp_error( $result ) ? ': '.$result->get_error_message() : '' ) );
    return $result;
}
check_dup( ueb_duplicates_normalize(' ÉLÉONORE - N’DONGO ') === 'eleonorendongo', 'Accents, casse, espaces, ponctuation' );
check_dup( ueb_duplicates_phone('6 99 12 34 56') === ueb_duplicates_phone('00237 699123456'), 'Téléphones local/international' );
check_dup( ueb_duplicates_phone('+237699123456') === '237699123456', 'Téléphone +237' );
$old = add_dup( array('email'=>'JEAN.DUPONT@example.test') );
$new = add_dup( array('email'=>'jean.dupont@example.test','date_creation'=>'2026-02-01 10:00:00') );
$tie = add_dup( array('email'=>'jean.dupont@example.test','date_creation'=>'2026-02-01 10:00:00') );
$p1 = add_dup( array('nom'=>'ÉLÉONORE','prenom'=>'N’DONGO','date_naissance'=>'2000-02-01') );
$p2 = add_dup( array('nom'=>'N dongo','prenom'=>'Eleonore','date_naissance'=>'2000-02-01') );
$c1 = add_dup( array('email'=>'cross@example.test') );
$c2 = add_dup( array('email'=>'cross@example.test','faculte_id'=>$fb) );
$t1 = add_dup( array(), '6 99 12 34 56' ); $t2 = add_dup( array(), '+237699123456' );
$blank1 = add_dup(); $blank2 = add_dup();
scan_dup();
check_dup( (int)$wpdb->get_var("SELECT keeper_id FROM ueb_duplicate_groups WHERE id={$old}") === $tie, 'Gardien date récente puis ID le plus élevé' );
check_dup( $wpdb->get_var("SELECT confidence FROM ueb_duplicate_groups WHERE id={$p1}") === 'probable', 'Identité inversée probable' );
check_dup( $wpdb->get_var("SELECT confidence FROM ueb_duplicate_groups WHERE id={$t1}") === 'certain', 'Téléphones normalisés certains' );
check_dup( !$wpdb->get_var("SELECT id FROM ueb_duplicate_groups WHERE id={$blank1}"), 'Champs vides sans rapprochement' );
$list = ueb_duplicates_list( array(), '', 1, 25, 'nom','ASC' );
check_dup( $list['total'] === 9 && (int)$list['duplicates']['groups_count'] === 4, 'Liste et compteurs groupes SQL' );
check_dup( is_wp_error( ueb_duplicates_preview(array('operation'=>'disable','id'=>$tie)) ), 'Gardien non désactivable individuellement' );
$bulk = ueb_duplicates_preview(array('operation'=>'bulk'));
check_dup( $bulk['count'] === 3, 'Bulk Certain uniquement, sans inter-établissements ni Probable' );
$base = ueb_admin_kpis()['total'];
$result = ueb_duplicates_commit($bulk['token']);
check_dup( !is_wp_error($result) && $result['count']===3, 'Bulk appliqué exactement' );
check_dup( ueb_admin_kpis()['total'] === $base-3, 'KPI avant/après' );
check_dup( ueb_effectifs_vue_universite()['total'] === $base-3, 'Effectifs université corrigés' );
check_dup( ueb_effectifs_vue_etablissement($fa)['total'] === $base-4, 'Effectifs établissement corrigés' );
$stats = ueb_portal_stats(true);
check_dup( !is_wp_error($stats), 'Statistiques portail accessibles' );
check_dup( ueb_duplicates_hidden_count(array())===3, 'Indicateur masqués' );
$restored = act_dup(array('operation'=>'undo','batch'=>$result['batch']));
check_dup( ueb_admin_kpis()['total'] === $base, 'Annulation et statistiques restaurées' );
act_dup(array('operation'=>'disable','id'=>$old));
act_dup(array('operation'=>'reactivate','id'=>$old));
check_dup( $wpdb->get_var("SELECT statut FROM ueb_preinscriptions WHERE id={$old}")==='soumis', 'Réactivation au statut initial' );
$false = act_dup(array('operation'=>'dismiss','group'=>$p1));
check_dup( ueb_duplicates_list(array(),'',1,25,'date_creation','DESC')['total']===7, 'Faux positif absent du filtre' );
act_dup(array('operation'=>'undo','batch'=>$false['batch']));
check_dup( ueb_duplicates_list(array(),'',1,25,'date_creation','DESC')['total']===9, 'Annulation du faux positif' );
actor_dup(null,array($fa));
check_dup( ueb_duplicates_list(array(),'',1,25,'date_creation','DESC')['total']===7, 'Portée limitée : aucun groupe transversal même partiel' );
check_dup( is_wp_error(ueb_duplicates_preview(array('operation'=>'group','group'=>$c1))), 'Action transversale refusée en portée limitée' );
actor_dup(array('ueb_view_students','ueb_view_duplicates'),array($fa));
check_dup( ueb_duplicates_row_meta($wpdb->get_row("SELECT * FROM ueb_preinscriptions WHERE id={$old}"))['manage']===false, 'Lecture seule : aucune action' );
actor_dup();
$stale = ueb_duplicates_preview(array('operation'=>'group','group'=>$old));
act_dup(array('operation'=>'disable','id'=>$old));
check_dup( is_wp_error(ueb_duplicates_commit($stale['token'])), 'Confirmation périmée refusée' );
act_dup(array('operation'=>'reactivate','id'=>$old));
act_dup(array('operation'=>'group','group'=>$p1));
check_dup( $wpdb->get_var("SELECT statut FROM ueb_preinscriptions WHERE id={$p1}")==='doublon_desactive', 'Probable traité manuellement par groupe' );
act_dup(array('operation'=>'group','group'=>$c1));
check_dup( $wpdb->get_var("SELECT statut FROM ueb_preinscriptions WHERE id={$c1}")==='doublon_desactive', 'Transversal traité manuellement par portée globale' );
// Groupe traversant une page : même identité et référence au dossier gardé.
for($i=0;$i<30;$i++) add_dup(array('email'=>'large@example.test'));
scan_dup();
$pg1=ueb_duplicates_list(array(),'TEST-DUP',1,25,'date_creation','DESC');
$pg2=ueb_duplicates_list(array(),'TEST-DUP',2,25,'date_creation','DESC');
check_dup( $pg1['rows'][24]->duplicate_group === $pg2['rows'][0]->duplicate_group, 'Groupe continu identifié sur deux pages' );
check_dup( $pg1['rows'][24]->keeper_number === $pg2['rows'][0]->keeper_number, 'Référence du gardien rappelée sur chaque page' );
if ( in_array('--volume',$argv,true) ) {
    $start=microtime(true);
    for($batch=0;$batch<20;$batch++) {
        $values=array();
        for($i=0;$i<500;$i++) { $n=$batch*500+$i; $values[]=$wpdb->prepare('(%s,%s,%d,%s)', 'VOLUME-'.$n, 'volume'.intdiv($n,2).'@example.test', $fa, 'soumis'); }
        sql_dup('INSERT INTO ueb_preinscriptions (numero_dossier,email,faculte_id,statut) VALUES '.implode(',',$values));
    }
    scan_dup();
    $indexed=microtime(true);
    $volume=ueb_duplicates_list(array(),'',1,25,'date_creation','DESC');
    check_dup( (int)$volume['duplicates']['groups_count']>=5000, 'Volume : 10 000 dossiers / 5 000 groupes' );
    echo 'MESURE : index+groupes '.round($indexed-$start,3).' s ; liste '.round(microtime(true)-$indexed,3)." s\n";
}
check_dup( !$wpdb->last_error, 'Aucune erreur SQL' );
echo "FIN : tables temporaires uniquement, aucune donnée réelle modifiée.\n";
