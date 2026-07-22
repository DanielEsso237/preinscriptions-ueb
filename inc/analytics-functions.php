<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Toutes les fonctions de stats acceptent désormais un tableau $filters
 * (mêmes clés que ueb_admin_build_where(), déjà utilisé par la liste des
 * dossiers), pour que graphiques et liste restent toujours cohérents avec
 * les mêmes filtres actifs.
 */

/**
 * Fonction générique : répartition des dossiers selon une table de
 * référence liée par clé étrangère, avec application des filtres actifs.
 *
 * $table, $fk et $label sont TOUJOURS des littéraux fournis par notre
 * propre code (jamais issus d'une requête utilisateur), donc pas de
 * risque d'injection malgré l'absence de placeholder sur les identifiants.
 *
 * @param string   $table   Table de référence (ex. 'ueb_facultes').
 * @param string   $fk      Colonne de clé étrangère dans ueb_preinscriptions (ex. 'faculte_id').
 * @param array    $filters Filtres actifs du dashboard.
 * @param string   $label   Colonne libellé dans la table de référence (ex. 'nom_fr').
 * @param int|null $limit   Nombre max de lignes (null = pas de limite).
 */
function ueb_admin_stats_generique( $table, $fk, $filters = array(), $label = 'libelle', $limit = null ) {
    global $wpdb;

    $clause    = ueb_admin_build_where( $filters );
    $limit_sql = $limit ? ' LIMIT ' . (int) $limit : '';

    $sql = "SELECT t.`{$label}` AS label, COUNT(*) AS total
            FROM ueb_preinscriptions p
            JOIN `{$table}` t ON t.id = p.`{$fk}`
            WHERE {$clause['where']}
            GROUP BY t.id, t.`{$label}`
            ORDER BY total DESC{$limit_sql}";

    if ( $clause['params'] ) {
        $sql = $wpdb->prepare( $sql, $clause['params'] );
    }

    return $wpdb->get_results( $sql );
}

function ueb_admin_stats_par_faculte( $filters = array() ) {
    return ueb_admin_stats_generique( 'ueb_facultes', 'faculte_id', $filters, 'nom_fr' );
}

function ueb_admin_stats_par_filiere( $filters = array() ) {
    global $wpdb;
    $clause = ueb_admin_build_where( $filters );
    $sql = "SELECT fi.libelle AS label, COUNT(*) AS total
            FROM ueb_preinscriptions p JOIN ueb_filieres fi ON fi.id = p.filiere_1_id
            WHERE {$clause['where']}
            GROUP BY fi.id, fi.libelle ORDER BY total DESC LIMIT 15";
    if ( $clause['params'] ) {
        $sql = $wpdb->prepare( $sql, $clause['params'] );
    }
    return $wpdb->get_results( $sql );
}

function ueb_admin_stats_par_region( $filters = array() ) {
    return ueb_admin_stats_generique( 'ueb_regions', 'region_origine_id', $filters, 'nom' );
}

function ueb_admin_stats_par_departement( $filters = array() ) {
    return ueb_admin_stats_generique( 'ueb_departements', 'departement_origine_id', $filters, 'nom', 15 );
}

function ueb_admin_stats_par_diplome( $filters = array() ) {
    return ueb_admin_stats_generique( 'ueb_diplomes_admission', 'diplome_admission_id', $filters, 'libelle' );
}

function ueb_admin_stats_par_niveau_lmd( $filters = array() ) {
    return ueb_admin_stats_generique( 'ueb_niveaux_lmd', 'niveau_lmd_id', $filters, 'libelle' );
}

function ueb_admin_stats_par_mention( $filters = array() ) {
    return ueb_admin_stats_generique( 'ueb_mentions', 'mention_id', $filters, 'libelle' );
}

function ueb_admin_stats_par_nationalite( $filters = array() ) {
    return ueb_admin_stats_generique( 'ueb_nationalites', 'nationalite_id', $filters, 'nom', 10 );
}

function ueb_admin_stats_par_langue( $filters = array() ) {
    return ueb_admin_stats_generique( 'ueb_langues', 'premiere_langue_id', $filters, 'nom' );
}

function ueb_admin_stats_par_situation_matrimoniale( $filters = array() ) {
    return ueb_admin_stats_generique( 'ueb_situations_matrimoniales', 'situation_matrimoniale_id', $filters, 'libelle' );
}

function ueb_admin_stats_par_statut_socio( $filters = array() ) {
    return ueb_admin_stats_generique( 'ueb_statuts_socio_professionnels', 'statut_socio_professionnel_id', $filters, 'libelle' );
}

function ueb_admin_stats_par_sport( $filters = array() ) {
    return ueb_admin_stats_generique( 'ueb_sports', 'sport_prefere_id', $filters, 'libelle', 10 );
}

function ueb_admin_stats_par_art( $filters = array() ) {
    return ueb_admin_stats_generique( 'ueb_arts', 'art_pratique_id', $filters, 'libelle', 10 );
}

/**
 * Répartition par statut étudiant. Nom de table corrigé : ueb_statuts_etudiant
 * (singulier), conforme à la vraie définition dans db-schema.php et à sa
 * contrainte de clé étrangère — pas "ueb_statuts_etudiants" (pluriel), qui
 * n'existe pas et faisait planter cette stat silencieusement.
 */
function ueb_admin_stats_par_statut_etudiant( $filters = array() ) {
    return ueb_admin_stats_generique( 'ueb_statuts_etudiant', 'statut_etudiant_id', $filters, 'libelle' );
}

function ueb_admin_stats_par_type_formation( $filters = array() ) {
    global $wpdb;
    $clause = ueb_admin_build_where( $filters );
    $sql = "SELECT type_formation AS label, COUNT(*) AS total
            FROM ueb_preinscriptions p WHERE {$clause['where']} GROUP BY type_formation";
    if ( $clause['params'] ) {
        $sql = $wpdb->prepare( $sql, $clause['params'] );
    }
    return $wpdb->get_results( $sql );
}

function ueb_admin_stats_par_handicap( $filters = array() ) {
    global $wpdb;
    $clause = ueb_admin_build_where( $filters );
    $sql = "SELECT handicap AS label, COUNT(*) AS total
            FROM ueb_preinscriptions p WHERE {$clause['where']} AND handicap IS NOT NULL GROUP BY handicap";
    if ( $clause['params'] ) {
        $sql = $wpdb->prepare( $sql, $clause['params'] );
    }
    return $wpdb->get_results( $sql );
}

function ueb_admin_stats_par_sexe( $filters = array() ) {
    global $wpdb;
    $clause = ueb_admin_build_where( $filters );
    $sql = "SELECT sexe AS label, COUNT(*) AS total FROM ueb_preinscriptions p
            WHERE {$clause['where']} AND sexe IS NOT NULL GROUP BY sexe";
    if ( $clause['params'] ) {
        $sql = $wpdb->prepare( $sql, $clause['params'] );
    }
    return $wpdb->get_results( $sql );
}

/**
 * Répartition croisée faculté x sexe, pour le graphe en barres empilées
 * "chart-faculte-sexe" (attendu par page-administration.php, jamais
 * implémenté auparavant).
 *
 * @return array Liste d'objets { label, hommes, femmes }.
 */
function ueb_admin_stats_faculte_sexe( $filters = array() ) {
    global $wpdb;
    $clause = ueb_admin_build_where( $filters );
    $sql = "SELECT f.nom_fr AS label,
                   SUM(CASE WHEN p.sexe = 'M' THEN 1 ELSE 0 END) AS hommes,
                   SUM(CASE WHEN p.sexe = 'F' THEN 1 ELSE 0 END) AS femmes
            FROM ueb_preinscriptions p
            JOIN ueb_facultes f ON f.id = p.faculte_id
            WHERE {$clause['where']}
            GROUP BY f.id, f.nom_fr
            ORDER BY f.nom_fr ASC";
    if ( $clause['params'] ) {
        $sql = $wpdb->prepare( $sql, $clause['params'] );
    }
    return $wpdb->get_results( $sql );
}

function ueb_admin_stats_evolution( $filters = array() ) {
    global $wpdb;
    $clause = ueb_admin_build_where( $filters );
    $sql = "SELECT DATE(date_creation) AS label, COUNT(*) AS total
            FROM ueb_preinscriptions p WHERE {$clause['where']}
            GROUP BY DATE(date_creation) ORDER BY label ASC";
    if ( $clause['params'] ) {
        $sql = $wpdb->prepare( $sql, $clause['params'] );
    }
    return $wpdb->get_results( $sql );
}

/**
 * Chiffres clés affichés en haut du dashboard (cartes KPI), désormais
 * cohérents avec les filtres actifs : "total" et "aujourd'hui" ne portent
 * plus toujours sur l'ensemble de la base, mais sur le sous-ensemble filtré.
 */
function ueb_admin_chiffres_cles( $filters = array() ) {
    global $wpdb;
    $clause = ueb_admin_build_where( $filters );

    $sql_total = "SELECT COUNT(*) FROM ueb_preinscriptions p WHERE {$clause['where']}";
    $sql_today = "SELECT COUNT(*) FROM ueb_preinscriptions p WHERE {$clause['where']} AND DATE(p.date_creation) = CURDATE()";

    if ( $clause['params'] ) {
        $sql_total = $wpdb->prepare( $sql_total, $clause['params'] );
        $sql_today = $wpdb->prepare( $sql_today, $clause['params'] );
    }

    return array(
        'total'      => (int) $wpdb->get_var( $sql_total ),
        'aujourdhui' => (int) $wpdb->get_var( $sql_today ),
    );
}

/**
 * Taux de dossiers par faculté, en pourcentage du total FILTRÉ (pas du
 * total absolu), pour rester cohérent avec le reste du dashboard quand
 * un filtre est actif.
 *
 * @return array Liste d'objets { label, total, pourcentage }.
 */
function ueb_admin_taux_par_faculte( $filters = array() ) {
    $rows  = ueb_admin_stats_par_faculte( $filters );
    $total = array_sum( wp_list_pluck( $rows, 'total' ) );
    foreach ( $rows as $row ) {
        $row->pourcentage = $total > 0 ? round( ( $row->total / $total ) * 100, 1 ) : 0;
    }
    return $rows;
}
