<?php
/**
 * Fonctions utilitaires pour les réseaux sociaux.
 *
 * Fichier : inc/social-medias-functions.php
 * Inclus via functions.php du thème.
 *
 * @package Preinscriptions_UEB
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Récupère les réseaux sociaux actifs depuis la base de données.
 *
 * @return array Tableau indexé par plateforme, chaque entrée contenant
 *               'url', 'label', 'icone'.
 */
function ueb_get_reseaux_sociaux() {
    global $wpdb;

    // Les tables ueb_* sont créées sans le préfixe WordPress (cf. db-schema.php).
    $rows = $wpdb->get_results(
        "SELECT plateforme, url, icone
         FROM ueb_reseaux_sociaux
         WHERE actif = 1
         ORDER BY ordre ASC",
        ARRAY_A
    );

    if ( empty( $rows ) || is_wp_error( $rows ) ) {
        return array();
    }

    $reseaux = array();
    foreach ( $rows as $row ) {
        $plateforme = sanitize_key( $row['plateforme'] );
        $reseaux[ $plateforme ] = array(
            'url'   => esc_url_raw( $row['url'] ),
            'label' => ucfirst( str_replace( '_', ' ', $plateforme ) ),
            'icone' => sanitize_text_field( $row['icone'] ),
        );
    }

    return $reseaux;
}