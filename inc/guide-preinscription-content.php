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
 * Montant des droits de préinscription, en francs CFA.
 * Défini ici parce qu'il apparaît à trois endroits du guide (procédure,
 * paiement, dépôt physique) et dans le PDF.
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
 * d'ouverture et se referme à la fin de ce même mois, les visites médicales
 * des nouveaux étudiants ayant lieu pendant le mois d'ouverture et celles
 * des anciens le mois suivant. On reproduit ce calendrier relatif à partir
 * de PREINSCRIPTIONS_DATE_OUVERTURE : si la campagne est décalée d'une
 * année, aucune date de cette page n'est à reprendre à la main.
 *
 * @return array{ouverture:string,cloture:string,periode:string,visites_nouveaux:string,visites_anciens:string,annee_academique:string}
 */
function ueb_guide_dates() {
    $ouverture = new DateTimeImmutable( PREINSCRIPTIONS_DATE_OUVERTURE );
    $cloture   = new DateTimeImmutable( PREINSCRIPTIONS_DATE_CLOTURE );

    /* Visites médicales : le mois d'ouverture pour les nouveaux étudiants,
       le mois suivant pour les anciens. */
    $mois_nouveaux    = $ouverture;
    $fin_nouveaux     = $ouverture->modify( 'last day of this month' );
    $debut_anciens    = $ouverture->modify( 'first day of next month' );
    $fin_anciens      = $debut_anciens->modify( 'last day of this month' );

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
            ueb_guide_date_fr( $debut_anciens, false ),
            ueb_guide_date_fr( $fin_anciens )
        ),
        'annee_academique' => $an . ' – ' . ( $an + 1 ),
    );
}

/**
 * Montant des droits, formaté avec une espace insécable fine avant l'unité
 * (« 11 000 FCFA ») pour éviter que le montant ne se coupe en fin de ligne.
 *
 * @param bool $html Vrai pour l'entité HTML, faux pour le PDF (texte brut).
 * @return string
 */
function ueb_guide_frais( $html = true ) {
    $espace = $html ? '&nbsp;' : ' ';
    return number_format( UEB_FRAIS_PREINSCRIPTION, 0, ',', $espace ) . $espace . 'FCFA';
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
            'desc'  => "Réglez les droits de préinscription ou de réinscription, ainsi que les frais médicaux le cas échéant, par l'un des canaux officiels.",
            'aide'  => 'Précisez clairement l\'objet du paiement et conservez le reçu : il fait partie du dossier physique.',
        ),
        array(
            'titre' => 'Déposer votre dossier',
            'desc'  => "Rendez-vous à la faculté choisie pour le dépôt du dossier physique, muni de la preuve de paiement et des pièces demandées.",
            'aide'  => '',
        ),
    );
}

/**
 * Les trois canaux de paiement officiels.
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
            'texte' => "Sans frais supplémentaires, les visites médicales des anciens étudiants s'effectuent " . $dates['visites_anciens'] . ", selon le programme établi dans chaque établissement. Les étudiants qui n'auront pas fait leur visite médicale seront suspendus.",
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
