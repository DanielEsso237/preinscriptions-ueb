<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Fonction générique : répartition des dossiers selon une table de
 * référence liée par clé étrangère. Réutilisée par toutes les stats
 * "par X" ci-dessous pour éviter de dupliquer la même requête.
 *
 * $table, $fk et $label sont TOUJOURS des littéraux fournis par notre
 * propre code (jamais issus d'une requête utilisateur), donc pas de
 * risque d'injection malgré l'absence de placeholder sur les identifiants.
 *
 * @param string   $table Table de référence (ex. 'ueb_facultes').
 * @param string   $fk    Colonne de clé étrangère dans ueb_preinscriptions (ex. 'faculte_id').
 * @param string   $label Colonne libellé dans la table de référence (ex. 'nom_fr').
 * @param int|null $limit Nombre max de lignes (null = pas de limite).
 */
function ueb_admin_stats_generique( $table, $fk, $label = 'libelle', $limit = null ) {
    global $wpdb;

    $limit_sql = $limit ? ' LIMIT ' . (int) $limit : '';

    return $wpdb->get_results(
        "SELECT t.`{$label}` AS label, COUNT(*) AS total
         FROM ueb_preinscriptions p
         JOIN `{$table}` t ON t.id = p.`{$fk}`
         GROUP BY t.id, t.`{$label}`
         ORDER BY total DESC{$limit_sql}"
    );
}

/**
 * Répartition des dossiers par faculté (pour graphe en barres).
 */
function ueb_admin_stats_par_faculte() {
    return ueb_admin_stats_generique( 'ueb_facultes', 'faculte_id', 'nom_fr' );
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
    return ueb_admin_stats_generique( 'ueb_regions', 'region_origine_id', 'nom' );
}

/**
 * Répartition des dossiers par département d'origine (top 15), pour graphe en barres.
 */
function ueb_admin_stats_par_departement() {
    return ueb_admin_stats_generique( 'ueb_departements', 'departement_origine_id', 'nom', 15 );
}

/**
 * Répartition des dossiers par diplôme d'admission, pour graphe en barres.
 */
function ueb_admin_stats_par_diplome() {
    return ueb_admin_stats_generique( 'ueb_diplomes_admission', 'diplome_admission_id', 'libelle' );
}

/**
 * Répartition des dossiers par niveau LMD, pour graphe en barres.
 */
function ueb_admin_stats_par_niveau_lmd() {
    return ueb_admin_stats_generique( 'ueb_niveaux_lmd', 'niveau_lmd_id', 'libelle' );
}

/**
 * Répartition des dossiers par mention, pour graphe en barres.
 */
function ueb_admin_stats_par_mention() {
    return ueb_admin_stats_generique( 'ueb_mentions', 'mention_id', 'libelle' );
}

/**
 * Répartition des dossiers par nationalité (top 10), pour graphe en barres.
 */
function ueb_admin_stats_par_nationalite() {
    return ueb_admin_stats_generique( 'ueb_nationalites', 'nationalite_id', 'nom', 10 );
}

/**
 * Répartition des dossiers par première langue, pour graphe en camembert.
 */
function ueb_admin_stats_par_langue() {
    return ueb_admin_stats_generique( 'ueb_langues', 'premiere_langue_id', 'nom' );
}

/**
 * Répartition des dossiers par situation matrimoniale, pour graphe en barres.
 */
function ueb_admin_stats_par_situation_matrimoniale() {
    return ueb_admin_stats_generique( 'ueb_situations_matrimoniales', 'situation_matrimoniale_id', 'libelle' );
}

/**
 * Répartition des dossiers par statut socio-professionnel, pour graphe en barres.
 */
function ueb_admin_stats_par_statut_socio() {
    return ueb_admin_stats_generique( 'ueb_statuts_socio_professionnels', 'statut_socio_professionnel_id', 'libelle' );
}

/**
 * Répartition des dossiers par sport préféré (top 10), pour graphe en barres.
 */
function ueb_admin_stats_par_sport() {
    return ueb_admin_stats_generique( 'ueb_sports', 'sport_prefere_id', 'libelle', 10 );
}

/**
 * Répartition des dossiers par art pratiqué (top 10), pour graphe en barres.
 */
function ueb_admin_stats_par_art() {
    return ueb_admin_stats_generique( 'ueb_arts', 'art_pratique_id', 'libelle', 10 );
}

/**
 * Répartition des dossiers par statut étudiant (CEMAC / hors CEMAC), pour graphe en camembert.
 *
 * NOTE : dépend de la table ueb_statuts_etudiant (schéma) — si le nom
 * réel en base diffère (ueb_statuts_etudiants, avec un "s", utilisé par
 * ailleurs dans ajax-functions.php et db-seed.php), cette stat peut
 * remonter vide.
 */
function ueb_admin_stats_par_statut_etudiant() {
    return ueb_admin_stats_generique( 'ueb_statuts_etudiants', 'statut_etudiant_id', 'libelle' );
}

/**
 * Répartition des dossiers par type de formation (classique / pro), pour graphe en camembert.
 */
function ueb_admin_stats_par_type_formation() {
    global $wpdb;
    return $wpdb->get_results(
        "SELECT type_formation AS label, COUNT(*) AS total
         FROM ueb_preinscriptions GROUP BY type_formation"
    );
}

/**
 * Répartition des dossiers par situation de handicap (oui/non), pour graphe en camembert.
 */
function ueb_admin_stats_par_handicap() {
    global $wpdb;
    return $wpdb->get_results(
        "SELECT handicap AS label, COUNT(*) AS total
         FROM ueb_preinscriptions WHERE handicap IS NOT NULL GROUP BY handicap"
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
    $rows  = ueb_admin_stats_par_faculte();

    foreach ( $rows as $row ) {
        $row->pourcentage = $total > 0 ? round( ( $row->total / $total ) * 100, 1 ) : 0;
    }

    return $rows;
}