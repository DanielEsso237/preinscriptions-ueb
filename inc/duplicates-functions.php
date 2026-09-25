<?php
/** Doublons administratifs : index dérivé, décisions réversibles et journal immuable. */
if ( ! defined( 'ABSPATH' ) ) exit;

/** Prédicat UNIQUE de population statistique ; alias exclusivement fourni par le code. */
function ueb_stats_population_sql( $alias = 'p' ) {
    return "{$alias}.statut <> 'doublon_desactive'";
}

function ueb_duplicates_settings() {
    return array_merge( array( 'email' => true, 'phone' => true, 'identity' => true, 'country' => '237' ), (array) get_option( 'ueb_duplicates_settings', array() ) );
}

/** Migration indépendante, additive et rejouable. Aucun dossier n'est supprimé. */
function ueb_duplicates_migrate() {
    if ( '1' === get_option( 'ueb_duplicates_version' ) ) return true;
    global $wpdb;
    $schemas = array(
        'index' => 'id INT UNSIGNED NOT NULL, source_updated DATETIME NOT NULL, PRIMARY KEY(id)',
        'keys' => 'dossier_id INT UNSIGNED NOT NULL, kind VARCHAR(12) NOT NULL, token CHAR(64) NOT NULL, PRIMARY KEY(dossier_id,kind,token), KEY match_key(kind,token,dossier_id)',
        'members' => 'dossier_id INT UNSIGNED NOT NULL, group_id INT UNSIGNED NOT NULL, strong_id INT UNSIGNED NOT NULL, PRIMARY KEY(dossier_id), KEY group_lookup(group_id,dossier_id)',
        'groups' => 'id INT UNSIGNED NOT NULL, keeper_id INT UNSIGNED NOT NULL, confidence VARCHAR(12) NOT NULL, cross_scope TINYINT NOT NULL, size INT UNSIGNED NOT NULL, signature CHAR(64) NOT NULL, PRIMARY KEY(id), KEY signature(signature)',
        'dismissals' => 'batch CHAR(36) NOT NULL, signature CHAR(64) NOT NULL, actor_id BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(signature)',
        'state' => 'dossier_id INT UNSIGNED NOT NULL, previous_status VARCHAR(24) NOT NULL, revision BIGINT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY(dossier_id)',
        'audit' => 'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, batch CHAR(36) NOT NULL, dossier_id INT UNSIGNED DEFAULT NULL, faculte_id INT UNSIGNED DEFAULT NULL, actor_id BIGINT UNSIGNED NOT NULL, operation VARCHAR(24) NOT NULL, before_status VARCHAR(24) NOT NULL, after_status VARCHAR(24) NOT NULL, reason TEXT NOT NULL, created_at DATETIME NOT NULL, revision BIGINT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY(id), KEY batch_lookup(batch), KEY scope_history(faculte_id,id), KEY dossier_history(dossier_id,id)',
    );
    foreach ( $schemas as $suffix => $definition ) {
        if ( false === $wpdb->query( "CREATE TABLE IF NOT EXISTS ueb_duplicate_{$suffix} ({$definition}) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" ) ) return false;
    }
    if ( false === $wpdb->query( "ALTER TABLE ueb_preinscriptions MODIFY statut ENUM('brouillon','soumis','doublon_desactive') NOT NULL DEFAULT 'brouillon'" ) ) return false;
    if ( ! $wpdb->get_var( "SHOW INDEX FROM ueb_preinscriptions WHERE Key_name = 'ueb_duplicate_status'" ) && false === $wpdb->query( 'ALTER TABLE ueb_preinscriptions ADD INDEX ueb_duplicate_status (statut,faculte_id,date_creation,id)' ) ) return false;
    update_option( 'ueb_duplicates_version', '1', false );
    return true;
}
// Sur « init », comme ueb_access_migrate() : les rôles métier ne passent jamais
// par wp-admin — le portail les en redirige dès admin_init priorité 1 — donc un
// accrochage à admin_init laissait les tables non créées pour toute une équipe
// qui ne travaille que depuis le front.
add_action( 'init', function() {
    if ( is_user_logged_in() && ueb_access_has( array( 'ueb_view_students', 'ueb_view_duplicates' ) ) ) ueb_duplicates_migrate();
}, 30 );

function ueb_duplicates_normalize( $value ) {
    return preg_replace( '/[^a-z0-9]/', '', strtolower( remove_accents( trim( (string) $value ), 'fr_FR' ) ) );
}
function ueb_duplicates_phone( $value, $country = '237' ) {
    $digits = preg_replace( '/\D/', '', (string) $value );
    if ( str_starts_with( $digits, '00' ) ) $digits = substr( $digits, 2 );
    if ( strlen( $digits ) === 9 ) $digits = $country . $digits;
    return strlen( $digits ) >= 10 && strlen( $digits ) <= 15 ? $digits : '';
}
function ueb_duplicates_keys( $row, $phones, $settings ) {
    $keys = array();
    // La ponctuation d'un email distingue parfois deux personnes : la confiance
    // reste une aide à la revue, pas une preuve légale d'identité.
    if ( $settings['email'] && is_email( trim( (string) $row->email ) ) ) $keys['email'][] = ueb_duplicates_normalize( $row->email );
    if ( $settings['phone'] ) foreach ( $phones as $phone ) {
        $key = ueb_duplicates_phone( $phone, $settings['country'] );
        if ( $key ) $keys['phone'][] = $key;
    }
    $names = array( ueb_duplicates_normalize( $row->nom ), ueb_duplicates_normalize( $row->prenom ) );
    if ( $settings['identity'] && $names[0] && $names[1] && $row->date_naissance && '0000-00-00' !== $row->date_naissance ) {
        sort( $names, SORT_STRING ); // Tolère l'inversion nom/prénom sans concaténation ambiguë.
        $keys['identity'][] = implode( '|', $names ) . '|' . $row->date_naissance;
    }
    return $keys;
}
function ueb_duplicates_query( $sql ) {
    global $wpdb;
    $result = $wpdb->query( $sql );
    if ( false === $result ) throw new RuntimeException( 'Le traitement des doublons a échoué. Réessayez.' );
    return $result;
}
function ueb_duplicates_lock() {
    global $wpdb;
    return 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', 'ueb_duplicates_' . get_current_blog_id() ) );
}
function ueb_duplicates_unlock() {
    global $wpdb;
    $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', 'ueb_duplicates_' . get_current_blog_id() ) );
}

/** Une passe de normalisation bornée. Les comparaisons et composantes sont SQL,
 * jamais une double boucle PHP sur les candidats. Appel admin uniquement. */
function ueb_duplicates_index_batch( $limit = 500 ) {
    global $wpdb;
    $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT p.* FROM ueb_preinscriptions p LEFT JOIN ueb_duplicate_index i ON i.id=p.id WHERE i.id IS NULL OR i.source_updated<>p.date_modification ORDER BY p.id LIMIT %d', $limit ) );
    if ( ! $rows ) return 0;
    $ids = implode( ',', array_map( 'intval', wp_list_pluck( $rows, 'id' ) ) );
    $phones = array();
    foreach ( $wpdb->get_results( "SELECT preinscription_id, numero FROM ueb_preinscriptions_telephones WHERE type='candidat' AND preinscription_id IN ({$ids})" ) as $phone ) $phones[ $phone->preinscription_id ][] = $phone->numero;
    $settings = ueb_duplicates_settings();
    ueb_duplicates_query( 'START TRANSACTION' );
    try {
        // Seules les clés dérivées sont remplacées ; aucun enregistrement métier.
        ueb_duplicates_query( "DELETE FROM ueb_duplicate_keys WHERE dossier_id IN ({$ids})" );
        $values = array(); $indexed = array();
        foreach ( $rows as $row ) {
            foreach ( ueb_duplicates_keys( $row, $phones[ $row->id ] ?? array(), $settings ) as $kind => $tokens ) foreach ( array_unique( $tokens ) as $token ) $values[] = $wpdb->prepare( '(%d,%s,%s)', $row->id, $kind, hash( 'sha256', $token ) );
            $indexed[] = $wpdb->prepare( '(%d,%s)', $row->id, $row->date_modification );
        }
        if ( $values ) ueb_duplicates_query( 'INSERT IGNORE INTO ueb_duplicate_keys (dossier_id,kind,token) VALUES ' . implode( ',', $values ) );
        ueb_duplicates_query( 'REPLACE INTO ueb_duplicate_index (id,source_updated) VALUES ' . implode( ',', $indexed ) );
        update_option( 'ueb_duplicates_dirty', 1, false );
        ueb_duplicates_query( 'COMMIT' );
    } catch ( Throwable $e ) { $wpdb->query( 'ROLLBACK' ); throw $e; }
    return count( $rows );
}

/** Propagation de labels via agrégats indexés, sans jointure quadratique par paire.
 * Les liens probables ne rendent pas certaine une composante forte distincte. */
function ueb_duplicates_rebuild() {
    global $wpdb;
    ueb_duplicates_query( 'START TRANSACTION' );
    try {
        ueb_duplicates_query( 'DELETE FROM ueb_duplicate_members' );
        ueb_duplicates_query( 'INSERT INTO ueb_duplicate_members SELECT id,id,id FROM ueb_duplicate_index' );
        foreach ( array( 'strong_id', 'group_id' ) as $column ) {
            $kind = 'strong_id' === $column ? "WHERE k.kind <> 'identity'" : '';
            $changed = 0;
            for ( $round = 0; $round < 1000; $round++ ) {
                // Double matérialisation compatible MySQL et MariaDB (table cible).
                $changed = ueb_duplicates_query( "UPDATE ueb_duplicate_members m JOIN (
                    SELECT k.dossier_id, MIN(t.root) root FROM ueb_duplicate_keys k JOIN (
                        SELECT k.kind,k.token,MIN(n.{$column}) root FROM ueb_duplicate_keys k
                        JOIN ueb_duplicate_members n ON n.dossier_id=k.dossier_id {$kind} GROUP BY k.kind,k.token
                    ) t ON t.kind=k.kind AND t.token=k.token GROUP BY k.dossier_id
                ) v ON v.dossier_id=m.dossier_id SET m.{$column}=v.root WHERE m.{$column}>v.root" );
                if ( ! $changed ) break;
            }
            if ( $changed ) throw new RuntimeException( 'Groupe trop complexe : aucun résultat partiel publié.' );
        }
        ueb_duplicates_query( 'SET SESSION group_concat_max_len=16777216' );
        ueb_duplicates_query( 'DELETE FROM ueb_duplicate_groups' );
        ueb_duplicates_query( "INSERT INTO ueb_duplicate_groups (id,keeper_id,confidence,cross_scope,size,signature)
            SELECT m.group_id,CAST(SUBSTRING_INDEX(GROUP_CONCAT(p.id ORDER BY p.date_creation DESC,p.id DESC),',',1) AS UNSIGNED),
            IF(COUNT(DISTINCT m.strong_id)=1,'certain','probable'),COUNT(DISTINCT COALESCE(p.faculte_id,0))>1,COUNT(*),
            SHA2(GROUP_CONCAT(p.id ORDER BY p.id),256)
            FROM ueb_duplicate_members m JOIN ueb_preinscriptions p ON p.id=m.dossier_id GROUP BY m.group_id HAVING COUNT(*)>1" );
        update_option( 'ueb_duplicates_dirty', 0, false );
        update_option( 'ueb_duplicates_generation', wp_generate_uuid4(), false );
        ueb_duplicates_query( 'COMMIT' );
    } catch ( Throwable $e ) { $wpdb->query( 'ROLLBACK' ); throw $e; }
}

/** Une analyse en cours ne peut jamais servir à une mutation. */
function ueb_duplicates_pending() {
    global $wpdb;
    return get_option( 'ueb_duplicates_dirty' ) || $wpdb->get_var( 'SELECT p.id FROM ueb_preinscriptions p LEFT JOIN ueb_duplicate_index i ON i.id=p.id WHERE i.id IS NULL OR i.source_updated<>p.date_modification LIMIT 1' );
}
function ueb_duplicates_scope_sql( $mutate = false ) {
    $caps = array( 'ueb_view_students', 'ueb_view_duplicates' );
    if ( $mutate ) $caps[] = 'ueb_manage_duplicates';
    $scope = ueb_access_sql( 'p.faculte_id', $caps );
    if ( null !== ueb_access_scope( $caps ) ) $scope .= ' AND g.cross_scope=0';
    return $scope;
}
function ueb_duplicates_join_sql() {
    return ' JOIN ueb_duplicate_members m ON m.dossier_id=p.id JOIN ueb_duplicate_groups g ON g.id=m.group_id LEFT JOIN ueb_duplicate_dismissals d ON d.signature=g.signature ';
}

function ueb_duplicates_list( $filters, $search, $page, $per_page, $orderby, $order ) {
    global $wpdb;
    if ( ! ueb_access_has( array( 'ueb_view_students', 'ueb_view_duplicates' ) ) ) return new WP_Error( 'denied', 'Accès refusé.' );
    $filters['duplicate_status'] = 'all';
    $caps = array_unique( array_merge( array( 'ueb_view_students', 'ueb_view_duplicates' ), ueb_access_endpoint_caps() ) );
    $clause = ueb_admin_build_where( $filters, $caps, true );
    // ueb_duplicates_scope_sql() pose déjà la frontière inter-établissements
    // pour une portée limitée : ne pas la répéter ici.
    $where = $clause['where'] . ' AND d.signature IS NULL AND ' . ueb_duplicates_scope_sql();
    // Les paramètres du filtre sont substitués AVANT d'ajouter la recherche :
    // le motif LIKE contient des « % » littéraux, qu'un prepare() ultérieur
    // relirait comme des marqueurs et qui casseraient la requête dès qu'une
    // recherche et un filtre paramétré sont actifs en même temps.
    if ( $clause['params'] ) $where = $wpdb->prepare( $where, $clause['params'] );
    if ( $search !== '' ) {
        $like = '%' . $wpdb->esc_like( $search ) . '%';
        $where .= $wpdb->prepare( ' AND (p.nom LIKE %s OR p.prenom LIKE %s OR p.numero_dossier LIKE %s)', $like, $like, $like );
    }
    $join = ueb_duplicates_join_sql();
    // Les filtres choisissent les groupes. Leurs autres dossiers restent visibles
    // pour que le plus récent et les différences soient toujours vérifiables.
    $matched = "SELECT DISTINCT g.id FROM ueb_preinscriptions p {$join} WHERE {$where}";
    $base = "FROM ueb_preinscriptions p {$join} JOIN ({$matched}) selected ON selected.id=g.id";
    $summary = $wpdb->get_row( "SELECT COUNT(*) total,COUNT(DISTINCT g.id) groups_count,SUM(p.id<>g.keeper_id AND p.statut<>'doublon_desactive') old_count {$base}", ARRAY_A );
    $cols = ueb_admin_colonnes_triables();
    $sort = $cols[ $orderby ] ?? $cols['date_creation'];
    // Ordre des groupes par valeur extrême du champ demandé ; ordre interne fixe.
    $dir = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';
    $agg = 'ASC' === $dir ? 'MIN' : 'MAX';
    $group_sort = "SELECT g.id,{$agg}({$sort}) sort_value {$base} LEFT JOIN ueb_facultes f ON f.id=p.faculte_id LEFT JOIN ueb_filieres fi1 ON fi1.id=p.filiere_1_id GROUP BY g.id";
    $total = (int) $summary['total']; $page = max( 1, min( $page, max( 1, (int) ceil( $total / $per_page ) ) ) );
    $rows = $wpdb->get_results( $wpdb->prepare( "SELECT p.*,f.nom_fr faculte_nom,fi1.libelle filiere1_libelle,g.id duplicate_group,g.keeper_id,g.confidence,g.cross_scope,g.size,
        (SELECT COUNT(*) FROM ueb_duplicate_members m2 JOIN ueb_preinscriptions p2 ON p2.id=m2.dossier_id
         WHERE m2.group_id=m.group_id AND (p2.date_creation>p.date_creation OR (p2.date_creation=p.date_creation AND p2.id>p.id))) group_rank,
        keeper.numero_dossier keeper_number,keeper.nom keeper_nom,keeper.prenom keeper_prenom,keeper.email keeper_email,keeper.date_naissance keeper_birth,keeper.faculte_id keeper_faculte,keeper.filiere_1_id keeper_filiere,
        (SELECT GROUP_CONCAT(t.numero SEPARATOR ' · ') FROM ueb_preinscriptions_telephones t WHERE t.preinscription_id=p.id AND t.type='candidat') phones
        {$base} JOIN ({$group_sort}) gs ON gs.id=g.id JOIN ueb_preinscriptions keeper ON keeper.id=g.keeper_id
        LEFT JOIN ueb_facultes f ON f.id=p.faculte_id LEFT JOIN ueb_filieres fi1 ON fi1.id=p.filiere_1_id
        ORDER BY gs.sort_value {$dir},g.id DESC,p.date_creation DESC,p.id DESC LIMIT %d OFFSET %d", $per_page, ( $page - 1 ) * $per_page ) );
    return array( 'rows' => $rows, 'total' => $total, 'page' => $page, 'par_page' => $per_page, 'nb_pages' => (int) ceil( $total / $per_page ), 'duplicates' => $summary, 'matched_sql' => $matched );
}

function ueb_duplicates_invalidate_stats() {
    // Aucun transient statistique historique : namespace versionné pour les futurs
    // consommateurs ; ne pas vider les caches d'authentification du portail.
    update_option( 'ueb_stats_revision', wp_generate_uuid4(), false );
    delete_transient( 'ueb_admin_stats' );
    delete_transient( 'ueb_effectifs' );
    delete_transient( 'ueb_portal_stats' );
    do_action( 'ueb_stats_invalidated' );
}

/** Caches de page : groupe et appartenance inter-établissements par dossier. */
function &ueb_duplicates_cache( $bucket ) {
    static $cache = array( 'group' => array(), 'cross' => array() );
    return $cache[ $bucket ];
}

/**
 * Charge en DEUX requêtes le contexte doublons de toute une page de dossiers.
 *
 * Sans ce préchargement, ueb_duplicates_row_meta() interrogeait la base une à
 * deux fois par ligne, soit jusqu'à cinquante requêtes pour une page de
 * vingt-cinq dossiers — et cela sur la liste ordinaire, celle que l'équipe
 * ouvre en permanence, pas seulement sur le filtre doublons.
 *
 * @param int[] $ids Identifiants des dossiers affichés.
 */
function ueb_duplicates_preload( $ids ) {
    global $wpdb;
    if ( ! ueb_access_has( array( 'ueb_view_students', 'ueb_view_duplicates' ) ) || '1' !== get_option( 'ueb_duplicates_version' ) ) return;

    $groups =& ueb_duplicates_cache( 'group' );
    $cross  =& ueb_duplicates_cache( 'cross' );
    $ids    = array_values( array_diff( array_filter( array_map( 'absint', (array) $ids ) ), array_keys( $groups ) ) );
    if ( ! $ids ) return;

    $list = implode( ',', $ids );
    // L'absence de groupe est mémorisée elle aussi : sinon chaque dossier sans
    // doublon reprovoquerait une requête à l'affichage suivant.
    foreach ( $ids as $id ) { $groups[ $id ] = null; $cross[ $id ] = false; }

    $scope = ueb_duplicates_scope_sql();
    $join  = ueb_duplicates_join_sql();
    foreach ( (array) $wpdb->get_results( "SELECT p.id,g.id duplicate_group,g.keeper_id,g.confidence,g.cross_scope,g.size,k.numero_dossier keeper_number
        FROM ueb_preinscriptions p {$join} JOIN ueb_preinscriptions k ON k.id=g.keeper_id
        WHERE p.id IN ({$list}) AND d.signature IS NULL AND {$scope}" ) as $found ) {
        $groups[ (int) $found->id ] = $found;
    }

    // Un dossier peut appartenir à un groupe inter-établissements même quand ce
    // groupe est hors de la portée : la restriction de gestion doit le savoir.
    foreach ( (array) $wpdb->get_col( "SELECT m.dossier_id FROM ueb_duplicate_members m
        JOIN ueb_duplicate_groups g ON g.id=m.group_id
        WHERE m.dossier_id IN ({$list}) AND g.cross_scope=1" ) as $id ) {
        $cross[ (int) $id ] = true;
    }
}

function ueb_duplicates_row_meta( $row ) {
    // Plus aucune requête ici : tout vient du préchargement de la page.
    if ( ! ueb_access_has( array( 'ueb_view_students', 'ueb_view_duplicates' ) ) || '1' !== get_option( 'ueb_duplicates_version' ) ) return null;
    if ( ! isset( $row->duplicate_group ) ) {
        ueb_duplicates_preload( array( $row->id ) );
        $groups =& ueb_duplicates_cache( 'group' );
        $group = $groups[ (int) $row->id ] ?? null;
        if ( $group ) foreach ( get_object_vars( $group ) as $key => $value ) {
            if ( 'id' !== $key ) $row->$key = $value;
        }
    }
    $can_manage = ueb_access_contains( array( 'ueb_view_students', 'ueb_view_duplicates', 'ueb_manage_duplicates' ), $row->faculte_id );
    if ( ! empty( $row->cross_scope ) && null !== ueb_access_scope( array( 'ueb_view_students', 'ueb_view_duplicates', 'ueb_manage_duplicates' ) ) ) $can_manage = false;
    if ( $can_manage && null !== ueb_access_scope( array( 'ueb_view_students', 'ueb_view_duplicates', 'ueb_manage_duplicates' ) ) ) {
        $cross =& ueb_duplicates_cache( 'cross' );
        if ( ! empty( $cross[ (int) $row->id ] ) ) $can_manage = false;
    }
    // Un dossier désactivé peut être réactivé même après exclusion du groupe.
    if ( ! isset( $row->duplicate_group ) ) return array( 'manage' => $can_manage, 'disabled' => 'doublon_desactive' === $row->statut );
    $diffs = array();
    foreach ( array( 'nom' => 'keeper_nom', 'prenom' => 'keeper_prenom', 'email' => 'keeper_email', 'date_naissance' => 'keeper_birth', 'faculte_id' => 'keeper_faculte', 'filiere_1_id' => 'keeper_filiere' ) as $field => $ref ) {
        if ( isset( $row->$ref ) && ueb_duplicates_normalize( $row->$field ) !== ueb_duplicates_normalize( $row->$ref ) ) $diffs[] = $field;
    }
    return array( 'group' => (int) $row->duplicate_group, 'rank' => (int) ( $row->group_rank ?? 0 ), 'keeper' => (int) $row->keeper_id === (int) $row->id, 'keeper_number' => $row->keeper_number,
        'confidence' => $row->confidence, 'cross' => (bool) $row->cross_scope, 'size' => (int) $row->size, 'manage' => $can_manage,
        'disabled' => 'doublon_desactive' === $row->statut, 'email' => $row->email ?? '', 'birth' => $row->date_naissance ?? '', 'phones' => $row->phones ?? '', 'differences' => $diffs );
}
function ueb_duplicates_hidden_count( $filters, $search = '' ) {
    global $wpdb;
    $filters['duplicate_status'] = 'disabled';
    $clause = ueb_admin_build_where( $filters, ueb_access_endpoint_caps(), true );
    $where = $clause['where'];
    if ( $clause['params'] ) $where = $wpdb->prepare( $where, $clause['params'] );
    if ( $search !== '' ) {
        $like = '%' . $wpdb->esc_like( $search ) . '%';
        $where .= $wpdb->prepare( ' AND (p.nom LIKE %s OR p.prenom LIKE %s OR p.numero_dossier LIKE %s)', $like, $like, $like );
    }
    return (int) $wpdb->get_var( "SELECT COUNT(*) FROM ueb_preinscriptions p WHERE {$where}" );
}

/** Retour arrière NON destructif : restaure les statuts, conserve tables/index
 * et audit. Le code doit rester chargé le temps d'exécuter cette fonction CLI. */
function ueb_duplicates_rollback() {
    global $wpdb;
    if ( ! current_user_can( 'manage_options' ) ) return new WP_Error( 'denied', 'Accès refusé.' );
    if ( ! ueb_duplicates_lock() ) return new WP_Error( 'busy', 'Traitement en cours.' );
    try {
        ueb_duplicates_query( 'START TRANSACTION' );
        $batch = wp_generate_uuid4();
        ueb_duplicates_query( "UPDATE ueb_duplicate_state s JOIN ueb_preinscriptions p ON p.id=s.dossier_id SET s.revision=s.revision+1 WHERE p.statut='doublon_desactive'" );
        ueb_duplicates_query( $wpdb->prepare( "INSERT INTO ueb_duplicate_audit (batch,dossier_id,faculte_id,actor_id,operation,before_status,after_status,reason,created_at,revision) SELECT %s,p.id,p.faculte_id,%d,'rollback',p.statut,s.previous_status,'Retour arrière de la migration',NOW(),s.revision FROM ueb_preinscriptions p JOIN ueb_duplicate_state s ON s.dossier_id=p.id WHERE p.statut='doublon_desactive'", $batch, get_current_user_id() ) );
        ueb_duplicates_query( "UPDATE ueb_preinscriptions p JOIN ueb_duplicate_state s ON s.dossier_id=p.id SET p.statut=s.previous_status,p.date_modification=p.date_modification WHERE p.statut='doublon_desactive'" );
        ueb_duplicates_query( 'COMMIT' );
        ueb_duplicates_invalidate_stats();
        return true;
    } catch ( Throwable $e ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'rollback', $e->getMessage() ); }
    finally { ueb_duplicates_unlock(); }
}
