<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function ueb_admin_get_facultes_liste() {
    global $wpdb;
    return $wpdb->get_results( "SELECT id, nom_fr FROM ueb_facultes ORDER BY nom_fr ASC" );
}

function ueb_admin_get_dossiers( $faculte_id = 0, $statut = '' ) {
    global $wpdb;

    $where  = array( '1=1' );
    $params = array();

    if ( $faculte_id ) {
        $where[]  = 'p.faculte_id = %d';
        $params[] = $faculte_id;
    }
    if ( $statut && in_array( $statut, array( 'brouillon', 'soumis' ), true ) ) {
        $where[]  = 'p.statut = %s';
        $params[] = $statut;
    }

    $sql = "SELECT p.numero_dossier, p.nom, p.prenom, p.statut, p.date_creation, f.nom_fr AS faculte_nom
            FROM ueb_preinscriptions p
            LEFT JOIN ueb_facultes f ON f.id = p.faculte_id
            WHERE " . implode( ' AND ', $where ) . "
            ORDER BY p.date_creation DESC";

    if ( $params ) {
        $sql = $wpdb->prepare( $sql, $params );
    }

    return $wpdb->get_results( $sql );
}

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

    if ( ! $dossier ) return null;

    $telephones = $wpdb->get_results( $wpdb->prepare(
        "SELECT type, numero FROM ueb_preinscriptions_telephones WHERE preinscription_id = %d",
        $dossier->id
    ) );

    return array( 'dossier' => $dossier, 'telephones' => $telephones );
}