<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Sauvegarde en base de données (CPT "preinscription") les données du
 * formulaire, AVANT la génération du PDF. Se déclenche sur la même action
 * que le PDF (action=generate_pdf), avec une priorité plus basse (5) que
 * ueb_handle_pdf_generation() (priorité par défaut 10) pour s'exécuter avant.
 */
function ueb_handle_db_save() {
    if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'generate_pdf' ) {
        return;
    }

    if ( ! isset( $_POST['preinscription_nonce'] ) ||
         ! wp_verify_nonce( $_POST['preinscription_nonce'], 'preinscription_submit' ) ) {
        wp_die( 'Erreur de sécurité.' );
    }

    // Retire les antislashs ajoutés par WordPress (magic quotes) AVANT sanitisation,
    // sinon "Eto'o" devient "Eto\'o" en base.
    $posted = wp_unslash( $_POST );

    $champs = array(
        /* Formation */
        'faculte'                => 'sanitize_text_field',
        'diplome_admission'      => 'sanitize_text_field',
        'serie_diplome'          => 'sanitize_text_field',
        'niveau_lmd'             => 'sanitize_text_field',
        'type_formation'         => 'sanitize_text_field',
        'filiere_1'              => 'sanitize_text_field',
        'filiere_2'              => 'sanitize_text_field',
        'annee_obtention'        => 'absint',
        /* État civil */
        'nom'                    => 'sanitize_text_field',
        'prenom'                 => 'sanitize_text_field',
        'sexe'                   => 'sanitize_text_field',
        'date_naissance'         => 'sanitize_text_field',
        'lieu_naissance'         => 'sanitize_text_field',
        'nationalite'            => 'sanitize_text_field',
        'situation_matrimoniale' => 'sanitize_text_field',
        'handicap'               => 'sanitize_text_field',
        /* Contact */
        'email'                  => 'sanitize_email',
        'adresse'                => 'sanitize_text_field',
        'region_origine'         => 'sanitize_text_field',
        'departement_origine'    => 'sanitize_text_field',
        'arrondissement_origine' => 'sanitize_text_field',
        'nom_pere'               => 'sanitize_text_field',
        'nom_mere'               => 'sanitize_text_field',
        'profession_pere'        => 'sanitize_text_field',
    );

    $data = array();
    foreach ( $champs as $key => $sanitizer ) {
        $raw = isset( $posted[ $key ] ) ? $posted[ $key ] : '';
        $data[ $key ] = call_user_func( $sanitizer, $raw );
    }

    /* Téléphones multiples : tableaux joints par " / " (même logique que pdf-functions.php) */
    $telephones  = isset( $posted['telephone'] ) ? (array) $posted['telephone'] : array();
    $tels_tuteur = isset( $posted['tel_tuteur'] ) ? (array) $posted['tel_tuteur'] : array();
    $telephones  = array_map( 'sanitize_text_field', $telephones );
    $tels_tuteur = array_map( 'sanitize_text_field', $tels_tuteur );

    $data['telephone']  = implode( ' / ', array_filter( $telephones ) );
    $data['tel_tuteur'] = implode( ' / ', array_filter( $tels_tuteur ) );

    /* Titre du post : NOM Prénom — Filière */
    $titre = strtoupper( $data['nom'] ) . ' ' . $data['prenom'] . ' — ' . $data['filiere_1'];

    $post_id = wp_insert_post( array(
        'post_title'  => $titre,
        'post_type'   => 'preinscription',
        'post_status' => 'publish',
    ), true );

    if ( is_wp_error( $post_id ) ) {
        wp_die( 'Une erreur est survenue lors de l\'enregistrement. Veuillez réessayer.' );
    }

    foreach ( $data as $key => $value ) {
        update_post_meta( $post_id, '_preinscription_' . $key, $value );
    }

    $_SESSION['preinscription_post_id'] = $post_id;
}
add_action( 'template_redirect', 'ueb_handle_db_save', 5 );
