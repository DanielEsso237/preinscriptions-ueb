<?php
/** Endpoints privés : nonce, capabilities, portée et revalidation avant écriture. */
if ( ! defined( 'ABSPATH' ) ) exit;

function ueb_duplicates_target_sql( $input ) {
    global $wpdb;
    $operation = sanitize_key( $input['operation'] ?? '' );
    $scope = ueb_duplicates_scope_sql( true );
    $join = ueb_duplicates_join_sql();
    $id = absint( $input['id'] ?? 0 );
    $group = absint( $input['group'] ?? 0 );
    if ( 'reactivate' === $operation ) {
        $scope = ueb_access_sql( 'p.faculte_id', array( 'ueb_view_students', 'ueb_view_duplicates', 'ueb_manage_duplicates' ) );
        // Même un dossier désactivé reste soumis à la frontière inter-établissements.
        $cross = null === ueb_access_scope( array( 'ueb_view_students', 'ueb_view_duplicates', 'ueb_manage_duplicates' ) ) ? '1=1' : 'NOT EXISTS (SELECT 1 FROM ueb_duplicate_members cm JOIN ueb_duplicate_groups cg ON cg.id=cm.group_id WHERE cm.dossier_id=p.id AND cg.cross_scope=1)';
        return $wpdb->prepare( "SELECT p.id FROM ueb_preinscriptions p WHERE p.id=%d AND p.statut='doublon_desactive' AND {$scope} AND {$cross}", $id );
    }
    if ( 'undo' === $operation ) {
        $batch = sanitize_text_field( $input['batch'] ?? '' );
        $scope = ueb_access_sql( 'p.faculte_id', array( 'ueb_view_students', 'ueb_view_duplicates', 'ueb_manage_duplicates' ) );
        $cross = null === ueb_access_scope( array( 'ueb_view_students', 'ueb_view_duplicates', 'ueb_manage_duplicates' ) ) ? '1=1' : 'NOT EXISTS (SELECT 1 FROM ueb_duplicate_members cm JOIN ueb_duplicate_groups cg ON cg.id=cm.group_id WHERE cm.dossier_id=p.id AND cg.cross_scope=1)';
        // Pas d'écrasement d'une décision ultérieure, même faite par le même agent.
        // Une annulation qui désactive à nouveau ne doit pas toucher le nouveau gardien.
        return $wpdb->prepare( "SELECT p.id FROM ueb_preinscriptions p JOIN ueb_duplicate_audit a ON a.dossier_id=p.id JOIN ueb_duplicate_state s ON s.dossier_id=p.id
            WHERE a.batch=%s AND a.actor_id=%d AND a.created_at>=DATE_SUB(NOW(),INTERVAL 15 MINUTE)
            AND s.revision=a.revision AND p.statut=a.after_status AND {$scope} AND {$cross}
            AND (a.before_status<>'doublon_desactive' OR NOT EXISTS (SELECT 1 FROM ueb_duplicate_groups protect WHERE protect.keeper_id=p.id))", $batch, get_current_user_id() );
    }
    $where = "d.signature IS NULL AND {$scope}";
    if ( 'dismiss' === $operation ) return $wpdb->prepare( "SELECT p.id FROM ueb_preinscriptions p {$join} WHERE {$where} AND g.id=%d", $group );
    $where .= " AND p.id<>g.keeper_id AND p.statut<>'doublon_desactive'";
    if ( 'disable' === $operation ) $where .= $wpdb->prepare( ' AND p.id=%d', $id );
    elseif ( 'group' === $operation ) $where .= $wpdb->prepare( ' AND g.id=%d', $group );
    elseif ( 'bulk' === $operation ) {
        $where .= " AND g.confidence='certain' AND g.cross_scope=0";
        $groups = array_values( array_filter( array_map( 'absint', explode( ',', (string) ( $input['groups'] ?? '' ) ) ) ) );
        if ( $groups ) $where .= ' AND g.id IN (' . implode( ',', $groups ) . ')';
        else {
            $filters = (array) ( $input['filters'] ?? array() );
            $result = ueb_duplicates_list( $filters, sanitize_text_field( $input['recherche'] ?? '' ), 1, 1, 'date_creation', 'DESC' );
            if ( is_wp_error( $result ) ) return $result;
            $where .= ' AND g.id IN (' . $result['matched_sql'] . ')';
        }
    } else return new WP_Error( 'operation', 'Action inconnue.' );
    return "SELECT p.id FROM ueb_preinscriptions p {$join} WHERE {$where}";
}

/** Prévisualisation calculée par le serveur. Le jeton lie compte, critères et
 * empreinte exacte des dossiers ; le client ne peut pas modifier le nombre. */
function ueb_duplicates_preview( $input ) {
    global $wpdb;
    if ( ! ueb_access_has( array( 'ueb_view_students', 'ueb_view_duplicates', 'ueb_manage_duplicates' ) ) ) return new WP_Error( 'denied', 'Accès refusé.' );
    if ( ueb_duplicates_pending() ) return new WP_Error( 'pending', 'Actualisez l’analyse avant cette action.' );
    $sql = ueb_duplicates_target_sql( $input );
    if ( is_wp_error( $sql ) ) return $sql;
    $rows = $wpdb->get_results( "SELECT p.id,p.statut,COALESCE(s.revision,0) revision FROM ueb_preinscriptions p JOIN ({$sql}) target ON target.id=p.id LEFT JOIN ueb_duplicate_state s ON s.dossier_id=p.id ORDER BY p.id", ARRAY_A );
    if ( ! $rows ) return new WP_Error( 'empty', 'Aucun dossier autorisé à modifier. Le groupe a peut-être déjà été traité.' );
    $token = wp_generate_uuid4();
    $plan = array( 'input' => $input, 'actor' => get_current_user_id(), 'hash' => hash( 'sha256', wp_json_encode( $rows ) ), 'generation' => get_option( 'ueb_duplicates_generation' ), 'count' => count( $rows ) );
    set_transient( 'ueb_dup_plan_' . $token, $plan, 5 * MINUTE_IN_SECONDS );
    return array( 'token' => $token, 'count' => count( $rows ), 'operation' => $input['operation'] );
}

function ueb_duplicates_commit( $token, $reason = '' ) {
    global $wpdb;
    if ( ! ueb_access_has( array( 'ueb_view_students', 'ueb_view_duplicates', 'ueb_manage_duplicates' ) ) ) return new WP_Error( 'denied', 'Accès refusé.' );
    $plan = get_transient( 'ueb_dup_plan_' . $token );
    if ( ! $plan || (int) $plan['actor'] !== get_current_user_id() ) return new WP_Error( 'expired', 'Confirmation expirée. Recommencez.' );
    if ( ueb_duplicates_pending() || $plan['generation'] !== get_option( 'ueb_duplicates_generation' ) ) return new WP_Error( 'changed', 'Les dossiers ont changé. Actualisez puis confirmez à nouveau.' );
    $input = $plan['input'];
    $sql = ueb_duplicates_target_sql( $input );
    if ( is_wp_error( $sql ) ) return $sql;
    $operation = sanitize_key( $input['operation'] );
    $batch = wp_generate_uuid4();
    ueb_duplicates_query( 'START TRANSACTION' );
    try {
        $rows = $wpdb->get_results( "SELECT p.id,p.statut,COALESCE(s.revision,0) revision FROM ueb_preinscriptions p JOIN ({$sql}) target ON target.id=p.id LEFT JOIN ueb_duplicate_state s ON s.dossier_id=p.id ORDER BY p.id FOR UPDATE", ARRAY_A );
        if ( hash( 'sha256', wp_json_encode( $rows ) ) !== $plan['hash'] ) throw new RuntimeException( 'La sélection a changé. Confirmez le nouveau nombre de dossiers.' );
        $ids = implode( ',', array_map( 'intval', wp_list_pluck( $rows, 'id' ) ) );
        ueb_duplicates_query( "INSERT INTO ueb_duplicate_state (dossier_id,previous_status,revision) SELECT id,statut,0 FROM ueb_preinscriptions WHERE id IN ({$ids}) ON DUPLICATE KEY UPDATE dossier_id=VALUES(dossier_id)" );
        $undo_join = '';
        if ( 'undo' === $operation ) {
            $undo_join = $wpdb->prepare( ' JOIN ueb_duplicate_audit old ON old.dossier_id=p.id AND old.batch=%s ', $input['batch'] );
            $after = 'old.before_status';
            // Une annulation de faux positif restaure uniquement le signalement.
            ueb_duplicates_query( $wpdb->prepare( 'DELETE FROM ueb_duplicate_dismissals WHERE batch=%s AND actor_id=%d', $input['batch'], get_current_user_id() ) );
        } elseif ( 'dismiss' === $operation ) {
            $after = 'p.statut';
            ueb_duplicates_query( $wpdb->prepare( 'INSERT IGNORE INTO ueb_duplicate_dismissals (signature,actor_id,created_at,batch) SELECT signature,%d,NOW(),%s FROM ueb_duplicate_groups WHERE id=%d', get_current_user_id(), $batch, absint( $input['group'] ) ) );
        } elseif ( 'reactivate' === $operation ) $after = 's.previous_status';
        else $after = "'doublon_desactive'";
        if ( in_array( $operation, array( 'disable', 'group', 'bulk' ), true ) ) ueb_duplicates_query( "UPDATE ueb_duplicate_state s JOIN ueb_preinscriptions p ON p.id=s.dossier_id SET s.previous_status=p.statut WHERE p.id IN ({$ids})" );
        ueb_duplicates_query( "UPDATE ueb_duplicate_state SET revision=revision+1 WHERE dossier_id IN ({$ids})" );
        ueb_duplicates_query( $wpdb->prepare( "INSERT INTO ueb_duplicate_audit (batch,dossier_id,faculte_id,actor_id,operation,before_status,after_status,reason,created_at,revision)
            SELECT %s,p.id,p.faculte_id,%d,%s,p.statut,{$after},%s,NOW(),s.revision FROM ueb_preinscriptions p JOIN ueb_duplicate_state s ON s.dossier_id=p.id {$undo_join} WHERE p.id IN ({$ids})", $batch, get_current_user_id(), $operation, mb_substr( sanitize_textarea_field( $reason ), 0, 2000 ) ) );
        ueb_duplicates_query( $wpdb->prepare( "UPDATE ueb_preinscriptions p JOIN ueb_duplicate_audit a ON a.dossier_id=p.id AND a.batch=%s SET p.statut=a.after_status, p.date_modification=p.date_modification", $batch ) );
        ueb_duplicates_query( 'COMMIT' );
        delete_transient( 'ueb_dup_plan_' . $token );
        ueb_duplicates_invalidate_stats();
        return array( 'count' => count( $rows ), 'batch' => $batch, 'operation' => $operation );
    } catch ( Throwable $e ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'conflict', $e->getMessage() ); }
}

function ueb_duplicates_endpoint() {
    $task = sanitize_key( $_POST['task'] ?? '' );
    $caps = array( 'ueb_view_students', 'ueb_view_duplicates' );
    if ( in_array( $task, array( 'preview', 'commit' ), true ) ) $caps[] = 'ueb_manage_duplicates';
    if ( 'configure' === $task ) $caps[] = 'ueb_configure_duplicates';
    ueb_access_require( $caps, 'ueb_admin_dashboard' );
    if ( ! ueb_duplicates_migrate() ) wp_send_json_error( array( 'message' => 'Migration indisponible.' ), 503 );
    if ( ! ueb_duplicates_lock() ) wp_send_json_error( array( 'message' => 'Une analyse est en cours. Réessayez.' ), 409 );
    try {
        global $wpdb;
        $input = wp_unslash( $_POST );
        if ( 'scan' === $task ) {
            $count = ueb_duplicates_index_batch();
            $more = (bool) $wpdb->get_var( 'SELECT p.id FROM ueb_preinscriptions p LEFT JOIN ueb_duplicate_index i ON i.id=p.id WHERE i.id IS NULL OR i.source_updated<>p.date_modification LIMIT 1' );
            if ( ! $more && get_option( 'ueb_duplicates_dirty' ) ) ueb_duplicates_rebuild();
            $result = array( 'more' => $more, 'processed' => null === ueb_access_scope( $caps ) ? $count : null );
        } elseif ( 'configure' === $task ) {
            if ( null !== ueb_access_scope( $caps ) ) throw new RuntimeException( 'La configuration commune exige la portée tous les établissements.' );
            $settings = array( 'email' => ! empty( $input['email'] ), 'phone' => ! empty( $input['phone'] ), 'identity' => ! empty( $input['identity'] ), 'country' => preg_replace( '/\D/', '', $input['country'] ?? '237' ) );
            if ( ! $settings['email'] && ! $settings['phone'] && ! $settings['identity'] ) throw new RuntimeException( 'Choisissez au moins un critère.' );
            if ( ! preg_match( '/^[1-9][0-9]{0,2}$/', $settings['country'] ) ) throw new RuntimeException( 'Indicatif pays invalide.' );
            update_option( 'ueb_duplicates_settings', $settings, false );
            // Marque l'index dérivé obsolète sans toucher les données du candidat.
            ueb_duplicates_query( "UPDATE ueb_duplicate_index SET source_updated='1970-01-01 00:00:00'" );
            update_option( 'ueb_duplicates_dirty', 1, false );
            $result = array( 'message' => 'Critères enregistrés. Analyse à actualiser.' );
        } elseif ( 'preview' === $task ) {
            $input['filters'] = ueb_admin_ajax_extract_filters();
            $result = ueb_duplicates_preview( $input );
        } elseif ( 'commit' === $task ) $result = ueb_duplicates_commit( sanitize_text_field( $input['token'] ?? '' ), $input['reason'] ?? '' );
        elseif ( 'audit' === $task ) {
            $scope = ueb_access_sql( 'a.faculte_id', $caps );
            if ( null !== ueb_access_scope( $caps ) ) $scope .= ' AND NOT EXISTS (SELECT 1 FROM ueb_duplicate_members m JOIN ueb_duplicate_groups g ON g.id=m.group_id WHERE m.dossier_id=a.dossier_id AND g.cross_scope=1)';
            $before = absint( $input['before'] ?? 0 );
            if ( $before ) $scope .= $wpdb->prepare( ' AND a.id<%d', $before );
            $result = $wpdb->get_results( "SELECT a.*,p.numero_dossier,u.display_name actor FROM ueb_duplicate_audit a LEFT JOIN ueb_preinscriptions p ON p.id=a.dossier_id LEFT JOIN {$wpdb->users} u ON u.ID=a.actor_id WHERE {$scope} ORDER BY a.id DESC LIMIT 50", ARRAY_A );
        } else $result = new WP_Error( 'task', 'Action inconnue.' );
    } catch ( Throwable $e ) { $result = new WP_Error( 'error', $e->getMessage() ); }
    finally { ueb_duplicates_unlock(); }
    if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 409 );
    wp_send_json_success( $result );
}
add_action( 'wp_ajax_ueb_duplicates', 'ueb_duplicates_endpoint' );
