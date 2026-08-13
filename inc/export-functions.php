<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Export de la liste des préinscrits — modèle « A · Officiel classique ».
 *
 * Un seul jeu de données (les dossiers correspondant aux filtres et à la
 * recherche actifs à l'écran) rendu dans trois formats : PDF (TCPDF), Excel
 * (.xlsx) et Word (.docx). Les deux formats Office sont écrits directement
 * en OOXML dans une archive ZIP — voir inc/export-office.php — pour ne
 * dépendre d'aucune librairie externe.
 *
 * Le modèle A reprend les codes d'un document de service :
 *   - en-tête administratif bilingue (français / armoiries / anglais),
 *   - N/Réf. à gauche, lieu et date à droite,
 *   - titre souligné + sous-titre anglais + année académique,
 *   - tableau à filets fins, police à empattements,
 *   - formule d'arrêt du nombre de candidats en toutes lettres,
 *   - bloc de signature et pied de page paginé.
 *
 * @package Preinscriptions_UEB
 */

/* ============================================================
   1. COLONNES DU DOCUMENT
   ============================================================ */

/**
 * Colonnes de la liste exportée, dans l'ordre du modèle A.
 *
 * Les largeurs sont exprimées en millimètres : c'est l'unité du PDF, et les
 * deux formats Office les convertissent (caractères pour Excel, twips pour
 * Word) pour que les trois documents aient les mêmes proportions.
 *
 * @return array<int,array{cle:string,titre:string,largeur:float,align:string}>
 */
function ueb_export_colonnes() {
    return array(
        // La colonne de rang est plus large que sur la maquette (qui n'en
        // montrait que douze) : elle doit accueillir un rang à quatre
        // chiffres sans tronquer.
        array( 'cle' => 'index',          'titre' => 'N°',                  'largeur' => 11, 'align' => 'C' ),
        array( 'cle' => 'numero_dossier', 'titre' => 'N° de dossier',       'largeur' => 31, 'align' => 'L' ),
        array( 'cle' => 'nom',            'titre' => 'Nom',                 'largeur' => 26, 'align' => 'L' ),
        array( 'cle' => 'prenom',         'titre' => 'Prénom(s)',           'largeur' => 27, 'align' => 'L' ),
        array( 'cle' => 'sexe',           'titre' => 'Sexe',                'largeur' => 9,  'align' => 'C' ),
        array( 'cle' => 'faculte',        'titre' => 'Faculté',             'largeur' => 16, 'align' => 'C' ),
        array( 'cle' => 'filiere',        'titre' => 'Filière (1er choix)', 'largeur' => 44, 'align' => 'L' ),
        array( 'cle' => 'date',           'titre' => 'Déposé le',           'largeur' => 20, 'align' => 'C' ),
    );
}

/* ============================================================
   2. DONNÉES
   ============================================================ */

/**
 * Toutes les lignes correspondant aux filtres, sans pagination : l'export
 * porte par définition sur l'intégralité du résultat filtré.
 *
 * Requête propre à l'export (et non ueb_admin_get_dossiers_filtres) parce
 * que le modèle A affiche le *code* de la faculté — FSEG, ENSET — là où le
 * tableau à l'écran affiche son nom complet.
 *
 * @param array  $filters   Filtres déjà sanitizés.
 * @param string $recherche Recherche texte libre.
 * @param string $orderby   Clé de tri (liste blanche ueb_admin_colonnes_triables()).
 * @param string $order     ASC ou DESC.
 * @return array<int,array<string,string>> Lignes prêtes à écrire.
 */
function ueb_export_get_rows( $filters, $recherche = '', $orderby = 'date_creation', $order = 'DESC' ) {
    global $wpdb;

    $clause = ueb_admin_build_where( $filters );
    $where  = $clause['where'];
    $params = $clause['params'];

    $recherche = trim( (string) $recherche );
    if ( '' !== $recherche ) {
        $like     = '%' . $wpdb->esc_like( $recherche ) . '%';
        $where   .= ' AND (p.nom LIKE %s OR p.prenom LIKE %s OR p.numero_dossier LIKE %s)';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $colonnes = ueb_admin_colonnes_triables();
    $col_sql  = isset( $colonnes[ $orderby ] ) ? $colonnes[ $orderby ] : $colonnes['date_creation'];
    $sens_sql = 'ASC' === strtoupper( (string) $order ) ? 'ASC' : 'DESC';

    $sql = "SELECT p.numero_dossier, p.nom, p.prenom, p.sexe, p.date_creation,
                   f.code AS faculte_code, f.nom_fr AS faculte_nom,
                   fi1.libelle AS filiere1_libelle
            FROM ueb_preinscriptions p
            LEFT JOIN ueb_facultes f   ON f.id   = p.faculte_id
            LEFT JOIN ueb_filieres fi1 ON fi1.id = p.filiere_1_id
            WHERE {$where}
            ORDER BY {$col_sql} {$sens_sql}, p.id DESC";

    if ( $params ) {
        $sql = $wpdb->prepare( $sql, $params );
    }

    $rows    = $wpdb->get_results( $sql );
    $sortie  = array();
    $rang    = 0;

    foreach ( (array) $rows as $row ) {
        $rang++;
        $sortie[] = array(
            'index'          => (string) $rang,
            'numero_dossier' => (string) $row->numero_dossier,
            'nom'            => (string) $row->nom,
            'prenom'         => (string) $row->prenom,
            'sexe'           => (string) $row->sexe,
            // Le code tient dans la colonne étroite du modèle ; s'il manque
            // en base, le nom complet vaut mieux qu'une case vide.
            'faculte'        => (string) ( $row->faculte_code ?: $row->faculte_nom ),
            'filiere'        => (string) $row->filiere1_libelle,
            'date'           => $row->date_creation ? date_i18n( 'd/m/Y', strtotime( $row->date_creation ) ) : '',
        );
    }

    return $sortie;
}

/**
 * Libellés lisibles des filtres actifs, pour la ligne de contexte du
 * document (« Statut : … »). Les filtres sont stockés en IDs : on va
 * chercher le libellé dans la table de référence correspondante.
 *
 * @param array $filters
 * @return array<int,string> Ex. ["Faculté : FSEG", "Sexe : Féminin"]
 */
function ueb_export_filtres_lisibles( $filters ) {
    // clé de filtre => [libellé affiché, table de référence, colonne du libellé]
    $refs = array(
        'faculte'                    => array( 'Faculté',                    'ueb_facultes',                         'nom_fr' ),
        'diplome_admission'          => array( "Diplôme d'admission",        'ueb_diplomes_admission',               'libelle' ),
        'specialite_diplome'         => array( 'Série / Spécialité',         'ueb_specialites_diplome',              'libelle' ),
        'niveau_lmd'                 => array( 'Niveau LMD',                 'ueb_niveaux_lmd',                      'libelle' ),
        'mention'                    => array( 'Mention',                    'ueb_mentions',                         'libelle' ),
        'statut_etudiant'            => array( 'Statut étudiant',            'ueb_statuts_etudiants',                'libelle' ),
        'nationalite'                => array( 'Nationalité',                'ueb_nationalites',                     'nom' ),
        'premiere_langue'            => array( 'Première langue',            'ueb_langues',                          'libelle' ),
        'situation_matrimoniale'     => array( 'Situation matrimoniale',     'ueb_situations_matrimoniales',         'libelle' ),
        'statut_socio_professionnel' => array( 'Statut socio-professionnel', 'ueb_statuts_socio_professionnels',     'libelle' ),
        'region_origine'             => array( "Région d'origine",           'ueb_regions',                          'nom' ),
        'departement_origine'        => array( "Département d'origine",      'ueb_departements',                     'nom' ),
        'commune_origine'            => array( "Commune d'origine",          'ueb_communes',                         'nom' ),
        'sport_prefere'              => array( 'Sport préféré',              'ueb_sports',                           'libelle' ),
        'art_pratique'               => array( 'Art pratiqué',               'ueb_arts',                             'libelle' ),
        'filiere'                    => array( 'Filière',                    'ueb_filieres',                         'libelle' ),
    );

    // Filtres à valeurs fixes (pas de table de référence).
    $enums = array(
        'type_formation' => array( 'Type de formation', array( 'classique' => 'Classique', 'pro' => 'Licence Pro (LP)' ) ),
        'sexe'           => array( 'Sexe',              array( 'M' => 'Masculin', 'F' => 'Féminin' ) ),
        'handicap'       => array( 'Situation de handicap', array( 'oui' => 'Oui', 'non' => 'Non' ) ),
    );

    $actifs = array();

    foreach ( $refs as $cle => $def ) {
        if ( empty( $filters[ $cle ] ) ) {
            continue;
        }
        list( $label, $table, $colonne ) = $def;
        $valeur = ueb_pdf_lookup( $table, absint( $filters[ $cle ] ), $colonne );
        if ( $valeur ) {
            $actifs[] = $label . ' : ' . $valeur;
        }
    }

    foreach ( $enums as $cle => $def ) {
        if ( empty( $filters[ $cle ] ) ) {
            continue;
        }
        list( $label, $map ) = $def;
        if ( isset( $map[ $filters[ $cle ] ] ) ) {
            $actifs[] = $label . ' : ' . $map[ $filters[ $cle ] ];
        }
    }

    return $actifs;
}

/**
 * Numéro de référence du courrier (N/Réf.). Chaque export tire un numéro
 * d'ordre, remis à 1 au changement d'année : c'est le fonctionnement d'un
 * registre de correspondance, et cela rend deux exports distinguables.
 *
 * @return string Ex. « UEB/R/DAAC/2026/L-047 »
 */
function ueb_export_reference() {
    $annee   = (int) date_i18n( 'Y' );
    $registre = get_option( 'ueb_export_registre', array( 'annee' => 0, 'numero' => 0 ) );

    if ( ! is_array( $registre ) || (int) ( $registre['annee'] ?? 0 ) !== $annee ) {
        $registre = array( 'annee' => $annee, 'numero' => 0 );
    }

    $registre['numero'] = (int) $registre['numero'] + 1;
    update_option( 'ueb_export_registre', $registre, false );

    return sprintf( 'UEB/R/DAAC/%d/L-%03d', $annee, $registre['numero'] );
}

/**
 * Écrit un entier en toutes lettres, en français. Utilisé par la formule
 * d'arrêt (« Arrêtée la présente liste à mille deux cent quarante-huit
 * (1 248) candidats »), qui est ce qui donne au document sa valeur d'acte.
 *
 * @param int $n Entier positif.
 * @return string
 */
function ueb_export_nombre_en_lettres( $n ) {
    $n = (int) $n;

    if ( $n < 0 )  return 'moins ' . ueb_export_nombre_en_lettres( -$n );
    if ( 0 === $n ) return 'zéro';

    $unites = array(
        0 => '', 1 => 'un', 2 => 'deux', 3 => 'trois', 4 => 'quatre', 5 => 'cinq',
        6 => 'six', 7 => 'sept', 8 => 'huit', 9 => 'neuf', 10 => 'dix',
        11 => 'onze', 12 => 'douze', 13 => 'treize', 14 => 'quatorze', 15 => 'quinze',
        16 => 'seize', 17 => 'dix-sept', 18 => 'dix-huit', 19 => 'dix-neuf',
    );
    $dizaines = array(
        2 => 'vingt', 3 => 'trente', 4 => 'quarante', 5 => 'cinquante',
        6 => 'soixante', 7 => 'soixante', 8 => 'quatre-vingt', 9 => 'quatre-vingt',
    );

    /**
     * 0–99. Les paliers 70 et 90 se comptent en soixante-dix / quatre-vingt-dix.
     *
     * $accord vaut faux quand le groupe est suivi de « mille » : on écrit
     * « quatre-vingt mille » sans s, alors qu'on écrit « quatre-vingts
     * millions » — « mille » est un adjectif numéral invariable, « million »
     * un nom qui appelle l'accord.
     */
    $sous_cent = static function ( $x, $accord = true ) use ( $unites, $dizaines ) {
        if ( $x < 20 ) {
            return $unites[ $x ];
        }

        $d = intdiv( $x, 10 );
        $u = $x % 10;

        if ( 7 === $d || 9 === $d ) {
            $base  = $dizaines[ $d ];
            $reste = 10 + $u;
            // « soixante et onze », mais « quatre-vingt-onze ».
            $liant = ( 7 === $d && 11 === $reste ) ? ' et ' : '-';
            return $base . $liant . $unites[ $reste ];
        }

        $mot = $dizaines[ $d ];

        // « quatre-vingts » prend un s quand il termine le nombre.
        if ( 8 === $d && 0 === $u ) {
            return $accord ? $mot . 's' : $mot;
        }
        if ( 0 === $u ) {
            return $mot;
        }
        // « vingt et un », « trente et un »… jusqu'à soixante et un.
        if ( 1 === $u && $d <= 6 ) {
            return $mot . ' et un';
        }

        return $mot . '-' . $unites[ $u ];
    };

    // 0–999 : « cent » s'accorde au pluriel s'il termine le nombre.
    $sous_mille = static function ( $x, $accord = true ) use ( $sous_cent ) {
        if ( $x < 100 ) {
            return $sous_cent( $x, $accord );
        }

        $c     = intdiv( $x, 100 );
        $reste = $x % 100;
        $mot   = ( 1 === $c ? 'cent' : $sous_cent( $c ) . ' cent' );

        if ( 0 === $reste ) {
            if ( 1 === $c ) {
                return 'cent';
            }
            return $accord ? $mot . 's' : $mot;
        }

        return $mot . ' ' . $sous_cent( $reste, $accord );
    };

    $parts   = array();
    $milliards = intdiv( $n, 1000000000 );
    $millions  = intdiv( $n % 1000000000, 1000000 );
    $milliers  = intdiv( $n % 1000000, 1000 );
    $unite     = $n % 1000;

    if ( $milliards ) {
        $parts[] = $sous_mille( $milliards ) . ( $milliards > 1 ? ' milliards' : ' milliard' );
    }
    if ( $millions ) {
        $parts[] = $sous_mille( $millions ) . ( $millions > 1 ? ' millions' : ' million' );
    }
    if ( $milliers ) {
        // « mille » est invariable et ne se dit jamais « un mille » ; il
        // bloque aussi l'accord du groupe qui le précède (quatre-vingt mille).
        $parts[] = ( 1 === $milliers ? 'mille' : $sous_mille( $milliers, false ) . ' mille' );
    }
    if ( $unite ) {
        $parts[] = $sous_mille( $unite );
    }

    return implode( ' ', $parts );
}

/** Nombre formaté avec espace insécable fine comme séparateur de milliers. */
function ueb_export_nombre_chiffres( $n ) {
    return number_format( (int) $n, 0, ',', ' ' );
}

/**
 * Tous les éléments d'en-tête et de pied communs aux trois formats.
 *
 * @param array  $filters
 * @param string $recherche
 * @param int    $nb_lignes     Nombre de dossiers exportés.
 * @param int    $total_global  Nombre total de dossiers en base, filtres ignorés.
 * @return array<string,string>
 */
function ueb_export_meta( $filters, $recherche, $nb_lignes, $total_global ) {
    $filtres  = ueb_export_filtres_lisibles( $filters );
    $recherche = trim( (string) $recherche );

    if ( '' !== $recherche ) {
        $filtres[] = 'Recherche : « ' . $recherche . ' »';
    }

    $arrete = sprintf(
        'Arrêtée la présente liste à %s (%s) candidat%s',
        ueb_export_nombre_en_lettres( $nb_lignes ),
        ueb_export_nombre_chiffres( $nb_lignes ),
        $nb_lignes > 1 ? 's' : ''
    );

    // La mention du total n'a de sens que si la liste est un extrait.
    if ( $nb_lignes < $total_global ) {
        $arrete .= sprintf(
            ', sur un total de %s (%s) dossiers enregistrés.',
            ueb_export_nombre_en_lettres( $total_global ),
            ueb_export_nombre_chiffres( $total_global )
        );
    } else {
        $arrete .= '.';
    }

    return array(
        'reference'   => ueb_export_reference(),
        'lieu_date'   => 'Ébolowa, le ' . date_i18n( 'j F Y' ),
        'titre'       => 'LISTE DES CANDIDATS PRÉINSCRITS',
        'titre_en'    => 'List of pre-registered applicants',
        'annee'       => 'Année académique ' . str_replace( '-', ' – ', ueb_get_annee_academique() ),
        'situation'   => 'Situation arrêtée au ' . date_i18n( 'j F Y' ) . ' à ' . date_i18n( 'H' ) . ' h ' . date_i18n( 'i' ),
        'perimetre'   => $filtres ? 'Sélection : ' . implode( ' · ', $filtres ) : 'Statut : dossiers soumis — toutes facultés',
        'arrete'      => $arrete,
        'signataire'  => 'Le Chef de Service de la Scolarité',
        'signature'   => 'Nom, signature et cachet',
        'pied'        => "Université d'Ébolowa — B.P. 118, Ébolowa, Cameroun",
        'nb_lignes'   => (int) $nb_lignes,
    );
}

/** Nom de fichier horodaté, commun aux trois formats. */
function ueb_export_nom_fichier( $extension ) {
    return 'liste-preinscrits-ueb-' . date_i18n( 'Y-m-d-Hi' ) . '.' . $extension;
}

/* ============================================================
   3. POINT D'ENTRÉE AJAX
   ============================================================ */

/**
 * Export de la liste dans le format demandé (pdf, excel, word).
 *
 * Déclenché par une navigation GET et non par fetch() : c'est le navigateur
 * qui doit recevoir les en-têtes de téléchargement.
 */
function ueb_admin_ajax_export() {
    ueb_admin_ajax_check_access();

    $format = isset( $_REQUEST['format'] ) ? sanitize_key( wp_unslash( $_REQUEST['format'] ) ) : 'pdf';
    if ( ! in_array( $format, array( 'pdf', 'excel', 'word' ), true ) ) {
        $format = 'pdf';
    }

    $filters   = ueb_admin_ajax_extract_filters();
    $recherche = isset( $_REQUEST['recherche'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['recherche'] ) ) : '';
    $orderby   = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'date_creation';
    $order     = isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'desc';

    $rows = ueb_export_get_rows( $filters, $recherche, $orderby, $order );

    global $wpdb;
    $total_global = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ueb_preinscriptions' );

    $meta = ueb_export_meta( $filters, $recherche, count( $rows ), $total_global );

    // Un warning PHP émis avant les en-têtes corromprait le fichier binaire.
    if ( ob_get_level() ) {
        @ob_end_clean();
    }
    nocache_headers();

    switch ( $format ) {
        case 'excel':
            ueb_export_rendre_xlsx( $rows, $meta );
            break;
        case 'word':
            ueb_export_rendre_docx( $rows, $meta );
            break;
        default:
            ueb_export_rendre_pdf( $rows, $meta );
    }

    exit;
}
add_action( 'wp_ajax_ueb_admin_export', 'ueb_admin_ajax_export' );

/* ============================================================
   4. RENDU PDF — modèle A
   ============================================================ */

/** Géométrie de la page A4 du modèle A, en millimètres. */
function ueb_export_pdf_geometrie() {
    return array(
        'marge_g'   => 13,
        'marge_d'   => 13,
        'haut'      => 12,
        'largeur'   => 184,   // 210 − 13 × 2
        'bas_corps' => 274,   // dernière ligne de tableau possible
        'pied'      => 283,   // ligne du pied de page
        'h_ligne'   => 5.2,
        'h_entete'  => 5.8,
        'reserve'   => 44,    // place du bloc « arrêté + signature »
    );
}

/**
 * Tronque un texte pour qu'il tienne dans une largeur donnée, en ajoutant
 * une ellipse. Le modèle A garde une hauteur de ligne constante : mieux
 * vaut couper un libellé trop long que casser la trame du tableau.
 */
function ueb_export_pdf_tronquer( $pdf, $txt, $largeur_mm ) {
    $txt = (string) $txt;
    if ( '' === $txt || $pdf->GetStringWidth( $txt ) <= $largeur_mm ) {
        return $txt;
    }

    while ( mb_strlen( $txt ) > 1 && $pdf->GetStringWidth( $txt . '…' ) > $largeur_mm ) {
        $txt = mb_substr( $txt, 0, -1 );
    }

    return rtrim( $txt ) . '…';
}

/** Écrit une cellule de texte à position absolue. */
function ueb_export_pdf_txt( $pdf, $x, $y, $w, $txt, $size, $style = '', $align = 'L', $color = array( 26, 26, 26 ) ) {
    $pdf->SetFont( 'dejavuserif', $style, $size );
    $pdf->SetTextColor( $color[0], $color[1], $color[2] );
    $pdf->SetXY( $x, $y );
    $pdf->Cell( $w, 4.5, $txt, 0, 0, $align );
}

/**
 * En-tête administratif bilingue + filet double. Rendu sur la première page
 * uniquement : les suivantes reprennent un bandeau allégé.
 *
 * @return float Ordonnée sous le filet.
 */
function ueb_export_pdf_entete( $pdf, $g ) {
    $y    = $g['haut'];
    $vert = array( 22, 106, 58 );
    $noir = array( 26, 26, 26 );
    $logo = get_template_directory() . '/assets/images/logo-ueb.png';

    $col_w = 62;
    $x_fr  = $g['marge_g'];
    $x_en  = 210 - $g['marge_d'] - $col_w;

    ueb_export_pdf_txt( $pdf, $x_fr, $y,        $col_w, 'RÉPUBLIQUE DU CAMEROUN', 9.5, 'B', 'C', $noir );
    ueb_export_pdf_txt( $pdf, $x_fr, $y + 4.6,  $col_w, 'Paix – Travail – Patrie', 8.5, 'I', 'C', $noir );
    ueb_export_pdf_txt( $pdf, $x_fr, $y + 9.4,  $col_w, "MINISTÈRE DE L'ENSEIGNEMENT SUPÉRIEUR", 7.5, '', 'C', $noir );
    ueb_export_pdf_txt( $pdf, $x_fr, $y + 14,   $col_w, "UNIVERSITÉ D'ÉBOLOWA", 9, 'B', 'C', $vert );

    if ( file_exists( $logo ) ) {
        $pdf->Image( $logo, 105 - 12, $y - 1, 24, 0, 'PNG' );
    }

    ueb_export_pdf_txt( $pdf, $x_en, $y,        $col_w, 'REPUBLIC OF CAMEROON', 9.5, 'B', 'C', $noir );
    ueb_export_pdf_txt( $pdf, $x_en, $y + 4.6,  $col_w, 'Peace – Work – Fatherland', 8.5, 'I', 'C', $noir );
    ueb_export_pdf_txt( $pdf, $x_en, $y + 9.4,  $col_w, 'MINISTRY OF HIGHER EDUCATION', 7.5, '', 'C', $noir );
    ueb_export_pdf_txt( $pdf, $x_en, $y + 14,   $col_w, 'THE UNIVERSITY OF EBOLOWA', 9, 'B', 'C', $vert );

    // Filet double : trait épais puis trait fin, comme sur un en-tête de service.
    $y_filet = $y + 25;
    $pdf->SetDrawColor( 26, 26, 26 );
    $pdf->SetLineWidth( 0.32 );
    $pdf->Line( $g['marge_g'], $y_filet, 210 - $g['marge_d'], $y_filet );
    $pdf->SetLineWidth( 0.11 );
    $pdf->Line( $g['marge_g'], $y_filet + 1.1, 210 - $g['marge_d'], $y_filet + 1.1 );

    return $y_filet + 1.1;
}

/** Bandeau réduit des pages 2 et suivantes. */
function ueb_export_pdf_entete_suite( $pdf, $g, $meta ) {
    $y = $g['haut'];

    ueb_export_pdf_txt( $pdf, $g['marge_g'], $y, $g['largeur'] / 2, "UNIVERSITÉ D'ÉBOLOWA — " . $meta['titre'], 8.5, 'B', 'L' );
    ueb_export_pdf_txt( $pdf, $g['marge_g'] + $g['largeur'] / 2, $y, $g['largeur'] / 2, $meta['annee'], 8.5, '', 'R' );

    $pdf->SetDrawColor( 26, 26, 26 );
    $pdf->SetLineWidth( 0.32 );
    $pdf->Line( $g['marge_g'], $y + 5.4, 210 - $g['marge_d'], $y + 5.4 );

    return $y + 5.4;
}

/** Pied de page : coordonnées de l'université à gauche, pagination à droite. */
function ueb_export_pdf_pied( $pdf, $g, $meta, $page, $total_pages ) {
    $gris = array( 90, 100, 94 );

    $pdf->SetDrawColor( 200, 206, 202 );
    $pdf->SetLineWidth( 0.11 );
    $pdf->Line( $g['marge_g'], $g['pied'] - 2.5, 210 - $g['marge_d'], $g['pied'] - 2.5 );

    ueb_export_pdf_txt( $pdf, $g['marge_g'], $g['pied'], $g['largeur'] / 2, $meta['pied'], 7, '', 'L', $gris );
    ueb_export_pdf_txt( $pdf, $g['marge_g'] + $g['largeur'] / 2, $g['pied'], $g['largeur'] / 2,
        'Page ' . $page . ' / ' . $total_pages, 7, '', 'R', $gris );
}

/** Ligne d'en-tête du tableau, répétée en haut de chaque page. */
function ueb_export_pdf_entete_tableau( $pdf, $g, $y ) {
    $x = $g['marge_g'];

    $pdf->SetFont( 'dejavuserif', 'B', 7.6 );
    $pdf->SetTextColor( 26, 26, 26 );
    $pdf->SetFillColor( 237, 240, 238 );
    $pdf->SetDrawColor( 150, 158, 152 );
    $pdf->SetLineWidth( 0.14 );

    foreach ( ueb_export_colonnes() as $col ) {
        $pdf->SetXY( $x, $y );
        $pdf->Cell( $col['largeur'], $g['h_entete'], $col['titre'], 1, 0, 'C', true );
        $x += $col['largeur'];
    }

    return $y + $g['h_entete'];
}

/**
 * Répartit les lignes sur les pages, en réservant sur la dernière la place
 * du bloc « formule d'arrêt + signature ». Calculé avant le rendu pour que
 * le pied de page puisse annoncer « Page X / Y » dès la première page.
 *
 * @return array<int,int> Nombre de lignes par page.
 */
function ueb_export_pdf_pagination( $nb_lignes, $g, $y_debut_p1, $y_debut_pn ) {
    $cap = static function ( $y_debut, $reserve ) use ( $g ) {
        $hauteur = $g['bas_corps'] - $reserve - ( $y_debut + $g['h_entete'] );
        return max( 0, (int) floor( $hauteur / $g['h_ligne'] ) );
    };

    $cap_p1      = $cap( $y_debut_p1, 0 );
    $cap_pn      = $cap( $y_debut_pn, 0 );
    $cap_p1_fin  = $cap( $y_debut_p1, $g['reserve'] );
    $cap_pn_fin  = $cap( $y_debut_pn, $g['reserve'] );

    $pages    = array();
    $restant  = (int) $nb_lignes;
    $premiere = true;

    while ( true ) {
        $capacite     = $premiere ? $cap_p1 : $cap_pn;
        $capacite_fin = $premiere ? $cap_p1_fin : $cap_pn_fin;

        // Tout ce qui reste tient sur cette page avec le bloc de clôture.
        if ( $restant <= $capacite_fin ) {
            $pages[] = $restant;
            break;
        }

        $pages[]  = $capacite;
        $restant -= $capacite;
        $premiere = false;

        // Page remplie au ras : la clôture prend une page à elle seule.
        if ( 0 === $restant ) {
            $pages[] = 0;
            break;
        }
    }

    return $pages;
}

/** Bloc de clôture : formule d'arrêt puis emplacement de signature. */
function ueb_export_pdf_cloture( $pdf, $g, $meta, $y ) {
    $pdf->SetFont( 'dejavuserif', 'I', 8.5 );
    $pdf->SetTextColor( 26, 26, 26 );
    $pdf->SetXY( $g['marge_g'], $y + 4 );
    $pdf->MultiCell( $g['largeur'], 4.2, $meta['arrete'], 0, 'L' );

    $y_sign = $pdf->GetY() + 6;
    $x_sign = 210 - $g['marge_d'] - 68;

    ueb_export_pdf_txt( $pdf, $x_sign, $y_sign, 68, $meta['signataire'], 9, 'B', 'C' );

    $pdf->SetDrawColor( 26, 26, 26 );
    $pdf->SetLineWidth( 0.14 );
    $pdf->Line( $x_sign, $y_sign + 17, $x_sign + 68, $y_sign + 17 );

    ueb_export_pdf_txt( $pdf, $x_sign, $y_sign + 18, 68, $meta['signature'], 7.5, '', 'C', array( 90, 100, 94 ) );
}

/**
 * Construit et envoie le PDF de la liste (modèle A).
 *
 * @param array $rows Lignes issues de ueb_export_get_rows().
 * @param array $meta Éléments d'en-tête/pied issus de ueb_export_meta().
 */
function ueb_export_rendre_pdf( $rows, $meta ) {
    if ( ! class_exists( 'TCPDF' ) ) {
        require_once get_template_directory() . '/lib/tcpdf/tcpdf.php';
    }

    $g        = ueb_export_pdf_geometrie();
    $colonnes = ueb_export_colonnes();

    $pdf = new TCPDF( 'P', 'mm', 'A4', true, 'UTF-8', false );
    $pdf->SetCreator( 'UEB Préinscriptions' );
    $pdf->SetAuthor( "Université d'Ébolowa" );
    $pdf->SetTitle( $meta['titre'] . ' — ' . $meta['annee'] );
    $pdf->setPrintHeader( false );
    $pdf->setPrintFooter( false );
    $pdf->SetMargins( $g['marge_g'], $g['haut'], $g['marge_d'] );
    $pdf->SetAutoPageBreak( false, 0 ); // pagination gérée à la main
    $pdf->SetFont( 'dejavuserif', '', 8 );

    // Hauteurs d'en-tête : mesurées une fois pour paginer, puis réutilisées.
    $y_bloc_p1 = $g['haut'] + 26.1 // filet double
        + 4 + 4.5                  // ligne N/Réf. + lieu-date
        + 5 + 6.5 + 4.4 + 5        // titre, sous-titre anglais, année
        + 4 + 4.6;                 // ligne de contexte + filet
    $y_bloc_pn = $g['haut'] + 5.4 + 4;

    $pages = ueb_export_pdf_pagination( count( $rows ), $g, $y_bloc_p1, $y_bloc_pn );
    $total_pages = count( $pages );

    $index = 0;

    foreach ( $pages as $i => $nb_lignes_page ) {
        $numero_page = $i + 1;
        $pdf->AddPage();

        if ( 0 === $i ) {
            $y = ueb_export_pdf_entete( $pdf, $g ) + 4;

            ueb_export_pdf_txt( $pdf, $g['marge_g'], $y, $g['largeur'] / 2, 'N/Réf. : ' . $meta['reference'], 8.5, '', 'L' );
            ueb_export_pdf_txt( $pdf, $g['marge_g'] + $g['largeur'] / 2, $y, $g['largeur'] / 2, $meta['lieu_date'], 8.5, '', 'R' );
            $y += 4.5;

            // Titre souligné : le trait est tracé sous la largeur exacte du texte.
            $y += 5;
            $pdf->SetFont( 'dejavuserif', 'B', 13 );
            $largeur_titre = $pdf->GetStringWidth( $meta['titre'] ) + 4;
            ueb_export_pdf_txt( $pdf, $g['marge_g'], $y, $g['largeur'], $meta['titre'], 13, 'B', 'C' );
            $pdf->SetDrawColor( 26, 26, 26 );
            $pdf->SetLineWidth( 0.2 );
            $pdf->Line( 105 - $largeur_titre / 2, $y + 5.6, 105 + $largeur_titre / 2, $y + 5.6 );
            $y += 6.5;

            ueb_export_pdf_txt( $pdf, $g['marge_g'], $y, $g['largeur'], $meta['titre_en'], 9, 'I', 'C' );
            $y += 4.4;
            ueb_export_pdf_txt( $pdf, $g['marge_g'], $y, $g['largeur'], $meta['annee'], 9, 'B', 'C' );
            $y += 5;

            $y += 4;
            ueb_export_pdf_txt( $pdf, $g['marge_g'], $y, $g['largeur'] * 0.42, $meta['situation'], 8.5, '', 'L' );
            $pdf->SetFont( 'dejavuserif', '', 8.5 );
            $pdf->SetXY( $g['marge_g'] + $g['largeur'] * 0.42, $y );
            $pdf->Cell( $g['largeur'] * 0.58, 4.5,
                ueb_export_pdf_tronquer( $pdf, $meta['perimetre'], $g['largeur'] * 0.58 ), 0, 0, 'R' );

            $pdf->SetDrawColor( 200, 206, 202 );
            $pdf->SetLineWidth( 0.11 );
            $pdf->Line( $g['marge_g'], $y + 4.6, 210 - $g['marge_d'], $y + 4.6 );
            $y += 4.6;
        } else {
            $y = ueb_export_pdf_entete_suite( $pdf, $g, $meta ) + 4;
        }

        $y = ueb_export_pdf_entete_tableau( $pdf, $g, $y );

        // Corps du tableau.
        $pdf->SetDrawColor( 150, 158, 152 );
        $pdf->SetLineWidth( 0.14 );

        for ( $n = 0; $n < $nb_lignes_page; $n++ ) {
            $row = $rows[ $index ];
            $x   = $g['marge_g'];

            foreach ( $colonnes as $col ) {
                $pdf->SetFont( 'dejavuserif', '', 8 );
                $pdf->SetTextColor( 26, 26, 26 );
                $pdf->SetXY( $x, $y );
                // 2 mm retirés : la marge interne que TCPDF applique de part
                // et d'autre du texte dans une cellule.
                $pdf->Cell(
                    $col['largeur'],
                    $g['h_ligne'],
                    ueb_export_pdf_tronquer( $pdf, $row[ $col['cle'] ], $col['largeur'] - 2 ),
                    1,
                    0,
                    $col['align']
                );
                $x += $col['largeur'];
            }

            $y += $g['h_ligne'];
            $index++;
        }

        if ( 0 === $nb_lignes_page && 0 === count( $rows ) ) {
            ueb_export_pdf_txt( $pdf, $g['marge_g'], $y + 3, $g['largeur'],
                'Aucun dossier ne correspond à la sélection.', 9, 'I', 'C', array( 90, 100, 94 ) );
            $y += 8;
        }

        // Clôture sur la dernière page seulement.
        if ( $numero_page === $total_pages ) {
            ueb_export_pdf_cloture( $pdf, $g, $meta, $y );
        }

        ueb_export_pdf_pied( $pdf, $g, $meta, $numero_page, $total_pages );
    }

    $pdf->Output( ueb_export_nom_fichier( 'pdf' ), 'D' );
}
