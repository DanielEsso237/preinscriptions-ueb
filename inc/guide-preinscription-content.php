<?php
/**
 * Contenu du guide de préinscription — source unique de vérité.
 *
 * Le texte provient de la note de procédure du Recteur ; il est retranscrit
 * ici sous forme de données (et non de HTML) parce qu'il est rendu à DEUX
 * endroits qui n'ont pas la même mise en page :
 *   - la page web  (page-guide-preinscription.php)
 *   - le PDF       (inc/guide-pdf-functions.php)
 * Toute correction de fond se fait donc une seule fois, dans ce fichier.
 *
 * Les dates ne sont jamais écrites en dur : elles sont dérivées des
 * constantes PREINSCRIPTIONS_DATE_OUVERTURE / _CLOTURE, seule référence du
 * thème (compte à rebours, blocage du formulaire). Voir ueb_guide_dates().
 *
 * @package Preinscriptions_UEB
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Montant des droits de préinscription par défaut, en francs CFA.
 *
 * Les tarifs réels sont portés par ueb_tarifs, par établissement et par
 * cycle, et administrés depuis « Gestion des références » (cf. ueb_tarif()).
 * Cette constante n'est plus que le repli d'un niveau hors cycle connu.
 */
if ( ! defined( 'UEB_FRAIS_PREINSCRIPTION' ) ) {
    define( 'UEB_FRAIS_PREINSCRIPTION', 11000 );
}

/**
 * Numéro de compte bancaire officiel de l'Université (CCA Bank).
 */
if ( ! defined( 'UEB_COMPTE_CCA' ) ) {
    define( 'UEB_COMPTE_CCA', '10039-10012-00272772201-07' );
}

/**
 * URL du service d'obtention du certificat médical.
 */
if ( ! defined( 'UEB_URL_CERTIFICAT_MEDICAL' ) ) {
    define( 'UEB_URL_CERTIFICAT_MEDICAL', 'https://medicalcertificate.cms.unv-ebolowa.cm/UEb_MedicalCertificate' );
}

/**
 * URL publique de la plateforme de préinscription (adresse officielle
 * communiquée par le Rectorat, affichée à titre indicatif). La navigation
 * interne du site passe, elle, par preinscriptions_bouton_url().
 */
if ( ! defined( 'UEB_URL_PLATEFORME' ) ) {
    define( 'UEB_URL_PLATEFORME', 'https://preinscription.unv-ebolowa.cm/' );
}

/**
 * Formate une date en français sans dépendre de la locale installée sur
 * le serveur — même approche que page-compte-rebours.php, qui ne peut pas
 * se permettre d'afficher « 1 September » sur une page publique.
 *
 * @param DateTimeImmutable $date       Date à formater.
 * @param bool              $avec_annee Inclure l'année (faux pour « 1er au 30 septembre 2026 »).
 * @return string
 */
function ueb_guide_date_fr( DateTimeImmutable $date, $avec_annee = true ) {
    $mois = array(
        1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
    );

    $jour   = (int) $date->format( 'j' );
    $libelle = ( 1 === $jour ? '1er' : $jour ) . ' ' . $mois[ (int) $date->format( 'n' ) ];

    return $avec_annee ? $libelle . ' ' . $date->format( 'Y' ) : $libelle;
}

/**
 * Dates de la campagne, toutes dérivées des constantes du thème.
 *
 * La note du Recteur décrit une campagne qui s'ouvre le 1er du mois
 * d'ouverture et se referme à la fin du mois suivant : les visites médicales
 * des nouveaux étudiants ont lieu pendant le mois d'ouverture, celles des
 * anciens sur toute la période de la campagne (de l'ouverture à la clôture).
 * On reproduit ce calendrier relatif à partir de
 * PREINSCRIPTIONS_DATE_OUVERTURE / _CLOTURE : si la campagne est décalée
 * d'une année, aucune date de cette page n'est à reprendre à la main.
 *
 * @return array{ouverture:string,cloture:string,periode:string,visites_nouveaux:string,visites_anciens:string,annee_academique:string}
 */
function ueb_guide_dates() {
    $ouverture = new DateTimeImmutable( PREINSCRIPTIONS_DATE_OUVERTURE );
    $cloture   = new DateTimeImmutable( PREINSCRIPTIONS_DATE_CLOTURE );

    /* Visites médicales : le mois d'ouverture pour les nouveaux étudiants ;
       toute la période de la campagne (ouverture → clôture) pour les anciens. */
    $mois_nouveaux    = $ouverture;
    $fin_nouveaux     = $ouverture->modify( 'last day of this month' );

    /* Année académique : « 2026 – 2027 » pour une campagne ouverte en 2026. */
    $an               = (int) $ouverture->format( 'Y' );

    return array(
        'ouverture'        => ueb_guide_date_fr( $ouverture ),
        'cloture'          => ueb_guide_date_fr( $cloture ),
        'periode'          => sprintf(
            'du %s au %s',
            ueb_guide_date_fr( $ouverture, false ),
            ueb_guide_date_fr( $cloture )
        ),
        'visites_nouveaux' => sprintf(
            'du %s au %s',
            ueb_guide_date_fr( $mois_nouveaux, false ),
            ueb_guide_date_fr( $fin_nouveaux )
        ),
        'visites_anciens'  => sprintf(
            'du %s au %s',
            ueb_guide_date_fr( $ouverture, false ),
            ueb_guide_date_fr( $cloture )
        ),
        'annee_academique' => $an . ' – ' . ( $an + 1 ),
    );
}

/**
 * Établissements, avec ce qui leur est propre côté paiement.
 *
 * Par défaut tout se verse sur le compte de l'Université ; un établissement
 * peut avoir, pour ses masters, son propre compte (la FALSH), réglé depuis
 * « Gestion des références ».
 *
 * @return array[] { id, code, nom, compte, actif } — compte vide = compte de
 *                 l'Université.
 */
function ueb_etablissements_paiement() {
    static $etablissements = null;

    if ( null !== $etablissements ) {
        return $etablissements;
    }

    global $wpdb;

    // Erreurs masquées, comme dans ueb_tarif() : sur une base
    // pas encore migrée, les pages publiques s'en tiennent au compte de
    // l'Université.
    $masque = $wpdb->suppress_errors( true );
    $lignes = $wpdb->get_results( 'SELECT id, code, nom_fr, compte_bancaire, actif FROM ueb_facultes ORDER BY code ASC' );
    $wpdb->suppress_errors( $masque );

    $etablissements = array();
    foreach ( (array) $lignes as $ligne ) {
        $etablissements[] = array(
            'id'     => (int) $ligne->id,
            'code'   => (string) $ligne->code,
            'nom'    => (string) $ligne->nom_fr,
            'compte' => trim( (string) $ligne->compte_bancaire ),
            'actif'  => (bool) $ligne->actif,
        );
    }

    return $etablissements;
}

/**
 * Le niveau LMD est-il un niveau de master (M1, M2) ?
 *
 * Le compte propre à un établissement ne concerne que ses masters : c'est
 * le périmètre du communiqué de la FALSH. Licences et doctorat restent sur
 * le compte de l'Université.
 *
 * @param int $niveau_id Identifiant de ueb_niveaux_lmd.
 * @return bool
 */
function ueb_niveau_est_master( $niveau_id ) {
    return 'master' === ueb_cycle_tarif( $niveau_id );
}

/**
 * Compte CCA Bank sur lequel verse un candidat : celui de son
 * établissement s'il est en master et que l'établissement en a un, sinon
 * celui de l'Université.
 *
 * @param int $faculte_id Identifiant de ueb_facultes.
 * @param int $niveau_id  Identifiant de ueb_niveaux_lmd.
 * @return string
 */
function ueb_compte_cca_etablissement( $faculte_id, $niveau_id ) {
    if ( ueb_niveau_est_master( $niveau_id ) ) {
        foreach ( ueb_etablissements_paiement() as $etablissement ) {
            if ( $etablissement['id'] === (int) $faculte_id && '' !== $etablissement['compte'] ) {
                return $etablissement['compte'];
            }
        }
    }

    return UEB_COMPTE_CCA;
}

/**
 * Tarif appliqué à défaut de ligne dans ueb_tarifs : établissement créé
 * après coup, ligne supprimée, ou base pas encore migrée.
 *
 * @return array[] cycle => { droits, frais_medicaux }
 */
function ueb_tarifs_defaut() {
    return array(
        'licence'  => array( 'droits' => 11000, 'frais_medicaux' => 3000 ),
        'master'   => array( 'droits' => 11000, 'frais_medicaux' => 3000 ),
        'doctorat' => array( 'droits' => 20000, 'frais_medicaux' => 0 ),
    );
}

/**
 * Cycles tarifaires, dans l'ordre d'affichage.
 *
 * @return string[] cycle => libellé
 */
function ueb_tarifs_cycles() {
    return array( 'licence' => 'Licence', 'master' => 'Master', 'doctorat' => 'Doctorat' );
}

/**
 * Cycle tarifaire d'un niveau LMD (L1 → licence, M2 → master, DOC →
 * doctorat). Ne pas confondre avec ueb_cycle_pour_niveau(), qui répartit
 * les filières et distingue le tronc commun de la L3.
 *
 * @param int $niveau_id Identifiant de ueb_niveaux_lmd.
 * @return string Cycle, '' pour un niveau inconnu.
 */
function ueb_cycle_tarif( $niveau_id ) {
    foreach ( ueb_niveaux_lmd_liste() as $niveau ) {
        if ( $niveau['id'] === (int) $niveau_id ) {
            if ( preg_match( '/^L\d/', $niveau['code'] ) ) {
                return 'licence';
            }
            if ( preg_match( '/^M\d/', $niveau['code'] ) ) {
                return 'master';
            }
            return 'DOC' === $niveau['code'] ? 'doctorat' : '';
        }
    }

    return '';
}

/**
 * Tarif d'un cycle dans un établissement.
 *
 * Source unique des montants : le formulaire (montant proposé par défaut),
 * les quitus, le guide et la page d'accueil la lisent tous.
 *
 * @param int    $faculte_id Identifiant de ueb_facultes.
 * @param string $cycle      Valeur de ueb_tarifs.cycle.
 * @return array { droits, frais_medicaux } en francs CFA.
 */
function ueb_tarif( $faculte_id, $cycle ) {
    static $tarifs = null;

    if ( null === $tarifs ) {
        global $wpdb;

        // Erreurs masquées : sur une base pas encore migrée, la table
        // n'existe pas et les pages publiques s'en tiennent aux défauts.
        $masque = $wpdb->suppress_errors( true );
        $lignes = $wpdb->get_results( 'SELECT faculte_id, cycle, droits_preinscription, frais_medicaux FROM ueb_tarifs' );
        $wpdb->suppress_errors( $masque );

        $tarifs = array();
        foreach ( (array) $lignes as $ligne ) {
            $tarifs[ (int) $ligne->faculte_id ][ $ligne->cycle ] = array(
                'droits'         => (int) $ligne->droits_preinscription,
                'frais_medicaux' => (int) $ligne->frais_medicaux,
            );
        }
    }

    if ( isset( $tarifs[ (int) $faculte_id ][ $cycle ] ) ) {
        return $tarifs[ (int) $faculte_id ][ $cycle ];
    }

    $defauts = ueb_tarifs_defaut();

    return isset( $defauts[ $cycle ] )
        ? $defauts[ $cycle ]
        : array( 'droits' => (int) UEB_FRAIS_PREINSCRIPTION, 'frais_medicaux' => 0 );
}

/**
 * Tarif d'un dossier, d'après son établissement et son niveau.
 *
 * @param int $faculte_id Identifiant de ueb_facultes.
 * @param int $niveau_id  Identifiant de ueb_niveaux_lmd.
 * @return array { droits, frais_medicaux } en francs CFA.
 */
function ueb_tarif_dossier( $faculte_id, $niveau_id ) {
    return ueb_tarif( $faculte_id, ueb_cycle_tarif( $niveau_id ) );
}

/**
 * Frais de visite médicale à régler avec ce dossier, ou 0 s'il n'y en a
 * pas. Un montant positif ajoute le quitus de frais médicaux à la fiche.
 *
 * @param int $faculte_id Identifiant de ueb_facultes.
 * @param int $niveau_id  Identifiant de ueb_niveaux_lmd.
 * @return int Montant en francs CFA.
 */
function ueb_frais_medicaux_dossier( $faculte_id, $niveau_id ) {
    $tarif = ueb_tarif_dossier( $faculte_id, $niveau_id );

    return $tarif['frais_medicaux'];
}

/**
 * Ce que le guide doit signaler pour les établissements qui encaissent sur
 * leur propre compte. Vide si tous versent sur celui de l'Université.
 *
 * @return array[] { titre, texte }
 */
function ueb_guide_particularites_paiement() {
    $notes = array();

    foreach ( ueb_etablissements_paiement() as $etablissement ) {
        if ( '' === $etablissement['compte'] ) {
            continue;
        }

        $tarif = ueb_tarif( $etablissement['id'], 'master' );
        $texte = 'En master, les droits de préinscription se versent sur le compte CCA Bank de l\'établissement, n° ' . $etablissement['compte'] . ', et non sur celui de l\'Université.';
        if ( $tarif['frais_medicaux'] > 0 ) {
            $texte .= ' Les frais médicaux (' . ueb_guide_montant( $tarif['frais_medicaux'], false ) . ') se versent sur ce même compte.';
        }

        $notes[] = array(
            'titre' => 'Master à la ' . $etablissement['code'] . ' — ' . $etablissement['nom'],
            'texte' => $texte,
        );
    }

    return $notes;
}

/**
 * Frais médicaux à annoncer au public, en incise : « 3 000 FCFA en licence
 * et master et 5 000 FCFA en master à la FALSH ». Chaîne vide s'ils sont
 * partout à 0.
 *
 * @return string
 */
function ueb_guide_frais_medicaux_payants() {
    $payants = array();

    foreach ( ueb_guide_grille( 'frais_medicaux' ) as $ligne ) {
        if ( $ligne['montant'] > 0 ) {
            $payants[] = $ligne;
        }
    }

    return ueb_guide_phrase_tarifs( $payants, false );
}

/**
 * Niveaux LMD, dans l'ordre d'affichage.
 *
 * @return array[] { id, code, libelle }
 */
function ueb_niveaux_lmd_liste() {
    static $niveaux = null;

    if ( null !== $niveaux ) {
        return $niveaux;
    }

    global $wpdb;

    $lignes = $wpdb->get_results( 'SELECT id, code, libelle FROM ueb_niveaux_lmd ORDER BY ordre ASC' );

    $niveaux = array();
    foreach ( (array) $lignes as $ligne ) {
        $niveaux[] = array(
            'id'      => (int) $ligne->id,
            'code'    => (string) $ligne->code,
            'libelle' => (string) $ligne->libelle,
        );
    }

    return $niveaux;
}

/**
 * Droits de préinscription par établissement pour un niveau LMD : le
 * formulaire y pioche le montant à proposer quand la faculté ou le niveau
 * change.
 *
 * @param int $niveau_id Identifiant de ueb_niveaux_lmd.
 * @return object faculte_id => montant en francs CFA.
 */
function ueb_droits_par_faculte( $niveau_id ) {
    $droits = array();

    foreach ( ueb_etablissements_paiement() as $etablissement ) {
        $tarif = ueb_tarif_dossier( $etablissement['id'], $niveau_id );
        $droits[ $etablissement['id'] ] = $tarif['droits'];
    }

    return (object) $droits;
}

/**
 * Montant des droits retenu pour un dossier : celui que le candidat a
 * laissé ou corrigé dans le formulaire, à défaut le tarif de sa faculté et
 * de son cycle.
 *
 * Le champ est volontairement libre (aucun plancher) ; seule une valeur
 * absente ou non numérique renvoie au tarif.
 *
 * @param mixed $saisie     Valeur postée du champ « montant_droits ».
 * @param int   $faculte_id Identifiant de ueb_facultes.
 * @param int   $niveau_id  Identifiant de ueb_niveaux_lmd.
 * @return int Montant en francs CFA.
 */
function ueb_montant_droits_saisi( $saisie, $faculte_id, $niveau_id ) {
    $saisie = is_scalar( $saisie ) ? trim( (string) $saisie ) : '';

    if ( '' === $saisie || ! ctype_digit( $saisie ) ) {
        $tarif = ueb_tarif_dossier( $faculte_id, $niveau_id );
        return $tarif['droits'];
    }

    return (int) $saisie;
}

/**
 * Grille publique d'un montant, regroupée pour ne pas répéter une ligne par
 * établissement. Dans chaque cycle, le montant le plus répandu fait la
 * règle (« Master ») et les établissements qui s'en écartent sont nommés
 * (« Master à la FALSH »). Les lignes voisines de même montant fusionnent
 * (« Licence et master »).
 *
 * @param string $champ 'droits' ou 'frais_medicaux'.
 * @return array[] { libelle, minuscule, montant } — minuscule : le libellé
 *                 à glisser dans une phrase, le sigle restant en capitales.
 */
function ueb_guide_grille( $champ ) {
    $actifs = array();
    foreach ( ueb_etablissements_paiement() as $etablissement ) {
        if ( $etablissement['actif'] ) {
            $actifs[] = $etablissement;
        }
    }

    $lignes = array();
    foreach ( ueb_tarifs_cycles() as $cycle => $nom ) {
        $par_montant = array();
        foreach ( $actifs as $etablissement ) {
            $tarif = ueb_tarif( $etablissement['id'], $cycle );
            $par_montant[ $tarif[ $champ ] ][] = $etablissement['code'];
        }

        if ( ! $par_montant ) {
            $defauts = ueb_tarifs_defaut();
            $par_montant[ $defauts[ $cycle ][ $champ ] ] = array();
        }

        // Tri stable : à égalité, le premier montant rencontré fait la règle.
        uasort(
            $par_montant,
            function ( $a, $b ) {
                return count( $b ) - count( $a );
            }
        );

        $regle = true;
        foreach ( $par_montant as $montant => $codes ) {
            $suffixe = $regle ? '' : ' à la ' . implode( ', ', $codes );
            $regle   = false;

            $lignes[] = array(
                'libelle'   => $nom . $suffixe,
                'minuscule' => mb_strtolower( $nom ) . $suffixe,
                'montant'   => (int) $montant,
            );
        }
    }

    $groupes = array();
    foreach ( $lignes as $ligne ) {
        $dernier = count( $groupes ) - 1;
        if ( $dernier >= 0 && $groupes[ $dernier ]['montant'] === $ligne['montant'] ) {
            $groupes[ $dernier ]['minuscules'][] = $ligne['minuscule'];
        } else {
            $groupes[] = array(
                'premier'    => $ligne['libelle'],
                'minuscules' => array( $ligne['minuscule'] ),
                'montant'    => $ligne['montant'],
            );
        }
    }

    $grille = array();
    foreach ( $groupes as $groupe ) {
        $suite     = array_slice( $groupe['minuscules'], 1 );
        $dernier   = array_pop( $suite );
        $liste     = $suite ? ', ' . implode( ', ', $suite ) : '';
        $fin       = null !== $dernier ? $liste . ' et ' . $dernier : '';

        $grille[] = array(
            'libelle'   => $groupe['premier'] . $fin,
            'minuscule' => $groupe['minuscules'][0] . $fin,
            'montant'   => $groupe['montant'],
        );
    }

    return $grille;
}

/**
 * Droits de préinscription à annoncer au public.
 *
 * @return array[] { libelle, minuscule, montant }
 */
function ueb_guide_tarifs() {
    return ueb_guide_grille( 'droits' );
}

/**
 * Un montant formaté avec une espace insécable avant l'unité
 * (« 11 000 FCFA ») pour éviter qu'il ne se coupe en fin de ligne.
 *
 * @param int  $montant Montant en francs CFA.
 * @param bool $html    Vrai pour l'entité HTML, faux pour le PDF (texte brut).
 * @return string
 */
function ueb_guide_montant( $montant, $html = true ) {
    $espace = $html ? '&nbsp;' : ' ';
    return number_format( (int) $montant, 0, ',', $espace ) . $espace . 'FCFA';
}

/**
 * Des lignes de grille en une proposition, à glisser dans une phrase :
 * « 11 000 FCFA en licence et 20 000 FCFA en master ».
 *
 * @param array[] $lignes Lignes de ueb_guide_grille().
 * @param bool    $html   Vrai pour l'entité HTML, faux pour le PDF (texte brut).
 * @return string
 */
function ueb_guide_phrase_tarifs( $lignes, $html = true ) {
    $parts = array();
    foreach ( $lignes as $ligne ) {
        $parts[] = ueb_guide_montant( $ligne['montant'], $html ) . ' en ' . $ligne['minuscule'];
    }

    $dernier = array_pop( $parts );

    return $parts ? implode( ', ', $parts ) . ' et ' . $dernier : (string) $dernier;
}

/**
 * Les droits en une proposition. Un tarif unique pour tous donne
 * simplement « 11 000 FCFA ».
 *
 * @param bool $html Vrai pour l'entité HTML, faux pour le PDF (texte brut).
 * @return string
 */
function ueb_guide_frais( $html = true ) {
    $tarifs = ueb_guide_tarifs();

    if ( 1 === count( $tarifs ) ) {
        return ueb_guide_montant( $tarifs[0]['montant'], $html );
    }

    return ueb_guide_phrase_tarifs( $tarifs, $html );
}

/**
 * Les 6 étapes du parcours de préinscription.
 *
 * La note du Recteur décrit deux fois la même séquence (sous « Procédure de
 * préinscription en ligne » puis sous « Modalités de paiement ») : les deux
 * listes sont fusionnées ici en un seul parcours chronologique, sans perdre
 * aucune instruction. Les canaux de paiement, eux, sont sortis dans leur
 * propre section — cf. ueb_guide_paiements().
 *
 * @return array[] { titre, desc, aide }
 */
function ueb_guide_etapes() {
    return array(
        array(
            'titre' => 'Accéder à la plateforme',
            'desc'  => "Ouvrez la plateforme de préinscription en ligne de l'Université d'Ébolowa.",
            'aide'  => 'Adresse officielle : ' . UEB_URL_PLATEFORME,
        ),
        array(
            'titre' => 'Choisir votre formation',
            'desc'  => "Consultez les offres de formation et leurs débouchés, puis choisissez une faculté en fonction de vos compétences et de vos aspirations.",
            'aide'  => '',
        ),
        array(
            'titre' => 'Remplir le formulaire',
            'desc'  => "Renseignez l'information demandée dans chaque champ, puis enregistrez votre saisie en cliquant sur le bouton « Valider » à la fin du formulaire.",
            'aide'  => 'Préparez votre acte de naissance et vos relevés de notes : les informations doivent être identiques à celles de vos pièces.',
        ),
        array(
            'titre' => 'Imprimer votre fiche',
            'desc'  => "Après la validation, imprimez la fiche obtenue : elle contient votre identifiant unique, qui vous suivra pendant toute la procédure.",
            'aide'  => 'Conservez votre identifiant : il est demandé lors du dépôt du dossier.',
        ),
        array(
            'titre' => 'Régler vos droits',
            'desc'  => sprintf(
                "Réglez vos droits de préinscription ou de réinscription (%s), ainsi que les frais médicaux le cas échéant, à la CCA Bank, par Express Union, MTN Mobile Money ou Campost Money.",
                ueb_guide_frais( false )
            ),
            'aide'  => 'Précisez clairement l\'objet du paiement et conservez le reçu : il est exigé au dépôt du dossier physique.',
        ),
        array(
            'titre' => 'Déposer votre dossier',
            'desc'  => "Rendez-vous à la faculté choisie pour le dépôt du dossier physique, muni de la preuve de paiement et des pièces demandées.",
            'aide'  => '',
        ),
    );
}

/**
 * Les canaux de paiement officiels.
 *
 * Le nombre de canaux n'est écrit nulle part ailleurs (page, PDF) : les
 * deux rendus se calent sur la longueur de ce tableau, un canal peut donc
 * être ajouté ou retiré ici seul.
 *
 * @return array[] { nom, detail, ref }
 */
function ueb_guide_paiements() {
    return array(
        array(
            'nom'    => 'CCA Bank',
            'detail' => 'Versement en agence sur le compte de l\'Université'
                . ( ueb_guide_particularites_paiement() ? ' (sauf établissement signalé ci-dessous)' : '' ),
            'ref'    => UEB_COMPTE_CCA,
        ),
        array(
            'nom'    => 'Express Union',
            'detail' => 'Dans toute agence Express Union du territoire',
            'ref'    => '',
        ),
        array(
            'nom'    => 'MTN Mobile Money',
            'detail' => 'Depuis votre téléphone, via le service UNIVPAY',
            'ref'    => 'UNIVPAY',
        ),
        array(
            'nom'    => 'Campost Money',
            'detail' => 'Dans tout bureau de poste Campost',
            'ref'    => '',
        ),
    );
}

/**
 * Pièces à fournir, par niveau d'entrée.
 *
 * @return array[] { cle, niveau, contexte, pieces[] }
 */
function ueb_guide_dossiers() {
    return array(
        array(
            'cle'      => 'licence-1',
            'niveau'   => 'Licence 1',
            'contexte' => 'Première inscription',
            'pieces'   => array(
                '01 copie certifiée conforme de l\'acte de naissance',
                '01 copie certifiée conforme du probatoire (diplôme ou relevé de notes)',
                '01 copie certifiée conforme du baccalauréat ou de tout diplôme équivalent',
                'Photocopie du reçu de paiement des droits de préinscription',
                '04 photos 4×4',
            ),
            'note'     => 'Les candidats titulaires d\'une capacité en Droit et en Économie doivent justifier d\'une moyenne supérieure ou égale à 13/20.',
        ),
        array(
            'cle'      => 'licence-2-3',
            'niveau'   => 'Licence 2 et Licence 3',
            'contexte' => 'Réinscription — étudiants en situation de transfert',
            'pieces'   => array(
                '01 copie certifiée conforme de l\'acte de naissance',
                '01 copie certifiée conforme du baccalauréat ou de tout diplôme équivalent',
                'Photocopies certifiées des relevés de notes du niveau 1 et/ou du niveau 2',
                'Une lettre de transfert',
                'Un certificat de non-exclusion',
                'Photocopie du reçu de paiement des droits de préinscription',
                '04 photos 4×4',
            ),
            'note'     => '',
        ),
        array(
            'cle'      => 'master-1',
            'niveau'   => 'Master 1',
            'contexte' => 'Première inscription en master',
            'pieces'   => array(
                '01 copie certifiée conforme de l\'acte de naissance',
                '01 copie certifiée conforme du baccalauréat ou de tout diplôme équivalent',
                'Photocopies certifiées des relevés de notes des niveaux 1, 2 et 3',
                'Photocopie certifiée de l\'attestation de licence',
                'Photocopie du reçu de paiement des droits de préinscription',
                '04 photos 4×4',
            ),
            'note'     => '',
        ),
    );
}

/**
 * Pièces exigées le jour du dépôt physique, au rendez-vous fixé par
 * l'établissement.
 *
 * @return string[]
 */
function ueb_guide_depot_pieces() {
    return array(
        'Reçu de paiement des droits de préinscription, qui s\'élèvent à ' . ueb_guide_frais( false ),
        '02 exemplaires de votre fiche de préinscription',
        'Photocopie certifiée conforme du relevé de notes du baccalauréat / GCE A-Level',
        'Photocopie certifiée conforme du probatoire / GCE O-Level ou de l\'attestation de réussite',
        'Photocopie certifiée conforme de l\'acte de naissance',
        '04 photos couleurs 4×4',
    );
}

/**
 * Points d'attention réglementaires (« Bon à savoir »).
 *
 * @return array[] { titre, texte, ton } — ton : 'info' | 'alerte'
 */
function ueb_guide_bon_a_savoir() {
    $dates = ueb_guide_dates();

    return array(
        array(
            'titre' => 'Diplômes acceptés',
            'texte' => "Seuls les baccalauréats délivrés par l'Office du Baccalauréat du Cameroun (ou admis en équivalence) et les GCE délivrés par le GCE Board (ou admis en équivalence) sont acceptés à l'Université d'Ébolowa.",
            'ton'   => 'info',
        ),
        array(
            'titre' => 'Période de préinscription',
            'texte' => "Les préinscriptions à l'Université d'Ébolowa ont lieu " . $dates['periode'] . '.',
            'ton'   => 'info',
        ),
        array(
            'titre' => 'Visites médicales — nouveaux étudiants',
            'texte' => ( ueb_guide_frais_medicaux_payants() ? "Payantes (" . ueb_guide_frais_medicaux_payants() . "), les" : "Sans frais supplémentaires, les" ) . " visites médicales des nouveaux étudiants s'effectuent " . $dates['visites_nouveaux'] . ", selon le programme établi dans chaque établissement. Les étudiants qui n'auront pas fait leur visite médicale seront suspendus.",
            'ton'   => 'alerte',
        ),
        array(
            'titre' => 'Visites médicales — anciens étudiants',
            'texte' => "Les visites médicales des anciens étudiants s'effectuent " . $dates['visites_anciens'] . ", selon le programme établi par chaque établissement. Elles sont payantes : 3 000 FCFA pour les anciens étudiants, et 5 000 FCFA pour ceux revenant après une suspension de matricule. Les étudiants qui n'auront pas fait leur visite médicale seront suspendus.",
            'ton'   => 'alerte',
        ),
        array(
            'titre' => 'Rentrée académique',
            'texte' => "La date de la rentrée académique sera précisée sur cette page.",
            'ton'   => 'info',
        ),
    );
}

/**
 * Les établissements cités dans la note du Recteur.
 *
 * Volontairement distincts de preinscriptions_facultes() (9 entrées, qui
 * alimente la landing page) : ce guide ne liste que les établissements
 * nommés dans le texte officiel dont il est la transcription.
 *
 * @return array[] { abbr, nom }
 */
function ueb_guide_etablissements() {
    return array(
        array( 'abbr' => 'FSJP',  'nom' => 'Faculté des Sciences Juridiques et Politiques' ),
        array( 'abbr' => 'FS',    'nom' => 'Faculté des Sciences' ),
        array( 'abbr' => 'FALSH', 'nom' => 'Faculté des Arts, Lettres et Sciences Humaines' ),
        array( 'abbr' => 'FSEG',  'nom' => 'Faculté des Sciences Économiques et de Gestion' ),
        array( 'abbr' => 'FMSP',  'nom' => 'Faculté de Médecine et des Sciences Pharmaceutiques de Sangmélima' ),
    );
}

/**
 * Sections du guide, dans l'ordre — pilote à la fois le sommaire de la page
 * et la table des matières du PDF, pour qu'ils ne puissent pas diverger.
 *
 * @return array[] { id, titre, court }
 */
function ueb_guide_sections() {
    return array(
        array( 'id' => 'procedure',      'titre' => 'La procédure en ligne',       'court' => 'Procédure' ),
        array( 'id' => 'paiement',       'titre' => 'Modalités de paiement',       'court' => 'Paiement' ),
        array( 'id' => 'pieces',         'titre' => 'Pièces à fournir',            'court' => 'Pièces' ),
        array( 'id' => 'depot',          'titre' => 'Dépôt du dossier physique',   'court' => 'Dépôt' ),
        array( 'id' => 'validation',     'titre' => 'Validation du dossier',       'court' => 'Validation' ),
        array( 'id' => 'bon-a-savoir',   'titre' => 'Bon à savoir',                'court' => 'Bon à savoir' ),
        array( 'id' => 'etablissements', 'titre' => 'Nos établissements',          'court' => 'Établissements' ),
    );
}
