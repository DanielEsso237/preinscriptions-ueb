<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Génération du PDF de préinscription — nouveau format 3 pages :
 *
 *   Page 1 : fiche de préinscription + coupon récépissé de dépôt
 *            (détachable, ligne de découpe), QR code du numéro de dossier.
 *   Page 2 : fiche médicale (code de préinscription, informations
 *            personnelles, personne à contacter en cas d'urgence).
 *   Page 3 : fiche d'examen médical officielle, reproduite telle quelle
 *            depuis le modèle du prof (image assets/pdf/, à remplir à la
 *            main par le médecin).
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

function ueb_get_annee_academique() {
    $mois  = (int) date( 'n' );
    $annee = (int) date( 'Y' );

    if ( $mois >= 10 ) {
        return $annee . '-' . ( $annee + 1 );
    }

    return ( $annee - 1 ) . '-' . $annee;
}

function ueb_translate_type_formation( $code ) {
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

    $posted = wp_unslash( $_POST );

    $numero_dossier  = sanitize_text_field( $posted['numero_dossier'] ?? '' );
    $nom             = sanitize_text_field( $posted['nom'] ?? '' );
    $prenom          = sanitize_text_field( $posted['prenom'] ?? '' );
    $sexe            = sanitize_text_field( $posted['sexe'] ?? '' );
    $date_naissance  = sanitize_text_field( $posted['date_naissance'] ?? '' );
    $lieu_naissance  = sanitize_text_field( $posted['lieu_naissance'] ?? '' );
    $type_formation  = sanitize_text_field( $posted['type_formation'] ?? 'classique' );
    $annee_obtention = absint( $posted['annee_obtention'] ?? 0 );
    $email           = sanitize_email( $posted['email'] ?? '' );
    $adresse         = sanitize_text_field( $posted['adresse'] ?? '' );

    $moyenne_diplome = isset( $posted['moyenne_diplome'] ) && $posted['moyenne_diplome'] !== ''
        ? number_format( (float) $posted['moyenne_diplome'], 2 )
        : '';

    $handicap = in_array( $posted['handicap'] ?? '', array( 'oui', 'non' ), true ) ? $posted['handicap'] : 'non';

    $nom_pere         = sanitize_text_field( $posted['nom_pere'] ?? '' );
    $numero_pere      = sanitize_text_field( $posted['numero_pere'] ?? '' );
    $profession_pere  = sanitize_text_field( $posted['profession_pere'] ?? '' );
    $nom_mere         = sanitize_text_field( $posted['nom_mere'] ?? '' );
    $numero_mere      = sanitize_text_field( $posted['numero_mere'] ?? '' );
    $profession_mere  = sanitize_text_field( $posted['profession_mere'] ?? '' );
    $nom_tuteur       = sanitize_text_field( $posted['nom_tuteur'] ?? '' );
    $numero_tuteur    = sanitize_text_field( $posted['numero_tuteur'] ?? '' );

    $numero_certificat_medical = sanitize_text_field( $posted['numero_certificat_medical'] ?? '' );
    $lieu_obtention_certificat = sanitize_text_field( $posted['lieu_obtention_certificat'] ?? '' );

    $faculte_id         = absint( $posted['faculte'] ?? 0 );
    $diplome_id         = absint( $posted['diplome_admission'] ?? 0 );
    $serie_id           = absint( $posted['serie_diplome'] ?? 0 );
    $filiere_1_id       = absint( $posted['filiere_1'] ?? 0 );
    $filiere_2_id       = absint( $posted['filiere_2'] ?? 0 );
    $filiere_3_id       = absint( $posted['filiere_3'] ?? 0 );
    $niveau_lmd_id      = absint( $posted['niveau_lmd'] ?? 0 );
    $mention_id         = absint( $posted['mention'] ?? 0 );
    $statut_etudiant_id = absint( $posted['statut_etudiant'] ?? 0 );
    $nationalite_id     = absint( $posted['nationalite'] ?? 0 );
    $premiere_langue_id = absint( $posted['premiere_langue'] ?? 0 );
    $situation_id       = absint( $posted['situation_matrimoniale'] ?? 0 );
    $statut_socio_id    = absint( $posted['statut_socio_professionnel'] ?? 0 );
    $region_id          = absint( $posted['region_origine'] ?? 0 );
    $departement_id     = absint( $posted['departement_origine'] ?? 0 );
    $commune_id         = absint( $posted['commune_origine'] ?? 0 );
    $sport_id           = absint( $posted['sport_prefere'] ?? 0 );
    $art_id             = absint( $posted['art_pratique'] ?? 0 );

    global $wpdb;
    $faculte_row = $faculte_id ? $wpdb->get_row( $wpdb->prepare(
        "SELECT code, nom_fr, nom_en, logo FROM ueb_facultes WHERE id = %d", $faculte_id
    ) ) : null;

    $telephones = isset( $posted['telephone'] ) ? (array) $posted['telephone'] : array();
    $telephones = array_map( 'sanitize_text_field', $telephones );

    $d = array(
        'numero_dossier'         => $numero_dossier,
        'annee_academique'       => ueb_get_annee_academique(),
        'faculte'                => $faculte_row ? $faculte_row->nom_fr : '',
        'faculte_code'           => $faculte_row ? $faculte_row->code : '',
        'diplome_admission'      => ueb_pdf_lookup( 'ueb_diplomes_admission', $diplome_id, 'libelle' ),
        'serie_diplome'          => ueb_pdf_lookup( 'ueb_specialites_diplome', $serie_id, 'libelle' ),
        'annee_obtention'        => $annee_obtention ? (string) $annee_obtention : '',
        'moyenne_diplome'        => $moyenne_diplome,
        'niveau_lmd'             => ueb_pdf_lookup( 'ueb_niveaux_lmd', $niveau_lmd_id, 'libelle' ),
        'mention'                => ueb_pdf_lookup( 'ueb_mentions', $mention_id, 'libelle' ),
        'statut_etudiant'        => ueb_pdf_lookup( 'ueb_statuts_etudiants', $statut_etudiant_id, 'libelle' ),
        'type_formation'         => ueb_translate_type_formation( $type_formation ),
        'filiere_1'              => ueb_pdf_lookup( 'ueb_filieres', $filiere_1_id, 'libelle' ),
        'filiere_2'              => ueb_pdf_lookup( 'ueb_filieres', $filiere_2_id, 'libelle' ),
        'filiere_3'              => ueb_pdf_lookup( 'ueb_filieres', $filiere_3_id, 'libelle' ),
        'nom'                    => $nom,
        'prenom'                 => $prenom,
        'sexe'                   => $sexe,
        'date_naissance'         => $date_naissance,
        'lieu_naissance'         => $lieu_naissance,
        'nationalite'            => ueb_pdf_lookup( 'ueb_nationalites', $nationalite_id, 'nom' ),
        'premiere_langue'        => ueb_pdf_lookup( 'ueb_langues', $premiere_langue_id, 'libelle' ),
        'situation_matrimoniale' => ueb_pdf_lookup( 'ueb_situations_matrimoniales', $situation_id, 'libelle' ),
        'statut_socio'           => ueb_pdf_lookup( 'ueb_statuts_socio_professionnels', $statut_socio_id, 'libelle' ),
        'handicap'               => $handicap === 'oui' ? 'Oui' : 'Non',
        'region'                 => ueb_pdf_lookup( 'ueb_regions', $region_id, 'nom' ),
        'departement'            => ueb_pdf_lookup( 'ueb_departements', $departement_id, 'nom' ),
        'commune'                => ueb_pdf_lookup( 'ueb_communes', $commune_id, 'nom' ),
        'email'                  => $email,
        'adresse'                => $adresse,
        'telephone'              => implode( ' / ', array_filter( $telephones ) ),
        'nom_pere'               => $nom_pere,
        'numero_pere'            => $numero_pere,
        'profession_pere'        => $profession_pere,
        'nom_mere'               => $nom_mere,
        'numero_mere'            => $numero_mere,
        'profession_mere'        => $profession_mere,
        'nom_tuteur'             => $nom_tuteur,
        'numero_tuteur'          => $numero_tuteur,
        'sport_prefere'          => ueb_pdf_lookup( 'ueb_sports', $sport_id, 'libelle' ),
        'art_pratique'           => ueb_pdf_lookup( 'ueb_arts', $art_id, 'libelle' ),
        'numero_certificat_medical' => $numero_certificat_medical,
        'lieu_obtention_certificat' => $lieu_obtention_certificat,
    );

    $pdf = ueb_pdf_build_document( $d );

    $filename = 'fiche-preinscription-' . sanitize_title( $nom . '-' . $prenom ) . '.pdf';
    $pdf->Output( $filename, 'D' );
    exit;
}
add_action( 'template_redirect', 'ueb_handle_pdf_generation' );


function ueb_pdf_build_document( array $d ) {
    $pdf = new TCPDF( 'P', 'mm', 'A4', true, 'UTF-8', false );
    $pdf->SetCreator( 'UEB Préinscriptions' );
    $pdf->SetAuthor( "Université d'Ebolowa" );
    $pdf->SetTitle( 'Fiche de Préinscription — ' . strtoupper( $d['nom'] ) . ' ' . $d['prenom'] );
    $pdf->setPrintHeader( false );
    $pdf->setPrintFooter( false );
    $pdf->SetMargins( 0, 0, 0 );
    $pdf->SetAutoPageBreak( false, 0 );

    ueb_pdf_page_fiche( $pdf, $d );
    ueb_pdf_page_medicale( $pdf, $d );
    ueb_pdf_page_examen( $pdf );

    return $pdf;
}


/* ============================================================
   PALETTE & HELPERS
   ============================================================ */

function ueb_pdf_couleurs() {
    return array(
        'vert'       => array( 22, 82, 49 ),
        'vert_titre' => array( 22, 106, 58 ),
        'orange'     => array( 232, 126, 24 ),
        'or'         => array( 240, 190, 60 ),
        'noir'       => array( 33, 37, 41 ),
        'gris'       => array( 107, 114, 128 ),
        'ligne'      => array( 225, 229, 226 ),
        'fond'       => array( 243, 247, 244 ),
        'vert_pale'  => array( 238, 245, 239 ),
    );
}

function ueb_pdf_sans_accents( $txt ) {
    $map = array(
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'œ' => 'oe',
        'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Î' => 'I', 'Ï' => 'I', 'Ô' => 'O', 'Ö' => 'O', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'Ç' => 'C', 'Œ' => 'OE',
        '—' => '-', '–' => '-', '’' => "'",
    );
    $s     = strtr( (string) $txt, $map );
    $ascii = iconv( 'UTF-8', 'ASCII//IGNORE', $s );
    return $ascii !== false ? $ascii : $s;
}

function ueb_pdf_qr_stylise( $pdf, $texte, $x, $y, $taille ) {
    if ( ! class_exists( 'TCPDF2DBarcode' ) ) {
        require_once get_template_directory() . '/lib/tcpdf/tcpdf_barcodes_2d.php';
    }

    $encre = array( 0, 0, 0 );
    $blanc = array( 255, 255, 255 );

    $barcode = new TCPDF2DBarcode( $texte, 'QRCODE,L' );
    $matrice = $barcode->getBarcodeArray();
    if ( empty( $matrice['bcode'] ) ) {
        return;
    }

    $n = $matrice['num_cols'];
    $m = $taille / $n;

    $marge = 1.2;
    $pdf->SetLineStyle( array( 'width' => 0.2, 'dash' => 0, 'color' => $encre ) );
    $pdf->Rect( $x - $marge, $y - $marge, $taille + 2 * $marge, $taille + 2 * $marge );

    $dans_repere = static function ( $r, $c ) use ( $n ) {
        return ( $r < 7 && $c < 7 ) || ( $r < 7 && $c >= $n - 7 ) || ( $r >= $n - 7 && $c < 7 );
    };

    foreach ( $matrice['bcode'] as $r => $ligne ) {
        foreach ( $ligne as $c => $plein ) {
            if ( ! $plein || $dans_repere( $r, $c ) ) {
                continue;
            }
            $pdf->Circle( $x + ( $c + 0.5 ) * $m, $y + ( $r + 0.5 ) * $m, $m * 0.475, 0, 360, 'F', array(), $encre );
        }
    }

    foreach ( array( array( 0, 0 ), array( 0, $n - 7 ), array( $n - 7, 0 ) ) as $coin ) {
        list( $r0, $c0 ) = $coin;
        $fx = $x + $c0 * $m;
        $fy = $y + $r0 * $m;
        $pdf->RoundedRect( $fx, $fy, 7 * $m, 7 * $m, 2 * $m, '1111', 'F', array(), $encre );
        $pdf->RoundedRect( $fx + $m, $fy + $m, 5 * $m, 5 * $m, 1.3 * $m, '1111', 'F', array(), $blanc );
        $pdf->RoundedRect( $fx + 2 * $m, $fy + 2 * $m, 3 * $m, 3 * $m, $m, '1111', 'F', array(), $encre );
    }
}

function ueb_pdf_txt( $pdf, $x, $y, $txt, $size, $style = '', $color = null, $align = 'L', $w = 0 ) {
    $c = $color ?: ueb_pdf_couleurs()['noir'];
    $pdf->SetFont( 'dejavusans', $style, $size );
    $pdf->SetTextColor( $c[0], $c[1], $c[2] );
    $pdf->SetXY( $x, $y );
    if ( $w > 0 ) {
        $pdf->Cell( $w, 5, $txt, 0, 0, $align );
    } else {
        $pdf->Cell( $pdf->GetStringWidth( $txt ) + 1, 5, $txt, 0, 0, 'L' );
    }
}

function ueb_pdf_txt_trad( $pdf, $x, $y, $label_fr, $label_en, $size, $style, $color ) {
    ueb_pdf_txt( $pdf, $x, $y, $label_fr, $size, $style, $color );

    if ( $label_en !== '' ) {
        $pdf->setAlpha( 0.45 );
        ueb_pdf_txt( $pdf, $x, $y + 2.3, $label_en, max( 4.6, $size - 1.3 ), 'I', $color );
        $pdf->setAlpha( 1 );
    }
}

function ueb_pdf_ligne( $pdf, $x1, $y1, $x2, $y2, $color, $width = 0.2, $dash = 0 ) {
    $pdf->SetLineStyle( array( 'width' => $width, 'dash' => $dash, 'color' => $color ) );
    $pdf->Line( $x1, $y1, $x2, $y2 );
}

function ueb_pdf_entete_bilingue( $pdf, $y ) {
    $c    = ueb_pdf_couleurs();
    $logo = get_template_directory() . '/assets/images/logo-ueb.png';

    ueb_pdf_txt( $pdf, 8,  $y,        'RÉPUBLIQUE DU CAMEROUN', 9, 'B', $c['noir'], 'C', 66 );
    ueb_pdf_txt( $pdf, 8,  $y + 5,    'Paix – Travail – Patrie', 8, 'I', $c['noir'], 'C', 66 );
    ueb_pdf_txt( $pdf, 8,  $y + 10,   'MINISTÈRE DE L\'ENSEIGNEMENT SUPÉRIEUR', 7, '', $c['noir'], 'C', 66 );
    ueb_pdf_txt( $pdf, 8,  $y + 15,   'UNIVERSITÉ D\'ÉBOLOWA', 8.5, 'B', $c['vert_titre'], 'C', 66 );

    if ( file_exists( $logo ) ) {
        $pdf->Image( $logo, 94, $y - 1, 22, 0, 'PNG' );
    }

    ueb_pdf_txt( $pdf, 136, $y,      'REPUBLIC OF CAMEROON', 9, 'B', $c['noir'], 'C', 66 );
    ueb_pdf_txt( $pdf, 136, $y + 5,  'Peace – Work – Fatherland', 8, 'I', $c['noir'], 'C', 66 );
    ueb_pdf_txt( $pdf, 136, $y + 10, 'MINISTRY OF HIGHER EDUCATION', 7, '', $c['noir'], 'C', 66 );
    ueb_pdf_txt( $pdf, 136, $y + 15, 'THE UNIVERSITY OF EBOLOWA', 8.5, 'B', $c['vert_titre'], 'C', 66 );

    return $y + 22;
}


/* ============================================================
   PAGE 1
   ============================================================ */

function ueb_pdf_section_fiche( $pdf, $titre, $lignes, $y ) {
    $c = ueb_pdf_couleurs();

    $pdf->RoundedRect( 8, $y, 194, 4.6, 0.6, '1111', 'F', array(), $c['vert'] );
    ueb_pdf_txt( $pdf, 11, $y + 0.15, $titre, 7.5, 'B', array( 255, 255, 255 ) );
    $y += 4.6;

    $pdf->SetFont( 'dejavusans', '', 6.8 );
    $i = 0;
    foreach ( $lignes as $ligne ) {
        list( $labelG, $labelG_en, $valG, $labelD, $labelD_en, $valD ) = $ligne;

        $nbG = $valG !== '' ? $pdf->getNumLines( $valG, 58 ) : 1;
        $nbD = $valD !== '' ? $pdf->getNumLines( $valD, 48.5 ) : 1;
        $nb  = max( $nbG, $nbD );
        $h   = max( 6.4, $nb * 2.8 + 4.0 );

        if ( $i % 2 === 0 ) {
            $pdf->Rect( 8, $y, 194, $h, 'F', array(), $c['fond'] );
        }

        $ty = $y + 0.7;

        if ( $labelG !== '' ) {
            ueb_pdf_txt_trad( $pdf, 11, $ty, $labelG, $labelG_en, 6.3, 'B', $c['noir'] );
            if ( $valG !== '' ) {
                $pdf->SetFont( 'dejavusans', '', 7 );
                $pdf->SetTextColor( $c['noir'][0], $c['noir'][1], $c['noir'][2] );
                $pdf->SetXY( 50, $y + ( $h - $nbG * 2.8 ) / 2 - 0.2 );
                $pdf->MultiCell( 58, 2.8, $valG, 0, 'L' );
            }
        }
        if ( $labelD !== '' ) {
            ueb_pdf_txt_trad( $pdf, 110, $ty, $labelD, $labelD_en, 6.3, 'B', $c['noir'] );
            if ( $valD !== '' ) {
                $pdf->SetFont( 'dejavusans', '', 7 );
                $pdf->SetTextColor( $c['noir'][0], $c['noir'][1], $c['noir'][2] );
                $pdf->SetXY( 152.5, $y + ( $h - $nbD * 2.8 ) / 2 - 0.2 );
                $pdf->MultiCell( 48.5, 2.8, $valD, 0, 'L' );
            }
        }

        ueb_pdf_ligne( $pdf, 8, $y + $h, 202, $y + $h, $c['ligne'], 0.12 );
        $y += $h;
        $i++;
    }

    return $y;
}

function ueb_pdf_page_fiche( $pdf, $d ) {
    $c  = ueb_pdf_couleurs();
    $nr = '(non renseigné)';

    $pdf->AddPage();
    $pdf->Rect( 0, 0, 210, 2.5, 'F', array(), $c['vert'] );

    ueb_pdf_entete_bilingue( $pdf, 6 );

    /* --- Cadre PHOTO --- */
    $pdf->SetLineStyle( array( 'width' => 0.25, 'dash' => '1.6,1.4', 'color' => $c['gris'] ) );
    $pdf->Rect( 8, 28, 22, 24 );
    ueb_pdf_txt( $pdf, 8, 38.5, 'PHOTO', 6.5, '', $c['gris'], 'C', 22 );

    /* --- Titre + numéro de dossier --- */
    ueb_pdf_txt( $pdf, 37, 30, 'FICHE DE PRÉINSCRIPTION', 15.5, 'B', $c['vert_titre'], 'C', 130 );

    ueb_pdf_ligne( $pdf, 82, 40.5, 99, 40.5, $c['gris'], 0.2 );
    ueb_pdf_txt( $pdf, 99.5, 38.7, '✂', 7, '', $c['gris'] );
    ueb_pdf_ligne( $pdf, 106, 40.5, 123, 40.5, $c['gris'], 0.2 );

    $pdf->SetFont( 'dejavusans', 'B', 10.5 );
    $w1 = $pdf->GetStringWidth( 'N° Dossier :  ' );
    $pdf->SetFont( 'dejavusans', 'B', 11 );
    $w2 = $pdf->GetStringWidth( $d['numero_dossier'] );
    $x0 = 37 + ( 130 - $w1 - $w2 ) / 2;
    ueb_pdf_txt( $pdf, $x0, 43, 'N° Dossier :  ', 10.5, 'B', $c['noir'] );
    ueb_pdf_txt( $pdf, $x0 + $w1, 42.9, $d['numero_dossier'], 11, 'B', $c['orange'] );

    /* --- QR code + année académique --- */
    $etab = $d['faculte_code'] !== '' ? $d['faculte_code'] : ueb_pdf_sans_accents( $d['faculte'] );

    $qr_fiche = 'Dossier : ' . $d['numero_dossier'] . "\n"
        . 'Nom : '     . ueb_pdf_sans_accents( strtoupper( $d['nom'] ) . ' ' . $d['prenom'] ) . "\n"
        . 'Ne(e) : '   . $d['date_naissance'] . "\n"
        . 'Sexe : '    . $d['sexe'] . "\n"
        . 'Etab : '    . $etab . "\n"
        . 'Niveau : '  . ueb_pdf_sans_accents( $d['niveau_lmd'] ) . "\n"
        . 'Choix 1 : ' . ueb_pdf_sans_accents( $d['filiere_1'] ) . "\n"
        . 'Choix 2 : ' . ueb_pdf_sans_accents( $d['filiere_2'] ) . "\n"
        . 'Email : '   . $d['email'] . "\n"
        . 'Tel : '     . str_replace( ' ', '', $d['telephone'] );

    ueb_pdf_qr_stylise( $pdf, $qr_fiche, 178, 27, 20 );
    ueb_pdf_txt( $pdf, 168, 48.3, 'Année académique', 7, 'B', $c['noir'], 'C', 40 );
    ueb_pdf_txt( $pdf, 168, 52,   $d['annee_academique'], 7, 'B', $c['noir'], 'C', 40 );

    /* --- Sections --- */
    $y = 59;

    $y = ueb_pdf_section_fiche( $pdf, 'FORMATION CHOISIE', array(
        array( 'Faculté', 'Faculty', $d['faculte'], "Diplôme d'admission", 'Admission diploma', $d['diplome_admission'] ),
        array( 'Type de formation', 'Training type', $d['type_formation'], 'Série / Spécialité', 'Series / Specialty', $d['serie_diplome'] ),
        array( '1er choix de filière', '1st choice of program', $d['filiere_1'], 'Moyenne obtenue', 'Average obtained', $d['moyenne_diplome'] ),
        array( '2e choix de filière', '2nd choice of program', $d['filiere_2'], 'Mention', 'Mention / Honors', $d['mention'] ),
        array( '3e choix de filière', '3rd choice of program', $d['filiere_3'], "Année d'obtention", 'Year obtained', $d['annee_obtention'] ),
        array( 'Niveau LMD', 'LMD level', $d['niveau_lmd'], 'Statut', 'Student status', $d['statut_etudiant'] ),
    ), $y );

    $y += 1.8;

    $date_lieu = trim( $d['date_naissance'] . ( $d['lieu_naissance'] !== '' ? ' à ' . $d['lieu_naissance'] : '' ) );
    $y = ueb_pdf_section_fiche( $pdf, 'ÉTAT CIVIL', array(
        array( 'Nom', 'Surname', strtoupper( $d['nom'] ), 'Nationalité', 'Nationality', $d['nationalite'] ),
        array( 'Prénom(s)', 'First name(s)', $d['prenom'], 'Première langue', 'First language', $d['premiere_langue'] ),
        array( 'Date et lieu de naissance', 'Date & place of birth', $date_lieu, 'Statut matrimonial', 'Marital status', $d['situation_matrimoniale'] ),
        array( 'Sexe', 'Gender', $d['sexe'] === 'M' ? 'Masculin' : ( $d['sexe'] === 'F' ? 'Féminin' : '' ), 'Statut socio-professionnel', 'Occupation status', $d['statut_socio'] ),
        array( 'Situation de handicap', 'Disability status', $d['handicap'], '', '', '' ),
    ), $y );

    $y += 1.8;

    $y = ueb_pdf_section_fiche( $pdf, 'CONTACT ET ORIGINE', array(
        array( 'Téléphone', 'Phone', $d['telephone'], 'Nom du père', "Father's name", $d['nom_pere'] ?: $nr ),
        array( 'E-mail', 'Email', $d['email'], 'Numéro du père', "Father's phone number", $d['numero_pere'] ?: $nr ),
        array( 'Adresse actuelle', 'Current address', $d['adresse'], 'Profession du père', "Father's occupation", $d['profession_pere'] ?: $nr ),
        array( 'Département', 'Department', $d['departement'], 'Nom de la mère', "Mother's name", $d['nom_mere'] ?: $nr ),
        array( 'Commune', 'Municipality', $d['commune'], 'Numéro de la mère', "Mother's phone number", $d['numero_mere'] ?: $nr ),
        array( "Région d'origine", 'Region of origin', $d['region'], 'Profession de la mère', "Mother's occupation", $d['profession_mere'] ?: $nr ),
        array( '', '', '', 'Nom du tuteur', "Guardian's name", $d['nom_tuteur'] ?: $nr ),
        array( '', '', '', 'Numéro du tuteur', "Guardian's phone number", $d['numero_tuteur'] ?: $nr ),
    ), $y );

    $y += 1.8;

    $y = ueb_pdf_section_fiche( $pdf, 'INFORMATIONS DIVERSES', array(
        array( 'Sport préféré', 'Favorite sport', $d['sport_prefere'] ?: $nr, 'N° certificat médical', 'Medical certificate no.', $d['numero_certificat_medical'] ?: $nr ),
        array( 'Art pratiqué', 'Art practiced', $d['art_pratique'] ?: $nr, "Lieu d'obtention du certificat", 'Certificate obtained at', $d['lieu_obtention_certificat'] ?: $nr ),
    ), $y );

    /* --- Déclaration + signatures --- */
    ueb_pdf_txt( $pdf, 8, $y + 1.5, "Je déclare sur l'honneur que les informations saisies sont exactes.", 7, '', $c['noir'] );

    $y_sig = $y + 5.5;
    ueb_pdf_txt( $pdf, 12,  $y_sig, "Signature de l'Administration", 7.3, 'B', $c['noir'], 'C', 51 );
    ueb_pdf_txt( $pdf, 139, $y_sig, 'Signature du Candidat',         7.3, 'B', $c['noir'], 'C', 51 );
    $pdf->SetLineStyle( array( 'width' => 0.2, 'dash' => '1.6,1.4', 'color' => $c['gris'] ) );
    $pdf->Rect( 12,  $y_sig + 3.8, 51, 7 );
    $pdf->Rect( 139, $y_sig + 3.8, 51, 7 );

    $y_bas_fiche = $y_sig + 3.8 + 7;

    /* --- Ligne de découpe --- */
    $y_decoupe = $y_bas_fiche + 3;
    ueb_pdf_txt( $pdf, 4, $y_decoupe - 0.6, '✂', 9, '', $c['noir'] );
    ueb_pdf_ligne( $pdf, 11, $y_decoupe, 206, $y_decoupe, $c['noir'], 0.3, '2.2,1.6' );

    /* ================================================
       COUPON RÉCÉPISSÉ DE DÉPÔT (dernière version)
       ================================================ */
    $ct = $y_decoupe + 2.5;
    $coupon_h = 42;

    if ( $ct + $coupon_h > 296 ) {
        $ct = 296 - $coupon_h;
    }

    $pdf->RoundedRect( 4, $ct, 202, $coupon_h, 1.5, '1111', 'D',
        array( 'width' => 0.5, 'dash' => 0, 'color' => $c['vert'] ) );

    ueb_pdf_txt( $pdf, 55, $ct + 3, 'RÉCÉPISSÉ DE DÉPÔT', 11.5, 'B', $c['vert_titre'], 'C', 100 );

    // Colonne gauche
    ueb_pdf_txt( $pdf, 10, $ct + 7.5, 'Code :', 8, 'B', $c['noir'] );
    ueb_pdf_txt( $pdf, 21.5, $ct + 7.5, $d['numero_dossier'], 8, 'B', $c['orange'] );
    ueb_pdf_txt( $pdf, 10, $ct + 12.4, 'Nom(s) et Prénom(s) :', 8, 'B', $c['noir'] );
    ueb_pdf_txt( $pdf, 46.5, $ct + 12.4, strtoupper( $d['nom'] ) . ' ' . $d['prenom'], 8, 'B', $c['noir'] );
    ueb_pdf_txt( $pdf, 10, $ct + 17.3, 'Filière :', 8, 'B', $c['noir'] );
    ueb_pdf_txt( $pdf, 23, $ct + 17.3, $d['filiere_1'] !== '' ? $d['filiere_1'] . ' (1er choix)' : '—', 8, 'B', $c['noir'] );
    ueb_pdf_txt( $pdf, 10, $ct + 22.2, 'Établissement :', 8, 'B', $c['noir'] );
    ueb_pdf_txt( $pdf, 36.5, $ct + 22.2, $d['faculte'] !== '' ? $d['faculte'] : '—', 8, 'B', $c['noir'] );

    // Colonne droite
    ueb_pdf_txt( $pdf, 126, $ct + 7.5, 'Niveau :', 8, 'B', $c['noir'] );
    ueb_pdf_txt( $pdf, 140, $ct + 7.5, $d['niveau_lmd'], 8, 'B', $c['vert_titre'] );
    ueb_pdf_txt( $pdf, 138, $ct + 12, 'Avis', 8, 'B', $c['noir'], 'C', 44 );
    ueb_pdf_txt( $pdf, 138, $ct + 15.8, "Signature de l'Administration", 8, 'B', $c['noir'], 'C', 44 );
    $pdf->SetLineStyle( array( 'width' => 0.25, 'dash' => '1.6,1.4', 'color' => $c['gris'] ) );
    $pdf->Rect( 138, $ct + 19, 44, 7 );

    // QR Code
    $qr_coupon = 'Code : ' . $d['numero_dossier'] . "\n"
        . 'Nom : ' . ueb_pdf_sans_accents( strtoupper( $d['nom'] ) . ' ' . $d['prenom'] ) . "\n"
        . 'Fil : ' . ueb_pdf_sans_accents( $d['filiere_1'] ) . "\n"
        . 'Etab : ' . $etab . "\n"
        . 'Niveau : ' . ueb_pdf_sans_accents( $d['niveau_lmd'] ) . "\n"
        . "Bq : CCABANK\n"
        . 'Compte : 10039-10012-0027277050';

    ueb_pdf_qr_stylise( $pdf, $qr_coupon, 186.5, $ct + 5.5, 15 );

    /* Encadré paiement */
    $pdf->RoundedRect( 9, $ct + 27, 192, 11.5, 1, '1111', 'D',
        array( 'width' => 0.3, 'dash' => 0, 'color' => $c['noir'] ) );

    ueb_pdf_ligne( $pdf, 68, $ct + 28, 68, $ct + 37.5, $c['ligne'], 0.25 );
    ueb_pdf_ligne( $pdf, 114, $ct + 28, 114, $ct + 37.5, $c['ligne'], 0.25 );

    ueb_pdf_icone( $pdf, 'banque', 12, $ct + 29.5, 6.5 );
    ueb_pdf_txt( $pdf, 21.5, $ct + 29, '*Agence de paiement :', 6.8, 'B', $c['noir'] );
    ueb_pdf_txt( $pdf, 21.5, $ct + 33, 'CCABANK', 6.8, '', $c['noir'] );

    ueb_pdf_icone( $pdf, 'recu', 69.5, $ct + 30, 6 );
    ueb_pdf_txt( $pdf, 77.5, $ct + 29, '*Numéro de transaction :', 6.4, 'B', $c['noir'] );
    ueb_pdf_txt( $pdf, 77.5, $ct + 33, '.....................', 6.6, '', $c['noir'] );

    ueb_pdf_icone( $pdf, 'banque', 117, $ct + 29.5, 6.5 );
    ueb_pdf_txt( $pdf, 126.5, $ct + 28.5, '*N°Compte Bancaire :', 6.8, 'B', $c['noir'] );
    $pdf->SetFont( 'dejavusans', '', 6.2 );
    $pdf->SetTextColor( $c['noir'][0], $c['noir'][1], $c['noir'][2] );
    $pdf->SetXY( 126.5, $ct + 32 );
    $pdf->MultiCell( 73.5, 3, "FACULTÉS DES SCIENCES ÉCONOMIQUES ET\nDE GESTION | CCA BANK-10039-10012-0027277050", 0, 'L' );
}

/* ============================================================
   PAGE 2 & 3 + ICÔNES (inchangées)
   ============================================================ */

function ueb_pdf_boite_medicale( $pdf, $titre, $icone_titre, $largeur_pastille, $lignes, $y ) {
    $c     = ueb_pdf_couleurs();
    $h_row = 13.4;
    $h_box = count( $lignes ) * $h_row + 7.5;

    $pdf->RoundedRect( 10, $y + 4.5, 190, $h_box, 2, '1111', 'D', array( 'width' => 0.3, 'dash' => 0, 'color' => $c['gris'] ) );

    $pdf->RoundedRect( 10, $y, $largeur_pastille, 9, 2, '1111', 'F', array(), $c['vert'] );
    ueb_pdf_icone( $pdf, $icone_titre, 14.5, $y + 2, 5, array( 255, 255, 255 ) );
    ueb_pdf_txt( $pdf, 23, $y + 2, $titre, 9.5, 'B', array( 255, 255, 255 ) );

    $ry = $y + 9 + 1.5;
    foreach ( $lignes as $idx => $ligne ) {
        list( $icone, $label, $valeur ) = $ligne;
        $ty = $ry + ( $h_row - 5 ) / 2;

        ueb_pdf_icone( $pdf, $icone, 18, $ty - 0.2, 5 );
        ueb_pdf_txt( $pdf, 28, $ty, $label, 10, 'B', $c['noir'] );
        ueb_pdf_txt( $pdf, 79, $ty, ':', 10, 'B', $c['noir'] );
        ueb_pdf_txt( $pdf, 86, $ty, $valeur !== '' ? $valeur : '—', 10.5, 'BI', $c['noir'] );

        if ( $idx < count( $lignes ) - 1 ) {
            ueb_pdf_ligne( $pdf, 14, $ry + $h_row, 196, $ry + $h_row, $c['ligne'], 0.2, '0.6,1.2' );
        }
        $ry += $h_row;
    }

    return $y + 4.5 + $h_box;
}

function ueb_pdf_page_medicale( $pdf, $d ) {
    $c = ueb_pdf_couleurs();

    $pdf->AddPage();
    ueb_pdf_entete_bilingue( $pdf, 9 );

    $pdf->SetFont( 'dejavusans', 'B', 11 );
    $w1 = $pdf->GetStringWidth( 'CODE DE PRÉINSCRIPTION : ' );
    $w2 = $pdf->GetStringWidth( $d['numero_dossier'] );
    $wb = $w1 + $w2 + 16;
    $xb = ( 210 - $wb ) / 2;
    $pdf->RoundedRect( $xb, 40, $wb, 10.5, 1.5, '1111', 'F', array(), $c['vert'] );
    ueb_pdf_txt( $pdf, $xb + 8, 42.6, 'CODE DE PRÉINSCRIPTION : ', 11, 'B', array( 255, 255, 255 ) );
    ueb_pdf_txt( $pdf, $xb + 8 + $w1, 42.6, $d['numero_dossier'], 11, 'B', $c['or'] );

    ueb_pdf_txt( $pdf, 0, 55, '(Imprimez ces deux fiches et apportez-les au Centre médico-social lors de la visite médicale)',
        9, 'I', $c['gris'], 'C', 210 );

    $date_fr = $d['date_naissance'];
    $ts = strtotime( $d['date_naissance'] );
    if ( $d['date_naissance'] !== '' && $ts ) {
        $date_fr = date( 'd/m/Y', $ts );
    }
    $sexe_label = $d['sexe'] === 'M' ? 'MASCULIN' : ( $d['sexe'] === 'F' ? 'FÉMININ' : '' );

    $y = 64;
    $y = ueb_pdf_boite_medicale( $pdf, 'INFORMATIONS PERSONNELLES', 'personne', 76, array(
        array( 'personne',   'Nom(s)',            strtoupper( $d['nom'] ) ),
        array( 'personne',   'Prénom(s)',         strtoupper( $d['prenom'] ) ),
        array( 'calendrier', 'Date de Naissance', $date_fr ),
        array( 'mail',       'Email',             $d['email'] ),
        array( 'telephone',  'Téléphone',         $d['telephone'] ),
        array( 'genre',      'Sexe',              $sexe_label ),
        array( 'lieu',       'Adresse',           strtoupper( $d['adresse'] ) ),
    ), $y );

    $y += 7.5;
    $y = ueb_pdf_boite_medicale( $pdf, "PERSONNE À CONTACTER EN CAS D'URGENCE", 'tel_urgence', 104, array(
        array( 'personne',  'Nom et Prénom',       $d['nom_tuteur'] ),
        array( 'telephone', 'Téléphone (urgence)', $d['numero_tuteur'] ),
        array( 'lieu',      'Adresse (urgence)',   '' ),
    ), $y );

    $y += 7.5;
    $pdf->RoundedRect( 10, $y, 190, 27, 2, '1111', 'DF',
        array( 'width' => 0.3, 'dash' => 0, 'color' => array( 200, 215, 202 ) ), $c['vert_pale'] );
    ueb_pdf_icone( $pdf, 'info', 16, $y + 4, 5.5 );
    ueb_pdf_txt( $pdf, 25, $y + 4, 'NOTES IMPORTANTES', 10.5, 'B', $c['noir'] );
    ueb_pdf_icone( $pdf, 'coche', 17, $y + 12.5, 4.5 );
    ueb_pdf_txt( $pdf, 25, $y + 12.2, 'Imprimez ces deux fiches.', 9.5, '', $c['noir'] );
    ueb_pdf_icone( $pdf, 'coche', 17, $y + 19.5, 4.5 );
    ueb_pdf_txt( $pdf, 25, $y + 19.2, 'Apportez-les au Centre médico-social lors de votre visite médicale.', 9.5, '', $c['noir'] );

    $yf = 272;
    ueb_pdf_ligne( $pdf, 30, $yf, 99,  $yf, $c['gris'], 0.25 );
    ueb_pdf_ligne( $pdf, 111, $yf, 180, $yf, $c['gris'], 0.25 );
    ueb_pdf_icone( $pdf, 'coeur', 101.5, $yf - 3.5, 7 );
    ueb_pdf_txt( $pdf, 0, $yf + 6,  'Merci de votre collaboration.', 10.5, 'B', $c['noir'], 'C', 210 );
    ueb_pdf_txt( $pdf, 0, $yf + 12, 'Thank you for your cooperation.', 9.5, 'I', $c['gris'], 'C', 210 );
}

function ueb_pdf_page_examen( $pdf ) {
    $pdf->AddPage();

    $img = get_template_directory() . '/assets/pdf/fiche-visite-medicale-p3.png';
    if ( file_exists( $img ) ) {
        $pdf->Image( $img, 2.4, 0, 205.2, 297, 'PNG' );
    } else {
        $c = ueb_pdf_couleurs();
        ueb_pdf_txt( $pdf, 0, 140, "Fiche d'examen médical indisponible (assets/pdf/fiche-visite-medicale-p3.png manquant).", 10, 'B', $c['gris'], 'C', 210 );
    }
}

function ueb_pdf_icone( $pdf, $type, $x, $y, $s, $color = null ) {
    $c  = $color ?: ueb_pdf_couleurs()['vert'];
    $lw = max( 0.35, $s * 0.09 );
    $pdf->SetLineStyle( array( 'width' => $lw, 'dash' => 0, 'color' => $c ) );
    $cx = $x + $s / 2;

    switch ( $type ) {
        case 'personne':
            $pdf->Circle( $cx, $y + $s * 0.3, $s * 0.19 );
            $pdf->Ellipse( $cx, $y + $s * 1.02, $s * 0.32, $s * 0.42, 0, 55, 125 );
            break;
        case 'calendrier':
            $pdf->RoundedRect( $x + $s * 0.08, $y + $s * 0.16, $s * 0.84, $s * 0.74, $s * 0.08, '1111', 'D', array(), array() );
            $pdf->Line( $x + $s * 0.08, $y + $s * 0.4, $x + $s * 0.92, $y + $s * 0.4 );
            $pdf->Line( $x + $s * 0.3, $y + $s * 0.05, $x + $s * 0.3, $y + $s * 0.26 );
            $pdf->Line( $x + $s * 0.7, $y + $s * 0.05, $x + $s * 0.7, $y + $s * 0.26 );
            break;
        case 'mail':
            $pdf->RoundedRect( $x + $s * 0.05, $y + $s * 0.2, $s * 0.9, $s * 0.6, $s * 0.05, '1111', 'D', array(), array() );
            $pdf->Line( $x + $s * 0.09, $y + $s * 0.25, $cx, $y + $s * 0.55 );
            $pdf->Line( $cx, $y + $s * 0.55, $x + $s * 0.91, $y + $s * 0.25 );
            break;
        case 'telephone':
            $pdf->SetLineStyle( array( 'width' => $lw * 1.5, 'dash' => 0, 'color' => $c ) );
            $pdf->Ellipse( $cx, $y + $s * 0.62, $s * 0.34, $s * 0.34, 0, 30, 150 );
            $pdf->Circle( $cx - $s * 0.3, $y + $s * 0.48, $s * 0.09, 0, 360, 'F', array(), $c );
            $pdf->Circle( $cx + $s * 0.3, $y + $s * 0.48, $s * 0.09, 0, 360, 'F', array(), $c );
            break;
        case 'tel_urgence':
            ueb_pdf_icone( $pdf, 'telephone', $x, $y + $s * 0.12, $s * 0.85, $c );
            $pdf->SetLineStyle( array( 'width' => $lw * 0.8, 'dash' => 0, 'color' => $c ) );
            $pdf->Ellipse( $x + $s * 0.78, $y + $s * 0.3, $s * 0.14, $s * 0.14, 0, 300, 60 );
            $pdf->Ellipse( $x + $s * 0.78, $y + $s * 0.3, $s * 0.26, $s * 0.26, 0, 300, 60 );
            break;
        case 'genre':
            $pdf->Circle( $cx - $s * 0.1, $y + $s * 0.6, $s * 0.24 );
            $pdf->Line( $cx + $s * 0.07, $y + $s * 0.43, $x + $s * 0.88, $y + $s * 0.12 );
            $pdf->Line( $x + $s * 0.88, $y + $s * 0.12, $x + $s * 0.62, $y + $s * 0.1 );
            $pdf->Line( $x + $s * 0.88, $y + $s * 0.12, $x + $s * 0.9, $y + $s * 0.38 );
            break;
        case 'lieu':
            $pdf->Circle( $cx, $y + $s * 0.34, $s * 0.24 );
            $pdf->Circle( $cx, $y + $s * 0.34, $s * 0.07, 0, 360, 'F', array(), $c );
            $pdf->Line( $cx - $s * 0.17, $y + $s * 0.51, $cx, $y + $s * 0.92 );
            $pdf->Line( $cx, $y + $s * 0.92, $cx + $s * 0.17, $y + $s * 0.51 );
            break;
        case 'info':
            $pdf->Circle( $cx, $y + $s / 2, $s / 2, 0, 360, 'F', array(), $c );
            ueb_pdf_txt( $pdf, $x, $y + $s * 0.14, 'i', $s * 2.2, 'BI', array( 255, 255, 255 ), 'C', $s );
            break;
        case 'coche':
            $pdf->Circle( $cx, $y + $s / 2, $s / 2, 0, 360, 'F', array(), $c );
            ueb_pdf_txt( $pdf, $x, $y + $s * 0.08, '✓', $s * 1.9, 'B', array( 255, 255, 255 ), 'C', $s );
            break;
        case 'coeur':
            $pdf->Circle( $cx - $s * 0.18, $y + $s * 0.3, $s * 0.22 );
            $pdf->Circle( $cx + $s * 0.18, $y + $s * 0.3, $s * 0.22 );
            $pdf->Line( $cx - $s * 0.38, $y + $s * 0.42, $cx, $y + $s * 0.92 );
            $pdf->Line( $cx, $y + $s * 0.92, $cx + $s * 0.38, $y + $s * 0.42 );
            $pdf->Line( $cx, $y + $s * 0.3, $cx, $y + $s * 0.62 );
            $pdf->Line( $cx - $s * 0.16, $y + $s * 0.46, $cx + $s * 0.16, $y + $s * 0.46 );
            break;
        case 'banque':
            $pdf->Line( $x + $s * 0.05, $y + $s * 0.32, $cx, $y + $s * 0.02 );
            $pdf->Line( $cx, $y + $s * 0.02, $x + $s * 0.95, $y + $s * 0.32 );
            $pdf->Line( $x + $s * 0.05, $y + $s * 0.32, $x + $s * 0.95, $y + $s * 0.32 );
            foreach ( array( 0.2, 0.5, 0.8 ) as $fx ) {
                $pdf->Line( $x + $s * $fx, $y + $s * 0.4, $x + $s * $fx, $y + $s * 0.78 );
            }
            $pdf->Line( $x, $y + $s * 0.88, $x + $s, $y + $s * 0.88 );
            break;
        case 'recu':
            $pdf->RoundedRect( $x + $s * 0.14, $y + $s * 0.04, $s * 0.72, $s * 0.84, $s * 0.06, '1111', 'D', array(), array() );
            $pdf->Line( $x + $s * 0.28, $y + $s * 0.24, $x + $s * 0.72, $y + $s * 0.24 );
            $pdf->Line( $x + $s * 0.28, $y + $s * 0.4, $x + $s * 0.72, $y + $s * 0.4 );
            $pdf->Circle( $cx, $y + $s * 0.64, $s * 0.13 );
            break;
    }
}