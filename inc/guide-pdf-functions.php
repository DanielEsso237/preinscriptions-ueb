<?php
/**
 * Guide de préinscription — version PDF téléchargeable.
 *
 * Même contenu que page-guide-preinscription.php (les deux lisent
 * inc/guide-preinscription-content.php), mais mis en page pour le papier :
 * en-tête administratif bilingue, mise en page en flux avec sauts de page
 * automatiques, pied de page paginé.
 *
 * Contrairement à la fiche de préinscription (inc/pdf-functions.php), qui
 * exige un positionnement au millimètre, ce document est du texte long :
 * il est composé en flux (MultiCell + SetAutoPageBreak) et les seuls
 * calculs de position servent à ne pas couper un encadré entre deux pages.
 *
 * @package Preinscriptions_UEB
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* Marges du document, en millimètres. */
if ( ! defined( 'UEB_GUIDE_PDF_MARGE' ) ) {
    define( 'UEB_GUIDE_PDF_MARGE', 16 );
}

/**
 * URL de téléchargement du guide au format PDF.
 *
 * Volontairement accrochée à l'accueil plutôt qu'à la page du guide : le
 * lien reste valide même si la page n'a pas encore été créée dans
 * WordPress, et il peut être partagé tel quel.
 *
 * @return string
 */
function ueb_guide_pdf_url() {
    return add_query_arg( 'ueb_guide_pdf', '1', home_url( '/' ) );
}

/**
 * Point d'entrée : intercepte ?ueb_guide_pdf=1 et renvoie le document.
 *
 * Pas de nonce ici, contrairement à ueb_handle_pdf_generation() : ce PDF
 * ne contient que du contenu public déjà affiché sur la page, ne lit aucune
 * donnée personnelle et ne modifie rien. Exiger un jeton empêcherait
 * simplement de partager ou de mettre en favori le lien de téléchargement.
 */
function ueb_guide_handle_pdf() {
    if ( empty( $_GET['ueb_guide_pdf'] ) ) {
        return;
    }

    require_once get_template_directory() . '/lib/tcpdf/tcpdf.php';

    $pdf = ueb_guide_pdf_document();
    $pdf->Output( 'guide-preinscription-ueb.pdf', 'D' );
    exit;
}
add_action( 'template_redirect', 'ueb_guide_handle_pdf' );

/**
 * Construit le document complet.
 *
 * Séparé du handler pour rester testable en CLI (cf. tools/), sans
 * WordPress ni base de données au-delà des helpers de contenu.
 *
 * @return TCPDF
 */
function ueb_guide_pdf_document() {
    $dates = ueb_guide_dates();

    $pdf = new TCPDF( 'P', 'mm', 'A4', true, 'UTF-8', false );
    $pdf->SetCreator( 'UEB Préinscriptions' );
    $pdf->SetAuthor( "Université d'Ebolowa" );
    $pdf->SetTitle( 'Guide de préinscription ' . $dates['annee_academique'] );
    $pdf->SetSubject( "Procédure de préinscription et de réinscription à l'Université d'Ébolowa" );

    /* En-tête et pied de page natifs désactivés : le pied est dessiné à la
       fin, en une passe, une fois le nombre total de pages connu. */
    $pdf->setPrintHeader( false );
    $pdf->setPrintFooter( false );
    $pdf->SetMargins( UEB_GUIDE_PDF_MARGE, UEB_GUIDE_PDF_MARGE, UEB_GUIDE_PDF_MARGE );
    /* 20 mm de réserve en bas : la zone du pied de page paginé. */
    $pdf->SetAutoPageBreak( true, 20 );

    $pdf->AddPage();
    ueb_guide_pdf_couverture( $pdf, $dates );

    ueb_guide_pdf_section_procedure( $pdf );
    ueb_guide_pdf_section_paiement( $pdf );
    ueb_guide_pdf_section_pieces( $pdf );
    ueb_guide_pdf_section_depot( $pdf );
    ueb_guide_pdf_section_validation( $pdf );
    ueb_guide_pdf_section_savoir( $pdf );
    ueb_guide_pdf_section_etablissements( $pdf );

    ueb_guide_pdf_pieds_de_page( $pdf, $dates );

    return $pdf;
}

/* ============================================================
   PALETTE & HELPERS DE RENDU
   ============================================================ */

/**
 * Palette du guide.
 *
 * C'est une note de procédure administrative : elle se compose comme telle,
 * en noir sur blanc, structurée par des filets et par la typographie. Le
 * vert de l'Université sert uniquement de repère de structure (numéros et
 * titres de section, coches) ; l'ambre ne sert qu'aux filets de vigilance.
 * Aucun aplat de couleur derrière du texte courant — un document destiné à
 * être photocopié et classé doit rester lisible en noir et blanc.
 *
 * @return array<string,int[]>
 */
function ueb_guide_pdf_couleurs() {
    return array(
        'vert'       => array( 30, 79, 33 ),   // repères de structure
        'ambre'      => array( 150, 112, 20 ), // filets de vigilance
        'noir'       => array( 20, 35, 27 ),
        'gris'       => array( 82, 95, 88 ),
        'gris_clair' => array( 130, 142, 135 ),
        'ligne'      => array( 205, 213, 208 ),
        'ligne_pale' => array( 228, 233, 230 ),
        'fond'       => array( 246, 248, 247 ), // seul gris de fond, très léger
        'blanc'      => array( 255, 255, 255 ),
    );
}

/** Largeur utile entre les marges. */
function ueb_guide_pdf_largeur( $pdf ) {
    return $pdf->getPageWidth() - 2 * UEB_GUIDE_PDF_MARGE;
}

/**
 * Réserve une hauteur : si le bloc ne tient pas sur la page courante, on
 * passe à la suivante AVANT de commencer à dessiner. Indispensable pour
 * tous les blocs à fond coloré, dont le rectangle ne sait pas se couper.
 *
 * @param TCPDF $pdf
 * @param float $hauteur Hauteur nécessaire, en mm.
 */
function ueb_guide_pdf_reserver( $pdf, $hauteur ) {
    $limite = $pdf->getPageHeight() - $pdf->getBreakMargin();

    if ( $pdf->GetY() + $hauteur > $limite ) {
        $pdf->AddPage();
    }
}

/**
 * Écrit un paragraphe sur toute la largeur utile et rend la position Y
 * juste en dessous.
 *
 * @param TCPDF  $pdf
 * @param string $texte
 * @param float  $taille  Corps, en points.
 * @param string $style   Style TCPDF ('', 'B', 'I'…).
 * @param int[]  $couleur RVB.
 * @param float  $apres   Espace ajouté sous le paragraphe, en mm.
 */
function ueb_guide_pdf_para( $pdf, $texte, $taille = 9.5, $style = '', $couleur = null, $apres = 2.0 ) {
    $c = $couleur ?: ueb_guide_pdf_couleurs()['gris'];

    $pdf->SetFont( 'dejavusans', $style, $taille );
    $pdf->SetTextColor( $c[0], $c[1], $c[2] );
    $pdf->SetX( UEB_GUIDE_PDF_MARGE );
    $pdf->MultiCell( ueb_guide_pdf_largeur( $pdf ), 0, $texte, 0, 'L', false, 1 );

    if ( $apres > 0 ) {
        $pdf->SetY( $pdf->GetY() + $apres );
    }
}

/**
 * Titre de section : filet de rappel, numéro d'article et intitulé — la
 * présentation d'un article de note administrative, sans pastille ni aplat.
 * Toujours gardé solidaire du début de la section pour ne pas laisser un
 * titre seul en bas de page.
 *
 * @param TCPDF  $pdf
 * @param int    $numero  Numéro d'article.
 * @param string $titre
 * @param string $intro   Chapeau optionnel.
 */
function ueb_guide_pdf_titre_section( $pdf, $numero, $titre, $intro = '' ) {
    $c       = ueb_guide_pdf_couleurs();
    $largeur = ueb_guide_pdf_largeur( $pdf );
    $x       = UEB_GUIDE_PDF_MARGE;

    /* Un titre de section ne doit pas rester seul en bas de page : on
       réserve de quoi afficher aussi son chapeau et les premières lignes
       du contenu qui suit. */
    ueb_guide_pdf_reserver( $pdf, '' !== $intro ? 46 : 30 );

    $y = $pdf->GetY() + 4;

    /* Filet plein au-dessus du titre : c'est lui qui sépare les articles. */
    $pdf->SetLineStyle( array( 'width' => 0.5, 'color' => $c['noir'] ) );
    $pdf->Line( $x, $y, $x + $largeur, $y );

    /* Numéro d'article, dans la marge du titre. */
    $pdf->SetFont( 'dejavusans', 'B', 8 );
    $pdf->SetTextColor( $c['vert'][0], $c['vert'][1], $c['vert'][2] );
    $pdf->SetXY( $x, $y + 3.4 );
    $pdf->Cell( 7, 6, sprintf( '%02d', $numero ), 0, 0, 'L' );

    /* Intitulé. Décalé de 7 mm seulement : au-delà, le titre paraît indenté
       par rapport au corps de texte qui reprend à la marge. */
    $pdf->SetFont( 'dejavusans', 'B', 13 );
    $pdf->SetTextColor( $c['noir'][0], $c['noir'][1], $c['noir'][2] );
    $pdf->SetXY( $x + 7, $y + 2.8 );
    $pdf->Cell( $largeur - 7, 7, $titre, 0, 0, 'L' );

    $pdf->SetY( $y + 11.5 );

    if ( '' !== $intro ) {
        ueb_guide_pdf_para( $pdf, $intro, 9.5, '', $c['gris'], 3 );
    }
}

/**
 * Liste à puces « coche verte ».
 *
 * @param TCPDF    $pdf
 * @param string[] $items
 * @param float    $taille
 */
function ueb_guide_pdf_liste( $pdf, array $items, $taille = 9.5 ) {
    $c       = ueb_guide_pdf_couleurs();
    $largeur = ueb_guide_pdf_largeur( $pdf ) - 6;

    foreach ( $items as $item ) {
        $pdf->SetFont( 'dejavusans', '', $taille );
        $h = $pdf->getStringHeight( $largeur, $item );
        ueb_guide_pdf_reserver( $pdf, $h + 2 );

        $y = $pdf->GetY();

        /* Coche : deux segments, dessinés à la main (aucune police d'icônes
           n'est embarquée dans le document). */
        $pdf->SetLineStyle( array(
            'width' => 0.45,
            'cap'   => 'round',
            'join'  => 'round',
            'color' => $c['vert'],
        ) );
        $pdf->Line( UEB_GUIDE_PDF_MARGE + 0.6, $y + 2.4, UEB_GUIDE_PDF_MARGE + 1.7, $y + 3.4 );
        $pdf->Line( UEB_GUIDE_PDF_MARGE + 1.7, $y + 3.4, UEB_GUIDE_PDF_MARGE + 3.6, $y + 1.2 );

        $pdf->SetTextColor( $c['noir'][0], $c['noir'][1], $c['noir'][2] );
        $pdf->SetXY( UEB_GUIDE_PDF_MARGE + 6, $y );
        $pdf->MultiCell( $largeur, 0, $item, 0, 'L', false, 1 );

        $pdf->SetY( $pdf->GetY() + 1.2 );
    }
}

/**
 * Hauteur qu'occuperait une liste rendue par ueb_guide_pdf_liste(), sans
 * rien dessiner — permet de garder une liste courte solidaire de son
 * sous-titre plutôt que de l'éparpiller sur deux pages.
 *
 * @param TCPDF    $pdf
 * @param string[] $items
 * @param float    $taille
 * @return float Hauteur en mm.
 */
function ueb_guide_pdf_hauteur_liste( $pdf, array $items, $taille = 9.5 ) {
    $largeur = ueb_guide_pdf_largeur( $pdf ) - 6;
    $hauteur = 0;

    $pdf->SetFont( 'dejavusans', '', $taille );
    foreach ( $items as $item ) {
        $hauteur += $pdf->getStringHeight( $largeur, $item ) + 1.2;
    }

    return $hauteur;
}

/**
 * Encadré : texte en retrait derrière un filet vertical, sans aplat.
 *
 * Le ton se lit dans l'intitulé (« IMPORTANT », « À FAIRE »…) ; la couleur
 * du filet ne fait que le confirmer. Un document photocopié en noir et
 * blanc reste donc aussi clair que l'original.
 *
 * @param TCPDF  $pdf
 * @param string $titre  Intitulé en capitales, optionnel.
 * @param string $texte
 * @param string $ton    'info' | 'alerte' | 'danger' | 'succes'
 */
function ueb_guide_pdf_encart( $pdf, $titre, $texte, $ton = 'info' ) {
    $c = ueb_guide_pdf_couleurs();

    /* Le filet peut être discret (info) sans que l'intitulé le devienne :
       les deux couleurs sont donc distinctes, sinon un titre « FRAIS
       MÉDICAUX » hériterait du gris de filet et deviendrait illisible. */
    $tons = array(
        'info'   => array( 'filet' => $c['ligne'], 'titre' => $c['noir'] ),
        'alerte' => array( 'filet' => $c['ambre'], 'titre' => $c['ambre'] ),
        'danger' => array( 'filet' => $c['noir'],  'titre' => $c['noir'] ),
        'succes' => array( 'filet' => $c['vert'],  'titre' => $c['vert'] ),
    );
    $p     = isset( $tons[ $ton ] ) ? $tons[ $ton ] : $tons['info'];
    $filet = $p['filet'];

    $largeur     = ueb_guide_pdf_largeur( $pdf );
    $largeur_txt = $largeur - 6;

    $pdf->SetFont( 'dejavusans', '', 9 );
    $h_texte = $pdf->getStringHeight( $largeur_txt, $texte );
    $h_titre = '' !== $titre ? 4.8 : 0;
    $h_bloc  = $h_texte + $h_titre;

    ueb_guide_pdf_reserver( $pdf, $h_bloc + 4 );

    $y = $pdf->GetY();
    $x = UEB_GUIDE_PDF_MARGE;

    /* Filet vertical de retrait, sur toute la hauteur du bloc. */
    $pdf->SetLineStyle( array( 'width' => 0.6, 'color' => $filet ) );
    $pdf->Line( $x + 0.3, $y, $x + 0.3, $y + $h_bloc );

    $curseur = $y;

    if ( '' !== $titre ) {
        $pdf->SetFont( 'dejavusans', 'B', 8 );
        $pdf->SetTextColor( $p['titre'][0], $p['titre'][1], $p['titre'][2] );
        $pdf->SetXY( $x + 6, $curseur );
        $pdf->Cell( $largeur_txt, 4.2, ueb_guide_pdf_capitales( $titre ), 0, 0, 'L' );
        $curseur += $h_titre;
    }

    $pdf->SetFont( 'dejavusans', '', 9 );
    $pdf->SetTextColor( $c['gris'][0], $c['gris'][1], $c['gris'][2] );
    $pdf->SetXY( $x + 6, $curseur );
    $pdf->MultiCell( $largeur_txt, 0, $texte, 0, 'L', false, 1 );

    $pdf->SetY( $y + $h_bloc + 4 );
}

/**
 * Passe un intitulé en capitales espacées, accents compris.
 * mb_strtoupper est requis : strtoupper() laisserait « é » intact.
 *
 * @param string $texte
 * @return string
 */
function ueb_guide_pdf_capitales( $texte ) {
    return function_exists( 'mb_strtoupper' )
        ? mb_strtoupper( $texte, 'UTF-8' )
        : strtoupper( $texte );
}

/* ============================================================
   COUVERTURE
   ============================================================ */

/**
 * En-tête administratif bilingue, titre du document et repères essentiels.
 *
 * @param TCPDF $pdf
 * @param array $dates Retour de ueb_guide_dates().
 */
function ueb_guide_pdf_couverture( $pdf, array $dates ) {
    $c = ueb_guide_pdf_couleurs();

    /* En-tête officiel partagé avec la fiche de préinscription : les deux
       documents doivent se présenter de la même façon. */
    $y = ueb_pdf_entete_bilingue( $pdf, 12 );

    $largeur = ueb_guide_pdf_largeur( $pdf );
    $x       = UEB_GUIDE_PDF_MARGE;

    /* Titre du document : encadré par deux filets, comme l'intitulé d'une
       note de service. Aucun aplat — le document se photocopie. */
    $pdf->SetLineStyle( array( 'width' => 0.8, 'color' => $c['noir'] ) );
    $pdf->Line( $x, $y + 5, $x + $largeur, $y + 5 );

    $pdf->SetFont( 'dejavusans', 'B', 17 );
    $pdf->SetTextColor( $c['noir'][0], $c['noir'][1], $c['noir'][2] );
    $pdf->SetXY( $x, $y + 8.5 );
    $pdf->Cell( $largeur, 9, 'GUIDE DE PRÉINSCRIPTION', 0, 0, 'C' );

    $pdf->SetFont( 'dejavusans', '', 9.5 );
    $pdf->SetTextColor( $c['gris'][0], $c['gris'][1], $c['gris'][2] );
    $pdf->SetXY( $x, $y + 17.5 );
    $pdf->Cell( $largeur, 5, 'Année académique ' . $dates['annee_academique'], 0, 0, 'C' );

    $pdf->SetLineStyle( array( 'width' => 0.25, 'color' => $c['noir'] ) );
    $pdf->Line( $x, $y + 24, $x + $largeur, $y + 24 );

    $pdf->SetY( $y + 29 );

    ueb_guide_pdf_para(
        $pdf,
        "Procédure officielle de préinscription et de réinscription à l'Université d'Ébolowa : "
        . "comment s'inscrire en ligne, comment régler ses droits, quelles pièces réunir et où "
        . "déposer son dossier.",
        9.5,
        '',
        $c['gris'],
        3
    );

    /* Trois repères essentiels, sur une ligne. */
    $repere = array(
        array( 'Période',       ucfirst( $dates['periode'] ) ),
        array( 'Droits',        ueb_guide_frais( false ) ),
        array( 'Dépôt', "Sur place, à l'établissement choisi" ),
    );

    /* Ligne de données : trois colonnes sous un filet commun, pas trois
       cartes. 15 mm suffisent pour deux lignes de valeur — « Du 1er
       septembre au 31 octobre 2026 » ne tient pas sur une seule. */
    $y_faits = $pdf->GetY() + 1;
    $col     = ( $largeur - 2 * 4 ) / 3; // 4 mm de gouttière
    $h_fait  = 15;

    $pdf->SetLineStyle( array( 'width' => 0.4, 'color' => $c['noir'] ) );

    foreach ( $repere as $i => $fait ) {
        $cx = $x + $i * ( $col + 4 );

        $pdf->Line( $cx, $y_faits, $cx + $col, $y_faits );

        $pdf->SetFont( 'dejavusans', 'B', 6.6 );
        $pdf->SetTextColor( $c['gris_clair'][0], $c['gris_clair'][1], $c['gris_clair'][2] );
        $pdf->SetXY( $cx, $y_faits + 2 );
        $pdf->Cell( $col, 3, ueb_guide_pdf_capitales( $fait[0] ), 0, 0, 'L' );

        $pdf->SetFont( 'dejavusans', 'B', 8.4 );
        $pdf->SetTextColor( $c['noir'][0], $c['noir'][1], $c['noir'][2] );
        $pdf->SetXY( $cx, $y_faits + 6 );
        $pdf->MultiCell( $col, 0, $fait[1], 0, 'L', false, 0, null, null, true, 0, false, true, 9.5, 'T' );
    }

    $pdf->SetY( $y_faits + $h_fait + 6 );
}

/* ============================================================
   SECTIONS
   ============================================================ */

/** §1 — La procédure en ligne. */
function ueb_guide_pdf_section_procedure( $pdf ) {
    $c       = ueb_guide_pdf_couleurs();
    $largeur = ueb_guide_pdf_largeur( $pdf );

    ueb_guide_pdf_titre_section(
        $pdf,
        1,
        'La procédure en ligne',
        "La préinscription commence sur la plateforme en ligne et se termine au guichet de votre "
        . "établissement. Six étapes, à suivre dans l'ordre."
    );

    foreach ( ueb_guide_etapes() as $i => $etape ) {
        $largeur_txt = $largeur - 12;

        /* Hauteur du bloc mesurée avant tout tracé : la pastille numérotée
           et son texte doivent rester sur la même page. */
        $pdf->SetFont( 'dejavusans', '', 9 );
        $h = 5 + $pdf->getStringHeight( $largeur_txt, $etape['desc'] );
        if ( '' !== $etape['aide'] ) {
            $pdf->SetFont( 'dejavusans', 'I', 8.2 );
            $h += $pdf->getStringHeight( $largeur_txt - 3, $etape['aide'] ) + 1.5;
        }
        ueb_guide_pdf_reserver( $pdf, $h + 4 );

        $y = $pdf->GetY();
        $x = UEB_GUIDE_PDF_MARGE;

        /* Numéro d'étape : un chiffre dans la marge, pas une pastille. La
           séquence se lit dans la colonne de chiffres. */
        $pdf->SetFont( 'dejavusans', 'B', 9.5 );
        $pdf->SetTextColor( $c['vert'][0], $c['vert'][1], $c['vert'][2] );
        $pdf->SetXY( $x, $y );
        $pdf->Cell( 8, 5, (string) ( $i + 1 ) . '.', 0, 0, 'L' );

        /* Titre de l'étape. */
        $pdf->SetFont( 'dejavusans', 'B', 10 );
        $pdf->SetTextColor( $c['noir'][0], $c['noir'][1], $c['noir'][2] );
        $pdf->SetXY( $x + 12, $y );
        $pdf->Cell( $largeur_txt, 5, $etape['titre'], 0, 0, 'L' );

        /* Description. */
        $pdf->SetFont( 'dejavusans', '', 9 );
        $pdf->SetTextColor( $c['gris'][0], $c['gris'][1], $c['gris'][2] );
        $pdf->SetXY( $x + 12, $y + 5 );
        $pdf->MultiCell( $largeur_txt, 0, $etape['desc'], 0, 'L', false, 1 );

        if ( '' !== $etape['aide'] ) {
            $y_aide = $pdf->GetY() + 1;

            $pdf->SetFont( 'dejavusans', 'I', 8.2 );
            $h_aide = $pdf->getStringHeight( $largeur_txt - 3, $etape['aide'] );

            $pdf->SetLineStyle( array( 'width' => 0.4, 'color' => $c['ligne'] ) );
            $pdf->Line( $x + 12, $y_aide, $x + 12, $y_aide + $h_aide );

            $pdf->SetTextColor( $c['gris'][0], $c['gris'][1], $c['gris'][2] );
            $pdf->SetXY( $x + 15, $y_aide );
            $pdf->MultiCell( $largeur_txt - 3, 0, $etape['aide'], 0, 'L', false, 1 );
        }

        $pdf->SetY( $pdf->GetY() + 3 );
    }
}

/** §2 — Modalités de paiement. */
function ueb_guide_pdf_section_paiement( $pdf ) {
    $c       = ueb_guide_pdf_couleurs();
    $largeur = ueb_guide_pdf_largeur( $pdf );

    ueb_guide_pdf_titre_section(
        $pdf,
        2,
        'Modalités de paiement',
        "Les droits de préinscription ou de réinscription, et le cas échéant les frais médicaux, "
        . "se règlent par l'un des trois canaux officiels ci-dessous."
    );

    /* Montant : c'est la donnée saillante de l'article, il l'est par sa
       taille et non par un cartouche coloré. */
    ueb_guide_pdf_reserver( $pdf, 20 );
    $y = $pdf->GetY() + 1;

    $pdf->SetFont( 'dejavusans', 'B', 7.4 );
    $pdf->SetTextColor( $c['gris_clair'][0], $c['gris_clair'][1], $c['gris_clair'][2] );
    $pdf->SetXY( UEB_GUIDE_PDF_MARGE, $y );
    $pdf->Cell( $largeur, 3.5, 'DROITS DE PRÉINSCRIPTION', 0, 0, 'L' );

    $pdf->SetFont( 'dejavusans', 'B', 17 );
    $pdf->SetTextColor( $c['noir'][0], $c['noir'][1], $c['noir'][2] );
    $pdf->SetXY( UEB_GUIDE_PDF_MARGE, $y + 4 );
    $pdf->Cell( $largeur, 8, ueb_guide_frais( false ), 0, 0, 'L' );

    $pdf->SetLineStyle( array( 'width' => 0.25, 'color' => $c['ligne'] ) );
    $pdf->Line( UEB_GUIDE_PDF_MARGE, $y + 14, UEB_GUIDE_PDF_MARGE + $largeur, $y + 14 );

    $pdf->SetY( $y + 18 );

    /* Les trois canaux, en colonnes sous un filet commun. */
    $paiements = ueb_guide_paiements();
    $col       = ( $largeur - 2 * 4 ) / 3;
    $h_bloc    = 24;

    ueb_guide_pdf_reserver( $pdf, $h_bloc + 4 );
    $y = $pdf->GetY();

    $pdf->SetLineStyle( array( 'width' => 0.25, 'color' => $c['ligne'] ) );

    foreach ( $paiements as $i => $pay ) {
        $cx = UEB_GUIDE_PDF_MARGE + $i * ( $col + 4 );

        $pdf->Line( $cx, $y, $cx + $col, $y );

        $pdf->SetFont( 'dejavusans', 'B', 9.5 );
        $pdf->SetTextColor( $c['noir'][0], $c['noir'][1], $c['noir'][2] );
        $pdf->SetXY( $cx, $y + 2.6 );
        $pdf->Cell( $col, 4.5, $pay['nom'], 0, 0, 'L' );

        $pdf->SetFont( 'dejavusans', '', 7.8 );
        $pdf->SetTextColor( $c['gris'][0], $c['gris'][1], $c['gris'][2] );
        $pdf->SetXY( $cx, $y + 7.6 );
        $pdf->MultiCell( $col, 0, $pay['detail'], 0, 'L', false, 1, null, null, true, 0, false, true, 9, 'T' );

        if ( '' !== $pay['ref'] ) {
            /* Référence de paiement en gras, à recopier au guichet : la
               mettre en évidence par la graisse suffit. */
            $pdf->SetFont( 'dejavusans', 'B', 8 );
            $pdf->SetTextColor( $c['noir'][0], $c['noir'][1], $c['noir'][2] );
            $pdf->SetXY( $cx, $y + $h_bloc - 6 );
            $pdf->MultiCell( $col, 0, $pay['ref'], 0, 'L', false, 1, null, null, true, 0, false, true, 6, 'T' );
        }
    }

    $pdf->SetY( $y + $h_bloc + 3 );

    ueb_guide_pdf_encart(
        $pdf,
        'À ne pas oublier',
        "Précisez clairement l'objet du paiement au moment du versement, et conservez votre reçu : "
        . "il est exigé lors du dépôt du dossier physique.",
        'alerte'
    );

    ueb_guide_pdf_encart(
        $pdf,
        'Frais médicaux et certificat médical',
        "Le paiement des frais médicaux et l'obtention du certificat médical se font sur le service "
        . "dédié de l'Université : " . UEB_URL_CERTIFICAT_MEDICAL,
        'info'
    );
}

/** §3 — Pièces à fournir, par niveau. */
function ueb_guide_pdf_section_pieces( $pdf ) {
    $c = ueb_guide_pdf_couleurs();

    ueb_guide_pdf_titre_section(
        $pdf,
        3,
        'Pièces à fournir',
        "Les pièces demandées dépendent du niveau auquel vous vous inscrivez."
    );

    foreach ( ueb_guide_dossiers() as $dossier ) {
        /* Chaque bloc de niveau tient en moins d'une demi-page : on le garde
           entier plutôt que de laisser deux pièces orphelines au verso. */
        ueb_guide_pdf_reserver(
            $pdf,
            11.5 + ueb_guide_pdf_hauteur_liste( $pdf, $dossier['pieces'] )
        );

        $pdf->SetFont( 'dejavusans', 'B', 10.5 );
        $pdf->SetTextColor( $c['noir'][0], $c['noir'][1], $c['noir'][2] );
        $pdf->SetX( UEB_GUIDE_PDF_MARGE );
        $pdf->Cell( ueb_guide_pdf_largeur( $pdf ), 5.5, $dossier['niveau'], 0, 1, 'L' );

        $pdf->SetFont( 'dejavusans', '', 8 );
        $pdf->SetTextColor( $c['gris_clair'][0], $c['gris_clair'][1], $c['gris_clair'][2] );
        $pdf->SetX( UEB_GUIDE_PDF_MARGE );
        $pdf->Cell( ueb_guide_pdf_largeur( $pdf ), 4.5, ueb_guide_pdf_capitales( $dossier['contexte'] ), 0, 1, 'L' );

        $pdf->SetY( $pdf->GetY() + 1.5 );
        ueb_guide_pdf_liste( $pdf, $dossier['pieces'] );

        if ( '' !== $dossier['note'] ) {
            $pdf->SetY( $pdf->GetY() + 3 );
            ueb_guide_pdf_encart( $pdf, '', $dossier['note'], 'alerte' );
            $pdf->SetY( $pdf->GetY() + 2 );
        } else {
            $pdf->SetY( $pdf->GetY() + 6 );
        }
    }
}

/** §4 — Dépôt du dossier physique. */
function ueb_guide_pdf_section_depot( $pdf ) {
    ueb_guide_pdf_titre_section(
        $pdf,
        4,
        'Dépôt du dossier physique',
        "Présentez-vous à l'établissement choisi le jour du rendez-vous, muni des pièces suivantes."
    );

    $depot = ueb_guide_depot_pieces();
    ueb_guide_pdf_reserver( $pdf, ueb_guide_pdf_hauteur_liste( $pdf, $depot ) );
    ueb_guide_pdf_liste( $pdf, $depot );

    $pdf->SetY( $pdf->GetY() + 2 );
    ueb_guide_pdf_encart(
        $pdf,
        'Cas particulier — FSJP, niveau 1',
        "La photocopie certifiée de la capacité en Droit avec une moyenne d'au moins 13/20, ainsi "
        . "qu'une attestation de classe de première avec une moyenne égale ou supérieure à 10/20, "
        . "sont acceptées.",
        'info'
    );
}

/** §5 — Validation du dossier. */
function ueb_guide_pdf_section_validation( $pdf ) {
    ueb_guide_pdf_titre_section( $pdf, 5, 'Validation du dossier' );

    ueb_guide_pdf_encart(
        $pdf,
        'À faire',
        "Faites absolument valider votre dossier à la scolarité qui vous sera indiquée lors du "
        . "dépôt, dans l'établissement choisi.",
        'succes'
    );

    ueb_guide_pdf_encart(
        $pdf,
        'À ne jamais faire',
        "Ne prenez pas le risque de faire valider votre dossier hors du service de la scolarité de "
        . "l'établissement sollicité.",
        'danger'
    );
}

/** §6 — Bon à savoir. */
function ueb_guide_pdf_section_savoir( $pdf ) {
    ueb_guide_pdf_titre_section( $pdf, 6, 'Bon à savoir' );

    foreach ( ueb_guide_bon_a_savoir() as $item ) {
        ueb_guide_pdf_encart(
            $pdf,
            $item['titre'],
            $item['texte'],
            'alerte' === $item['ton'] ? 'alerte' : 'info'
        );
    }
}

/** §7 — Les établissements. */
function ueb_guide_pdf_section_etablissements( $pdf ) {
    $c       = ueb_guide_pdf_couleurs();
    $largeur = ueb_guide_pdf_largeur( $pdf );

    ueb_guide_pdf_titre_section(
        $pdf,
        7,
        'Nos établissements',
        "L'Université d'Ébolowa (UEb) est un établissement public, scientifique et culturel, doté "
        . "de la personnalité morale et de l'autonomie financière. Créée par le décret n° 2022/0009 "
        . "du 06 janvier 2022, elle est constituée des établissements suivants."
    );

    /* Table de référence : deux colonnes, un filet par ligne. */
    $x = UEB_GUIDE_PDF_MARGE;
    $pdf->SetLineStyle( array( 'width' => 0.25, 'color' => $c['ligne'] ) );

    foreach ( ueb_guide_etablissements() as $etab ) {
        ueb_guide_pdf_reserver( $pdf, 11 );

        $y = $pdf->GetY();
        $pdf->Line( $x, $y, $x + $largeur, $y );

        $pdf->SetFont( 'dejavusans', 'B', 8.4 );
        $pdf->SetTextColor( $c['vert'][0], $c['vert'][1], $c['vert'][2] );
        $pdf->SetXY( $x, $y + 2.2 );
        $pdf->Cell( 22, 5, $etab['abbr'], 0, 0, 'L' );

        $pdf->SetFont( 'dejavusans', '', 9 );
        $pdf->SetTextColor( $c['noir'][0], $c['noir'][1], $c['noir'][2] );
        $pdf->SetXY( $x + 24, $y + 2.2 );
        $pdf->Cell( $largeur - 24, 5, $etab['nom'], 0, 0, 'L' );

        $pdf->SetY( $y + 8.5 );
    }

    /* Filet de clôture de la table. */
    $pdf->Line( $x, $pdf->GetY(), $x + $largeur, $pdf->GetY() );
    $pdf->SetY( $pdf->GetY() + 4 );

    $pdf->SetY( $pdf->GetY() + 2 );
    ueb_guide_pdf_encart(
        $pdf,
        'Préinscription en ligne',
        'Rendez-vous sur ' . UEB_URL_PLATEFORME . ' pour saisir votre demande.',
        'succes'
    );
}

/* ============================================================
   PIEDS DE PAGE
   ============================================================ */

/**
 * Dessine le pied de page sur chaque page, en une passe finale — le total
 * de pages n'est connu qu'une fois tout le contenu composé.
 *
 * @param TCPDF $pdf
 * @param array $dates Retour de ueb_guide_dates().
 */
function ueb_guide_pdf_pieds_de_page( $pdf, array $dates ) {
    $c       = ueb_guide_pdf_couleurs();
    $total   = $pdf->getNumPages();
    $largeur = ueb_guide_pdf_largeur( $pdf );
    $y       = $pdf->getPageHeight() - 14;

    for ( $page = 1; $page <= $total; $page++ ) {
        $pdf->setPage( $page );

        /* Le pied s'écrit volontairement SOUS la marge de rupture : avec la
           coupure automatique active, chaque pied ferait naître une page
           vide. La désactivation est refaite à chaque tour parce que
           setPage() restaure les marges mémorisées de la page — un appel
           unique avant la boucle serait écrasé dès la première itération. */
        $pdf->SetAutoPageBreak( false, 0 );

        $pdf->SetLineStyle( array( 'width' => 0.2, 'color' => $c['ligne'] ) );
        $pdf->Line( UEB_GUIDE_PDF_MARGE, $y, UEB_GUIDE_PDF_MARGE + $largeur, $y );

        $pdf->SetFont( 'dejavusans', '', 7.4 );
        $pdf->SetTextColor( $c['gris'][0], $c['gris'][1], $c['gris'][2] );

        $pdf->SetXY( UEB_GUIDE_PDF_MARGE, $y + 1.5 );
        $pdf->Cell(
            $largeur / 2,
            4,
            "Université d'Ébolowa — Guide de préinscription " . $dates['annee_academique'],
            0,
            0,
            'L'
        );

        $pdf->SetXY( UEB_GUIDE_PDF_MARGE + $largeur / 2, $y + 1.5 );
        $pdf->Cell( $largeur / 2, 4, 'Page ' . $page . ' / ' . $total, 0, 0, 'R' );
    }
}
