function ueb_generer_numero_dossier() {
    global $wpdb;
    $annee = date('Y');

    $wpdb->query( $wpdb->prepare(
        "INSERT INTO ueb_dossier_sequence (annee, dernier_numero) VALUES (%d, 1)
         ON DUPLICATE KEY UPDATE dernier_numero = LAST_INSERT_ID(dernier_numero + 1)",
        $annee
    ) );

    $numero = $wpdb->get_var( "SELECT LAST_INSERT_ID()" );

    return sprintf( 'UEB-%d-%06d', $annee, $numero );
}