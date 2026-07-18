<?php
/**
 * Endpoints AJAX pour les selects dynamiques du formulaire de préinscription
 * (facultés, diplômes, spécialités, filières, régions, départements, communes,
 * niveaux LMD, mentions, statuts étudiants, langues, sports, arts).
 *
 * @package Preinscriptions_UEB
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Vérifie le nonce partagé du formulaire de préinscription.
 * Stoppe l'exécution avec une réponse JSON d'erreur si invalide.
 */
function ueb_ajax_check_nonce() {
    check_ajax_referer( 'preinscription_submit', 'nonce' );
}

/* ------------------------------------------------------------------ */
/* Facultés                                                            */
/* ------------------------------------------------------------------ */
function ueb_ajax_get_facultes() {
    ueb_ajax_check_nonce();
    global $wpdb;

    $rows = $wpdb->get_results(
        "SELECT id, code, nom_fr AS libelle FROM ueb_facultes ORDER BY nom_fr ASC"
    );

    wp_send_json_success( $rows );
}
add_action( 'wp_ajax_ueb_get_facultes', 'ueb_ajax_get_facultes' );
add_action( 'wp_ajax_nopriv_ueb_get_facultes', 'ueb_ajax_get_facultes' );

/* ------------------------------------------------------------------ */
/* Diplômes d'admission                                                */
/* ------------------------------------------------------------------ */
function ueb_ajax_get_diplomes() {
    ueb_ajax_check_nonce();
    global $wpdb;

    $rows = $wpdb->get_results(
        "SELECT id, code, libelle FROM ueb_diplomes_admission ORDER BY libelle ASC"
    );

    wp_send_json_success( $rows );
}
add_action( 'wp_ajax_ueb_get_diplomes', 'ueb_ajax_get_diplomes' );
add_action( 'wp_ajax_nopriv_ueb_get_diplomes', 'ueb_ajax_get_diplomes' );

/* ------------------------------------------------------------------ */
/* Spécialités / séries (selon faculté + diplôme)                      */
/* ------------------------------------------------------------------ */
function ueb_ajax_get_specialites() {
    ueb_ajax_check_nonce();
    global $wpdb;

    $faculte_id = isset( $_POST['faculte_id'] ) ? absint( $_POST['faculte_id'] ) : 0;
    $diplome_id = isset( $_POST['diplome_id'] ) ? absint( $_POST['diplome_id'] ) : 0;

    if ( ! $faculte_id || ! $diplome_id ) {
        wp_send_json_error( array( 'message' => 'Paramètres manquants.' ) );
    }

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, code, libelle FROM ueb_specialites_diplome
         WHERE faculte_id = %d AND diplome_id = %d
         ORDER BY libelle ASC",
        $faculte_id,
        $diplome_id
    ) );

    wp_send_json_success( $rows );
}
add_action( 'wp_ajax_ueb_get_specialites', 'ueb_ajax_get_specialites' );
add_action( 'wp_ajax_nopriv_ueb_get_specialites', 'ueb_ajax_get_specialites' );

/* ------------------------------------------------------------------ */
/* Filières (selon faculté + type de formation)                        */
/* ------------------------------------------------------------------ */
function ueb_ajax_get_filieres() {
    ueb_ajax_check_nonce();
    global $wpdb;

    $faculte_id     = isset( $_POST['faculte_id'] ) ? absint( $_POST['faculte_id'] ) : 0;
    $type_formation = isset( $_POST['type_formation'] ) ? sanitize_text_field( $_POST['type_formation'] ) : 'classique';

    if ( ! $faculte_id || ! in_array( $type_formation, array( 'classique', 'pro' ), true ) ) {
        wp_send_json_error( array( 'message' => 'Paramètres manquants ou invalides.' ) );
    }

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, code, libelle FROM ueb_filieres
         WHERE faculte_id = %d AND type_formation = %s
         ORDER BY libelle ASC",
        $faculte_id,
        $type_formation
    ) );

    wp_send_json_success( $rows );
}
add_action( 'wp_ajax_ueb_get_filieres', 'ueb_ajax_get_filieres' );
add_action( 'wp_ajax_nopriv_ueb_get_filieres', 'ueb_ajax_get_filieres' );

/* ------------------------------------------------------------------ */
/* Régions                                                              */
/* ------------------------------------------------------------------ */
function ueb_ajax_get_regions() {
    ueb_ajax_check_nonce();
    global $wpdb;

    $rows = $wpdb->get_results(
        "SELECT id, code, nom AS libelle FROM ueb_regions ORDER BY nom ASC"
    );

    wp_send_json_success( $rows );
}
add_action( 'wp_ajax_ueb_get_regions', 'ueb_ajax_get_regions' );
add_action( 'wp_ajax_nopriv_ueb_get_regions', 'ueb_ajax_get_regions' );

/* ------------------------------------------------------------------ */
/* Départements (selon région choisie)                                 */
/* ------------------------------------------------------------------ */
function ueb_ajax_get_departements() {
    ueb_ajax_check_nonce();
    global $wpdb;

    $region_id = isset( $_POST['region_id'] ) ? absint( $_POST['region_id'] ) : 0;

    if ( ! $region_id ) {
        wp_send_json_error( array( 'message' => 'Paramètre région manquant.' ) );
    }

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, code, nom AS libelle FROM ueb_departements
         WHERE region_id = %d
         ORDER BY nom ASC",
        $region_id
    ) );

    wp_send_json_success( $rows );
}
add_action( 'wp_ajax_ueb_get_departements', 'ueb_ajax_get_departements' );
add_action( 'wp_ajax_nopriv_ueb_get_departements', 'ueb_ajax_get_departements' );

/* ------------------------------------------------------------------ */
/* Communes (selon département choisi)                                 */
/* ------------------------------------------------------------------ */
function ueb_ajax_get_communes() {
    ueb_ajax_check_nonce();
    global $wpdb;

    $departement_id = isset( $_POST['departement_id'] ) ? absint( $_POST['departement_id'] ) : 0;

    if ( ! $departement_id ) {
        wp_send_json_error( array( 'message' => 'Paramètre département manquant.' ) );
    }

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, code, nom AS libelle FROM ueb_communes
         WHERE departement_id = %d
         ORDER BY nom ASC",
        $departement_id
    ) );

    wp_send_json_success( $rows );
}
add_action( 'wp_ajax_ueb_get_communes', 'ueb_ajax_get_communes' );
add_action( 'wp_ajax_nopriv_ueb_get_communes', 'ueb_ajax_get_communes' );

/* ------------------------------------------------------------------ */
/* Statuts socio-professionnels                                        */
/* ------------------------------------------------------------------ */
function ueb_ajax_get_statuts_socio_pro() {
    ueb_ajax_check_nonce();
    global $wpdb;

    $rows = $wpdb->get_results(
        "SELECT id, libelle FROM ueb_statuts_socio_professionnels ORDER BY libelle ASC"
    );

    wp_send_json_success( $rows );
}
add_action( 'wp_ajax_ueb_get_statuts_socio_pro', 'ueb_ajax_get_statuts_socio_pro' );
add_action( 'wp_ajax_nopriv_ueb_get_statuts_socio_pro', 'ueb_ajax_get_statuts_socio_pro' );

/* ------------------------------------------------------------------ */
/* Sauvegarde de la progression (appelé à chaque clic "Suivant")       */
/* ------------------------------------------------------------------ */
function ueb_sanitize_progression_data( $donnees ) {
    $clean = array();
    foreach ( $donnees as $key => $value ) {
        $key = sanitize_key( $key );
        if ( is_array( $value ) ) {
            $clean[ $key ] = array_map( 'sanitize_text_field', $value );
        } elseif ( is_bool( $value ) ) {
            $clean[ $key ] = $value;
        } else {
            $clean[ $key ] = sanitize_text_field( (string) $value );
        }
    }
    return $clean;
}

function ueb_ajax_save_progression() {
    ueb_ajax_check_nonce();

    $numero_dossier = isset( $_POST['numero_dossier'] ) ? sanitize_text_field( wp_unslash( $_POST['numero_dossier'] ) ) : '';
    $etape          = isset( $_POST['etape'] ) ? absint( $_POST['etape'] ) : 0;
    $donnees_json   = isset( $_POST['donnees'] ) ? wp_unslash( $_POST['donnees'] ) : '';

    if ( ! $numero_dossier || ! $etape ) {
        wp_send_json_error( array( 'message' => 'Paramètres manquants.' ) );
    }

    $donnees = json_decode( $donnees_json, true );

    if ( ! is_array( $donnees ) ) {
        wp_send_json_error( array( 'message' => 'Données invalides.' ) );
    }

    $donnees = ueb_sanitize_progression_data( $donnees );
    $ok      = ueb_sauvegarder_progression( $numero_dossier, $etape, $donnees );

    if ( ! $ok ) {
        wp_send_json_error( array( 'message' => 'Échec de la sauvegarde.' ) );
    }

    wp_send_json_success();
}
add_action( 'wp_ajax_ueb_save_progression', 'ueb_ajax_save_progression' );
add_action( 'wp_ajax_nopriv_ueb_save_progression', 'ueb_ajax_save_progression' );

/* ------------------------------------------------------------------ */
/* Récupération de la progression d'un dossier (reprise)               */
/* ------------------------------------------------------------------ */
function ueb_ajax_get_progression() {
    ueb_ajax_check_nonce();

    $numero_dossier = isset( $_POST['numero_dossier'] ) ? sanitize_text_field( wp_unslash( $_POST['numero_dossier'] ) ) : '';

    if ( ! $numero_dossier ) {
        wp_send_json_error( array( 'message' => 'Numéro de dossier manquant.' ) );
    }

    $progression = ueb_recuperer_progression( $numero_dossier );

    if ( null === $progression ) {
        wp_send_json_error( array( 'message' => 'Numéro introuvable.' ) );
    }

    // Reprise manuelle : ce numéro devient le dossier "en cours" pour cet
    // appareil, la session/le cookie précédents (auto-générés) sont écrasés.
    $_SESSION['ueb_numero_dossier_en_cours'] = $numero_dossier;
    setcookie(
        'ueb_numero_dossier',
        $numero_dossier,
        time() + 30 * DAY_IN_SECONDS,
        COOKIEPATH,
        COOKIE_DOMAIN,
        is_ssl(),
        true
    );

    wp_send_json_success( array(
        'numero_dossier' => $numero_dossier,
        'etape_atteinte' => $progression['etape_atteinte'],
        'donnees'        => $progression['donnees'],
    ) );
}
add_action( 'wp_ajax_ueb_get_progression', 'ueb_ajax_get_progression' );
add_action( 'wp_ajax_nopriv_ueb_get_progression', 'ueb_ajax_get_progression' );

/* ------------------------------------------------------------------ */
/* Nationalités                                                        */
/* ------------------------------------------------------------------ */
function ueb_ajax_get_nationalites() {
    ueb_ajax_check_nonce();
    global $wpdb;

    $rows = $wpdb->get_results(
        "SELECT id, nom AS libelle FROM ueb_nationalites ORDER BY nom ASC"
    );

    wp_send_json_success( $rows );
}
add_action( 'wp_ajax_ueb_get_nationalites', 'ueb_ajax_get_nationalites' );
add_action( 'wp_ajax_nopriv_ueb_get_nationalites', 'ueb_ajax_get_nationalites' );

/* ------------------------------------------------------------------ */
/* Situations matrimoniales                                            */
/* ------------------------------------------------------------------ */
function ueb_ajax_get_situations_matrimoniales() {
    ueb_ajax_check_nonce();
    global $wpdb;

    $rows = $wpdb->get_results(
        "SELECT id, libelle FROM ueb_situations_matrimoniales ORDER BY libelle ASC"
    );

    wp_send_json_success( $rows );
}
add_action( 'wp_ajax_ueb_get_situations_matrimoniales', 'ueb_ajax_get_situations_matrimoniales' );
add_action( 'wp_ajax_nopriv_ueb_get_situations_matrimoniales', 'ueb_ajax_get_situations_matrimoniales' );

/* ------------------------------------------------------------------ */
/* Niveaux LMD                                                         */
/* ------------------------------------------------------------------ */
function ueb_ajax_get_niveaux_lmd() {
    ueb_ajax_check_nonce();
    global $wpdb;
    wp_send_json_success( $wpdb->get_results( "SELECT id, libelle FROM ueb_niveaux_lmd ORDER BY ordre ASC" ) );
}
add_action( 'wp_ajax_ueb_get_niveaux_lmd', 'ueb_ajax_get_niveaux_lmd' );
add_action( 'wp_ajax_nopriv_ueb_get_niveaux_lmd', 'ueb_ajax_get_niveaux_lmd' );

/* ------------------------------------------------------------------ */
/* Mentions                                                             */
/* ------------------------------------------------------------------ */
function ueb_ajax_get_mentions() {
    ueb_ajax_check_nonce();
    global $wpdb;
    wp_send_json_success( $wpdb->get_results( "SELECT id, libelle FROM ueb_mentions ORDER BY ordre ASC" ) );
}
add_action( 'wp_ajax_ueb_get_mentions', 'ueb_ajax_get_mentions' );
add_action( 'wp_ajax_nopriv_ueb_get_mentions', 'ueb_ajax_get_mentions' );

/* ------------------------------------------------------------------ */
/* Statuts étudiants (CEMAC / hors CEMAC)                               */
/* ------------------------------------------------------------------ */
function ueb_ajax_get_statuts_etudiant() {
    ueb_ajax_check_nonce();
    global $wpdb;
    wp_send_json_success( $wpdb->get_results( "SELECT id, libelle FROM ueb_statuts_etudiants ORDER BY libelle ASC" ) );
}
add_action( 'wp_ajax_ueb_get_statuts_etudiant', 'ueb_ajax_get_statuts_etudiant' );
add_action( 'wp_ajax_nopriv_ueb_get_statuts_etudiant', 'ueb_ajax_get_statuts_etudiant' );

/* ------------------------------------------------------------------ */
/* Langues                                                              */
/* ------------------------------------------------------------------ */
function ueb_ajax_get_langues() {
    ueb_ajax_check_nonce();
    global $wpdb;
    wp_send_json_success( $wpdb->get_results( "SELECT id, libelle FROM ueb_langues ORDER BY libelle ASC" ) );
}
add_action( 'wp_ajax_ueb_get_langues', 'ueb_ajax_get_langues' );
add_action( 'wp_ajax_nopriv_ueb_get_langues', 'ueb_ajax_get_langues' );

/* ------------------------------------------------------------------ */
/* Sports                                                               */
/* ------------------------------------------------------------------ */
function ueb_ajax_get_sports() {
    ueb_ajax_check_nonce();
    global $wpdb;
    wp_send_json_success( $wpdb->get_results( "SELECT id, libelle FROM ueb_sports ORDER BY libelle ASC" ) );
}
add_action( 'wp_ajax_ueb_get_sports', 'ueb_ajax_get_sports' );
add_action( 'wp_ajax_nopriv_ueb_get_sports', 'ueb_ajax_get_sports' );

/* ------------------------------------------------------------------ */
/* Arts                                                                 */
/* ------------------------------------------------------------------ */
function ueb_ajax_get_arts() {
    ueb_ajax_check_nonce();
    global $wpdb;
    wp_send_json_success( $wpdb->get_results( "SELECT id, libelle FROM ueb_arts ORDER BY libelle ASC" ) );
}
add_action( 'wp_ajax_ueb_get_arts', 'ueb_ajax_get_arts' );
add_action( 'wp_ajax_nopriv_ueb_get_arts', 'ueb_ajax_get_arts' );