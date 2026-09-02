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
 * Les 6 étapes de la préinscription, de la plateforme au guichet.
 *
 * Reprend la procédure officielle du Recteur (même séquence que
 * ueb_guide_etapes(), qui alimente la page Guide et son PDF), mais rédigée
 * pour la landing : on s'y adresse au candidat, et chaque étape dit ce
 * qu'il aura à faire ou à préparer, pas ce que le système exécute.
 *
 * Quatre étapes ne suffisaient pas : le paiement et le choix de la
 * formation, qui sont les deux points où les candidats se trompent le
 * plus, n'y figuraient pas.
 *
 * @return array[] { title, desc }
 */
function preinscriptions_etapes() {
    return array(
        array(
            'title' => 'Choisis ta formation',
            'desc'  => "Compare les offres de formation et leurs débouchés, puis retiens la faculté qui correspond à ton projet.",
        ),
        array(
            'title' => 'Remplis le formulaire',
            'desc'  => "Renseigne chaque champ tel qu'il figure sur tes pièces : acte de naissance, diplôme et relevés de notes.",
        ),
        array(
            'title' => 'Reçois ton numéro',
            'desc'  => "Un numéro de dossier unique t'est attribué à la validation. Note-le : il te suit jusqu'au dépôt.",
        ),
        array(
            'title' => 'Télécharge ta fiche',
            'desc'  => "Ta fiche de préinscription et ton coupon récépissé, en PDF, prêts à imprimer en deux exemplaires.",
        ),
        array(
            'title' => 'Règle tes droits',
            'desc'  => "11 000 FCFA à la CCA Bank, par Express Union ou par MTN Mobile Money. Conserve le reçu, il est exigé au dépôt.",
        ),
        array(
            'title' => 'Dépose ton dossier',
            'desc'  => "Présente-toi à ta faculté au jour du rendez-vous, avec tes pièces certifiées, tes photos et la preuve de paiement.",
        ),
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
 * Date/heure de clôture officielle des préinscriptions.
 * Un seul endroit à modifier si la date change.
 */
if ( ! defined( 'PREINSCRIPTIONS_DATE_CLOTURE' ) ) {
    define( 'PREINSCRIPTIONS_DATE_CLOTURE', '2026-10-31 23:59:59' );
}


/**
 * Interrupteur unique du mode maintenance. Tant qu'il est à true, tous les
 * boutons "Commencer / Continuer ma préinscription" du site (et l'accès
 * direct à page-preinscription.php) renvoient vers la page de maintenance
 * au lieu du formulaire — quelle que soit la date d'ouverture/clôture.
 *
 * Repasser à false pour rouvrir le formulaire au public.
 */
if ( ! defined( 'PREINSCRIPTIONS_MAINTENANCE_MODE' ) ) {
    define( 'PREINSCRIPTIONS_MAINTENANCE_MODE', false );
}

/**
 * Le mode maintenance est-il actif ? Passe par un filtre pour rester
 * ajustable sans toucher au code (ex. depuis un plugin ou du debug).
 *
 * @return bool
 */
function preinscriptions_maintenance_active() {
    return (bool) apply_filters( 'preinscriptions_maintenance_active', PREINSCRIPTIONS_MAINTENANCE_MODE );
}

/**
 * URL de la page de maintenance (template page-maintenance.php).
 * Même logique que preinscriptions_inscription_url().
 *
 * @return string
 */
function preinscriptions_maintenance_url() {
    $url = '#';

    $pages = get_posts( array(
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'meta_key'       => '_wp_page_template',
        'meta_value'     => 'page-maintenance.php',
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ) );

    if ( ! empty( $pages ) ) {
        $url = get_permalink( $pages[0] );
    }

    return apply_filters( 'preinscriptions_maintenance_url', $url );
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
 * Est-ce que la période de préinscription est terminée (heure du serveur) ?
 * Utilisée dans page-preinscription.php pour bloquer l'accès au formulaire
 * après la clôture et afficher un message d'information à la place.
 *
 * @return bool
 */
function preinscriptions_cloture_atteinte() {
    return new DateTimeImmutable( 'now' ) >= new DateTimeImmutable( PREINSCRIPTIONS_DATE_CLOTURE );
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
 * URL de la page « Guide de préinscription » (template
 * page-guide-preinscription.php). Même logique que
 * preinscriptions_inscription_url() : on résout par le template plutôt que
 * par un slug en dur, pour que la page reste renommable.
 *
 * Retourne '' (et non '#') si aucune page n'utilise le template : les
 * appelants s'en servent pour masquer le lien plutôt que d'afficher une
 * entrée de menu qui ne mène nulle part.
 *
 * @return string
 */
function preinscriptions_guide_url() {
    $url = '';

    $pages = get_posts( array(
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'meta_key'       => '_wp_page_template',
        'meta_value'     => 'page-guide-preinscription.php',
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ) );

    if ( ! empty( $pages ) ) {
        $url = get_permalink( $pages[0] );
    }

    return apply_filters( 'preinscriptions_guide_url', $url );
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
    if ( preinscriptions_maintenance_active() ) {
        return preinscriptions_maintenance_url();
    }
    if ( preinscriptions_ouverture_atteinte() ) {
        return preinscriptions_inscription_url();
    }
    return preinscriptions_compte_rebours_url();
}

/**
 * Ce visiteur a-t-il le droit d'ouvrir le formulaire avant l'ouverture ?
 *
 * Avant le 1er septembre, le formulaire n'est pas public : tout le monde est
 * renvoyé vers le compte à rebours. L'équipe, elle, doit continuer à tester le
 * parcours complet — elle passe simplement par sa session WordPress, sans
 * aucun lien ni paramètre d'URL à connaître.
 *
 * La capacité 'voir_preinscriptions' est celle qui ouvre déjà le tableau de
 * bord : les personnes qui l'ont sont exactement celles qui travaillent sur
 * l'outil. Se connecter à WordPress suffit donc à lever le blocage.
 *
 * @return bool
 */
function preinscriptions_acces_anticipe() {
    return is_user_logged_in() && current_user_can( 'voir_preinscriptions' );
}