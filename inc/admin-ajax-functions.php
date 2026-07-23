<?php
/**
 * Endpoints AJAX du dashboard admin (page-administration.php).
 * Séparés de inc/ajax-functions.php (endpoints du formulaire public) car
 * ils exigent une capacité utilisateur ('voir_preinscriptions') en plus
 * du nonce, et utilisent un nonce dédié 'ueb_admin_dashboard'.
 *
 * @package Preinscriptions_UEB
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Vérifie l'accès (connecté + capacité) puis le nonce. Stoppe l'exécution
 * avec une réponse JSON d'erreur sinon. À appeler en tout premier dans
 * chaque handler AJAX de ce fichier.
 */
function ueb_admin_ajax_check_access() {
    if ( ! is_user_logged_in() || ! current_user_can( 'voir_preinscriptions' ) ) {
        wp_send_json_error( array( 'message' => 'Accès refusé.' ), 403 );
    }
    check_ajax_referer( 'ueb_admin_dashboard', 'nonce' );
}

/**
 * Normalise le tableau de filtres reçu en POST (tous optionnels, une
 * valeur vide = "pas de filtre sur ce champ"). Mêmes clés que celles
 * utilisées par ueb_admin_build_where() dans inc/admin-functions.php.
 */
function ueb_admin_ajax_extract_filters() {
    $keys = array(
        'faculte', 'diplome_admission', 'specialite_diplome', 'niveau_lmd', 'mention',
        'statut_etudiant', 'nationalite', 'premiere_langue', 'situation_matrimoniale',
        'statut_socio_professionnel', 'region_origine', 'departement_origine', 'commune_origine',
        'sport_prefere', 'art_pratique', 'filiere', 'type_formation', 'sexe', 'handicap',
    );

    $filters = array();
    foreach ( $keys as $key ) {
        $filters[ $key ] = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
    }

    return $filters;
}

/* ------------------------------------------------------------------ */
/* Onglet "Liste des préinscrits"                                     */
/* ------------------------------------------------------------------ */

function ueb_admin_ajax_get_dossiers() {
    ueb_admin_ajax_check_access();

    $filters   = ueb_admin_ajax_extract_filters();
    $recherche = isset( $_POST['recherche'] ) ? sanitize_text_field( wp_unslash( $_POST['recherche'] ) ) : '';
    $page      = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;

    $result = ueb_admin_get_dossiers_filtres( $filters, $recherche, $page, 25 );

    $rows = array();
    foreach ( $result['rows'] as $row ) {
        $rows[] = array(
            'numero_dossier' => $row->numero_dossier,
            'nom'            => $row->nom,
            'prenom'         => $row->prenom,
            'sexe'           => $row->sexe,
            'faculte'        => $row->faculte_nom,
            'filiere'        => $row->filiere1_libelle,
            'date_creation'  => $row->date_creation,
        );
    }

    wp_send_json_success( array(
        'rows'     => $rows,
        'total'    => $result['total'],
        'page'     => $result['page'],
        'nb_pages' => $result['nb_pages'],
    ) );
}
add_action( 'wp_ajax_ueb_admin_get_dossiers', 'ueb_admin_ajax_get_dossiers' );

function ueb_admin_ajax_get_dossier_detail() {
    ueb_admin_ajax_check_access();

    $numero = isset( $_POST['numero_dossier'] ) ? sanitize_text_field( wp_unslash( $_POST['numero_dossier'] ) ) : '';
    if ( ! $numero ) {
        wp_send_json_error( array( 'message' => 'Numéro de dossier manquant.' ) );
    }

    $detail = ueb_admin_get_dossier_detail( $numero );
    if ( ! $detail ) {
        wp_send_json_error( array( 'message' => 'Dossier introuvable.' ) );
    }

    $d = $detail['dossier'];
    $telephones = array();
    foreach ( $detail['telephones'] as $tel ) {
        $telephones[] = $tel->numero . ' (' . $tel->type . ')';
    }

    wp_send_json_success( array(
        'numero_dossier' => $d->numero_dossier,
        'nom'            => $d->nom,
        'prenom'         => $d->prenom,
        'statut'         => $d->statut,
        'faculte'        => $d->faculte_nom,
        'diplome'        => $d->diplome_libelle,
        'serie'          => $d->serie_libelle,
        'filiere1'       => $d->filiere1_libelle,
        'filiere2'       => $d->filiere2_libelle,
        'sexe'           => $d->sexe === 'M' ? 'Masculin' : ( $d->sexe === 'F' ? 'Féminin' : '' ),
        'nationalite'    => $d->nationalite_nom,
        'email'          => $d->email,
        'origine'        => trim( $d->region_nom . ' / ' . $d->departement_nom . ' / ' . $d->commune_nom, ' /' ),
        'telephones'     => $telephones,
    ) );
}
add_action( 'wp_ajax_ueb_admin_get_dossier_detail', 'ueb_admin_ajax_get_dossier_detail' );

/* ------------------------------------------------------------------ */
/* Onglet "Statistiques"                                               */
/* ------------------------------------------------------------------ */

function ueb_admin_ajax_get_stats() {
    ueb_admin_ajax_check_access();

    $filters = ueb_admin_ajax_extract_filters();

    wp_send_json_success( array(
        'chiffres'    => ueb_admin_chiffres_cles( $filters ),
        'tauxFaculte' => ueb_admin_taux_par_faculte( $filters ),
        'parFaculte'  => ueb_admin_stats_par_faculte( $filters ),
        'parFiliere'  => ueb_admin_stats_par_filiere( $filters ),
        'parRegion'   => ueb_admin_stats_par_region( $filters ),
        'parSexe'     => ueb_admin_stats_par_sexe( $filters ),
        'evolution'   => ueb_admin_stats_evolution( $filters ),
        'faculteSexe' => ueb_admin_stats_faculte_sexe( $filters ),
    ) );
}
add_action( 'wp_ajax_ueb_admin_get_stats', 'ueb_admin_ajax_get_stats' );

/* ------------------------------------------------------------------ */
/* Cascades des filtres (filière selon faculté, série selon faculté +   */
/* diplôme, département selon région, commune selon département)       */
/* ------------------------------------------------------------------ */

function ueb_admin_ajax_get_filieres() {
    ueb_admin_ajax_check_access();
    global $wpdb;

    $faculte_id = isset( $_POST['faculte_id'] ) ? absint( $_POST['faculte_id'] ) : 0;
    if ( ! $faculte_id ) {
        wp_send_json_error( array( 'message' => 'Faculté manquante.' ) );
    }

    // Toutes les filières de la faculté (classique + pro confondues) :
    // c'est un filtre, pas une saisie de dossier, donc pas besoin de
    // distinguer par type_formation ici.
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, libelle FROM ueb_filieres WHERE faculte_id = %d ORDER BY libelle ASC",
        $faculte_id
    ) );

    wp_send_json_success( $rows );
}
add_action( 'wp_ajax_ueb_admin_get_filieres', 'ueb_admin_ajax_get_filieres' );

function ueb_admin_ajax_get_specialites() {
    ueb_admin_ajax_check_access();
    global $wpdb;

    $faculte_id = isset( $_POST['faculte_id'] ) ? absint( $_POST['faculte_id'] ) : 0;
    $diplome_id = isset( $_POST['diplome_id'] ) ? absint( $_POST['diplome_id'] ) : 0;

    if ( ! $faculte_id || ! $diplome_id ) {
        wp_send_json_error( array( 'message' => 'Paramètres manquants.' ) );
    }

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, libelle FROM ueb_specialites_diplome WHERE faculte_id = %d AND diplome_id = %d ORDER BY libelle ASC",
        $faculte_id,
        $diplome_id
    ) );

    wp_send_json_success( $rows );
}
add_action( 'wp_ajax_ueb_admin_get_specialites', 'ueb_admin_ajax_get_specialites' );

function ueb_admin_ajax_get_departements() {
    ueb_admin_ajax_check_access();
    global $wpdb;

    $region_id = isset( $_POST['region_id'] ) ? absint( $_POST['region_id'] ) : 0;
    if ( ! $region_id ) {
        wp_send_json_error( array( 'message' => 'Région manquante.' ) );
    }

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, nom AS libelle FROM ueb_departements WHERE region_id = %d ORDER BY nom ASC",
        $region_id
    ) );

    wp_send_json_success( $rows );
}
add_action( 'wp_ajax_ueb_admin_get_departements', 'ueb_admin_ajax_get_departements' );

function ueb_admin_ajax_get_communes() {
    ueb_admin_ajax_check_access();
    global $wpdb;

    $departement_id = isset( $_POST['departement_id'] ) ? absint( $_POST['departement_id'] ) : 0;
    if ( ! $departement_id ) {
        wp_send_json_error( array( 'message' => 'Département manquant.' ) );
    }

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, nom AS libelle FROM ueb_communes WHERE departement_id = %d ORDER BY nom ASC",
        $departement_id
    ) );

    wp_send_json_success( $rows );
}
add_action( 'wp_ajax_ueb_admin_get_communes', 'ueb_admin_ajax_get_communes' );