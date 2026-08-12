<?php
/**
 * Fonctions specifiques a la landing page (page d'accueil).
 *
 * Donnees et helpers de rendu propres a la front-page. Charge depuis
 * functions.php via require. Modifier les contenus ici (et non dans le HTML)
 * facilite la maintenance.
 *
 * @package Preinscriptions_UEB
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * URL de démarrage de la préinscription.
 *
 * Recherche automatiquement la page utilisant le template
 * 'page-preinscription.php' et retourne son permalien. Si aucune page
 * n'utilise ce template (ex. avant sa création), retourne '#' en repli.
 * Filtrable via 'preinscriptions_inscription_url'.
 *
 * @return string
 */
function preinscriptions_inscription_url() {
    $url = '#';

    $pages = get_posts( array(
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'meta_key'       => '_wp_page_template',
        'meta_value'     => 'page-preinscription.php',
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ) );

    if ( ! empty( $pages ) ) {
        $url = get_permalink( $pages[0] );
    }

    return apply_filters( 'preinscriptions_inscription_url', $url );
}

/**
 * URL d'une image du theme (dossier assets/images).
 *
 * @param string $file Nom du fichier (ex. 'logo-ueb.webp').
 * @return string
 */
function preinscriptions_img( $file ) {
    return get_template_directory_uri() . '/assets/images/' . ltrim( $file, '/' );
}

/**
 * Chiffres cles de l'UEB.
 *
 * @return array[] { count, suffix, label }
 */
function preinscriptions_stats() {
    return array(
        array( 'count' => 9,    'suffix' => '',  'label' => 'Facultés & écoles' ),
        array( 'count' => 8000, 'suffix' => '+', 'label' => 'Étudiants' ),
        array( 'count' => 4,    'suffix' => '',  'label' => 'Campus' ),
        array( 'count' => 92,   'suffix' => '%', 'label' => 'Taux de réussite' ),
    );
}

/**
 * Facultes et ecoles (9), avec logo.
 *
 * @return array[] { abbr, name, logo }
 */
function preinscriptions_facultes() {
    return array(
        array( 'abbr' => 'FMSP',   'name' => 'Médecine & Pharmacie',       'logo' => 'logos/logo-fmsp.webp' ),
        array( 'abbr' => 'FSEG',   'name' => 'Économie & Gestion',         'logo' => 'logos/logo-fseg.webp' ),
        array( 'abbr' => 'FS',     'name' => 'Sciences',                   'logo' => 'logos/logo-fs.webp' ),
        array( 'abbr' => 'FALSH',  'name' => 'Arts, Lettres & SH',         'logo' => 'logos/logo-falsh.webp' ),
        array( 'abbr' => 'FSJP',   'name' => 'Sciences Juridiques',        'logo' => 'logos/logo-fsjp.webp' ),
        array( 'abbr' => 'ISABEE', 'name' => 'Agriculture & Énergie',      'logo' => 'logos/logo-isabee.webp' ),
        array( 'abbr' => 'ENSET',  'name' => 'Enseignement Technique',     'logo' => 'logos/logo-enset.webp' ),
        array( 'abbr' => 'ENSTM',  'name' => 'Sciences & Technologie',     'logo' => 'logos/logo-enstmo.webp' ),
        array( 'abbr' => 'ESTLC',  'name' => 'Transport & Logistique',     'logo' => 'logos/logo-estlc.webp' ),
    );
}

/**
 * Campus (4), avec photo et description.
 *
 * @return array[] { name, label, img, alt, desc }
 */
function preinscriptions_campus() {
    return array(
        array(
            'name'  => 'Ébolowa',
            'label' => 'Siège',
            'img'   => 'campus-ebolowa.webp',
            'alt'   => "Campus d'Ébolowa",
            'desc'  => 'Le campus siège, au cœur de la région du Sud. Administration centrale et principales facultés.',
        ),
        array(
            'name'  => 'Ambam',
            'label' => 'Campus',
            'img'   => 'campus-ambam.webp',
            'alt'   => "Campus d'Ambam",
            'desc'  => 'Un campus de proximité, tourné vers les filières professionnalisantes.',
        ),
        array(
            'name'  => 'Monatélé',
            'label' => 'Campus',
            'img'   => 'campus-monatele.webp',
            'alt'   => 'Campus de Monatélé',
            'desc'  => 'Un site à taille humaine pour un encadrement renforcé.',
        ),
        array(
            'name'  => 'Sangmélima',
            'label' => 'Campus',
            'img'   => 'campus-sangmelima.webp',
            'alt'   => 'Campus de Sangmélima',
            'desc'  => "Un campus moderne ouvert sur les métiers d'avenir.",
        ),
    );
}

/**
 * Les 4 etapes de la preinscription.
 *
 * @return array[] { title, desc }
 */
function preinscriptions_etapes() {
    return array(
        array( 'title' => 'Reçois ton numéro',  'desc' => 'Un numéro de dossier t\'est attribué automatiquement. Note-le pour reprendre ta préinscription à tout moment.' ),
        array( 'title' => 'Remplis le formulaire', 'desc' => 'Tes infos, ta filière et tes choix en quelques minutes.' ),
        array( 'title' => 'Télécharge ta fiche', 'desc' => 'Récupère ta fiche de préinscription et ton coupon récépissé en PDF.' ),
        array( 'title' => 'Dépose ton dossier',  'desc' => 'Paie les frais en agence, imprime tes documents et dépose-les au campus.' ),
    );
}

/**
 * Photos de la vie etudiante (mosaique). La premiere est mise en avant (grande).
 *
 * @return array[] { img, alt, big }
 */
function preinscriptions_vie() {
    return array(
        array( 'img' => 'vie-etudiante-3.webp', 'alt' => 'Rassemblement étudiant',     'big' => true ),
        array( 'img' => 'vie-etudiante-2.webp', 'alt' => "Cérémonie d'excellence",      'big' => false ),
        array( 'img' => 'vie-etudiante-4.webp', 'alt' => 'Étudiants de la FSJP',        'big' => false ),
        array( 'img' => 'vie-etudiante-5.webp', 'alt' => 'Étudiants en cours',          'big' => false ),
        array( 'img' => 'vie-etudiante-1.webp', 'alt' => 'Sortie pédagogique',          'big' => false ),
    );
}

/**
 * Temoignages d'etudiants (defilement automatique).
 *
 * @return array[] { initials, quote, name, info }
 */
function preinscriptions_temoignages() {
    return array(
        array( 'initials' => 'AM', 'quote' => "La préinscription en ligne m'a pris 15 minutes. Tout est clair et le suivi est rassurant.", 'name' => 'Aïcha M.',  'info' => 'FSEG · L1' ),
        array( 'initials' => 'JT', 'quote' => "Un vrai accompagnement, dès le dépôt du dossier jusqu'à la rentrée.",                       'name' => 'Junior T.', 'info' => 'ENSET · L2' ),
        array( 'initials' => 'CN', 'quote' => "Le campus est moderne et à taille humaine, je m'y sens bien.",                            'name' => 'Carine N.', 'info' => 'FMSP · L1' ),
        array( 'initials' => 'EB', 'quote' => 'Les enseignants sont disponibles et la vie associative est riche.',                        'name' => 'Éric B.',   'info' => 'ISABEE · L3' ),
    );
    
}

/**
 * Date/heure d'ouverture officielle des préinscriptions.
 * Un seul endroit à modifier si la date change.
 */
if ( ! defined( 'PREINSCRIPTIONS_DATE_OUVERTURE' ) ) {
    define( 'PREINSCRIPTIONS_DATE_OUVERTURE', '2026-09-01 00:00:00' );
}

/**
 * Est-ce que la date d'ouverture est déjà atteinte (heure du serveur) ?
 *
 * @return bool
 */
function preinscriptions_ouverture_atteinte() {
    return new DateTimeImmutable( 'now' ) >= new DateTimeImmutable( PREINSCRIPTIONS_DATE_OUVERTURE );
}

/**
 * URL de la page de compte à rebours (template page-compte-rebours.php).
 * Même logique que preinscriptions_inscription_url().
 *
 * @return string
 */
function preinscriptions_compte_rebours_url() {
    $url = '#';

    $pages = get_posts( array(
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'meta_key'       => '_wp_page_template',
        'meta_value'     => 'page-compte-rebours.php',
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ) );

    if ( ! empty( $pages ) ) {
        $url = get_permalink( $pages[0] );
    }

    return apply_filters( 'preinscriptions_compte_rebours_url', $url );
}

/**
 * URL "effective" à utiliser pour tous les boutons de préinscription du
 * site : pointe vers le compte à rebours tant que l'ouverture n'est pas
 * atteinte, puis bascule automatiquement vers le vrai formulaire. Aucune
 * intervention manuelle nécessaire le jour de l'ouverture.
 *
 * @return string
 */
function preinscriptions_bouton_url() {
    if ( preinscriptions_ouverture_atteinte() ) {
        return preinscriptions_inscription_url();
    }
    return preinscriptions_compte_rebours_url();
}

