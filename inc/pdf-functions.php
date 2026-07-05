<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   TABLES DE TRADUCTION (codes → libellés complets)
   ============================================================ */

function ueb_translate_diplome( $code ) {
    $map = array(
        'bac'    => 'Baccalauréat',
        'gce_ol' => 'GCE O-Level',
    );
    return $map[ $code ] ?? $code;
}

function ueb_translate_type_formation( $code ) {
    $map = array(
        'classique' => 'Formation Classique (étude de dossier)',
        'pro'       => 'Formation Professionnelle — Licence Pro (LP)',
    );
    return $map[ $code ] ?? $code;
}

function ueb_translate_situation( $code ) {
    $map = array(
        'celibataire' => 'Célibataire',
        'marie'       => 'Marié(e)',
        'divorce'     => 'Divorcé(e)',
        'veuf'        => 'Veuf / Veuve',
    );
    return $map[ $code ] ?? $code;
}

function ueb_translate_region( $code ) {
    $map = array(
        'adamaoua'    => 'Adamaoua',
        'centre'      => 'Centre',
        'est'         => 'Est',
        'extreme_nord'=> 'Extrême-Nord',
        'littoral'    => 'Littoral',
        'nord'        => 'Nord',
        'nord_ouest'  => 'Nord-Ouest',
        'ouest'       => 'Ouest',
        'sud'         => 'Sud',
        'sud_ouest'   => 'Sud-Ouest',
    );
    return $map[ $code ] ?? $code;
}

function ueb_translate_serie( $faculte, $diplome, $code ) {
    $series = array(
        'FS' => array(
            'bac'    => array(
                'C'  => 'Série C — Mathématiques et Sciences Physiques',
                'D'  => 'Série D — Sciences Naturelles',
                'TI' => 'Série TI — Technique Industrielle',
                'F'  => 'Série F — Sciences Techniques',
            ),
            'gce_ol' => array(
                'GCE_OL_SCI' => 'GCE O/L — Sciences',
            ),
        ),
        'FALSH' => array(
            'bac'    => array(
                'A' => 'Série A — Lettres, Philosophie, Sciences Sociales',
                'B' => 'Série B — Sciences Économiques et Sociales',
            ),
            'gce_ol' => array(
                'GCE_OL_ART' => 'GCE O/L — Arts & Humanities',
                'GCE_OL_SOC' => 'GCE O/L — Social Sciences',
            ),
        ),
        'FSEG' => array(
            'bac'    => array(
                'B'  => 'Série B — Sciences Économiques et Sociales',
                'G'  => 'Série G — Techniques de Gestion',
                'TI' => 'Série TI — Technique Industrielle',
                'C'  => 'Série C — Mathématiques et Sciences Physiques',
                'D'  => 'Série D — Sciences Naturelles',
            ),
            'gce_ol' => array(
                'GCE_OL_COM' => 'GCE O/L — Commerce / Economics',
                'GCE_OL_GEN' => 'GCE O/L — General',
            ),
        ),
        'FSJP' => array(
            'bac'    => array(
                'A' => 'Série A — Lettres, Philosophie, Sciences Sociales',
                'B' => 'Série B — Sciences Économiques et Sociales',
                'C' => 'Série C — Mathématiques et Sciences Physiques',
                'D' => 'Série D — Sciences Naturelles',
                'G' => 'Série G — Techniques de Gestion',
            ),
            'gce_ol' => array(
                'GCE_OL_ALL' => 'GCE O/L — Toutes séries',
            ),
        ),
    );

    return $series[ $faculte ][ $diplome ][ $code ] ?? $code;
}

function ueb_translate_filiere( $faculte, $type_formation, $code ) {
    if ( empty( $code ) ) {
        return '';
    }

    $filieres = array(
        'FS' => array(
            'classique' => array(
                'TIC'  => "TIC — Technologies de l'Information et de la Communication",
                'PHY'  => 'Physique Appliquée',
                'CHIM' => 'Chimie Appliquée',
                'GEO'  => 'Géosciences et Environnement',
                'ROSE' => 'ROSE — Recherche Opérationnelle et Économétrie',
                'BIO'  => 'Biotechnologie et Pharmacognosie',
            ),
            'pro' => array(
                'LP_BIO_MED' => 'LP Sciences Biomédicales et Médico-Sanitaires',
                'LP_BIO_AGR' => "LP Sciences Biologiques Appliquées à l'Agriculture",
            ),
        ),
        'FALSH' => array(
            'classique' => array(
                'LMF'   => 'Lettres Modernes Françaises',
                'LEA'   => 'Langues Étrangères Appliquées',
                'HIST'  => 'Histoire',
                'GEO'   => 'Géographie',
                'PHILO' => 'Philosophie',
                'SOCIO' => 'Sociologie',
            ),
            'pro' => array(),
        ),
        'FSEG' => array(
            'classique' => array(
                'ECO'    => 'Économie',
                'GEST'   => 'Gestion',
                'COMPTA' => 'Comptabilité et Finance',
                'BANQUE' => 'Banque et Finance',
                'MKT'    => 'Marketing',
            ),
            'pro' => array(),
        ),
        'FSJP' => array(
            'classique' => array(
                'DPRIV' => 'Droit Privé',
                'DPUB'  => 'Droit Public',
                'SCPOL' => 'Science Politique',
                'RI'    => 'Relations Internationales',
            ),
            'pro' => array(),
        ),
    );

    if ( isset( $filieres[ $faculte ][ $type_formation ][ $code ] ) ) {
        return $filieres[ $faculte ][ $type_formation ][ $code ];
    }
    if ( isset( $filieres[ $faculte ]['classique'][ $code ] ) ) {
        return $filieres[ $faculte ]['classique'][ $code ];
    }

    return $code;
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

    $nom                    = sanitize_text_field( $_POST['nom'] ?? '' );
    $prenom                 = sanitize_text_field( $_POST['prenom'] ?? '' );
    $sexe                   = sanitize_text_field( $_POST['sexe'] ?? '' );
    $date_naissance         = sanitize_text_field( $_POST['date_naissance'] ?? '' );
    $lieu_naissance         = sanitize_text_field( $_POST['lieu_naissance'] ?? '' );
    $nationalite            = sanitize_text_field( $_POST['nationalite'] ?? '' );
    $situation_matrimoniale = sanitize_text_field( $_POST['situation_matrimoniale'] ?? '' );
    $faculte                = sanitize_text_field( $_POST['faculte'] ?? '' );
    $diplome_admission      = sanitize_text_field( $_POST['diplome_admission'] ?? '' );
    $serie_diplome          = sanitize_text_field( $_POST['serie_diplome'] ?? '' );
    $niveau_lmd             = sanitize_text_field( $_POST['niveau_lmd'] ?? '' );
    $type_formation         = sanitize_text_field( $_POST['type_formation'] ?? 'classique' );
    $filiere_1              = sanitize_text_field( $_POST['filiere_1'] ?? '' );
    $filiere_2              = sanitize_text_field( $_POST['filiere_2'] ?? '' );
    $annee_obtention        = absint( $_POST['annee_obtention'] ?? 0 );
    $email                  = sanitize_email( $_POST['email'] ?? '' );
    $adresse                = sanitize_text_field( $_POST['adresse'] ?? '' );
    $region_origine         = sanitize_text_field( $_POST['region_origine'] ?? '' );
    $departement_origine    = sanitize_text_field( $_POST['departement_origine'] ?? '' );
    $arrondissement_origine = sanitize_text_field( $_POST['arrondissement_origine'] ?? '' );
    $nom_pere               = sanitize_text_field( $_POST['nom_pere'] ?? '' );
    $nom_mere               = sanitize_text_field( $_POST['nom_mere'] ?? '' );
    $profession_pere        = sanitize_text_field( $_POST['profession_pere'] ?? '' );

    $telephones  = isset( $_POST['telephone'] ) ? (array) $_POST['telephone'] : array();
    $tels_tuteur = isset( $_POST['tel_tuteur'] ) ? (array) $_POST['tel_tuteur'] : array();
    $telephones  = array_map( 'sanitize_text_field', $telephones );
    $tels_tuteur = array_map( 'sanitize_text_field', $tels_tuteur );
    $telephone_str  = implode( ' / ', array_filter( $telephones ) );
    $tel_tuteur_str = implode( ' / ', array_filter( $tels_tuteur ) );

    $diplome_admission_label      = ueb_translate_diplome( $diplome_admission );
    $type_formation_label         = ueb_translate_type_formation( $type_formation );
    $situation_matrimoniale_label = ueb_translate_situation( $situation_matrimoniale );
    $region_origine_label         = ueb_translate_region( $region_origine );
    $serie_diplome_label          = ueb_translate_serie( $faculte, $diplome_admission, $serie_diplome );
    $filiere_1_label              = ueb_translate_filiere( $faculte, $type_formation, $filiere_1 );
    $filiere_2_label              = ueb_translate_filiere( $faculte, 'classique', $filiere_2 );

    $numero_dossier = 'UEB-' . date('Y') . '-' . strtoupper( substr( $nom, 0, 3 ) ) . rand(1000, 9999);

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
        $nationalite, $situation_matrimoniale_label, $faculte, $diplome_admission_label,
        $serie_diplome_label, $niveau_lmd, $type_formation_label, $filiere_1_label, $filiere_2_label,
        $annee_obtention, $email, $telephone_str, $adresse, $region_origine_label,
        $departement_origine, $arrondissement_origine, $nom_pere, $nom_mere,
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


function ueb_get_faculte_info( $code ) {
    $img_dir = get_template_directory() . '/assets/images/';

    $facultes = array(
        'FS'    => array(
            'fr'   => 'Faculté des Sciences',
            'en'   => 'Faculty of Science',
            'logo' => $img_dir . 'logo-fs.jpg',
        ),
        'FALSH' => array(
            'fr'   => 'Faculté des Arts, Lettres et Sciences Humaines',
            'en'   => 'Faculty of Arts, Letters and Human Sciences',
            'logo' => $img_dir . 'logo-falsh.jpg',
        ),
        'FSEG'  => array(
            'fr'   => 'Faculté des Sciences Économiques et de Gestion',
            'en'   => 'Faculty of Economics and Management',
            'logo' => $img_dir . 'logo-fseg.jpg',
        ),
        'FSJP'  => array(
            'fr'   => 'Faculté des Sciences Juridiques et Politiques',
            'en'   => 'Faculty of Law and Political Sciences',
            'logo' => $img_dir . 'logo-fsjp.jpg',
        ),
    );

    return $facultes[ $code ] ?? array(
        'fr'   => $code,
        'en'   => $code,
        'logo' => '',
    );
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
    $lieu_naissance, $nationalite, $situation_matrimoniale, $faculte,
    $diplome_admission, $serie_diplome, $niveau_lmd, $type_formation,
    $filiere_1, $filiere_2, $annee_obtention, $email, $telephone_str,
    $adresse, $region_origine, $departement_origine, $arrondissement_origine,
    $nom_pere, $nom_mere, $tel_tuteur_str, $profession_pere ) {

    $date_generation = date('d/m/Y à H:i');
    $logo_ueb = get_template_directory() . '/assets/images/logo-ueb.png';
    $fac_info = ueb_get_faculte_info( $faculte );

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
                <div style="height:6px;"></div>';

    if ( $fac_info['logo'] && file_exists( $fac_info['logo'] ) ) {
        $html .= '
                <img src="' . $fac_info['logo'] . '" width="40" /><br/>';
    }

    $html .= '
                <p style="margin:4px 0 0 0;font-size:10px;font-weight:bold;color:' . $vert . ';">' . $fac_info['fr'] . '</p>
                <p style="margin:0;font-size:8px;font-style:italic;color:' . $gris . ';">' . $fac_info['en'] . '</p>
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
                <p style="margin:2px 0 0 0;font-size:9px;color:' . $gris . ';">Année académique 2025–2027</p>
            </td>
            <td width="35%" align="right" style="vertical-align:middle;">
                <p style="font-size:9.5px;color:' . $gris . ';margin:0;white-space:nowrap;">N&deg; Dossier : <strong style="color:' . $or . ';">' . $numero_dossier . '</strong></p>
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
        'Nom'                    => ueb_e( strtoupper( $nom ) ),
        'Prénom(s)'               => ueb_e( $prenom ),
        'Sexe'                    => ( $sexe === 'M' ? 'Masculin' : 'Féminin' ),
        'Date de naissance'       => ueb_e( $date_naissance ),
        'Lieu de naissance'       => ueb_e( $lieu_naissance ),
        'Nationalité'             => ueb_e( $nationalite ),
        'Situation matrimoniale'  => ueb_e( $situation_matrimoniale ),
    ), $vert, $fond );

    $html .= '<div style="height:9px;"></div>';

    $html .= ueb_pdf_section_2col( 'CONTACT &amp; ORIGINE', array(
        'Email'                    => ueb_e( $email ),
        'Téléphone(s)'             => ueb_e( $telephone_str ),
        'Adresse actuelle'         => ueb_e( $adresse ),
        "Région d'origine"         => ueb_e( $region_origine ),
        "Département d'origine"    => ueb_e( $departement_origine ),
        'Arrondissement'           => ueb_e( $arrondissement_origine ),
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