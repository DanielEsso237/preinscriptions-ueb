<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Récupère un libellé (ou toute autre colonne) dans une table de référence
 * ueb_* à partir d'un ID. $table et $column sont toujours des littéraux
 * fournis par notre propre code (jamais issus de $_POST), donc pas de
 * risque d'injection malgré l'absence de placeholder sur les identifiants.
 */
function ueb_pdf_lookup( $table, $id, $column = 'libelle' ) {
    global $wpdb;

    if ( ! $id ) {
        return '';
    }

    $valeur = $wpdb->get_var( $wpdb->prepare(
        "SELECT `{$column}` FROM `{$table}` WHERE id = %d",
        $id
    ) );

    return $valeur ? $valeur : '';
}

/**
 * Calcule l'année académique en cours au format "AAAA-AAAA", avec bascule
 * au 1er octobre (avant octobre : année N-1/N ; à partir d'octobre : N/N+1).
 */
function ueb_get_annee_academique() {
    $mois = (int) date( 'n' );
    $annee = (int) date( 'Y' );

    if ( $mois >= 10 ) {
        return $annee . '&ndash;' . ( $annee + 1 );
    }

    return ( $annee - 1 ) . '&ndash;' . $annee;
}

function ueb_translate_type_formation( $code ) {
    // Enum simple (classique/pro), pas une table de référence : reste codé en dur.
    $map = array(
        'classique' => 'Formation Classique (étude de dossier)',
        'pro'       => 'Formation Professionnelle — Licence Pro (LP)',
    );
    return $map[ $code ] ?? $code;
}


/* ============================================================
   GÉNÉRATION DU PDF
   ============================================================ */

function ueb_handle_pdf_generation() {
    if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'generate_pdf' ) {
        return;
    }

    if ( ! isset( $_POST['preinscription_nonce'] ) ||
         ! wp_verify_nonce( $_POST['preinscription_nonce'], 'preinscription_submit' ) ) {
        wp_die( 'Erreur de sécurité.' );
    }

    require_once get_template_directory() . '/lib/tcpdf/tcpdf.php';

    // WordPress ajoute des antislashs devant les apostrophes dans $_POST
    // (émulation historique des "magic quotes"). On les retire AVANT
    // toute sanitisation, sinon des noms comme "Eto'o" deviennent "Eto\'o".
    $posted = wp_unslash( $_POST );

    $numero_dossier         = sanitize_text_field( $posted['numero_dossier'] ?? '' );
    $nom                    = sanitize_text_field( $posted['nom'] ?? '' );
    $prenom                 = sanitize_text_field( $posted['prenom'] ?? '' );
    $sexe                   = sanitize_text_field( $posted['sexe'] ?? '' );
    $date_naissance         = sanitize_text_field( $posted['date_naissance'] ?? '' );
    $lieu_naissance         = sanitize_text_field( $posted['lieu_naissance'] ?? '' );
    $niveau_lmd             = sanitize_text_field( $posted['niveau_lmd'] ?? '' );
    $type_formation         = sanitize_text_field( $posted['type_formation'] ?? 'classique' );
    $annee_obtention        = absint( $posted['annee_obtention'] ?? 0 );
    $email                  = sanitize_email( $posted['email'] ?? '' );
    $adresse                = sanitize_text_field( $posted['adresse'] ?? '' );
    $nom_pere               = sanitize_text_field( $posted['nom_pere'] ?? '' );
    $nom_mere               = sanitize_text_field( $posted['nom_mere'] ?? '' );
    $profession_pere        = sanitize_text_field( $posted['profession_pere'] ?? '' );

    // Champs stockés en ID de FK : on récupère les libellés via les tables ueb_*.
    $faculte_id       = absint( $posted['faculte'] ?? 0 );
    $diplome_id       = absint( $posted['diplome_admission'] ?? 0 );
    $serie_id         = absint( $posted['serie_diplome'] ?? 0 );
    $filiere_1_id     = absint( $posted['filiere_1'] ?? 0 );
    $filiere_2_id     = absint( $posted['filiere_2'] ?? 0 );
    $nationalite_id   = absint( $posted['nationalite'] ?? 0 );
    $situation_id     = absint( $posted['situation_matrimoniale'] ?? 0 );
    $statut_socio_id  = absint( $posted['statut_socio_professionnel'] ?? 0 );
    $region_id        = absint( $posted['region_origine'] ?? 0 );
    $departement_id   = absint( $posted['departement_origine'] ?? 0 );
    $commune_id       = absint( $posted['commune_origine'] ?? 0 );

    global $wpdb;
    $faculte_row = $faculte_id ? $wpdb->get_row( $wpdb->prepare(
        "SELECT code, nom_fr, nom_en, logo FROM ueb_facultes WHERE id = %d", $faculte_id
    ) ) : null;

    $diplome_admission_label      = ueb_pdf_lookup( 'ueb_diplomes_admission', $diplome_id, 'libelle' );
    $type_formation_label         = ueb_translate_type_formation( $type_formation );
    $situation_matrimoniale_label = ueb_pdf_lookup( 'ueb_situations_matrimoniales', $situation_id, 'libelle' );
    $nationalite_label            = ueb_pdf_lookup( 'ueb_nationalites', $nationalite_id, 'nom' );
    $statut_socio_pro_label       = ueb_pdf_lookup( 'ueb_statuts_socio_professionnels', $statut_socio_id, 'libelle' );
    $region_origine_label         = ueb_pdf_lookup( 'ueb_regions', $region_id, 'nom' );
    $departement_origine_label    = ueb_pdf_lookup( 'ueb_departements', $departement_id, 'nom' );
    $commune_origine_label        = ueb_pdf_lookup( 'ueb_communes', $commune_id, 'nom' );
    $serie_diplome_label          = ueb_pdf_lookup( 'ueb_specialites_diplome', $serie_id, 'libelle' );
    $filiere_1_label              = ueb_pdf_lookup( 'ueb_filieres', $filiere_1_id, 'libelle' );
    $filiere_2_label              = ueb_pdf_lookup( 'ueb_filieres', $filiere_2_id, 'libelle' );

    $telephones  = isset( $posted['telephone'] ) ? (array) $posted['telephone'] : array();
    $tels_tuteur = isset( $posted['tel_tuteur'] ) ? (array) $posted['tel_tuteur'] : array();
    $telephones  = array_map( 'sanitize_text_field', $telephones );
    $tels_tuteur = array_map( 'sanitize_text_field', $tels_tuteur );
    $telephone_str  = implode( ' / ', array_filter( $telephones ) );
    $tel_tuteur_str = implode( ' / ', array_filter( $tels_tuteur ) );

    $pdf = new TCPDF( 'P', 'mm', 'A4', true, 'UTF-8', false );
    $pdf->SetCreator( 'UEB Préinscriptions' );
    $pdf->SetAuthor( "Université d'Ebolowa" );
    $pdf->SetTitle( 'Fiche de Préinscription — ' . strtoupper($nom) . ' ' . $prenom );
    $pdf->setPrintHeader( false );
    $pdf->setPrintFooter( false );
    $pdf->SetMargins( 18, 18, 18 );
    $pdf->SetAutoPageBreak( false, 10 ); // sécurité anti-débordement sur page 2
    $pdf->AddPage();
    $pdf->SetFont( 'dejavusans', '', 9 );

    $html = ueb_pdf_html(
        $numero_dossier, $nom, $prenom, $sexe, $date_naissance, $lieu_naissance,
        $nationalite_label, $situation_matrimoniale_label, $statut_socio_pro_label,
        $faculte_row, $diplome_admission_label,
        $serie_diplome_label, $niveau_lmd, $type_formation_label, $filiere_1_label, $filiere_2_label,
        $annee_obtention, $email, $telephone_str, $adresse, $region_origine_label,
        $departement_origine_label, $commune_origine_label, $nom_pere, $nom_mere,
        $tel_tuteur_str, $profession_pere
    );

    $pdf->writeHTML( $html, true, false, true, false, '' );

    $filename = 'fiche-preinscription-' . sanitize_title( $nom . '-' . $prenom ) . '.pdf';
    $pdf->Output( $filename, 'D' );
    exit;
}
add_action( 'template_redirect', 'ueb_handle_pdf_generation' );


/**
 * Échappe les valeurs saisies par l'utilisateur avant insertion dans le HTML
 * du PDF, pour un affichage correct des apostrophes, accents et esperluettes
 * et pour éviter toute casse du balisage HTML.
 */
function ueb_e( $value ) {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}


/**
 * Construit une section en 2 colonnes (label/valeur x2 par ligne)
 * pour diviser la hauteur par ~2 par rapport à une colonne unique.
 */
function ueb_pdf_section_2col( $title, $fields, $vert, $fond ) {
    $html = '<table width="100%" cellpadding="4.5" cellspacing="0" style="font-size:8.5px;">';
    $html .= '<tr><td colspan="4" style="background-color:' . $vert . ';color:#ffffff;font-weight:bold;padding:4.5px 8px;font-size:9px;">' . $title . '</td></tr>';

    $pairs = array_chunk( $fields, 2, true );
    $i = 0;
    foreach ( $pairs as $pair ) {
        $bg = ( $i % 2 === 0 ) ? $fond : '#ffffff';
        $html .= '<tr style="background-color:' . $bg . ';">';
        foreach ( $pair as $label => $value ) {
            $html .= '<td width="17%"><strong>' . $label . '</strong></td><td width="33%">' . ( $value !== '' ? $value : '—' ) . '</td>';
        }
        if ( count( $pair ) === 1 ) {
            $html .= '<td width="17%"></td><td width="33%"></td>';
        }
        $html .= '</tr>';
        $i++;
    }
    $html .= '</table>';
    return $html;
}


function ueb_pdf_html( $numero_dossier, $nom, $prenom, $sexe, $date_naissance,
    $lieu_naissance, $nationalite, $situation_matrimoniale, $statut_socio_pro, $faculte_row,
    $diplome_admission, $serie_diplome, $niveau_lmd, $type_formation,
    $filiere_1, $filiere_2, $annee_obtention, $email, $telephone_str,
    $adresse, $region_origine, $departement_origine, $commune_origine,
    $nom_pere, $nom_mere, $tel_tuteur_str, $profession_pere ) {

    $date_generation = date('d/m/Y à H:i');
    $logo_ueb = get_template_directory() . '/assets/images/logo-ueb.png';

    $fac_nom_fr = $faculte_row ? $faculte_row->nom_fr : '';
    $fac_nom_en = $faculte_row ? $faculte_row->nom_en : '';
    $fac_logo   = ( $faculte_row && $faculte_row->logo )
        ? get_template_directory() . '/assets/images/' . $faculte_row->logo
        : '';

    $vert = '#1a4a2e';
    $or   = '#c9a227';
    $gris = '#6b7280';
    $fond = '#f7f7f5';

    /* ===== EN-TÊTE ADMINISTRATIF BILINGUE (compact) ===== */
    $html = '
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td width="34%" align="center" style="font-size:8.5px;line-height:1.7;">
                <strong>RÉPUBLIQUE DU CAMEROUN</strong><br/>
                <span style="font-style:italic;">Paix – Travail – Patrie</span><br/>
                MINISTÈRE DE L\'ENSEIGNEMENT SUPÉRIEUR<br/>
                <strong style="color:' . $vert . ';">UNIVERSITÉ D\'ÉBOLOWA</strong>
            </td>
            <td width="32%" align="center">
                <img src="' . $logo_ueb . '" width="40" /><br/>
                <div style="height:14px;"></div>';

    if ( $fac_logo && file_exists( $fac_logo ) ) {
        $html .= '
                <img src="' . $fac_logo . '" width="40" /><br/>';
    }

    $html .= '
                <p style="margin:4px 0 0 0;font-size:10px;font-weight:bold;color:' . $vert . ';">' . ueb_e( $fac_nom_fr ) . '</p>
                <p style="margin:0;font-size:8px;font-style:italic;color:' . $gris . ';">' . ueb_e( $fac_nom_en ) . '</p>
            </td>
            <td width="34%" align="center" style="font-size:8.5px;line-height:1.7;">
                <strong>REPUBLIC OF CAMEROON</strong><br/>
                <span style="font-style:italic;">Peace – Work – Fatherland</span><br/>
                MINISTRY OF HIGHER EDUCATION<br/>
                <strong style="color:' . $vert . ';">THE UNIVERSITY OF EBOLOWA</strong>
            </td>
        </tr>
    </table>

    <table width="100%" cellpadding="0" style="margin-top:16px;margin-bottom:14px;">
        <tr>
            <td width="65%" style="vertical-align:middle;">
                <h2 style="color:' . $vert . ';margin:0;font-size:17px;">FICHE DE PRÉINSCRIPTION</h2>
                <p style="margin:2px 0 0 0;font-size:9px;color:' . $gris . ';">Année académique ' . ueb_get_annee_academique() . '</p>
            </td>
            <td width="35%" align="right" style="vertical-align:middle;">
                <p style="font-size:9.5px;color:' . $gris . ';margin:0;white-space:nowrap;">N&deg; Dossier : <strong style="color:' . $or . ';">' . ueb_e( $numero_dossier ) . '</strong></p>
            </td>
        </tr>
    </table>
    ';

    /* ===== SECTIONS EN 2 COLONNES ===== */
    $html .= ueb_pdf_section_2col( 'FORMATION CHOISIE', array(
        "Diplôme d'admission"  => ueb_e( $diplome_admission ),
        'Série / Spécialité'   => ueb_e( $serie_diplome ),
        "Année d'obtention"    => ueb_e( $annee_obtention ),
        'Niveau LMD'           => ueb_e( $niveau_lmd ),
        'Type de formation'    => ueb_e( $type_formation ),
        '1er choix de filière' => ueb_e( $filiere_1 ),
        '2e choix de filière'  => ueb_e( $filiere_2 ),
    ), $vert, $fond );

    $html .= '<div style="height:9px;"></div>';

    $html .= ueb_pdf_section_2col( 'ÉTAT CIVIL', array(
        'Nom'                     => ueb_e( strtoupper( $nom ) ),
        'Prénom(s)'               => ueb_e( $prenom ),
        'Sexe'                    => ( $sexe === 'M' ? 'Masculin' : 'Féminin' ),
        'Date de naissance'       => ueb_e( $date_naissance ),
        'Lieu de naissance'       => ueb_e( $lieu_naissance ),
        'Nationalité'             => ueb_e( $nationalite ),
        'Situation matrimoniale'  => ueb_e( $situation_matrimoniale ),
        'Statut socio-professionnel' => ueb_e( $statut_socio_pro ),
    ), $vert, $fond );

    $html .= '<div style="height:9px;"></div>';

    $html .= ueb_pdf_section_2col( 'CONTACT &amp; ORIGINE', array(
        'Email'                    => ueb_e( $email ),
        'Téléphone(s)'             => ueb_e( $telephone_str ),
        'Adresse actuelle'         => ueb_e( $adresse ),
        "Région d'origine"         => ueb_e( $region_origine ),
        "Département d'origine"    => ueb_e( $departement_origine ),
        "Commune d'origine"        => ueb_e( $commune_origine ),
        'Nom du père'              => ueb_e( $nom_pere ),
        'Nom de la mère'           => ueb_e( $nom_mere ),
        'Tél. tuteur / parent'     => ueb_e( $tel_tuteur_str ),
        'Profession du père'       => ueb_e( $profession_pere ),
    ), $vert, $fond );

    $html .= '<div style="height:26px;"></div>

    <div style="height:1px;background-color:#e4e4e0;margin-top:16px;"></div>
    <p style="font-size:8.5px;color:' . $gris . ';text-align:center;margin-top:8px;">
        Document provisoire — Préinscription non définitive &nbsp;·&nbsp;
        Généré le ' . $date_generation . ' · Université d\'Ébolowa
    </p>';

    return $html;
}
