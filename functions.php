<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function preinscriptions_theme_assets() {
    wp_enqueue_style(
        'preinscriptions-style',
        get_stylesheet_uri(),
        array(),
        '1.0'
    );
}
add_action( 'wp_enqueue_scripts', 'preinscriptions_theme_assets' );

/* ==================
FONCTIONS DU FORMULAIRE
=======================*/

/* ── 1. Enqueue CSS + JS du formulaire sur la page de préinscription */
function preinscriptions_form_assets() {
    if ( is_page_template( 'page-preinscription.php' ) ) {
        wp_enqueue_style(
            'preinscriptions-form',
            get_template_directory_uri() . '/assets/css/form-preinscription.css',
            array( 'preinscriptions-style' ),
            '1.0'
        );
        wp_enqueue_script(
            'preinscriptions-form',
            get_template_directory_uri() . '/assets/js/form-preinscription.js',
            array(),
            '3.1',
            true  // chargé en footer
        );
    }
}
add_action( 'wp_enqueue_scripts', 'preinscriptions_form_assets' );


/* ── 2. Custom Post Type : preinscription ── */
function preinscriptions_register_cpt() {
    register_post_type( 'preinscription', array(
        'labels'        => array(
            'name'          => 'Préinscriptions',
            'singular_name' => 'Préinscription',
            'menu_name'     => 'Préinscriptions',
            'all_items'     => 'Toutes les demandes',
            'view_item'     => 'Voir la demande',
            'search_items'  => 'Rechercher',
        ),
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => true,
        'menu_icon'     => 'dashicons-welcome-write-blog',
        'supports'      => array( 'title' ),
        'capability_type' => 'post',
        'map_meta_cap'  => true,
    ) );
}
add_action( 'init', 'preinscriptions_register_cpt' );


/* ── 3. Traitement du formulaire (POST) ── */
function preinscriptions_handle_form() {
    if (
        ! isset( $_POST['action'] ) ||
        $_POST['action'] !== 'preinscription_submit'
    ) {
        return;
    }

    /* Vérification nonce */
    if (
        ! isset( $_POST['preinscription_nonce'] ) ||
        ! wp_verify_nonce( $_POST['preinscription_nonce'], 'preinscription_submit' )
    ) {
        wp_die( 'Erreur de sécurité. Veuillez recharger la page et réessayer.' );
    }

    /* Champs attendus + sanitisation */
    $champs = array(
        /* Formation */
        'faculte'               => 'sanitize_text_field',
        'diplome_admission'     => 'sanitize_text_field',
        'serie_diplome'         => 'sanitize_text_field',
        'niveau_lmd'            => 'sanitize_text_field',
        'type_formation'        => 'sanitize_text_field',
        'filiere_1'             => 'sanitize_text_field',
        'filiere_2'             => 'sanitize_text_field',
        'annee_obtention'       => 'absint',
        /* État civil */
        'nom'                   => 'sanitize_text_field',
        'prenom'                => 'sanitize_text_field',
        'sexe'                  => 'sanitize_text_field',
        'date_naissance'        => 'sanitize_text_field',
        'lieu_naissance'        => 'sanitize_text_field',
        'nationalite'           => 'sanitize_text_field',
        'situation_matrimoniale' => 'sanitize_text_field',
        'handicap'              => 'sanitize_text_field',
        /* Contact */
        'email'                 => 'sanitize_email',
        'telephone'             => 'sanitize_text_field',
        'adresse'               => 'sanitize_text_field',
        'region_origine'        => 'sanitize_text_field',
        'departement_origine'   => 'sanitize_text_field',
        'arrondissement_origine' => 'sanitize_text_field',
        'nom_pere'              => 'sanitize_text_field',
        'nom_mere'              => 'sanitize_text_field',
        'tel_tuteur'            => 'sanitize_text_field',
        'profession_pere'       => 'sanitize_text_field',
    );

    $data = array();
    foreach ( $champs as $key => $sanitizer ) {
        $raw = isset( $_POST[ $key ] ) ? $_POST[ $key ] : '';
        $data[ $key ] = call_user_func( $sanitizer, $raw );
    }

    /* Titre du post : NOM Prénom — Filière */
    $titre = strtoupper( $data['nom'] ) . ' ' . $data['prenom']
             . ' — ' . $data['filiere_1'];

    /* Insérer le post */
    $post_id = wp_insert_post( array(
        'post_title'  => $titre,
        'post_type'   => 'preinscription',
        'post_status' => 'publish',
    ), true );

    if ( is_wp_error( $post_id ) ) {
        wp_die( 'Une erreur est survenue lors de l\'enregistrement. Veuillez réessayer.' );
    }

    /* Sauvegarder chaque champ en post meta */
    foreach ( $data as $key => $value ) {
        update_post_meta( $post_id, '_preinscription_' . $key, $value );
    }

    /* Redirection anti double-soumission */
    wp_safe_redirect(
        add_query_arg( 'preinscription', 'success', get_permalink() )
    );
    exit;
}
add_action( 'template_redirect', 'preinscriptions_handle_form' );

require_once( get_template_directory() . '/inc/pdf-functions.php' );