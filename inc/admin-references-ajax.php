<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Endpoints AJAX de la page "Gestion des références" (page-references.php).
 *
 * Système d'autorisation VOLONTAIREMENT séparé de celui du dashboard des
 * dossiers (inc/admin-ajax-functions.php, capacité 'voir_preinscriptions',
 * nonce 'ueb_admin_dashboard') : gérer les tables de référence (facultés,
 * filières, régions...) est réservé aux vrais administrateurs WordPress
 * ('manage_options'), qui ne sont pas forcément les mêmes comptes que ceux
 * qui consultent les dossiers de préinscription au quotidien.
 *
 * @package Preinscriptions_UEB
 */

/**
 * Vérifie l'accès (connecté + administrateur) puis le nonce dédié. Stoppe
 * l'exécution avec une réponse JSON d'erreur sinon. À appeler en tout
 * premier dans chaque handler de ce fichier.
 */
function ueb_admin_ref_check_access() {
    if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Accès refusé.' ), 403 );
    }
    check_ajax_referer( 'ueb_admin_references', 'nonce' );
}

/**
 * Clé du registre demandée, sanitizée. '' si absente/invalide côté format
 * (la validité réelle — existe-t-elle dans le registre ? — est vérifiée
 * séparément par chaque handler).
 *
 * @return string
 */
function ueb_admin_ref_ajax_get_key() {
    return isset( $_POST['ref_key'] ) ? sanitize_key( wp_unslash( $_POST['ref_key'] ) ) : '';
}

function ueb_admin_ref_ajax_list() {
    ueb_admin_ref_check_access();

    $key      = ueb_admin_ref_ajax_get_key();
    $registry = ueb_admin_ref_registry();
    if ( ! isset( $registry[ $key ] ) ) {
        wp_send_json_error( array( 'message' => 'Table inconnue.' ) );
    }

    $search = isset( $_POST['recherche'] ) ? sanitize_text_field( wp_unslash( $_POST['recherche'] ) ) : '';
    $page   = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;

    $result = ueb_admin_ref_list( $key, $search, $page, 20 );

    wp_send_json_success( $result );
}
add_action( 'wp_ajax_ueb_admin_ref_list', 'ueb_admin_ref_ajax_list' );

function ueb_admin_ref_ajax_get() {
    ueb_admin_ref_check_access();

    $key      = ueb_admin_ref_ajax_get_key();
    $id       = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
    $registry = ueb_admin_ref_registry();

    if ( ! isset( $registry[ $key ] ) || ! $id ) {
        wp_send_json_error( array( 'message' => 'Paramètres invalides.' ) );
    }

    $row = ueb_admin_ref_get( $key, $id );
    if ( ! $row ) {
        wp_send_json_error( array( 'message' => 'Introuvable (a peut-être été supprimé entre-temps).' ) );
    }

    wp_send_json_success( $row );
}
add_action( 'wp_ajax_ueb_admin_ref_get', 'ueb_admin_ref_ajax_get' );

/**
 * Création OU modification selon la présence d'un id > 0. Les valeurs de
 * champs arrivent regroupées sous $_POST['champs'][...] (envoyées par le JS
 * en 'champs[code]', 'champs[nom_fr]', etc.), pour rester génériques quel
 * que soit le nombre/nom de colonnes de la table ciblée.
 */
function ueb_admin_ref_ajax_save() {
    ueb_admin_ref_check_access();

    $key      = ueb_admin_ref_ajax_get_key();
    $id       = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
    $registry = ueb_admin_ref_registry();

    if ( ! isset( $registry[ $key ] ) ) {
        wp_send_json_error( array( 'message' => 'Table inconnue.' ) );
    }

    $raw = ( isset( $_POST['champs'] ) && is_array( $_POST['champs'] ) ) ? $_POST['champs'] : array();

    $result = $id ? ueb_admin_ref_update( $key, $id, $raw ) : ueb_admin_ref_create( $key, $raw );

    if ( empty( $result['success'] ) ) {
        wp_send_json_error( array( 'message' => $result['message'] ) );
    }

    wp_send_json_success( $result );
}
add_action( 'wp_ajax_ueb_admin_ref_save', 'ueb_admin_ref_ajax_save' );

function ueb_admin_ref_ajax_delete() {
    ueb_admin_ref_check_access();

    $key      = ueb_admin_ref_ajax_get_key();
    $id       = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
    $registry = ueb_admin_ref_registry();

    if ( ! isset( $registry[ $key ] ) || ! $id ) {
        wp_send_json_error( array( 'message' => 'Paramètres invalides.' ) );
    }

    $result = ueb_admin_ref_delete( $key, $id );

    if ( empty( $result['success'] ) ) {
        wp_send_json_error( array( 'message' => $result['message'] ) );
    }

    wp_send_json_success( array( 'id' => $id ) );
}
add_action( 'wp_ajax_ueb_admin_ref_delete', 'ueb_admin_ref_ajax_delete' );
