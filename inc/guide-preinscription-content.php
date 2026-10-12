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
 * Les tarifs réels sont portés par ueb_niveaux_lmd.droits_preinscription
 * et administrés depuis « Gestion des références » (cf.
 * ueb_droits_par_niveau()). Cette constante n'est plus que le repli :
 * niveau inconnu, ou base pas encore migrée.
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
 * Droits de préinscription de chaque niveau LMD, dans l'ordre d'affichage.
 *
 * Source unique des tarifs : le formulaire (montant proposé par défaut),
 * le quitus, le guide et la page d'accueil la lisent tous.
 *
 * @return array[] { id, code, libelle, droits }
 */
function ueb_droits_par_niveau() {
    static $niveaux = null;

    if ( null !== $niveaux ) {
        return $niveaux;
    }

    global $wpdb;

    // Erreurs masquées : sur une base pas encore migrée (colonne absente),
    // la requête échoue et les pages publiques retombent sur la constante.
    $masque = $wpdb->suppress_errors( true );
    $lignes = $wpdb->get_results( 'SELECT id, code, libelle, droits_preinscription FROM ueb_niveaux_lmd ORDER BY ordre ASC' );
    $wpdb->suppress_errors( $masque );

    $niveaux = array();
    foreach ( (array) $lignes as $ligne ) {
        $niveaux[] = array(
            'id'      => (int) $ligne->id,
            'code'    => (string) $ligne->code,
            'libelle' => (string) $ligne->libelle,
            'droits'  => (int) $ligne->droits_preinscription,
        );
    }

    return $niveaux;
}

/**
 * Droits de préinscription proposés par défaut pour un niveau LMD.
 *
 * @param int $niveau_id Identifiant de ueb_niveaux_lmd.
 * @return int Montant en francs CFA.
 */
function ueb_droits_du_niveau( $niveau_id ) {
    foreach ( ueb_droits_par_niveau() as $niveau ) {
        if ( $niveau['id'] === (int) $niveau_id ) {
            return $niveau['droits'];
        }
    }

    return (int) UEB_FRAIS_PREINSCRIPTION;
}

/**
 * Montant des droits retenu pour un dossier : celui que le candidat a
 * laissé ou corrigé dans le formulaire, à défaut le tarif de son niveau.
 *
 * Le champ est volontairement libre (aucun plancher) ; seule une valeur
 * absente ou non numérique renvoie au tarif du niveau.
 *
 * @param mixed $saisie    Valeur postée du champ « montant_droits ».
 * @param int   $niveau_id Identifiant de ueb_niveaux_lmd.
 * @return int Montant en francs CFA.
 */
function ueb_montant_droits_saisi( $saisie, $niveau_id ) {
    $saisie = is_scalar( $saisie ) ? trim( (string) $saisie ) : '';

    if ( '' === $saisie || ! ctype_digit( $saisie ) ) {
        return ueb_droits_du_niveau( $niveau_id );
    }

    return (int) $saisie;
}

/**
 * Tarifs à annoncer au public, regroupés pour ne pas répéter six lignes :
 * les niveaux d'un même cycle au même tarif n'en font qu'une (« Licence »),
 * puis les cycles voisins au même tarif fusionnent (« Master et doctorat »).
 * Un cycle dont les niveaux n'ont pas tous le même tarif est détaillé
 * niveau par niveau.
 *
 * @return array[] { libelle, montant }
 */
function ueb_guide_tarifs() {
    $cycles = array();
    foreach ( ueb_droits_par_niveau() as $niveau ) {
        if ( 'DOC' === $niveau['code'] ) {
            $cycle = 'Doctorat';
        } elseif ( preg_match( '/^L\d/', $niveau['code'] ) ) {
            $cycle = 'Licence';
        } elseif ( preg_match( '/^M\d/', $niveau['code'] ) ) {
            $cycle = 'Master';
        } else {
            $cycle = $niveau['libelle'];
        }
        $cycles[ $cycle ][] = $niveau;
    }

    $lignes = array();
    foreach ( $cycles as $cycle => $niveaux ) {
        if ( 1 === count( array_unique( wp_list_pluck( $niveaux, 'droits' ) ) ) ) {
            $lignes[] = array( 'libelle' => $cycle, 'montant' => $niveaux[0]['droits'] );
            continue;
        }
        foreach ( $niveaux as $niveau ) {
            $lignes[] = array( 'libelle' => $niveau['libelle'], 'montant' => $niveau['droits'] );
        }
    }

    $tarifs = array();
    foreach ( $lignes as $ligne ) {
        $dernier = count( $tarifs ) - 1;
        if ( $dernier >= 0 && $tarifs[ $dernier ]['montant'] === $ligne['montant'] ) {
            $tarifs[ $dernier ]['libelles'][] = $ligne['libelle'];
        } else {
            $tarifs[] = array( 'libelles' => array( $ligne['libelle'] ), 'montant' => $ligne['montant'] );
        }
    }

    foreach ( $tarifs as $i => $tarif ) {
        $libelles = $tarif['libelles'];
        // « Master et doctorat », mais « Licence 1 et Licence 2 ».
        foreach ( $libelles as $j => $libelle ) {
            if ( $j > 0 && ! preg_match( '/\d/', $libelle ) ) {
                $libelles[ $j ] = mb_strtolower( $libelle );
            }
        }
        $dernier_libelle = array_pop( $libelles );
        $tarifs[ $i ]    = array(
            'libelle' => $libelles ? implode( ', ', $libelles ) . ' et ' . $dernier_libelle : $dernier_libelle,
            'montant' => $tarif['montant'],
        );
    }

    if ( ! $tarifs ) {
        $tarifs[] = array( 'libelle' => '', 'montant' => (int) UEB_FRAIS_PREINSCRIPTION );
    }

    return $tarifs;
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
 * Les droits en une proposition, à glisser dans une phrase :
 * « 11 000 FCFA en licence et 20 000 FCFA en master et doctorat ».
 * Un tarif unique pour tous les niveaux donne simplement « 11 000 FCFA ».
 *
 * @param bool $html Vrai pour l'entité HTML, faux pour le PDF (texte brut).
 * @return string
 */
function ueb_guide_frais( $html = true ) {
    $tarifs = ueb_guide_tarifs();

    if ( 1 === count( $tarifs ) ) {
        return ueb_guide_montant( $tarifs[0]['montant'], $html );
    }

    $parts = array();
    foreach ( $tarifs as $tarif ) {
        $libelle = $tarif['libelle'];
        // Un cycle se lit en minuscules dans une phrase, un niveau garde
        // sa majuscule (« en Licence 1 »).
        if ( ! preg_match( '/\d/', $libelle ) ) {
            $libelle = mb_strtolower( $libelle );
        }
        $parts[] = ueb_guide_montant( $tarif['montant'], $html ) . ' en ' . $libelle;
    }

    $dernier = array_pop( $parts );
    return implode( ', ', $parts ) . ' et ' . $dernier;
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
            'detail' => 'Versement en agence sur le compte de l\'Université',
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
            'texte' => "Sans frais supplémentaires, les visites médicales des nouveaux étudiants s'effectuent " . $dates['visites_nouveaux'] . ", selon le programme établi dans chaque établissement. Les étudiants qui n'auront pas fait leur visite médicale seront suspendus.",
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
