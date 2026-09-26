<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Rendus Excel (.xlsx) et Word (.docx) de la liste des préinscrits. Le Word
 * suit le modèle « A · Officiel classique » du PDF (inc/export-functions.php) ;
 * le classeur Excel, lui, est un tableau de données sans en-tête
 * institutionnel (voir ueb_export_rendre_xlsx()).
 *
 * Les deux formats sont des archives ZIP de fichiers XML (OOXML) écrites
 * directement, sans PhpSpreadsheet ni PHPWord : le document produit est
 * simple — un en-tête, un tableau, une formule d'arrêt — et une librairie
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
 * Classeur de données : un tableau brut, sans le modèle A.
 *
 * Le PDF et le Word sont des documents de service (en-tête institutionnel,
 * formule d'arrêt). Le fichier Excel, lui, sert à trier, filtrer et
 * retravailler la liste : l'en-tête administratif, les cellules fusionnées
 * et le bloc de clôture y gênaient le tri et le filtre. Il ne contient donc
 * que le tableau.
 *
 *   - ligne 1 : intitulés des colonnes, figée et filtrable ;
 *   - un champ par colonne, avec son vrai type : le rang est un nombre, la
 *     date de dépôt une vraie date Excel, le reste du texte ;
 *   - largeurs calculées sur le contenu réel, pour qu'aucune valeur ne soit
 *     coupée ni ne déborde sur la colonne voisine ;
 *   - à l'impression : paysage, ajusté en largeur, intitulés répétés.
 */
function ueb_export_rendre_xlsx( $rows, $meta ) {
    $colonnes = ueb_export_colonnes();
    $derniere = chr( 64 + count( $colonnes ) ); // 8 colonnes : H
    $nb       = count( $rows );
    $l_fin    = 1 + $nb; // dernière ligne occupée (1 si aucune donnée)

    // Index des styles, cf. ueb_export_xlsx_styles().
    $s_entete = array( 'L' => 1, 'C' => 2 );
    $s_cell   = array( 'L' => 3, 'C' => 4 );
    $s_date   = 5;

    $texte = static function ( $ref, $valeur, $style ) {
        if ( '' === $valeur ) {
            return '<c r="' . $ref . '" s="' . $style . '"/>';
        }
        // Chaîne typée : une valeur saisie par un candidat qui commence par
        // « = » reste du texte, jamais une formule.
        return '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">'
            . ueb_export_xml( $valeur ) . '</t></is></c>';
    };

    /* ---- Intitulés ---- */
    $cells = '';
    foreach ( $colonnes as $i => $col ) {
        $cells .= $texte( chr( 65 + $i ) . '1', $col['titre'], $s_entete[ $col['align'] ] );
    }
    $lignes = array( '<row r="1" ht="24" customHeight="1">' . $cells . '</row>' );

    /* ---- Données ---- */
    $num = 2;
    foreach ( $rows as $row ) {
        $cells = '';
        foreach ( $colonnes as $i => $col ) {
            $ref = chr( 65 + $i ) . $num;

            if ( 'index' === $col['cle'] ) {
                $cells .= '<c r="' . $ref . '" s="' . $s_cell['C'] . '"><v>' . (int) $row['index'] . '</v></c>';
            } elseif ( 'date' === $col['cle'] && ! empty( $row['date_iso'] ) ) {
                $cells .= '<c r="' . $ref . '" s="' . $s_date . '"><v>' . ueb_export_xlsx_date( $row['date_iso'] ) . '</v></c>';
            } else {
                $cells .= $texte( $ref, (string) $row[ $col['cle'] ], $s_cell[ $col['align'] ] );
            }
        }
        $lignes[] = '<row r="' . $num . '">' . $cells . '</row>';
        $num++;
    }

    /* ---- Largeurs : ajustées au contenu ---- */
    // Excel mesure en largeurs du chiffre « 0 » ; majuscules et tirets des
    // numéros de dossier sont plus larges, d'où les 10 % de plus, puis le
    // retrait et une marge. On part de la plus longue valeur de la colonne,
    // intitulé compris, dans des bornes raisonnables.
    $xml_cols = '<cols>';
    foreach ( $colonnes as $i => $col ) {
        $max = mb_strlen( $col['titre'] ) + 3; // place de la flèche du filtre
        foreach ( $rows as $row ) {
            $max = max( $max, mb_strlen( (string) $row[ $col['cle'] ] ) );
        }
        $largeur = min( 70, max( 8, (int) ceil( $max * 1.1 ) + 3 ) );
        $xml_cols .= '<col min="' . ( $i + 1 ) . '" max="' . ( $i + 1 ) . '" width="' . $largeur . '" customWidth="1"/>';
    }
    $xml_cols .= '</cols>';

    /* ---- Feuille ---- */
    $plage = 'A1:' . $derniere . $l_fin;

    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
        . '<dimension ref="' . $plage . '"/>'
        . '<sheetViews><sheetView tabSelected="1" workbookViewId="0">'
        . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
        . '<selection pane="bottomLeft" activeCell="A2" sqref="A2"/>'
        . '</sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="18" customHeight="1"/>'
        . $xml_cols
        . '<sheetData>' . implode( '', $lignes ) . '</sheetData>'
        . ( $nb ? '<autoFilter ref="' . $plage . '"/>' : '' )
        . '<printOptions horizontalCentered="1"/>'
        . '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.6" header="0.3" footer="0.3"/>'
        // Ajusté en largeur seulement (fitToHeight="0") : la liste s'imprime
        // sur autant de pages qu'il faut, sans être écrasée sur une seule.
        . '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
        . '<headerFooter><oddFooter>&amp;R&amp;8Page &amp;P / &amp;N</oddFooter></headerFooter>'
        . '</worksheet>';

    /* ---- Archive ---- */
    $fichiers = array(
        '[Content_Types].xml' =>
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '</Types>',

        '_rels/.rels' =>
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '</Relationships>',

        // Titre, année et auteur restent dans les propriétés du fichier.
        'docProps/core.xml' => ueb_export_core_xml( $meta ),

        'xl/workbook.xml' =>
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Préinscrits" sheetId="1" r:id="rId1"/></sheets>'
            . '<definedNames>'
            // Plage du filtre, attendue par Excel pour un autoFilter.
            . ( $nb ? '<definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">Préinscrits!$A$1:$' . $derniere . '$' . $l_fin . '</definedName>' : '' )
            // Titres d'impression : la ligne des intitulés en haut de chaque page.
            . '<definedName name="_xlnm.Print_Titles" localSheetId="0">Préinscrits!$1:$1</definedName>'
            . '</definedNames>'
            . '</workbook>',

        'xl/_rels/workbook.xml.rels' =>
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>',

        'xl/styles.xml'            => ueb_export_xlsx_styles(),
        'xl/worksheets/sheet1.xml' => $sheet,
    );

    ueb_export_envoyer_zip(
        $fichiers,
        ueb_export_nom_fichier( 'xlsx' ),
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
}

/**
 * Numéro de série Excel d'une date AAAA-MM-JJ (jours depuis le 30/12/1899).
 * Seul le jour est gardé : un filtre par date regroupe alors les dépôts d'une
 * même journée, quelle que soit l'heure.
 */
function ueb_export_xlsx_date( $iso ) {
    $jour = DateTimeImmutable::createFromFormat( '!Y-m-d', $iso, new DateTimeZone( 'UTC' ) );
    return $jour ? intdiv( $jour->getTimestamp(), 86400 ) + 25569 : '';
}

/**
 * Table des styles du classeur. L'ordre des <xf> définit les index utilisés
 * par les cellules (attribut s="…") :
 *   0 normal · 1 intitulé aligné à gauche · 2 intitulé centré
 *   3 cellule texte à gauche · 4 cellule centrée · 5 date (jj/mm/aaaa)
 *
 * Intitulés en blanc sur le vert de l'université, grille gris clair : le
 * tableau se lit comme un tableau, sans rien d'autre autour.
 */
function ueb_export_xlsx_styles() {
    $bordure = '<border>'
        . '<left style="thin"><color rgb="FFD3DAD5"/></left>'
        . '<right style="thin"><color rgb="FFD3DAD5"/></right>'
        . '<top style="thin"><color rgb="FFD3DAD5"/></top>'
        . '<bottom style="thin"><color rgb="FFD3DAD5"/></bottom>'
        . '<diagonal/></border>';

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="1"><numFmt numFmtId="164" formatCode="dd/mm/yyyy"/></numFmts>'
        . '<fonts count="2">'
        . '<font><sz val="10"/><color rgb="FF1C2621"/><name val="Arial"/><family val="2"/></font>'          // 0
        . '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Arial"/><family val="2"/></font>'     // 1
        . '</fonts>'
        . '<fills count="3">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF1A4A2E"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="2">'
        . '<border><left/><right/><top/><bottom/><diagonal/></border>'
        . $bordure
        . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="6">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'                                                                                                   // 0
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" indent="1"/></xf>'   // 1
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'           // 2
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" indent="1"/></xf>'                            // 3
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'                                      // 4
        . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'            // 5
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
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
