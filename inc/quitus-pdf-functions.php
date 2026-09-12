<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Page 4 du PDF de préinscription : quitus de paiement des droits de
 * préinscription.
 *
 * Reprise du modèle A « Bilingue officiel » du site d'inscription
 * (inscription-ueb/inc/quitus-pdf.php) : une page A4 porte quatre coupons
 * identiques (étudiant, DAF, scolarité, banque) séparés par des pointillés
 * de découpe. Chaque coupon reprend l'en-tête des actes officiels
 * camerounais (français à gauche, anglais à droite, emblèmes au centre)
 * dans la couleur d'identité de l'établissement.
 *
 * Écarts avec le modèle, imposés par la préinscription :
 *   - montant fixe (UEB_FRAIS_PREINSCRIPTION), payé en une fois sur le
 *     compte de l'Université (UEB_COMPTE_CCA) : pas de cases de tranche ;
 *   - le QR contient le texte du quitus et non une URL de vérification
 *     (la préinscription n'a pas de page de vérification, et le scan doit
 *     afficher directement le texte, comme les autres QR du document) ;
 *   - pas de numéro de quitus en base : le numéro de dossier en tient lieu.
 *
 * Unités : millimètres. Polices : Source Serif 4 et Source Sans 3,
 * converties pour TCPDF dans assets/pdf/fonts (et non dans lib/tcpdf/fonts,
 * qui est gitignoré) — familles « uebserif* » et « uebsans* ».
 */

const UEB_QUITUS_COUPONS = array( 'Coupon étudiant', 'Coupon DAF', 'Coupon scolarité', 'Coupon banque' );

/* Géométrie de la page */
const UEB_QUITUS_MARGE_X    = 7.0;
const UEB_QUITUS_MARGE_Y    = 6.0;
const UEB_QUITUS_LARGEUR    = 196.0;
const UEB_QUITUS_HAUTEUR    = 67.5;
const UEB_QUITUS_INTERVALLE = 5.0;

const UEB_QUITUS_UNIVERSITE = array(
    'fr'      => "Université d'Ebolowa",
    'en'      => 'The University of Ebolowa',
    'bp'      => 'BP 118 Ebolowa',
    'tel'     => '+237 6 76 29 54 88',
    'email'   => 'info@unv-ebolowa.cm',
    'couleur' => '#1E3A8A',
);

/**
 * Coordonnées et couleur d'identité des établissements, par code
 * (ueb_facultes.code). Les noms affichés viennent de la base ; « fr » et
 * « en » ne servent que de repli. Un « null » n'a pas encore été
 * communiqué : il s'imprime comme un champ à compléter.
 */
function ueb_quitus_etablissements() {
    return array(
        'FS'     => array( 'fr' => 'Faculté des Sciences', 'en' => 'Faculty of Science',
            'tel' => '699 73 07 81', 'email' => 'fsunivebolowa@gmail.com', 'bp' => 'BP 118 Ebolowa', 'couleur' => '#1f5aa6' ),
        'FSJP'   => array( 'fr' => 'Faculté des Sciences Juridiques et Politiques', 'en' => 'Faculty of Law and Political Science',
            'tel' => null, 'email' => null, 'bp' => 'BP 118 Ebolowa', 'couleur' => '#8B1E1E' ),
        'FSEG'   => array( 'fr' => 'Faculté des Sciences Économiques et de Gestion', 'en' => 'Faculty of Economics and Management',
            'tel' => null, 'email' => null, 'bp' => 'BP 118 Ebolowa', 'couleur' => '#16803C' ),
        'FALSH'  => array( 'fr' => 'Faculté des Arts, Lettres et Sciences Humaines', 'en' => 'Faculty of Arts, Letters and Human Sciences',
            'tel' => null, 'email' => null, 'bp' => 'BP 118 Ebolowa', 'couleur' => '#6A1B6D' ),
        'FMSP'   => array( 'fr' => 'Faculté de Médecine et des Sciences Pharmaceutiques', 'en' => 'Faculty of Medicine and Pharmaceutical Sciences',
            'tel' => null, 'email' => null, 'bp' => 'Sangmélima', 'couleur' => '#0077B6' ),
        'ENSET'  => array( 'fr' => "École Normale Supérieure d'Enseignement Technique", 'en' => 'Higher Technical Teacher Training College',
            'tel' => null, 'email' => null, 'bp' => 'BP 118 Ebolowa', 'couleur' => '#006B3C' ),
        'ISABEE' => array( 'fr' => "Institut Supérieur d'Agriculture, du Bois, de l'Eau et de l'Environnement", 'en' => 'Higher Institute of Agriculture, Forestry, Water and Environment',
            'tel' => '694 19 36 07 / 677 07 97 47', 'email' => 'contact@isabee.cm', 'bp' => 'BP 118 Ebolowa', 'couleur' => '#3A7D44' ),
        'ESTLC'  => array( 'fr' => 'École Supérieure de Transport, de Logistique et de Commerce', 'en' => 'Higher School of Transport, Logistics and Commerce',
            'tel' => null, 'email' => 'estlc@estlc.unv-ebolowa.cm', 'bp' => 'BP 22 Ambam', 'couleur' => '#4E7F1D' ),
        'ENSTMO' => array( 'fr' => 'École Nationale Supérieure des Sciences et Techniques Maritimes et Océaniques', 'en' => 'National Advanced School of Maritime and Ocean Science and Technology',
            'tel' => '695 73 41 50 / 677 17 54 40', 'email' => null, 'bp' => 'Kribi', 'couleur' => '#1b2c5a' ),
    );
}

/**
 * Établissement du candidat : noms de la base, coordonnées et couleur de la
 * configuration. Sans faculté connue, le quitus reste imprimable aux
 * couleurs et coordonnées de l'Université, sans ligne d'établissement.
 */
function ueb_quitus_etablissement( array $d ) {
    $code  = strtoupper( (string) ( $d['faculte_code'] ?? '' ) );
    $liste = ueb_quitus_etablissements();
    $conf  = $liste[ $code ] ?? null;

    if ( ! $conf ) {
        return array(
            'sigle'   => $code,
            'fr'      => (string) ( $d['faculte'] ?? '' ),
            'en'      => (string) ( $d['faculte_en'] ?? '' ),
            'bp'      => UEB_QUITUS_UNIVERSITE['bp'],
            'tel'     => UEB_QUITUS_UNIVERSITE['tel'],
            'email'   => UEB_QUITUS_UNIVERSITE['email'],
            'couleur' => UEB_QUITUS_UNIVERSITE['couleur'],
        );
    }

    return array(
        'sigle' => $code,
        'fr'    => ( $d['faculte'] ?? '' ) !== '' ? $d['faculte'] : $conf['fr'],
        'en'    => ( $d['faculte_en'] ?? '' ) !== '' ? $d['faculte_en'] : $conf['en'],
    ) + $conf;
}

/**
 * Logo d'un établissement (assets/images/logos/logo-<code>.*, fichiers PNG
 * même quand l'extension est .jpg) ou de l'Université. Null si absent.
 *
 * @return array{0:string,1:string}|null chemin, type TCPDF
 */
function ueb_quitus_logo( $sigle ) {
    $dir      = get_template_directory() . '/assets/images/';
    $fichiers = 'UEB' === $sigle
        ? array( $dir . 'logo-ueb.png' )
        : array( $dir . 'logos/logo-' . strtolower( $sigle ) . '.png', $dir . 'logos/logo-' . strtolower( $sigle ) . '.jpg' );

    foreach ( $fichiers as $fichier ) {
        $info = @getimagesize( $fichier );
        if ( $info ) {
            return array( $fichier, 'image/png' === $info['mime'] ? 'PNG' : 'JPG' );
        }
    }
    return null;
}

/** RIB du compte de l'Université, groupé comme sur le relevé : « CM21 10039 10012 00272772201 07 ». */
function ueb_quitus_rib() {
    return 'CM21 ' . str_replace( '-', ' ', UEB_COMPTE_CCA );
}

/** 11000 → « Onze mille francs CFA ». */
function ueb_quitus_montant_en_lettres( $montant ) {
    $lettres = ueb_export_nombre_en_lettres( (int) $montant );
    /* « deux millions de francs », mais « deux millions cent francs ». */
    $texte = $lettres . ( preg_match( '/(million|milliard)s?$/', $lettres ) ? ' de francs CFA' : ' francs CFA' );
    return mb_strtoupper( mb_substr( $texte, 0, 1 ) ) . mb_substr( $texte, 1 );
}

/**
 * Contenu du QR : le texte du quitus, sans accents (cf. ueb_pdf_sans_accents).
 * Correction d'erreur L, comme les autres QR du document : ~130 caractères
 * donnent 41×41 modules (0,45 mm sur 18,5 mm), un nom très long 45×45.
 * NE PAS rallonger ce texte : au-delà de ~155 caractères on passe en 49×49,
 * difficile à scanner (en M, le texte actuel y était déjà).
 */
function ueb_quitus_qr_texte( array $d, array $etab ) {
    return 'Quitus preinscription ' . $d['annee_academique'] . "\n"
        . 'Dossier : ' . $d['numero_dossier'] . "\n"
        . 'Nom : ' . ueb_pdf_sans_accents( mb_strtoupper( $d['nom'] ) . ' ' . $d['prenom'] ) . "\n"
        . ( $etab['sigle'] !== '' ? 'Etab : ' . $etab['sigle'] . "\n" : '' )
        . 'Montant : ' . (int) UEB_FRAIS_PREINSCRIPTION . ' FCFA';
}


/* ============================================================
   PAGE
   ============================================================ */

function ueb_pdf_page_quitus( TCPDF $pdf, array $d ) {
    // Polices embarquées ENTIÈRES (dernier argument false) : le sous-ensemble
    // de TCPDF produit des fichiers que les lecteurs PDF refusent pour ces
    // polices (« unknown file format »), et le texte retombe sur une police
    // système. Les DejaVu des pages 1 et 2 restent, elles, en sous-ensemble.
    $dir_polices = get_template_directory() . '/assets/pdf/fonts/';
    foreach ( array( 'uebsans', 'uebsansb', 'uebsemi', 'uebserif', 'uebserifb', 'uebserifi', 'uebserifbi' ) as $famille ) {
        $pdf->AddFont( $famille, '', $dir_polices . $famille . '.php', false );
    }

    // Le modèle est calé sans marge intérieure de cellule et avec un
    // interligne de 1,12 : on les impose ici et on rend l'état d'origine
    // à la fin, pour ne pas dérégler les pages qui suivraient.
    $paddings = $pdf->getCellPaddings();
    $ratio    = $pdf->getCellHeightRatio();
    $pdf->setCellPaddings( 0, 0, 0, 0 );
    $pdf->setCellHeightRatio( 1.12 );

    $pdf->AddPage();

    $etab = ueb_quitus_etablissement( $d );
    $c    = ueb_quitus_couleurs( $etab['couleur'] );
    for ( $i = 0; $i < 4; $i++ ) {
        $y = UEB_QUITUS_MARGE_Y + $i * ( UEB_QUITUS_HAUTEUR + UEB_QUITUS_INTERVALLE );
        ueb_quitus_coupon( $pdf, $d, $etab, $c, UEB_QUITUS_COUPONS[ $i ], UEB_QUITUS_MARGE_X, $y );
        if ( $i < 3 ) {
            ueb_quitus_ligne_coupe( $pdf, $y + UEB_QUITUS_HAUTEUR + UEB_QUITUS_INTERVALLE / 2 );
        }
    }

    $pdf->setCellPaddings( $paddings['L'], $paddings['T'], $paddings['R'], $paddings['B'] );
    $pdf->setCellHeightRatio( $ratio );
}


/* ============================================================
   COULEURS & OUTILS DE TEXTE
   ============================================================ */

function ueb_quitus_rvb( $hex ) {
    $hex = ltrim( $hex, '#' );
    return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
}

/** Mélange une couleur avec du blanc : $part de couleur (0-1). */
function ueb_quitus_teinte( array $rvb, $part ) {
    return array_map( static fn( $v ) => (int) round( $v * $part + 255 * ( 1 - $part ) ), $rvb );
}

function ueb_quitus_couleurs( $hex ) {
    $etab = ueb_quitus_rvb( $hex );
    return array(
        'etab'  => $etab,
        'ueb'   => ueb_quitus_rvb( UEB_QUITUS_UNIVERSITE['couleur'] ),
        'fond'  => ueb_quitus_teinte( $etab, 0.06 ),
        'encre' => array( 27, 36, 51 ),
        'gris'  => array( 91, 100, 114 ),
        'filet' => array( 185, 192, 200 ),
        'pale'  => array( 154, 161, 170 ),
    );
}

/** Taille de police réduite (par pas de 0,2 pt) jusqu'à tenir dans $largeur. */
function ueb_quitus_ajuster( TCPDF $pdf, $texte, $famille, $style, $taille, $largeur, $min = 6.0 ) {
    while ( $taille > $min ) {
        $pdf->SetFont( $famille, $style, $taille );
        if ( $pdf->GetStringWidth( $texte ) <= $largeur ) {
            break;
        }
        $taille -= 0.2;
    }
    $pdf->SetFont( $famille, $style, $taille );
    return $taille;
}

/** Écrit un texte à gauche d'une cellule, centré verticalement. Renvoie la largeur écrite. */
function ueb_quitus_texte( TCPDF $pdf, $x, $y, $h, $texte, array $couleur ) {
    $pdf->SetTextColorArray( $couleur );
    $pdf->SetXY( $x, $y );
    $pdf->Cell( 0, $h, $texte, 0, 0, 'L', false, '', 0, false, 'T', 'M' );
    return $pdf->GetStringWidth( $texte );
}


/* ============================================================
   ÉLÉMENTS DU COUPON
   ============================================================ */

function ueb_quitus_ligne_coupe( TCPDF $pdf, $y ) {
    $pdf->SetLineStyle( array( 'width' => 0.3, 'color' => array( 138, 146, 156 ), 'dash' => '1.2,1' ) );
    $pdf->Line( 0, $y, 210, $y );
    $pdf->SetFillColor( 255, 255, 255 );
    $pdf->Rect( 5.2, $y - 2, 4.6, 4, 'F' );
    $pdf->SetFont( 'zapfdingbats', '', 10 );
    $pdf->SetTextColor( 138, 146, 156 );
    $pdf->SetXY( 5.2, $y - 2 );
    $pdf->Cell( 4.6, 4, chr( 0x22 ), 0, 0, 'C', false, '', 0, false, 'T', 'M' ); // ciseaux
    $pdf->SetLineStyle( array( 'dash' => 0 ) );
}

/**
 * Colonne d'en-tête : République / devise / université / établissement,
 * centrée dans $largeur. Renvoie la hauteur occupée (sans rien dessiner si
 * $dessiner est faux, pour mesurer avant de centrer verticalement).
 */
function ueb_quitus_colonne_entete( TCPDF $pdf, array $lignes, $x, $y, $largeur, array $c, $dessiner ) {
    $pt   = 0.3528;
    $yCur = $y;
    foreach ( $lignes as $ligne ) {
        if ( 'sep' === $ligne['type'] ) {
            if ( $dessiner ) {
                $pdf->SetLineStyle( array( 'width' => 0.3, 'color' => $c['etab'], 'dash' => 0 ) );
                $pdf->Line( $x + $largeur / 2 - 3, $yCur + 1.1, $x + $largeur / 2 + 3, $yCur + 1.1 );
            }
            $yCur += 2.2;
            continue;
        }
        $pdf->SetFont( $ligne['famille'], $ligne['style'], $ligne['taille'] );
        $pdf->setFontSpacing( $ligne['espacement'] ?? 0 );
        $h        = $ligne['taille'] * $pt * 1.12;
        $nbLignes = $pdf->getNumLines( $ligne['texte'], $largeur );
        if ( $dessiner ) {
            $pdf->SetTextColorArray( $ligne['couleur'] );
            $pdf->SetXY( $x, $yCur );
            $pdf->MultiCell( $largeur, $h, $ligne['texte'], 0, 'C', false, 1, $x, $yCur, true, 0, false, true, 0, 'T' );
        }
        $yCur += $h * $nbLignes;
    }
    $pdf->setFontSpacing( 0 );
    return $yCur - $y;
}

function ueb_quitus_lignes_entete( $langue, array $etab, array $c ) {
    $fr     = 'fr' === $langue;
    $lignes = array(
        array( 'type' => 'txt', 'texte' => $fr ? 'RÉPUBLIQUE DU CAMEROUN' : 'REPUBLIC OF CAMEROON', 'famille' => 'uebserifb', 'style' => '', 'taille' => 7, 'espacement' => 0.15, 'couleur' => $c['encre'] ),
        array( 'type' => 'txt', 'texte' => $fr ? 'Paix – Travail – Patrie' : 'Peace – Work – Fatherland', 'famille' => 'uebserifi', 'style' => '', 'taille' => 6.4, 'couleur' => $c['gris'] ),
        array( 'type' => 'sep' ),
        array( 'type' => 'txt', 'texte' => mb_strtoupper( UEB_QUITUS_UNIVERSITE[ $langue ] ), 'famille' => 'uebserifb', 'style' => '', 'taille' => 8.2, 'espacement' => 0.14, 'couleur' => $c['ueb'] ),
    );
    if ( $etab[ $langue ] !== '' ) {
        $long     = mb_strlen( $etab[ $langue ] ) > 44;
        $lignes[] = array( 'type' => 'sep' );
        $lignes[] = array( 'type' => 'txt', 'texte' => mb_strtoupper( $etab[ $langue ] ), 'famille' => $fr ? 'uebserifb' : 'uebserifbi', 'style' => '', 'taille' => $long ? 6.9 : 8.4, 'espacement' => 0.05, 'couleur' => $c['etab'] );
    }
    return $lignes;
}

function ueb_quitus_coupon( TCPDF $pdf, array $d, array $etab, array $c, $libelle_coupon, $x0, $y0 ) {
    $W  = UEB_QUITUS_LARGEUR;
    $px = 3.0;              // marge intérieure horizontale
    $xi = $x0 + $px;        // bord intérieur gauche
    $Wi = $W - 2 * $px;     // largeur intérieure
    $y  = $y0 + 1.8;

    /* Cadre */
    $pdf->SetLineStyle( array( 'width' => 0.35, 'color' => $c['encre'], 'dash' => 0 ) );
    $pdf->Rect( $x0, $y0, $W, UEB_QUITUS_HAUTEUR );

    /* ---- En-tête bilingue ---- */
    $logo     = 15.0;
    $emblW    = $logo * 2 + 5.0;
    $colW     = ( $Wi - $emblW - 8 ) / 2;
    $xFr      = $xi;
    $xEmb     = $xi + $colW + 4;
    $xEn      = $xEmb + $emblW + 4;
    $lignesFr = ueb_quitus_lignes_entete( 'fr', $etab, $c );
    $lignesEn = ueb_quitus_lignes_entete( 'en', $etab, $c );
    $hFr      = ueb_quitus_colonne_entete( $pdf, $lignesFr, $xFr, 0, $colW, $c, false );
    $hEn      = ueb_quitus_colonne_entete( $pdf, $lignesEn, $xEn, 0, $colW, $c, false );
    $hEntete  = max( $hFr, $hEn, $logo );
    ueb_quitus_colonne_entete( $pdf, $lignesFr, $xFr, $y + ( $hEntete - $hFr ) / 2, $colW, $c, true );
    ueb_quitus_colonne_entete( $pdf, $lignesEn, $xEn, $y + ( $hEntete - $hEn ) / 2, $colW, $c, true );

    // Emblèmes : Université et établissement séparés par un filet ; seul
    // l'emblème de l'Université, centré, si celui de l'établissement manque.
    $yLogo     = $y + ( $hEntete - $logo ) / 2;
    $logo_ueb  = ueb_quitus_logo( 'UEB' );
    $logo_etab = $etab['sigle'] !== '' ? ueb_quitus_logo( $etab['sigle'] ) : null;
    if ( $logo_etab ) {
        if ( $logo_ueb ) {
            $pdf->Image( $logo_ueb[0], $xEmb, $yLogo, $logo, $logo, $logo_ueb[1], '', '', true, 300, '', false, false, 0, 'CM' );
        }
        $pdf->Image( $logo_etab[0], $xEmb + $logo + 5, $yLogo, $logo, $logo, $logo_etab[1], '', '', true, 300, '', false, false, 0, 'CM' );
        $pdf->SetLineStyle( array( 'width' => 0.25, 'color' => $c['filet'], 'dash' => 0 ) );
        $pdf->Line( $xEmb + $logo + 2.5, $yLogo + 2, $xEmb + $logo + 2.5, $yLogo + $logo - 2 );
    } elseif ( $logo_ueb ) {
        $pdf->Image( $logo_ueb[0], $xEmb + ( $emblW - $logo ) / 2, $yLogo, $logo, $logo, $logo_ueb[1], '', '', true, 300, '', false, false, 0, 'CM' );
    }
    $y += $hEntete + 0.6;

    /* ---- Coordonnées ---- */
    $pdf->SetLineStyle( array( 'width' => 0.25, 'color' => $c['filet'], 'dash' => 0 ) );
    $pdf->Line( $xi, $y, $xi + $Wi, $y );
    $morceaux = array(
        array( $etab['bp'], 'uebsans', $c['gris'] ),
        array( '   |   ', 'uebsans', $c['filet'] ),
        array( 'Tél. ', 'uebsans', $c['gris'] ),
        array( $etab['tel'] ?: '....................', 'uebsemi', $etab['tel'] ? $c['encre'] : $c['pale'] ),
        array( '   |   ', 'uebsans', $c['filet'] ),
        array( $etab['email'] ?: 'Email : ....................', 'uebsans', $etab['email'] ? $c['gris'] : $c['pale'] ),
    );
    $total = 0;
    foreach ( $morceaux as $m ) {
        $pdf->SetFont( $m[1], '', 7 );
        $total += $pdf->GetStringWidth( $m[0] );
    }
    $xc = $xi + ( $Wi - $total ) / 2;
    foreach ( $morceaux as $m ) {
        $pdf->SetFont( $m[1], '', 7 );
        $xc += ueb_quitus_texte( $pdf, $xc, $y + 0.3, 3.4, $m[0], $m[2] );
    }
    $y += 3.9 + 1.1;

    /* ---- Bandeau du coupon (seul le trait sous le bandeau sépare l'en-tête du corps) ---- */
    $hTitre = 5.4;
    $yT     = $y + 0.9;
    $pdf->SetFont( 'uebsansb', '', 8.2 );
    $wTag = $pdf->GetStringWidth( $libelle_coupon ) + 4.8;
    $pdf->SetFillColorArray( $c['encre'] );
    $pdf->Rect( $xi, $yT + 0.3, $wTag, $hTitre - 1.2, 'F' );
    $pdf->setFontSpacing( 0.05 );
    $pdf->SetTextColor( 255, 255, 255 );
    $pdf->SetXY( $xi, $yT + 0.3 );
    $pdf->Cell( $wTag, $hTitre - 1.2, $libelle_coupon, 0, 0, 'C', false, '', 0, false, 'T', 'M' );
    $pdf->setFontSpacing( 0 );

    $pdf->SetFont( 'uebserif', '', 9 );
    ueb_quitus_texte( $pdf, $xi + $wTag + 3, $yT, $hTitre - 0.6, 'Quitus de paiement des droits de préinscription', $c['encre'] );

    $annee = str_replace( '-', ' – ', $d['annee_academique'] );
    $pdf->SetFont( 'uebsansb', '', 9 );
    $wAnnee = $pdf->GetStringWidth( $annee );
    $pdf->SetFont( 'uebsans', '', 8 );
    $wLib = $pdf->GetStringWidth( 'Année académique ' );
    $xA   = $xi + $Wi - $wAnnee - $wLib;
    ueb_quitus_texte( $pdf, $xA, $yT, $hTitre - 0.6, 'Année académique ', $c['gris'] );
    $pdf->SetFont( 'uebsansb', '', 9 );
    ueb_quitus_texte( $pdf, $xA + $wLib, $yT, $hTitre - 0.6, $annee, $c['encre'] );

    $y = $yT + $hTitre - 0.4;
    $pdf->SetLineStyle( array( 'width' => 0.25, 'color' => $c['encre'], 'dash' => 0 ) );
    $pdf->Line( $xi, $y, $xi + $Wi, $y );
    $y += 1.1;

    /* ---- Registre + QR ---- */
    $hBanque = 5.8;
    $yBanque = $y0 + UEB_QUITUS_HAUTEUR - 1.8 - $hBanque;
    $hCorps  = $yBanque - 1.1 - $y;
    $wQr     = 24.0;
    $wReg    = $Wi - $wQr - 3.5;
    ueb_quitus_registre( $pdf, $d, $c, $xi, $y, $wReg, $hCorps );

    $tQr = 18.5;
    $xQr = $xi + $wReg + 3.5 + ( $wQr - $tQr ) / 2;
    $yQr = $y + ( $hCorps - $tQr - 5.2 ) / 2;
    $pdf->write2DBarcode( ueb_quitus_qr_texte( $d, $etab ), 'QRCODE,L', $xQr, $yQr, $tQr, $tQr, array(
        'border' => false, 'padding' => 0, 'fgcolor' => $c['encre'], 'bgcolor' => false,
    ), 'N' );
    $pdf->SetFont( 'uebsans', '', 6.2 );
    $pdf->SetTextColorArray( $c['gris'] );
    $pdf->SetXY( $xi + $wReg + 3.5, $yQr + $tQr + 0.6 );
    $pdf->Cell( $wQr, 2.4, 'Dossier n°', 0, 2, 'C' );
    ueb_quitus_ajuster( $pdf, $d['numero_dossier'], 'uebsansb', '', 6.6, $wQr );
    $pdf->SetTextColorArray( $c['encre'] );
    $pdf->Cell( $wQr, 2.6, $d['numero_dossier'], 0, 0, 'C' );

    /* ---- Ligne bancaire ---- */
    $pdf->SetFillColorArray( $c['fond'] );
    $pdf->Rect( $xi, $yBanque, $Wi, $hBanque, 'F' );
    $pdf->SetFillColorArray( $c['etab'] );
    $pdf->Rect( $xi, $yBanque, 0.9, $hBanque, 'F' );
    $xb = $xi + 3.3;
    $pdf->SetFont( 'uebsans', '', 8 );
    $xb += ueb_quitus_texte( $pdf, $xb, $yBanque, $hBanque, 'CCA Bank', $c['encre'] ) + 2.5;
    $xb += ueb_quitus_texte( $pdf, $xb, $yBanque, $hBanque, 'N° de compte', $c['encre'] ) + 2.5;
    $pdf->SetFont( 'uebsansb', '', 9.8 );
    $pdf->setFontSpacing( 0.25 );
    ueb_quitus_texte( $pdf, $xb, $yBanque, $hBanque, ueb_quitus_rib(), $c['encre'] );
    $pdf->setFontSpacing( 0 );

    $pdf->SetFont( 'uebsans', '', 8 );
    $wDate = $pdf->GetStringWidth( 'Date de paiement' );
    $xDate = $xi + $Wi - 2.4 - 28 - 1 - $wDate;
    ueb_quitus_texte( $pdf, $xDate, $yBanque, $hBanque, 'Date de paiement', $c['gris'] );
    $pdf->SetLineStyle( array( 'width' => 0.3, 'color' => $c['encre'], 'dash' => '0.3,0.7' ) );
    $pdf->Line( $xi + $Wi - 2.4 - 28, $yBanque + $hBanque - 1.7, $xi + $Wi - 2.4, $yBanque + $hBanque - 1.7 );
    $pdf->SetLineStyle( array( 'dash' => 0 ) );
}

/** Registre : 5 lignes, deux paires libellé / valeur par ligne. */
function ueb_quitus_registre( TCPDF $pdf, array $d, array $c, $x, $y, $w, $h ) {
    $wLib1 = 29.0;
    $wLib2 = 35.0;
    $reste = $w - $wLib1 - $wLib2;
    $wVal1 = $reste * 1.3 / 2.3;
    $wVal2 = $reste - $wVal1;
    $xLib2 = $x + $wLib1 + $wVal1;
    $xVal2 = $xLib2 + $wLib2;
    $hL    = $h / 5;
    $pad   = 1.8;

    // « Filière » et non « Département » comme sur le modèle : sur la fiche
    // de préinscription, « Département » désigne le département d'origine.
    $lignes = array(
        array( 'N° de dossier', $d['numero_dossier'], 'Sexe', $d['sexe'] ),
        array( 'Nom(s) et prénom(s)', trim( mb_strtoupper( $d['nom'] ) . ' ' . $d['prenom'] ), null, null ),
        array( 'Né(e) le', array( ueb_pdf_date_fr( $d['date_naissance'] ), $d['lieu_naissance'] ), 'Nationalité', $d['nationalite'] ),
        array( 'Filière', $d['filiere_1'], 'Cycle / niveau / parcours', $d['niveau_lmd'] ),
        array( 'Montant', 'montant', null, 'unique' ),
    );

    foreach ( $lignes as $i => $l ) {
        $yl = $y + $i * $hL;

        /* fonds des libellés */
        $pdf->SetFillColorArray( $c['fond'] );
        $pdf->Rect( $x, $yl, $wLib1, $hL, 'F' );
        if ( null !== $l[2] ) {
            $pdf->Rect( $xLib2, $yl, $wLib2, $hL, 'F' );
        }
        $pdf->SetFont( 'uebsans', '', 7 );
        ueb_quitus_texte( $pdf, $x + $pad, $yl, $hL, $l[0], $c['gris'] );
        if ( null !== $l[2] ) {
            ueb_quitus_texte( $pdf, $xLib2 + $pad, $yl, $hL, $l[2], $c['gris'] );
        }

        /* valeurs */
        $xv = $x + $wLib1 + $pad;
        if ( 'montant' === $l[1] ) {
            $chiffres = number_format( (int) UEB_FRAIS_PREINSCRIPTION, 0, ',', ' ' ) . ' FCFA';
            $lettres  = ueb_quitus_montant_en_lettres( UEB_FRAIS_PREINSCRIPTION );
            $place    = $wVal1 + $wLib2 - 2 * $pad;
            $taille   = 8.8;
            do {
                $pdf->SetFont( 'uebsansb', '', $taille );
                $w1 = $pdf->GetStringWidth( $chiffres );
                $pdf->SetFont( 'uebsans', '', $taille * 0.84 );
                $w2 = $pdf->GetStringWidth( $lettres );
                $taille -= 0.2;
            } while ( $w1 + 1.8 + $w2 > $place && $taille > 6 );
            $taille += 0.2;
            $pdf->SetFont( 'uebsansb', '', $taille );
            $xv += ueb_quitus_texte( $pdf, $xv, $yl, $hL, $chiffres, $c['encre'] ) + 1.8;
            $pdf->SetFont( 'uebsans', '', $taille * 0.84 );
            ueb_quitus_texte( $pdf, $xv, $yl, $hL, $lettres, $c['gris'] );
        } elseif ( is_array( $l[1] ) ) {
            list( $date, $lieu ) = $l[1];
            $pdf->SetFont( 'uebsansb', '', 8.8 );
            if ( $lieu === '' ) {
                ueb_quitus_ajuster( $pdf, $date, 'uebsansb', '', 8.8, $wVal1 - 2 * $pad );
                ueb_quitus_texte( $pdf, $xv, $yl, $hL, $date, $c['encre'] );
            } else {
                $place = $wVal1 - 2 * $pad;
                $wd    = $pdf->GetStringWidth( $date );
                $pdf->SetFont( 'uebsans', '', 7.4 );
                $wa = $pdf->GetStringWidth( ' à ' ) + 1;
                ueb_quitus_ajuster( $pdf, $lieu, 'uebsansb', '', 8.8, $place - $wd - $wa );
                $tLieu = $pdf->getFontSizePt();
                $pdf->SetFont( 'uebsansb', '', 8.8 );
                $xv += ueb_quitus_texte( $pdf, $xv, $yl, $hL, $date, $c['encre'] );
                $pdf->SetFont( 'uebsans', '', 7.4 );
                $xv += ueb_quitus_texte( $pdf, $xv + 0.5, $yl, $hL, ' à ', $c['gris'] ) + 1;
                $pdf->SetFont( 'uebsansb', '', $tLieu );
                ueb_quitus_texte( $pdf, $xv, $yl, $hL, $lieu, $c['encre'] );
            }
        } else {
            $place = ( null === $l[2] ? $w - $wLib1 : $wVal1 ) - 2 * $pad;
            ueb_quitus_ajuster( $pdf, $l[1], 'uebsansb', '', 8.8, $place );
            ueb_quitus_texte( $pdf, $xv, $yl, $hL, $l[1], $c['encre'] );
        }

        if ( 'unique' === $l[3] ) {
            /* Droits de préinscription : paiement unique, donc pas de cases de tranche. */
            $pdf->SetFont( 'uebsemi', '', 8 );
            ueb_quitus_texte( $pdf, $xVal2 + $pad, $yl, $hL, 'Paiement unique', $c['encre'] );
        } elseif ( null !== $l[3] ) {
            ueb_quitus_ajuster( $pdf, $l[3], 'uebsansb', '', 8.8, $wVal2 - 2 * $pad );
            ueb_quitus_texte( $pdf, $xVal2 + $pad, $yl, $hL, $l[3], $c['encre'] );
        }
    }

    /* filets */
    $pdf->SetLineStyle( array( 'width' => 0.25, 'color' => $c['filet'], 'dash' => 0 ) );
    $pdf->Rect( $x, $y, $w, $h );
    for ( $i = 1; $i < 5; $i++ ) {
        $pdf->Line( $x, $y + $i * $hL, $x + $w, $y + $i * $hL );
    }
    foreach ( array( 0, 2, 3 ) as $i ) { // séparateur avant la 2e paire
        $pdf->Line( $xLib2, $y + $i * $hL, $xLib2, $y + ( $i + 1 ) * $hL );
    }
    $pdf->Line( $xVal2, $y + 4 * $hL, $xVal2, $y + 5 * $hL ); // avant « Paiement unique »
}
