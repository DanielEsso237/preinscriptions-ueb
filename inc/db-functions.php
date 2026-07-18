<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Sauvegarde en base de données (tables ueb_*) les données du formulaire,
 * à la soumission finale. Se déclenche sur action=generate_pdf, priorité 5
 * (avant ueb_handle_pdf_generation(), priorité 10 par défaut), pour que le
 * PDF puisse ensuite relire les données fraîchement enregistrées si besoin.
 */
function ueb_handle_db_save() {
    if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'generate_pdf' ) {
        return;
    }

    if ( ! isset( $_POST['preinscription_nonce'] ) ||
         ! wp_verify_nonce( $_POST['preinscription_nonce'], 'preinscription_submit' ) ) {
        wp_die( 'Erreur de sécurité.' );
    }

    global $wpdb;

    // Retire les antislashs ajoutés par WordPress (magic quotes) AVANT sanitisation,
    // sinon "Eto'o" devient "Eto\'o" en base.
    $posted = wp_unslash( $_POST );

    $numero_dossier = isset( $posted['numero_dossier'] ) ? sanitize_text_field( $posted['numero_dossier'] ) : '';

    if ( ! $numero_dossier ) {
        wp_die( 'Numéro de dossier manquant. Merci de recharger la page.' );
    }

    /* Champs texte simples */
    $champs_texte = array(
        'nom'                    => 'sanitize_text_field',
        'prenom'                 => 'sanitize_text_field',
        'sexe'                   => 'sanitize_text_field',
        'date_naissance'         => 'sanitize_text_field',
        'lieu_naissance'         => 'sanitize_text_field',
        'handicap'               => 'sanitize_text_field',
        'email'                  => 'sanitize_email',
        'adresse'                => 'sanitize_text_field',
        'nom_pere'               => 'sanitize_text_field',
        'nom_mere'               => 'sanitize_text_field',
        'profession_pere'        => 'sanitize_text_field',
        'niveau_lmd'             => 'sanitize_text_field',
        'type_formation'         => 'sanitize_text_field',
        'numero_pere'            => 'sanitize_text_field',
        'numero_mere'            => 'sanitize_text_field',
        'profession_mere'        => 'sanitize_text_field',
        'nom_tuteur'             => 'sanitize_text_field',
        'numero_tuteur'          => 'sanitize_text_field',
        'numero_certificat_medical'  => 'sanitize_text_field',
        'lieu_obtention_certificat'  => 'sanitize_text_field',
    );

    $data = array();
    foreach ( $champs_texte as $key => $sanitizer ) {
        $raw = isset( $posted[ $key ] ) ? $posted[ $key ] : '';
        $data[ $key ] = call_user_func( $sanitizer, $raw );
    }

    /* Champs numériques (ID de FK + année) */
    $champs_id = array(
      'faculte', 'diplome_admission', 'serie_diplome', 'filiere_1', 'filiere_2', 'filiere_3',
      'nationalite', 'premiere_langue', 'situation_matrimoniale', 'statut_socio_professionnel',
      'region_origine', 'departement_origine', 'commune_origine',
      'niveau_lmd', 'mention', 'statut_etudiant', 'sport_prefere', 'art_pratique',
    );
    $data['moyenne_diplome'] = isset( $posted['moyenne_diplome'] ) && $posted['moyenne_diplome'] !== ''
    ? round( (float) $posted['moyenne_diplome'], 2 )
    : null;
    $data['handicap'] = in_array( $posted['handicap'] ?? '', array( 'oui', 'non' ), true ) ? $posted['handicap'] : 'non';

    foreach ( $champs_id as $key ) {
        $raw = isset( $posted[ $key ] ) ? $posted[ $key ] : '';
        $data[ $key ] = ( '' !== $raw ) ? absint( $raw ) : null;
    }

    $data['annee_obtention'] = isset( $posted['annee_obtention'] ) ? absint( $posted['annee_obtention'] ) : null;

    if ( '' === $data['type_formation'] ) {
        $data['type_formation'] = 'classique';
    }

    /* Insertion / mise à jour dans ueb_preinscriptions */
    $insert_data = array(
      'numero_dossier'                 => $numero_dossier,
      'statut'                         => 'soumis',
      'faculte_id'                     => $data['faculte'],
      'diplome_admission_id'           => $data['diplome_admission'],
      'specialite_diplome_id'          => $data['serie_diplome'],
      'niveau_lmd_id'                  => $data['niveau_lmd'],
      'type_formation'                 => $data['type_formation'],
      'filiere_1_id'                   => $data['filiere_1'],
      'filiere_2_id'                   => $data['filiere_2'],
      'filiere_3_id'                   => $data['filiere_3'],
      'annee_obtention'                => $data['annee_obtention'],
      'moyenne_diplome'                => $data['moyenne_diplome'],
      'mention_id'                     => $data['mention'],
      'statut_etudiant_id'             => $data['statut_etudiant'],
      'nom'                            => $data['nom'],
      'prenom'                         => $data['prenom'],
      'sexe'                           => $data['sexe'],
      'date_naissance'                 => $data['date_naissance'] ?: null,
      'lieu_naissance'                 => $data['lieu_naissance'],
      'nationalite_id'                 => $data['nationalite'],
      'premiere_langue_id'             => $data['premiere_langue'],
      'situation_matrimoniale_id'      => $data['situation_matrimoniale'],
      'statut_socio_professionnel_id'  => $data['statut_socio_professionnel'],
      'handicap'                       => $data['handicap'],
      'email'                          => $data['email'],
      'adresse'                        => $data['adresse'],
      'region_origine_id'              => $data['region_origine'],
      'departement_origine_id'         => $data['departement_origine'],
      'commune_origine_id'             => $data['commune_origine'],
      'nom_pere'                       => $data['nom_pere'],
      'numero_pere'                    => $data['numero_pere'],
      'profession_pere'                => $data['profession_pere'],
      'nom_mere'                       => $data['nom_mere'],
      'numero_mere'                    => $data['numero_mere'],
      'profession_mere'                => $data['profession_mere'],
      'nom_tuteur'                     => $data['nom_tuteur'],
      'numero_tuteur'                  => $data['numero_tuteur'],
      'sport_prefere_id'               => $data['sport_prefere'],
      'art_pratique_id'                => $data['art_pratique'],
      'numero_certificat_medical'      => $data['numero_certificat_medical'],
      'lieu_obtention_certificat'      => $data['lieu_obtention_certificat'],
    );

    $formats = array(
      '%s','%s','%d','%d','%d','%d','%s','%d','%d','%d','%d','%f','%d','%d',
      '%s','%s','%s','%s','%s','%d','%d','%d','%d','%s',
      '%s','%s','%d','%d','%d',
      '%s','%s','%s','%s','%s','%s','%s','%s',
      '%d','%d','%s','%s',
    );

    // numero_dossier est UNIQUE : on upsert au cas où le candidat soumettrait
    // deux fois le même dossier (double clic, retour navigateur, etc.).
    $existing_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM ueb_preinscriptions WHERE numero_dossier = %s",
        $numero_dossier
    ) );

    if ( $existing_id ) {
        $wpdb->update( 'ueb_preinscriptions', $insert_data, array( 'id' => $existing_id ), $formats, array( '%d' ) );
        $preinscription_id = (int) $existing_id;
    } else {
        $result = $wpdb->insert( 'ueb_preinscriptions', $insert_data, $formats );
        if ( false === $result ) {
            error_log( sprintf( '[UEB DB Save] Échec insertion ueb_preinscriptions pour %s : %s', $numero_dossier, $wpdb->last_error ) );
            wp_die( 'Une erreur est survenue lors de l\'enregistrement. Veuillez réessayer.' );
        }
        $preinscription_id = (int) $wpdb->insert_id;
    }

    /* Téléphones : on repart de zéro pour ce dossier (cas de l'upsert) */
    $wpdb->delete( 'ueb_preinscriptions_telephones', array( 'preinscription_id' => $preinscription_id ), array( '%d' ) );

    $telephones  = isset( $posted['telephone'] ) ? (array) $posted['telephone'] : array();
    $tels_tuteur = isset( $posted['tel_tuteur'] ) ? (array) $posted['tel_tuteur'] : array();

    foreach ( array_filter( array_map( 'sanitize_text_field', $telephones ) ) as $numero ) {
        $wpdb->insert( 'ueb_preinscriptions_telephones', array(
            'preinscription_id' => $preinscription_id,
            'type'              => 'candidat',
            'numero'            => $numero,
        ), array( '%d', '%s', '%s' ) );
    }

    foreach ( array_filter( array_map( 'sanitize_text_field', $tels_tuteur ) ) as $numero ) {
        $wpdb->insert( 'ueb_preinscriptions_telephones', array(
            'preinscription_id' => $preinscription_id,
            'type'              => 'tuteur',
            'numero'            => $numero,
        ), array( '%d', '%s', '%s' ) );
    }

    /* Nettoyage : le brouillon n'a plus lieu d'être une fois le dossier soumis */
    $wpdb->delete( 'ueb_preinscriptions_progression', array( 'numero_dossier' => $numero_dossier ), array( '%s' ) );

    /* Nettoyage session + cookie, pour qu'un futur candidat sur le même
       appareil reparte sur un numéro de dossier neuf (voir échanges avec
       Esso Dictator sur la gestion session/cookie). */
    unset( $_SESSION['ueb_numero_dossier_en_cours'] );
    setcookie(
        'ueb_numero_dossier',
        '',
        time() - 3600,
        COOKIEPATH,
        COOKIE_DOMAIN,
        is_ssl(),
        true
    );

    $_SESSION['preinscription_id'] = $preinscription_id;
}
add_action( 'template_redirect', 'ueb_handle_db_save', 5 );
