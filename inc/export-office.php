<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Rendus Excel (.xlsx) et Word (.docx) de la liste des préinscrits, au même
 * modèle « A · Officiel classique » que le PDF (inc/export-functions.php).
 *
 * Les deux formats sont des archives ZIP de fichiers XML (OOXML) écrites
 * directement, sans PhpSpreadsheet ni PHPWord : le document produit est
 * simple — un en-tête, un tableau, un bloc de signature — et une librairie
 * de plusieurs mégaoctets serait hors de proportion pour ce besoin, dans un
 * thème qui embarque déjà TCPDF.
 *
 * Les fichiers obtenus s'ouvrent sans avertissement dans Excel, Word,
 * LibreOffice et Google Docs/Sheets.
 *
 * @package Preinscriptions_UEB
 */

/* ============================================================
   OUTILS COMMUNS
   ============================================================ */

/** Échappe une valeur pour le contenu d'un nœud XML. */
function ueb_export_xml( $txt ) {
    return htmlspecialchars( (string) $txt, ENT_QUOTES | ENT_XML1, 'UTF-8' );
}

/**
 * Assemble une archive ZIP en mémoire de travail puis l'envoie au
 * navigateur en téléchargement.
 *
 * @param array<string,string> $fichiers Chemin dans l'archive => contenu.
 * @param string               $nom      Nom du fichier téléchargé.
 * @param string               $mime     Type MIME.
 */
function ueb_export_envoyer_zip( $fichiers, $nom, $mime ) {
    $chemin = wp_tempnam( $nom );

    $zip = new ZipArchive();
    if ( true !== $zip->open( $chemin, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
        wp_die( "Impossible de préparer le fichier d'export." );
    }

    foreach ( $fichiers as $interne => $contenu ) {
        $zip->addFromString( $interne, $contenu );
    }
    $zip->close();

    header( 'Content-Type: ' . $mime );
    header( 'Content-Disposition: attachment; filename="' . $nom . '"' );
    header( 'Content-Length: ' . filesize( $chemin ) );
    header( 'Content-Transfer-Encoding: binary' );

    readfile( $chemin );
    @unlink( $chemin );
}

/** Logo de l'université, ou chaîne vide s'il est absent du thème. */
function ueb_export_logo_binaire() {
    $logo = get_template_directory() . '/assets/images/logo-ueb.png';
    return file_exists( $logo ) ? (string) file_get_contents( $logo ) : '';
}

/* ============================================================
   EXCEL — .xlsx (SpreadsheetML)
   ============================================================ */

/**
 * Feuille de calcul reprenant le modèle A : en-tête bilingue avec
 * armoiries, références, titre, tableau à filets et bloc de clôture.
 *
 * La ligne d'en-tête du tableau est figée et filtrable : c'est ce qu'on
 * attend d'un classeur, et cela n'enlève rien à la mise en forme du modèle.
 */
function ueb_export_rendre_xlsx( $rows, $meta ) {
    $colonnes = ueb_export_colonnes();
    $nb_cols  = count( $colonnes );
    $derniere = 'H'; // 8 colonnes

    $logo = ueb_export_logo_binaire();

    /* ---- Trame du document : numéros de ligne ---- */
    $l_entete_tableau = 14;
    $l_premiere_donnee = $l_entete_tableau + 1;
    $l_derniere_donnee = $l_premiere_donnee + max( 0, count( $rows ) ) - 1;
    if ( ! $rows ) {
        $l_derniere_donnee = $l_entete_tableau;
    }

    $l_arrete    = $l_derniere_donnee + 2;
    $l_signataire = $l_arrete + 2;
    $l_signature = $l_signataire + 2;
    $l_pied      = $l_signature + 2;

    /* ---- Lignes ---- */
    $lignes = array();

    $cell = static function ( $col, $ligne, $valeur, $style, $type = 'inlineStr' ) {
        $ref = $col . $ligne;
        if ( '' === $valeur && 'inlineStr' === $type ) {
            return '<c r="' . $ref . '" s="' . $style . '"/>';
        }
        return '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">'
            . ueb_export_xml( $valeur ) . '</t></is></c>';
    };

    /** Une ligne complète A..H, la première cellule portant la valeur fusionnée. */
    $ligne_fusion = static function ( $num, $valeur, $style, $hauteur = null ) use ( $cell ) {
        $cells = $cell( 'A', $num, $valeur, $style );
        foreach ( array( 'B', 'C', 'D', 'E', 'F', 'G', 'H' ) as $c ) {
            $cells .= $cell( $c, $num, '', $style );
        }
        $ht = $hauteur ? ' ht="' . $hauteur . '" customHeight="1"' : '';
        return '<row r="' . $num . '"' . $ht . '>' . $cells . '</row>';
    };

    // 1–4 : en-tête bilingue (FR : A–C, armoiries : D–E, EN : F–H).
    $entete = array(
        array( 'RÉPUBLIQUE DU CAMEROUN', 'REPUBLIC OF CAMEROON', 1 ),
        array( 'Paix – Travail – Patrie', 'Peace – Work – Fatherland', 2 ),
        array( "MINISTÈRE DE L'ENSEIGNEMENT SUPÉRIEUR", 'MINISTRY OF HIGHER EDUCATION', 3 ),
        array( "UNIVERSITÉ D'ÉBOLOWA", 'THE UNIVERSITY OF EBOLOWA', 4 ),
    );

    foreach ( $entete as $i => $bloc ) {
        $num   = $i + 1;
        $style = $bloc[2];
        $cells = $cell( 'A', $num, $bloc[0], $style )
            . $cell( 'B', $num, '', $style )
            . $cell( 'C', $num, '', $style )
            . $cell( 'D', $num, '', 0 )
            . $cell( 'E', $num, '', 0 )
            . $cell( 'F', $num, $bloc[1], $style )
            . $cell( 'G', $num, '', $style )
            . $cell( 'H', $num, '', $style );
        $lignes[] = '<row r="' . $num . '" ht="16" customHeight="1">' . $cells . '</row>';
    }

    // 5 : filet double sous l'en-tête.
    $lignes[] = $ligne_fusion( 5, '', 17, 6 );

    // 6 : références.
    $lignes[] = '<row r="6">'
        . $cell( 'A', 6, 'N/Réf. : ' . $meta['reference'], 8 )
        . $cell( 'B', 6, '', 8 ) . $cell( 'C', 6, '', 8 ) . $cell( 'D', 6, '', 8 )
        . $cell( 'E', 6, $meta['lieu_date'], 9 )
        . $cell( 'F', 6, '', 9 ) . $cell( 'G', 6, '', 9 ) . $cell( 'H', 6, '', 9 )
        . '</row>';

    $lignes[] = '<row r="7"/>';
    $lignes[] = $ligne_fusion( 8, $meta['titre'], 5, 22 );
    $lignes[] = $ligne_fusion( 9, $meta['titre_en'], 6 );
    $lignes[] = $ligne_fusion( 10, $meta['annee'], 7 );
    $lignes[] = '<row r="11"/>';

    // 12 : contexte (situation à gauche, périmètre à droite).
    $lignes[] = '<row r="12">'
        . $cell( 'A', 12, $meta['situation'], 8 )
        . $cell( 'B', 12, '', 8 ) . $cell( 'C', 12, '', 8 ) . $cell( 'D', 12, '', 8 )
        . $cell( 'E', 12, $meta['perimetre'], 9 )
        . $cell( 'F', 12, '', 9 ) . $cell( 'G', 12, '', 9 ) . $cell( 'H', 12, '', 9 )
        . '</row>';

    $lignes[] = '<row r="13"/>';

    // 14 : en-tête du tableau.
    $cells = '';
    foreach ( $colonnes as $i => $col ) {
        $cells .= $cell( chr( 65 + $i ), $l_entete_tableau, $col['titre'], 10 );
    }
    $lignes[] = '<row r="' . $l_entete_tableau . '" ht="26" customHeight="1">' . $cells . '</row>';

    // Données.
    $num = $l_premiere_donnee;
    foreach ( $rows as $row ) {
        $cells = '';
        foreach ( $colonnes as $i => $col ) {
            // 12 = cellule centrée, 11 = cellule alignée à gauche.
            $style = ( 'C' === $col['align'] ) ? 12 : 11;
            $cells .= $cell( chr( 65 + $i ), $num, $row[ $col['cle'] ], $style );
        }
        $lignes[] = '<row r="' . $num . '">' . $cells . '</row>';
        $num++;
    }

    if ( ! $rows ) {
        $lignes[] = $ligne_fusion( $l_premiere_donnee, 'Aucun dossier ne correspond à la sélection.', 6 );
        $l_derniere_donnee = $l_premiere_donnee;
        $l_arrete          = $l_derniere_donnee + 2;
        $l_signataire      = $l_arrete + 2;
        $l_signature       = $l_signataire + 2;
        $l_pied            = $l_signature + 2;
    }

    // Clôture.
    $lignes[] = $ligne_fusion( $l_arrete, $meta['arrete'], 13, 28 );

    $lignes[] = '<row r="' . $l_signataire . '">'
        . $cell( 'A', $l_signataire, '', 0 ) . $cell( 'B', $l_signataire, '', 0 )
        . $cell( 'C', $l_signataire, '', 0 ) . $cell( 'D', $l_signataire, '', 0 )
        . $cell( 'E', $l_signataire, '', 0 )
        . $cell( 'F', $l_signataire, $meta['signataire'], 14 )
        . $cell( 'G', $l_signataire, '', 14 ) . $cell( 'H', $l_signataire, '', 14 )
        . '</row>';

    $lignes[] = '<row r="' . $l_signature . '" ht="34" customHeight="1">'
        . $cell( 'F', $l_signature, $meta['signature'], 15 )
        . $cell( 'G', $l_signature, '', 15 ) . $cell( 'H', $l_signature, '', 15 )
        . '</row>';

    $lignes[] = $ligne_fusion( $l_pied, $meta['pied'], 16 );

    /* ---- Fusions ---- */
    $fusions = array(
        'A1:C1', 'F1:H1', 'A2:C2', 'F2:H2', 'A3:C3', 'F3:H3', 'A4:C4', 'F4:H4',
        'D1:E4', 'A5:H5', 'A6:D6', 'E6:H6', 'A8:H8', 'A9:H9', 'A10:H10',
        'A12:D12', 'E12:H12',
        'A' . $l_arrete . ':H' . $l_arrete,
        'F' . $l_signataire . ':H' . $l_signataire,
        'F' . $l_signature . ':H' . $l_signature,
        'A' . $l_pied . ':H' . $l_pied,
    );
    if ( ! $rows ) {
        $fusions[] = 'A' . $l_premiere_donnee . ':H' . $l_premiere_donnee;
    }

    $xml_fusions = '<mergeCells count="' . count( $fusions ) . '">';
    foreach ( $fusions as $f ) {
        $xml_fusions .= '<mergeCell ref="' . $f . '"/>';
    }
    $xml_fusions .= '</mergeCells>';

    /* ---- Largeurs de colonnes ---- */
    // Excel compte en caractères : ~1,85 mm par caractère à la taille par défaut.
    $xml_cols = '<cols>';
    foreach ( $colonnes as $i => $col ) {
        $xml_cols .= '<col min="' . ( $i + 1 ) . '" max="' . ( $i + 1 ) . '" width="'
            . round( $col['largeur'] / 1.85, 2 ) . '" customWidth="1"/>';
    }
    $xml_cols .= '</cols>';

    /* ---- Feuille ---- */
    $plage_filtre = 'A' . $l_entete_tableau . ':' . $derniere . max( $l_derniere_donnee, $l_entete_tableau );

    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<dimension ref="A1:' . $derniere . $l_pied . '"/>'
        . '<sheetViews><sheetView showGridLines="0" tabSelected="1" workbookViewId="0">'
        . '<pane ySplit="' . $l_entete_tableau . '" topLeftCell="A' . $l_premiere_donnee . '" activePane="bottomLeft" state="frozen"/>'
        . '</sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="14.5"/>'
        . $xml_cols
        . '<sheetData>' . implode( '', $lignes ) . '</sheetData>'
        . ( $rows ? '<autoFilter ref="' . $plage_filtre . '"/>' : '' )
        . $xml_fusions
        . '<printOptions horizontalCentered="1"/>'
        . '<pageMargins left="0.5" right="0.5" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>'
        // Mise à l'échelle en largeur seulement : une contrainte de hauteur
        // (fitToHeight) fait tenir de force toute la liste sur une page, et
        // les tableurs coupent alors tout ce qui dépasse à l'impression.
        . '<pageSetup paperSize="9" orientation="portrait" scale="88"/>'
        . ( $logo ? '<drawing r:id="rId1"/>' : '' )
        . '</worksheet>';

    /* ---- Styles ---- */
    $styles = ueb_export_xlsx_styles();

    /* ---- Archive ---- */
    $fichiers = array(
        '[Content_Types].xml' =>
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Default Extension="png" ContentType="image/png"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . ( $logo ? '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>' : '' )
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '</Types>',

        '_rels/.rels' =>
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '</Relationships>',

        'docProps/core.xml' => ueb_export_core_xml( $meta ),

        'xl/workbook.xml' =>
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Préinscrits" sheetId="1" r:id="rId1"/></sheets>'
            // Titres d'impression : la ligne d'en-tête du tableau se répète
            // en haut de chaque page imprimée.
            . '<definedNames><definedName name="_xlnm.Print_Titles" localSheetId="0">'
            . 'Préinscrits!$' . $l_entete_tableau . ':$' . $l_entete_tableau
            . '</definedName></definedNames>'
            . '</workbook>',

        'xl/_rels/workbook.xml.rels' =>
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>',

        'xl/styles.xml'            => $styles,
        'xl/worksheets/sheet1.xml' => $sheet,
    );

    if ( $logo ) {
        $fichiers['xl/worksheets/_rels/sheet1.xml.rels'] =
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>'
            . '</Relationships>';

        // Armoiries ancrées sur la colonne D, en regard des quatre lignes d'en-tête.
        $fichiers['xl/drawings/drawing1.xml'] =
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing"'
            . ' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
            . '<xdr:oneCellAnchor>'
            . '<xdr:from><xdr:col>3</xdr:col><xdr:colOff>190500</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>19050</xdr:rowOff></xdr:from>'
            . '<xdr:ext cx="792000" cy="756000"/>'
            . '<xdr:pic><xdr:nvPicPr><xdr:cNvPr id="2" name="Armoiries UEB" descr="Logo de l\'Université d\'Ébolowa"/>'
            . '<xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr>'
            . '<xdr:blipFill><a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="rId1"/>'
            . '<a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
            . '<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="792000" cy="756000"/></a:xfrm>'
            . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr>'
            . '</xdr:pic><xdr:clientData/></xdr:oneCellAnchor></xdr:wsDr>';

        $fichiers['xl/drawings/_rels/drawing1.xml.rels'] =
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/logo-ueb.png"/>'
            . '</Relationships>';

        $fichiers['xl/media/logo-ueb.png'] = $logo;
    }

    ueb_export_envoyer_zip(
        $fichiers,
        ueb_export_nom_fichier( 'xlsx' ),
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
}

/**
 * Table des styles du classeur. L'ordre des <xf> définit les index utilisés
 * par les cellules (attribut s="…") :
 *   0 normal · 1 république · 2 devise · 3 ministère · 4 université
 *   5 titre · 6 sous-titre anglais · 7 année · 8 texte à gauche
 *   9 texte à droite · 10 en-tête de tableau · 11 cellule gauche
 *   12 cellule centrée · 13 formule d'arrêt · 14 signataire
 *   15 ligne de signature · 16 pied de page · 17 filet double
 */
function ueb_export_xlsx_styles() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="11">'
        . '<font><sz val="10"/><name val="Times New Roman"/></font>'                                    // 0
        . '<font><b/><sz val="11"/><name val="Times New Roman"/></font>'                                 // 1
        . '<font><i/><sz val="10"/><name val="Times New Roman"/></font>'                                 // 2
        . '<font><sz val="9"/><name val="Times New Roman"/></font>'                                      // 3
        . '<font><b/><sz val="10.5"/><color rgb="FF166A3A"/><name val="Times New Roman"/></font>'        // 4
        . '<font><b/><u/><sz val="15"/><name val="Times New Roman"/></font>'                             // 5
        . '<font><b/><sz val="10"/><name val="Times New Roman"/></font>'                                 // 6
        . '<font><b/><sz val="9"/><name val="Times New Roman"/></font>'                                  // 7
        . '<font><sz val="8"/><color rgb="FF5A645E"/><name val="Times New Roman"/></font>'               // 8
        . '<font><i/><sz val="9.5"/><name val="Times New Roman"/></font>'                                // 9
        . '<font><sz val="9.5"/><name val="Times New Roman"/></font>'                                    // 10
        . '</fonts>'
        . '<fills count="3">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFEDF0EE"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="4">'
        . '<border><left/><right/><top/><bottom/><diagonal/></border>'
        . '<border><left style="thin"><color rgb="FF969E98"/></left><right style="thin"><color rgb="FF969E98"/></right>'
        . '<top style="thin"><color rgb="FF969E98"/></top><bottom style="thin"><color rgb="FF969E98"/></bottom><diagonal/></border>'
        . '<border><left/><right/><top/><bottom style="double"><color rgb="FF1A1A1A"/></bottom><diagonal/></border>'
        . '<border><left/><right/><top style="thin"><color rgb="FF1A1A1A"/></top><bottom/><diagonal/></border>'
        . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="18">'
        . '<xf xfId="0" fontId="0" fillId="0" borderId="0"/>'                                                                                     // 0
        . '<xf xfId="0" fontId="1" fillId="0" borderId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' // 1
        . '<xf xfId="0" fontId="2" fillId="0" borderId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' // 2
        . '<xf xfId="0" fontId="3" fillId="0" borderId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>' // 3
        . '<xf xfId="0" fontId="4" fillId="0" borderId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' // 4
        . '<xf xfId="0" fontId="5" fillId="0" borderId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' // 5
        . '<xf xfId="0" fontId="9" fillId="0" borderId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center"/></xf>'                  // 6
        . '<xf xfId="0" fontId="7" fillId="0" borderId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center"/></xf>'                  // 7
        . '<xf xfId="0" fontId="10" fillId="0" borderId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>' // 8
        . '<xf xfId="0" fontId="10" fillId="0" borderId="0" applyFont="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' // 9
        . '<xf xfId="0" fontId="6" fillId="2" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>' // 10
        . '<xf xfId="0" fontId="0" fillId="0" borderId="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'   // 11
        . '<xf xfId="0" fontId="0" fillId="0" borderId="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' // 12
        . '<xf xfId="0" fontId="9" fillId="0" borderId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>'      // 13
        . '<xf xfId="0" fontId="6" fillId="0" borderId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center"/></xf>'                  // 14
        . '<xf xfId="0" fontId="8" fillId="0" borderId="3" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="top"/></xf>'   // 15
        . '<xf xfId="0" fontId="8" fillId="0" borderId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left"/></xf>'                    // 16
        . '<xf xfId="0" fontId="0" fillId="0" borderId="2" applyBorder="1"/>'                                                                       // 17
        . '</cellXfs>'
        . '</styleSheet>';
}

/* ============================================================
   WORD — .docx (WordprocessingML)
   ============================================================ */

/** Convertit des millimètres en twips (1/1440 de pouce), unité de Word. */
function ueb_export_mm_twips( $mm ) {
    return (int) round( $mm * 56.6929 );
}

/** Paragraphe Word. */
function ueb_export_docx_p( $texte, $options = array() ) {
    $o = wp_parse_args( $options, array(
        'align'   => 'left',
        'gras'    => false,
        'italique' => false,
        'souligne' => false,
        'taille'  => 19,   // demi-points : 19 = 9,5 pt
        'couleur' => '1A1A1A',
        'avant'   => 0,
        'apres'   => 0,
        'bordure_bas' => '',
        'bordure_haut' => '',
    ) );

    $rpr = '<w:rPr>'
        . ( $o['gras'] ? '<w:b/>' : '' )
        . ( $o['italique'] ? '<w:i/>' : '' )
        . ( $o['souligne'] ? '<w:u w:val="single"/>' : '' )
        . '<w:color w:val="' . $o['couleur'] . '"/>'
        . '<w:sz w:val="' . $o['taille'] . '"/><w:szCs w:val="' . $o['taille'] . '"/>'
        . '</w:rPr>';

    $bordures = '';
    if ( $o['bordure_bas'] || $o['bordure_haut'] ) {
        $bordures = '<w:pBdr>'
            . ( $o['bordure_haut'] ? '<w:top w:val="' . $o['bordure_haut'] . '" w:sz="6" w:space="1" w:color="1A1A1A"/>' : '' )
            . ( $o['bordure_bas'] ? '<w:bottom w:val="' . $o['bordure_bas'] . '" w:sz="6" w:space="1" w:color="1A1A1A"/>' : '' )
            . '</w:pBdr>';
    }

    return '<w:p><w:pPr>'
        . '<w:spacing w:before="' . $o['avant'] . '" w:after="' . $o['apres'] . '" w:line="240" w:lineRule="auto"/>'
        . $bordures
        . '<w:jc w:val="' . $o['align'] . '"/>'
        . $rpr
        . '</w:pPr>'
        . ( '' === $texte ? '' : '<w:r>' . $rpr . '<w:t xml:space="preserve">' . ueb_export_xml( $texte ) . '</w:t></w:r>' )
        . '</w:p>';
}

/** Cellule de tableau Word. */
function ueb_export_docx_tc( $contenu, $largeur_mm, $options = array() ) {
    $o = wp_parse_args( $options, array(
        'bordures' => true,
        'fond'     => '',
        'valign'   => 'center',
    ) );

    $bordure = '<w:tcBorders>';
    foreach ( array( 'top', 'left', 'bottom', 'right' ) as $cote ) {
        $bordure .= $o['bordures']
            ? '<w:' . $cote . ' w:val="single" w:sz="4" w:space="0" w:color="969E98"/>'
            : '<w:' . $cote . ' w:val="nil"/>';
    }
    $bordure .= '</w:tcBorders>';

    return '<w:tc><w:tcPr>'
        . '<w:tcW w:w="' . ueb_export_mm_twips( $largeur_mm ) . '" w:type="dxa"/>'
        . $bordure
        . ( $o['fond'] ? '<w:shd w:val="clear" w:color="auto" w:fill="' . $o['fond'] . '"/>' : '' )
        . '<w:vAlign w:val="' . $o['valign'] . '"/>'
        . '<w:tcMar><w:top w:w="28" w:type="dxa"/><w:bottom w:w="28" w:type="dxa"/>'
        . '<w:left w:w="57" w:type="dxa"/><w:right w:w="57" w:type="dxa"/></w:tcMar>'
        . '</w:tcPr>' . $contenu . '</w:tc>';
}

/**
 * Document Word reprenant le modèle A. L'en-tête bilingue est un tableau
 * sans bordures (seul moyen fiable d'obtenir trois colonnes alignées dans
 * Word), la liste un tableau à filets dont la ligne de titre se répète
 * automatiquement en haut de chaque page.
 */
function ueb_export_rendre_docx( $rows, $meta ) {
    $colonnes = ueb_export_colonnes();
    $logo     = ueb_export_logo_binaire();
    $largeur  = 184; // mm utiles entre les marges

    /* ---- En-tête bilingue : tableau 3 colonnes sans bordures ---- */
    $col_cote = 74;
    $col_logo = 36;

    $cell_fr = ueb_export_docx_p( 'RÉPUBLIQUE DU CAMEROUN', array( 'align' => 'center', 'gras' => true, 'taille' => 19 ) )
        . ueb_export_docx_p( 'Paix – Travail – Patrie', array( 'align' => 'center', 'italique' => true, 'taille' => 17 ) )
        . ueb_export_docx_p( "MINISTÈRE DE L'ENSEIGNEMENT SUPÉRIEUR", array( 'align' => 'center', 'taille' => 15, 'avant' => 40 ) )
        . ueb_export_docx_p( "UNIVERSITÉ D'ÉBOLOWA", array( 'align' => 'center', 'gras' => true, 'taille' => 18, 'couleur' => '166A3A', 'avant' => 40 ) );

    $cell_en = ueb_export_docx_p( 'REPUBLIC OF CAMEROON', array( 'align' => 'center', 'gras' => true, 'taille' => 19 ) )
        . ueb_export_docx_p( 'Peace – Work – Fatherland', array( 'align' => 'center', 'italique' => true, 'taille' => 17 ) )
        . ueb_export_docx_p( 'MINISTRY OF HIGHER EDUCATION', array( 'align' => 'center', 'taille' => 15, 'avant' => 40 ) )
        . ueb_export_docx_p( 'THE UNIVERSITY OF EBOLOWA', array( 'align' => 'center', 'gras' => true, 'taille' => 18, 'couleur' => '166A3A', 'avant' => 40 ) );

    // Image ancrée dans son paragraphe : 24 mm de large (864 000 EMU).
    $cell_logo = $logo
        ? '<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:drawing>'
            . '<wp:inline distT="0" distB="0" distL="0" distR="0">'
            . '<wp:extent cx="864000" cy="825600"/><wp:effectExtent l="0" t="0" r="0" b="0"/>'
            . '<wp:docPr id="1" name="Armoiries" descr="Armoiries de l\'Université d\'Ébolowa"/>'
            . '<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
            . '<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<pic:nvPicPr><pic:cNvPr id="1" name="logo-ueb.png"/><pic:cNvPicPr/></pic:nvPicPr>'
            . '<pic:blipFill><a:blip r:embed="rId5"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="864000" cy="825600"/></a:xfrm>'
            . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
            . '</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>'
        : ueb_export_docx_p( '' );

    $sans_bord = array( 'bordures' => false, 'valign' => 'top' );

    $table_entete = '<w:tbl><w:tblPr><w:tblW w:w="' . ueb_export_mm_twips( $largeur ) . '" w:type="dxa"/>'
        . '<w:tblLayout w:type="fixed"/><w:tblCellMar><w:left w:w="0" w:type="dxa"/><w:right w:w="0" w:type="dxa"/></w:tblCellMar>'
        . '</w:tblPr><w:tblGrid>'
        . '<w:gridCol w:w="' . ueb_export_mm_twips( $col_cote ) . '"/>'
        . '<w:gridCol w:w="' . ueb_export_mm_twips( $col_logo ) . '"/>'
        . '<w:gridCol w:w="' . ueb_export_mm_twips( $col_cote ) . '"/>'
        . '</w:tblGrid><w:tr>'
        . ueb_export_docx_tc( $cell_fr, $col_cote, $sans_bord )
        . ueb_export_docx_tc( $cell_logo, $col_logo, $sans_bord )
        . ueb_export_docx_tc( $cell_en, $col_cote, $sans_bord )
        . '</w:tr></w:tbl>';

    /* ---- Références et contexte : tableaux 2 colonnes sans bordures ---- */
    $ligne_deux_colonnes = static function ( $gauche, $droite ) use ( $largeur, $sans_bord ) {
        $demi = $largeur / 2;
        return '<w:tbl><w:tblPr><w:tblW w:w="' . ueb_export_mm_twips( $largeur ) . '" w:type="dxa"/>'
            . '<w:tblLayout w:type="fixed"/><w:tblCellMar><w:left w:w="0" w:type="dxa"/><w:right w:w="0" w:type="dxa"/></w:tblCellMar>'
            . '</w:tblPr><w:tblGrid>'
            . '<w:gridCol w:w="' . ueb_export_mm_twips( $demi ) . '"/><w:gridCol w:w="' . ueb_export_mm_twips( $demi ) . '"/>'
            . '</w:tblGrid><w:tr>'
            . ueb_export_docx_tc( ueb_export_docx_p( $gauche, array( 'taille' => 17 ) ), $demi, $sans_bord )
            . ueb_export_docx_tc( ueb_export_docx_p( $droite, array( 'align' => 'right', 'taille' => 17 ) ), $demi, $sans_bord )
            . '</w:tr></w:tbl>';
    };

    /* ---- Tableau principal ---- */
    $grid = '';
    foreach ( $colonnes as $col ) {
        $grid .= '<w:gridCol w:w="' . ueb_export_mm_twips( $col['largeur'] ) . '"/>';
    }

    $ligne_titres = '<w:tr><w:trPr><w:tblHeader/></w:trPr>';
    foreach ( $colonnes as $col ) {
        $ligne_titres .= ueb_export_docx_tc(
            ueb_export_docx_p( $col['titre'], array( 'align' => 'center', 'gras' => true, 'taille' => 15 ) ),
            $col['largeur'],
            array( 'fond' => 'EDF0EE' )
        );
    }
    $ligne_titres .= '</w:tr>';

    $lignes_donnees = '';
    foreach ( $rows as $row ) {
        $lignes_donnees .= '<w:tr>';
        foreach ( $colonnes as $col ) {
            $lignes_donnees .= ueb_export_docx_tc(
                ueb_export_docx_p( $row[ $col['cle'] ], array(
                    'align'  => ( 'C' === $col['align'] ) ? 'center' : 'left',
                    'taille' => 16,
                ) ),
                $col['largeur']
            );
        }
        $lignes_donnees .= '</w:tr>';
    }

    if ( ! $rows ) {
        $lignes_donnees = '<w:tr>' . ueb_export_docx_tc(
            ueb_export_docx_p( 'Aucun dossier ne correspond à la sélection.', array( 'align' => 'center', 'italique' => true, 'taille' => 17 ) ),
            $largeur
        ) . '</w:tr>';
    }

    $table_liste = '<w:tbl><w:tblPr><w:tblW w:w="' . ueb_export_mm_twips( $largeur ) . '" w:type="dxa"/>'
        . '<w:tblLayout w:type="fixed"/></w:tblPr><w:tblGrid>' . $grid . '</w:tblGrid>'
        . $ligne_titres . $lignes_donnees . '</w:tbl>';

    /* ---- Bloc de signature : tableau aligné à droite ---- */
    $col_vide = $largeur - 68;
    $table_signature = '<w:tbl><w:tblPr><w:tblW w:w="' . ueb_export_mm_twips( $largeur ) . '" w:type="dxa"/>'
        . '<w:tblLayout w:type="fixed"/><w:tblCellMar><w:left w:w="0" w:type="dxa"/><w:right w:w="0" w:type="dxa"/></w:tblCellMar>'
        . '</w:tblPr><w:tblGrid>'
        . '<w:gridCol w:w="' . ueb_export_mm_twips( $col_vide ) . '"/><w:gridCol w:w="' . ueb_export_mm_twips( 68 ) . '"/>'
        . '</w:tblGrid><w:tr>'
        . ueb_export_docx_tc( ueb_export_docx_p( '' ), $col_vide, $sans_bord )
        . ueb_export_docx_tc(
            ueb_export_docx_p( $meta['signataire'], array( 'align' => 'center', 'gras' => true, 'taille' => 18, 'apres' => 720 ) )
            . ueb_export_docx_p( $meta['signature'], array( 'align' => 'center', 'taille' => 15, 'couleur' => '5A645E', 'bordure_haut' => 'single' ) ),
            68,
            $sans_bord
        )
        . '</w:tr></w:tbl>';

    /* ---- Corps du document ---- */
    $corps = $table_entete
        . ueb_export_docx_p( '', array( 'bordure_bas' => 'double', 'apres' => 120 ) )
        . $ligne_deux_colonnes( 'N/Réf. : ' . $meta['reference'], $meta['lieu_date'] )
        . ueb_export_docx_p( '', array( 'apres' => 120 ) )
        . ueb_export_docx_p( $meta['titre'], array( 'align' => 'center', 'gras' => true, 'souligne' => true, 'taille' => 26 ) )
        . ueb_export_docx_p( $meta['titre_en'], array( 'align' => 'center', 'italique' => true, 'taille' => 18, 'avant' => 60 ) )
        . ueb_export_docx_p( $meta['annee'], array( 'align' => 'center', 'gras' => true, 'taille' => 18, 'avant' => 60, 'apres' => 180 ) )
        . $ligne_deux_colonnes( $meta['situation'], $meta['perimetre'] )
        . ueb_export_docx_p( '', array( 'bordure_bas' => 'single', 'apres' => 160 ) )
        . $table_liste
        . ueb_export_docx_p( $meta['arrete'], array( 'italique' => true, 'taille' => 17, 'avant' => 240 ) )
        . ueb_export_docx_p( '' )
        . $table_signature
        . '<w:sectPr>'
        . '<w:footerReference w:type="default" r:id="rId6"/>'
        . '<w:pgSz w:w="11906" w:h="16838"/>'
        . '<w:pgMar w:top="' . ueb_export_mm_twips( 14 ) . '" w:right="' . ueb_export_mm_twips( 13 ) . '"'
        . ' w:bottom="' . ueb_export_mm_twips( 16 ) . '" w:left="' . ueb_export_mm_twips( 13 ) . '"'
        . ' w:header="567" w:footer="567" w:gutter="0"/>'
        . '</w:sectPr>';

    $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
        . ' xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"'
        . ' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
        . ' xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
        . '<w:body>' . $corps . '</w:body></w:document>';

    /* ---- Pied de page paginé (champs PAGE / NUMPAGES) ---- */
    $champ = static function ( $instruction ) {
        return '<w:r><w:fldChar w:fldCharType="begin"/></w:r>'
            . '<w:r><w:instrText xml:space="preserve"> ' . $instruction . ' </w:instrText></w:r>'
            . '<w:r><w:fldChar w:fldCharType="separate"/></w:r>'
            . '<w:r><w:t>1</w:t></w:r>'
            . '<w:r><w:fldChar w:fldCharType="end"/></w:r>';
    };

    $footer = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:p><w:pPr>'
        . '<w:pBdr><w:top w:val="single" w:sz="4" w:space="4" w:color="C8CECA"/></w:pBdr>'
        . '<w:tabs><w:tab w:val="right" w:pos="' . ueb_export_mm_twips( 184 ) . '"/></w:tabs>'
        . '<w:rPr><w:color w:val="5A645E"/><w:sz w:val="14"/></w:rPr>'
        . '</w:pPr>'
        . '<w:r><w:rPr><w:color w:val="5A645E"/><w:sz w:val="14"/></w:rPr>'
        . '<w:t xml:space="preserve">' . ueb_export_xml( $meta['pied'] ) . '</w:t></w:r>'
        . '<w:r><w:rPr><w:color w:val="5A645E"/><w:sz w:val="14"/></w:rPr><w:tab/><w:t xml:space="preserve">Page </w:t></w:r>'
        . $champ( 'PAGE' )
        . '<w:r><w:rPr><w:color w:val="5A645E"/><w:sz w:val="14"/></w:rPr><w:t xml:space="preserve"> / </w:t></w:r>'
        . $champ( 'NUMPAGES' )
        . '</w:p></w:ftr>';

    /* ---- Archive ---- */
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '<Relationship Id="rId6" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>'
        . ( $logo ? '<Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/logo-ueb.png"/>' : '' )
        . '</Relationships>';

    $fichiers = array(
        '[Content_Types].xml' =>
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Default Extension="png" ContentType="image/png"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            . '<Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '</Types>',

        '_rels/.rels' =>
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '</Relationships>',

        'docProps/core.xml'         => ueb_export_core_xml( $meta ),
        'word/document.xml'         => $document,
        'word/_rels/document.xml.rels' => $rels,
        'word/footer1.xml'          => $footer,

        // Police à empattements par défaut, comme le modèle A.
        'word/styles.xml' =>
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:docDefaults><w:rPrDefault><w:rPr>'
            . '<w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:cs="Times New Roman"/>'
            . '<w:sz w:val="19"/><w:szCs w:val="19"/><w:lang w:val="fr-FR"/>'
            . '</w:rPr></w:rPrDefault>'
            . '<w:pPrDefault><w:pPr><w:spacing w:after="0" w:line="240" w:lineRule="auto"/></w:pPr></w:pPrDefault>'
            . '</w:docDefaults>'
            . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
            . '</w:styles>',
    );

    if ( $logo ) {
        $fichiers['word/media/logo-ueb.png'] = $logo;
    }

    ueb_export_envoyer_zip(
        $fichiers,
        ueb_export_nom_fichier( 'docx' ),
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    );
}

/** Propriétés du document, communes au classeur et au document Word. */
function ueb_export_core_xml( $meta ) {
    $date = gmdate( 'Y-m-d\TH:i:s\Z' );

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/"'
        . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
        . '<dc:title>' . ueb_export_xml( $meta['titre'] . ' — ' . $meta['annee'] ) . '</dc:title>'
        . '<dc:creator>Université d\'Ébolowa</dc:creator>'
        . '<cp:lastModifiedBy>Université d\'Ébolowa</cp:lastModifiedBy>'
        . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $date . '</dcterms:created>'
        . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $date . '</dcterms:modified>'
        . '</cp:coreProperties>';
}
