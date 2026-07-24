<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Fonctions de requêtage pour le dashboard admin (page-administration.php) :
 * - listes de référence pour peupler les filtres,
 * - construction de la clause WHERE partagée par la liste ET les stats,
 * - liste filtrée des dossiers,
 * - détail d'un dossier.
 *
 * @package Preinscriptions_UEB
 */

/**
 * Toutes les listes de référence nécessaires pour peupler les <select> de
 * filtres du dashboard. Chargé une seule fois par requête (localisé en JS
 * via wp_localize_script), pour éviter un aller-retour AJAX par select au
 * chargement de la page.
 *
 * Chaque table utilise systématiquement les alias id/libelle, pour que le
 * JS puisse peupler n'importe quel select avec la même fonction fillSelect().
 *
 * @return array
 */
function ueb_admin_get_reference_lists() {
    global $wpdb;

    return array(
        'facultes'                => $wpdb->get_results( "SELECT id, nom_fr AS libelle FROM ueb_facultes ORDER BY nom_fr ASC" ),
        'diplomes'                 => $wpdb->get_results( "SELECT id, libelle FROM ueb_diplomes_admission ORDER BY libelle ASC" ),
        'niveaux_lmd'              => $wpdb->get_results( "SELECT id, libelle FROM ueb_niveaux_lmd ORDER BY ordre ASC" ),
        'mentions'                 => $wpdb->get_results( "SELECT id, libelle FROM ueb_mentions ORDER BY ordre ASC" ),
        'statuts_etudiant'         => $wpdb->get_results( "SELECT id, libelle FROM ueb_statuts_etudiants ORDER BY libelle ASC" ),
        'nationalites'             => $wpdb->get_results( "SELECT id, nom AS libelle FROM ueb_nationalites ORDER BY nom ASC" ),
        'langues'                  => $wpdb->get_results( "SELECT id, libelle FROM ueb_langues ORDER BY libelle ASC" ),
        'situations_matrimoniales' => $wpdb->get_results( "SELECT id, libelle FROM ueb_situations_matrimoniales ORDER BY libelle ASC" ),
        'statuts_socio'            => $wpdb->get_results( "SELECT id, libelle FROM ueb_statuts_socio_professionnels ORDER BY libelle ASC" ),
        'regions'                  => $wpdb->get_results( "SELECT id, nom AS libelle FROM ueb_regions ORDER BY nom ASC" ),
        'sports'                   => $wpdb->get_results( "SELECT id, libelle FROM ueb_sports ORDER BY libelle ASC" ),
        'arts'                     => $wpdb->get_results( "SELECT id, libelle FROM ueb_arts ORDER BY libelle ASC" ),

        // Enums statiques (pas de table de référence dédiée).
        'sexes' => array(
            (object) array( 'id' => 'M', 'libelle' => 'Masculin' ),
            (object) array( 'id' => 'F', 'libelle' => 'Féminin' ),
        ),
        'handicaps' => array(
            (object) array( 'id' => 'oui', 'libelle' => 'Oui' ),
            (object) array( 'id' => 'non', 'libelle' => 'Non' ),
        ),
        'types_formation' => array(
            (object) array( 'id' => 'classique', 'libelle' => 'Classique' ),
            (object) array( 'id' => 'pro', 'libelle' => 'Licence Pro (LP)' ),
        ),
    );
}

/**
 * Construit la clause WHERE (et les paramètres associés à préparer) à
 * partir des filtres bruts envoyés par le dashboard. Partagée par la liste
 * des dossiers ET toutes les fonctions de statistiques, pour que "ce qui
 * est affiché dans le tableau" et "ce qui alimente les graphes" restent
 * toujours cohérents avec les mêmes filtres.
 *
 * Toutes les colonnes filtrables sont des FK directes sur ueb_preinscriptions
 * (alias p) : aucune jointure n'est nécessaire pour filtrer, seulement pour
 * afficher des libellés (fait par les fonctions appelantes).
 *
 * @param array $filters Tableau associatif de filtres (déjà sanitizés côté appelant).
 * @return array { where: string, params: array }
 */
function ueb_admin_build_where( $filters ) {
    $where  = array( '1=1' );
    $params = array();

    // Filtres "= un ID de FK", tous optionnels.
    $fk_filters = array(
        'faculte'                    => 'p.faculte_id',
        'diplome_admission'          => 'p.diplome_admission_id',
        'specialite_diplome'         => 'p.specialite_diplome_id',
        'niveau_lmd'                 => 'p.niveau_lmd_id',
        'mention'                    => 'p.mention_id',
        'statut_etudiant'            => 'p.statut_etudiant_id',
        'nationalite'                => 'p.nationalite_id',
        'premiere_langue'            => 'p.premiere_langue_id',
        'situation_matrimoniale'     => 'p.situation_matrimoniale_id',
        'statut_socio_professionnel' => 'p.statut_socio_professionnel_id',
        'region_origine'             => 'p.region_origine_id',
        'departement_origine'        => 'p.departement_origine_id',
        'commune_origine'            => 'p.commune_origine_id',
        'sport_prefere'              => 'p.sport_prefere_id',
        'art_pratique'               => 'p.art_pratique_id',
    );

    foreach ( $fk_filters as $key => $column ) {
        if ( ! empty( $filters[ $key ] ) ) {
            $where[]  = "{$column} = %d";
            $params[] = absint( $filters[ $key ] );
        }
    }

    // Filière : peut correspondre au 1er, 2e OU 3e choix du candidat.
    if ( ! empty( $filters['filiere'] ) ) {
        $fid      = absint( $filters['filiere'] );
        $where[]  = '(p.filiere_1_id = %d OR p.filiere_2_id = %d OR p.filiere_3_id = %d)';
        $params[] = $fid;
        $params[] = $fid;
        $params[] = $fid;
    }

    if ( ! empty( $filters['type_formation'] ) && in_array( $filters['type_formation'], array( 'classique', 'pro' ), true ) ) {
        $where[]  = 'p.type_formation = %s';
        $params[] = $filters['type_formation'];
    }

    if ( ! empty( $filters['sexe'] ) && in_array( $filters['sexe'], array( 'M', 'F' ), true ) ) {
        $where[]  = 'p.sexe = %s';
        $params[] = $filters['sexe'];
    }

    if ( ! empty( $filters['handicap'] ) && in_array( $filters['handicap'], array( 'oui', 'non' ), true ) ) {
        $where[]  = 'p.handicap = %s';
        $params[] = $filters['handicap'];
    }

    return array( 'where' => implode( ' AND ', $where ), 'params' => $params );
}

/**
 * Liste filtrée des dossiers (onglet "Liste des préinscrits"), avec le
 * total de résultats (pour l'affichage "X dossiers trouvés").
 *
 * Volontairement non paginée : le dashboard est un outil interne, et
 * l'équipe veut voir la liste complète correspondant au filtre, qu'elle
 * fasse 20 ou 500 lignes.
 *
 * @param array $filters
 * @return array { rows: array<object>, total: int }
 */
/**
 * Colonnes sur lesquelles le tableau des dossiers accepte d'être trié.
 * Liste blanche stricte : la clé vient du clic utilisateur sur un en-tête,
 * la valeur est injectée telle quelle dans l'ORDER BY (un identifiant SQL ne
 * peut pas passer par un placeholder $wpdb->prepare).
 *
 * @return array<string,string>
 */
function ueb_admin_colonnes_triables() {
    return array(
        'numero_dossier' => 'p.numero_dossier',
        'nom'            => 'p.nom',
        'prenom'         => 'p.prenom',
        'sexe'           => 'p.sexe',
        'faculte'        => 'f.nom_fr',
        'filiere'        => 'fi1.libelle',
        'date_creation'  => 'p.date_creation',
    );
}

function ueb_admin_get_dossiers_filtres( $filters, $recherche = '', $page = 1, $par_page = 25, $orderby = 'date_creation', $order = 'DESC' ) {
    global $wpdb;

    $clause = ueb_admin_build_where( $filters );
    $where  = $clause['where'];
    $params = $clause['params'];

    // Recherche texte libre : nom, prénom, ou numéro de dossier.
    $recherche = trim( (string) $recherche );
    if ( '' !== $recherche ) {
        $like     = '%' . $wpdb->esc_like( $recherche ) . '%';
        $where   .= ' AND (p.nom LIKE %s OR p.prenom LIKE %s OR p.numero_dossier LIKE %s)';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    // Total de résultats (pour la pagination), indépendant du LIMIT/OFFSET.
    $sql_total = "SELECT COUNT(*) FROM ueb_preinscriptions p WHERE {$where}";
    if ( $params ) {
        $sql_total = $wpdb->prepare( $sql_total, $params );
    }
    $total = (int) $wpdb->get_var( $sql_total );

    $page     = max( 1, absint( $page ) );
    $par_page = max( 1, absint( $par_page ) );
    $offset   = ( $page - 1 ) * $par_page;

    // Tri : la colonne passe par la liste blanche, le sens est réduit à
    // ASC/DESC. Tri secondaire stable sur l'id pour que la pagination ne
    // rejoue jamais deux fois la même ligne en cas d'ex aequo.
    $colonnes = ueb_admin_colonnes_triables();
    $col_sql  = isset( $colonnes[ $orderby ] ) ? $colonnes[ $orderby ] : $colonnes['date_creation'];
    $sens_sql = 'ASC' === strtoupper( (string) $order ) ? 'ASC' : 'DESC';

    $sql = "SELECT p.numero_dossier, p.nom, p.prenom, p.sexe, p.date_creation,
                   f.nom_fr AS faculte_nom, fi1.libelle AS filiere1_libelle
            FROM ueb_preinscriptions p
            LEFT JOIN ueb_facultes f   ON f.id  = p.faculte_id
            LEFT JOIN ueb_filieres fi1 ON fi1.id = p.filiere_1_id
            WHERE {$where}
            ORDER BY {$col_sql} {$sens_sql}, p.id DESC
            LIMIT %d OFFSET %d";

    $params_avec_limite   = $params;
    $params_avec_limite[] = $par_page;
    $params_avec_limite[] = $offset;

    $sql  = $wpdb->prepare( $sql, $params_avec_limite );
    $rows = $wpdb->get_results( $sql );

    return array(
        'rows'      => $rows,
        'total'     => $total,
        'page'      => $page,
        'par_page'  => $par_page,
        'nb_pages'  => (int) ceil( $total / $par_page ),
    );
}

/**
 * Détail complet d'un dossier (fiche + téléphones), à partir de son numéro.
 * Utilisé par le lien "Voir →" de la liste.
 *
 * @param string $numero_dossier
 * @return array|null { dossier: object, telephones: array<object> }
 */
function ueb_admin_get_dossier_detail( $numero_dossier ) {
    global $wpdb;
    $numero_dossier = sanitize_text_field( $numero_dossier );

    $dossier = $wpdb->get_row( $wpdb->prepare(
        "SELECT p.*, f.nom_fr AS faculte_nom, d.libelle AS diplome_libelle,
                s.libelle AS serie_libelle, f1.libelle AS filiere1_libelle, f2.libelle AS filiere2_libelle,
                n.nom AS nationalite_nom, sm.libelle AS situation_libelle,
                r.nom AS region_nom, dep.nom AS departement_nom, c.nom AS commune_nom
         FROM ueb_preinscriptions p
         LEFT JOIN ueb_facultes f ON f.id = p.faculte_id
         LEFT JOIN ueb_diplomes_admission d ON d.id = p.diplome_admission_id
         LEFT JOIN ueb_specialites_diplome s ON s.id = p.specialite_diplome_id
         LEFT JOIN ueb_filieres f1 ON f1.id = p.filiere_1_id
         LEFT JOIN ueb_filieres f2 ON f2.id = p.filiere_2_id
         LEFT JOIN ueb_nationalites n ON n.id = p.nationalite_id
         LEFT JOIN ueb_situations_matrimoniales sm ON sm.id = p.situation_matrimoniale_id
         LEFT JOIN ueb_regions r ON r.id = p.region_origine_id
         LEFT JOIN ueb_departements dep ON dep.id = p.departement_origine_id
         LEFT JOIN ueb_communes c ON c.id = p.commune_origine_id
         WHERE p.numero_dossier = %s",
        $numero_dossier
    ) );

    if ( ! $dossier ) {
        return null;
    }

    $telephones = $wpdb->get_results( $wpdb->prepare(
        "SELECT type, numero FROM ueb_preinscriptions_telephones WHERE preinscription_id = %d",
        $dossier->id
    ) );

    return array( 'dossier' => $dossier, 'telephones' => $telephones );
}