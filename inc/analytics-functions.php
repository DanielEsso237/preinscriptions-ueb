<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Répartition des dossiers par faculté (pour graphe en barres).
 */
function ueb_admin_stats_par_faculte() {
    global $wpdb;
    return $wpdb->get_results(
        "SELECT f.nom_fr AS label, COUNT(*) AS total
         FROM ueb_preinscriptions p JOIN ueb_facultes f ON f.id = p.faculte_id
         GROUP BY f.id, f.nom_fr ORDER BY total DESC"
    );
}

/**
 * Répartition des dossiers par filière (1er choix), pour graphe en barres.
 */
function ueb_admin_stats_par_filiere() {
    global $wpdb;
    return $wpdb->get_results(
        "SELECT fi.libelle AS label, COUNT(*) AS total
         FROM ueb_preinscriptions p JOIN ueb_filieres fi ON fi.id = p.filiere_1_id
         GROUP BY fi.id, fi.libelle ORDER BY total DESC LIMIT 15"
    );
}

/**
 * Répartition des dossiers par région d'origine, pour graphe en barres.
 */
function ueb_admin_stats_par_region() {
    global $wpdb;
    return $wpdb->get_results(
        "SELECT r.nom AS label, COUNT(*) AS total
         FROM ueb_preinscriptions p JOIN ueb_regions r ON r.id = p.region_origine_id
         GROUP BY r.id, r.nom ORDER BY total DESC"
    );
}

/**
 * Répartition des dossiers par sexe, pour graphe en camembert.
 */
function ueb_admin_stats_par_sexe() {
    global $wpdb;
    return $wpdb->get_results(
        "SELECT sexe AS label, COUNT(*) AS total FROM ueb_preinscriptions
         WHERE sexe IS NOT NULL GROUP BY sexe"
    );
}

/**
 * Évolution du nombre d'inscriptions par jour, pour graphe en courbe.
 */
function ueb_admin_stats_evolution() {
    global $wpdb;
    return $wpdb->get_results(
        "SELECT DATE(date_creation) AS label, COUNT(*) AS total
         FROM ueb_preinscriptions GROUP BY DATE(date_creation) ORDER BY label ASC"
    );
}

/**
 * Chiffres clés affichés en haut du dashboard (cartes KPI) :
 * total de dossiers et dossiers créés aujourd'hui.
 */
function ueb_admin_chiffres_cles() {
    global $wpdb;
    return array(
        'total'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM ueb_preinscriptions" ),
        'aujourdhui' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM ueb_preinscriptions WHERE DATE(date_creation) = CURDATE()" ),
    );
}

/**
 * Taux de dossiers par faculté, en pourcentage du total de dossiers.
 * Utilisé pour la carte KPI "Taux par faculté" en haut du dashboard.
 *
 * @return array Liste d'objets { label, total, pourcentage }.
 */
function ueb_admin_taux_par_faculte() {
    global $wpdb;

    $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM ueb_preinscriptions" );

    $rows = $wpdb->get_results(
        "SELECT f.nom_fr AS label, COUNT(*) AS total
         FROM ueb_preinscriptions p JOIN ueb_facultes f ON f.id = p.faculte_id
         GROUP BY f.id, f.nom_fr ORDER BY total DESC"
    );

    foreach ( $rows as $row ) {
        $row->pourcentage = $total > 0 ? round( ( $row->total / $total ) * 100, 1 ) : 0;
    }

    return $rows;
}