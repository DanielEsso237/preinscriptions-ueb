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

/**
 * Répartition par faculté.
 *
 * Renvoie aussi le sigle (FS, FALSH, FSEG, FSJP) : les intitulés complets
 * font jusqu'à 45 caractères et deux d'entre eux commencent par « Faculté
 * des Sciences… ». Tronqués dans une légende, ils deviennent impossibles à
 * distinguer — les graphiques affichent donc le sigle, et gardent le nom
 * complet pour l'infobulle.
 *
 * @return array Liste d'objets { label, code, total }.
 */
function ueb_admin_stats_par_faculte( $filters = array() ) {
    global $wpdb;
    $clause = ueb_admin_build_where( $filters );
    $sql = "SELECT f.nom_fr AS label, f.code AS code, COUNT(*) AS total
            FROM ueb_preinscriptions p
            JOIN ueb_facultes f ON f.id = p.faculte_id
            WHERE {$clause['where']}
            GROUP BY f.id, f.nom_fr, f.code
            ORDER BY total DESC";
    if ( $clause['params'] ) {
        $sql = $wpdb->prepare( $sql, $clause['params'] );
    }
    return $wpdb->get_results( $sql );
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
    return ueb_admin_stats_generique( 'ueb_langues', 'premiere_langue_id', $filters, 'libelle' );
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
 * Répartition par statut étudiant. La table s'appelle ueb_statuts_etudiants
 * (pluriel), conforme à sa définition dans db-schema.php et à la contrainte
 * fk_pi_statut_etudiant qui la référence.
 */
function ueb_admin_stats_par_statut_etudiant( $filters = array() ) {
    return ueb_admin_stats_generique( 'ueb_statuts_etudiants', 'statut_etudiant_id', $filters, 'libelle' );
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
    $sql = "SELECT f.nom_fr AS label, f.code AS code,
                   SUM(CASE WHEN p.sexe = 'M' THEN 1 ELSE 0 END) AS hommes,
                   SUM(CASE WHEN p.sexe = 'F' THEN 1 ELSE 0 END) AS femmes
            FROM ueb_preinscriptions p
            JOIN ueb_facultes f ON f.id = p.faculte_id
            WHERE {$clause['where']}
            GROUP BY f.id, f.nom_fr, f.code
            ORDER BY f.nom_fr ASC";
    if ( $clause['params'] ) {
        $sql = $wpdb->prepare( $sql, $clause['params'] );
    }
    return $wpdb->get_results( $sql );
}

/**
 * Évolution du nombre de dossiers par jour.
 *
 * Les jours sans aucun dossier sont réinjectés à 0 : sans cela, Chart.js
 * relie directement deux dates éloignées et la courbe ment sur le rythme
 * réel des dépôts (un trou de 5 jours ressemble à une pente douce).
 *
 * @param array $filters
 * @param int   $max_jours Fenêtre maximale affichée, en jours.
 * @return array Liste d'objets { label: 'YYYY-MM-DD', total: int }.
 */
function ueb_admin_stats_evolution( $filters = array(), $max_jours = 90 ) {
    global $wpdb;
    $clause = ueb_admin_build_where( $filters );
    $sql = "SELECT DATE(date_creation) AS label, COUNT(*) AS total
            FROM ueb_preinscriptions p WHERE {$clause['where']}
            GROUP BY DATE(date_creation) ORDER BY label ASC";
    if ( $clause['params'] ) {
        $sql = $wpdb->prepare( $sql, $clause['params'] );
    }
    $rows = $wpdb->get_results( $sql );

    if ( ! $rows ) {
        return array();
    }

    // Indexe les totaux réels par date, puis balaie la plage jour par jour.
    $par_date = array();
    foreach ( $rows as $row ) {
        $par_date[ $row->label ] = (int) $row->total;
    }

    $debut = new DateTimeImmutable( $rows[0]->label );
    $fin   = new DateTimeImmutable( end( $rows )->label );

    // Fenêtre bornée : au-delà, on ne garde que la fin de période.
    $borne_min = $fin->modify( '-' . max( 1, (int) $max_jours - 1 ) . ' days' );
    if ( $debut < $borne_min ) {
        $debut = $borne_min;
    }

    $serie   = array();
    $courant = $debut;
    while ( $courant <= $fin ) {
        $cle     = $courant->format( 'Y-m-d' );
        $serie[] = (object) array(
            'label' => $cle,
            'total' => isset( $par_date[ $cle ] ) ? $par_date[ $cle ] : 0,
        );
        $courant = $courant->modify( '+1 day' );
    }

    return $serie;
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
 * Jeu complet de KPI de la barre haute du dashboard.
 *
 * Regroupé en une seule fonction (et donc un seul aller-retour AJAX) car
 * les cartes se lisent ensemble : un total sans sa tendance, ou un « créés
 * aujourd'hui » sans comparaison à la veille, n'apprend rien.
 *
 * @param array $filters
 * @return array
 */
function ueb_admin_kpis( $filters = array() ) {
    global $wpdb;
    $clause = ueb_admin_build_where( $filters );
    $where  = $clause['where'];
    $params = $clause['params'];

    $prepare = function ( $sql ) use ( $wpdb, $params ) {
        return $params ? $wpdb->prepare( $sql, $params ) : $sql;
    };

    $total = (int) $wpdb->get_var( $prepare(
        "SELECT COUNT(*) FROM ueb_preinscriptions p WHERE {$where}"
    ) );

    $aujourdhui = (int) $wpdb->get_var( $prepare(
        "SELECT COUNT(*) FROM ueb_preinscriptions p
         WHERE {$where} AND DATE(p.date_creation) = CURDATE()"
    ) );

    $hier = (int) $wpdb->get_var( $prepare(
        "SELECT COUNT(*) FROM ueb_preinscriptions p
         WHERE {$where} AND DATE(p.date_creation) = SUBDATE(CURDATE(), 1)"
    ) );

    // Volume des 7 derniers jours (aujourd'hui inclus) et des 7 précédents,
    // pour exprimer une tendance hebdomadaire plutôt qu'un à-coup quotidien.
    $semaine = (int) $wpdb->get_var( $prepare(
        "SELECT COUNT(*) FROM ueb_preinscriptions p
         WHERE {$where} AND p.date_creation >= SUBDATE(CURDATE(), 6)"
    ) );

    $semaine_precedente = (int) $wpdb->get_var( $prepare(
        "SELECT COUNT(*) FROM ueb_preinscriptions p
         WHERE {$where}
           AND p.date_creation >= SUBDATE(CURDATE(), 13)
           AND p.date_creation <  SUBDATE(CURDATE(), 6)"
    ) );

    // Répartition par sexe, exprimée en part de femmes sur les dossiers
    // dont le sexe est renseigné (les NULL ne doivent pas fausser le ratio).
    $sexes = $wpdb->get_row( $prepare(
        "SELECT SUM(CASE WHEN p.sexe = 'M' THEN 1 ELSE 0 END) AS hommes,
                SUM(CASE WHEN p.sexe = 'F' THEN 1 ELSE 0 END) AS femmes
         FROM ueb_preinscriptions p WHERE {$where}"
    ) );

    $hommes      = $sexes ? (int) $sexes->hommes : 0;
    $femmes      = $sexes ? (int) $sexes->femmes : 0;
    $total_sexes = $hommes + $femmes;

    // Faculté la plus demandée sous le filtre courant.
    $top_faculte = $wpdb->get_row( $prepare(
        "SELECT f.nom_fr AS label, COUNT(*) AS total
         FROM ueb_preinscriptions p
         JOIN ueb_facultes f ON f.id = p.faculte_id
         WHERE {$where}
         GROUP BY f.id, f.nom_fr
         ORDER BY total DESC
         LIMIT 1"
    ) );

    // Mini-série des 14 derniers jours pour les sparklines des cartes.
    $spark_rows = $wpdb->get_results( $prepare(
        "SELECT DATE(p.date_creation) AS jour, COUNT(*) AS total
         FROM ueb_preinscriptions p
         WHERE {$where} AND p.date_creation >= SUBDATE(CURDATE(), 13)
         GROUP BY DATE(p.date_creation)"
    ) );

    $par_jour = array();
    foreach ( $spark_rows as $row ) {
        $par_jour[ $row->jour ] = (int) $row->total;
    }

    $sparkline = array();
    $curseur   = new DateTimeImmutable( 'today' );
    $curseur   = $curseur->modify( '-13 days' );
    for ( $i = 0; $i < 14; $i++ ) {
        $cle         = $curseur->format( 'Y-m-d' );
        $sparkline[] = isset( $par_jour[ $cle ] ) ? $par_jour[ $cle ] : 0;
        $curseur     = $curseur->modify( '+1 day' );
    }

    return array(
        'total'              => $total,
        'aujourdhui'         => $aujourdhui,
        'hier'               => $hier,
        'semaine'            => $semaine,
        'semainePrecedente'  => $semaine_precedente,
        'moyenneJour'        => round( $semaine / 7, 1 ),
        'hommes'             => $hommes,
        'femmes'             => $femmes,
        'partFemmes'         => $total_sexes > 0 ? round( ( $femmes / $total_sexes ) * 100 ) : 0,
        'topFaculte'         => $top_faculte ? $top_faculte->label : '',
        'topFacultePart'     => ( $top_faculte && $total > 0 ) ? round( ( $top_faculte->total / $total ) * 100 ) : 0,
        'sparkline'          => $sparkline,
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
