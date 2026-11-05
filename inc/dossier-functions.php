<?php
/**
 * Gestion du numéro de dossier et de la reprise du formulaire.
 *
 * Un numéro de dossier (ex. UEB-2026-000001) est généré dès que le
 * candidat ouvre le formulaire, AVANT toute saisie. Il sert de clé
 * partagée entre :
 * - ueb_preinscriptions_progression : brouillon en cours (JSON par étape)
 * - ueb_preinscriptions              : dossier final, une fois soumis
 *
 * @package Preinscriptions_UEB
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Génère un numéro de dossier unique, au format UEB-<année>-<6 chiffres>
 * (ex. UEB-2026-000001), avec remise à zéro chaque année.
 *
 * Anti-collision : repose sur ueb_dossier_sequence, une table avec une
 * ligne par année. L'incrémentation utilise le motif
 * "INSERT ... ON DUPLICATE KEY UPDATE ... LAST_INSERT_ID(...)", qui est
 * atomique côté MySQL (verrouillage de ligne InnoDB) : même si deux
 * candidats valident au même instant, ils ne peuvent jamais recevoir le
 * même numéro.
 *
 * @return string Le numéro de dossier généré (ex. "UEB-2026-000001").
 */
function ueb_generer_numero_dossier() {
    global $wpdb;
    $annee = (int) date( 'Y' );

    $wpdb->query( $wpdb->prepare(
        "INSERT INTO ueb_dossier_sequence (annee, dernier_numero) VALUES (%d, 1)
         ON DUPLICATE KEY UPDATE dernier_numero = LAST_INSERT_ID(dernier_numero + 1)",
        $annee
    ) );

    $numero = (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' );

    return sprintf( 'UEB-%d-%06d', $annee, $numero );
}

/**
 * Initialise un nouveau dossier : génère un numéro de dossier, puis crée
 * la ligne de progression correspondante (étape 1, données vides).
 *
 * À appeler dès que le candidat arrive sur le formulaire (avant qu'il ne
 * commence à remplir quoi que ce soit), pour pouvoir lui afficher tout de
 * suite son numéro de dossier ("Note-le pour reprendre plus tard").
 *
 * Tire aussi le code de reprise du dossier (seule son empreinte est gardée
 * en base) et ouvre le dossier sur cet appareil (session + cookie).
 *
 * @return string|false Le numéro de dossier créé, ou false en cas d'échec.
 */
function ueb_initialiser_dossier() {
    global $wpdb;

    $numero_dossier = ueb_generer_numero_dossier();
    $code           = ueb_generer_code_reprise();

    $result = $wpdb->insert(
        'ueb_preinscriptions_progression',
        array(
            'numero_dossier'    => $numero_dossier,
            'etape_atteinte'    => 1,
            'donnees_json'      => wp_json_encode( array() ),
            'code_reprise_hash' => ueb_hash_code_reprise( $numero_dossier, $code ),
        ),
        array( '%s', '%d', '%s', '%s' )
    );

    if ( false === $result ) {
        error_log( sprintf(
            '[UEB Dossier] Échec de création de la progression pour %s : %s',
            $numero_dossier,
            $wpdb->last_error
        ) );
        return false;
    }

    ueb_memoriser_dossier( $numero_dossier, $code );

    return $numero_dossier;
}

/**
 * Sauvegarde (ou met à jour) la progression d'un dossier en cours.
 *
 * Appelée à chaque changement d'étape du formulaire (ex. via une requête
 * AJAX déclenchée par le clic sur "Suivant"). Écrase l'étape atteinte et
 * les données JSON à chaque appel — la ligne de progression représente
 * toujours le dernier état connu du brouillon, pas un historique.
 *
 * @param string $numero_dossier Numéro de dossier (ex. "UEB-2026-000001").
 * @param int    $etape          Étape atteinte (1 à 4).
 * @param array  $donnees        Données saisies jusqu'ici (tableau associatif
 *                                de tous les champs du formulaire, y compris
 *                                les valeurs pas encore normalisées comme les
 *                                téléphones multiples).
 *
 * @return bool True si la sauvegarde a réussi, false sinon.
 */
function ueb_sauvegarder_progression( $numero_dossier, $etape, $donnees ) {
    global $wpdb;

    $numero_dossier = sanitize_text_field( $numero_dossier );
    $etape          = absint( $etape );
    $donnees_json   = wp_json_encode( $donnees );

    if ( false === $donnees_json ) {
        error_log( sprintf(
            '[UEB Dossier] Échec d\'encodage JSON pour %s (étape %d).',
            $numero_dossier,
            $etape
        ) );
        return false;
    }

    // upsert : si le dossier n'existe pas encore en progression (cas rare,
    // ex. reprise d'un vieux lien), on le crée ; sinon on met à jour.
    $result = $wpdb->query( $wpdb->prepare(
        "INSERT INTO ueb_preinscriptions_progression (numero_dossier, etape_atteinte, donnees_json)
         VALUES (%s, %d, %s)
         ON DUPLICATE KEY UPDATE etape_atteinte = VALUES(etape_atteinte), donnees_json = VALUES(donnees_json)",
        $numero_dossier,
        $etape,
        $donnees_json
    ) );

    if ( false === $result ) {
        error_log( sprintf(
            '[UEB Dossier] Échec de sauvegarde de la progression pour %s : %s',
            $numero_dossier,
            $wpdb->last_error
        ) );
        return false;
    }

    return true;
}

/**
 * Récupère la progression d'un dossier à partir de son numéro, pour
 * permettre au candidat de reprendre là où il s'était arrêté.
 *
 * @param string $numero_dossier Numéro de dossier saisi par le candidat.
 *
 * @return array|null {
 *     Tableau avec les clés suivantes, ou null si le numéro est introuvable.
 *
 *     @type int   $etape_atteinte Étape à laquelle reprendre (1 à 4).
 *     @type array $donnees        Données déjà saisies (tableau associatif,
 *                                   décodé depuis le JSON stocké).
 * }
 */
function ueb_recuperer_progression( $numero_dossier ) {
    global $wpdb;

    $numero_dossier = sanitize_text_field( $numero_dossier );

    $ligne = $wpdb->get_row( $wpdb->prepare(
        "SELECT etape_atteinte, donnees_json
         FROM ueb_preinscriptions_progression
         WHERE numero_dossier = %s",
        $numero_dossier
    ), ARRAY_A );

    if ( null === $ligne ) {
        return null; 
    }

    $donnees = json_decode( $ligne['donnees_json'], true );

    return array(
        'etape_atteinte' => (int) $ligne['etape_atteinte'],
        'donnees'        => is_array( $donnees ) ? $donnees : array(),
    );
}

/* ======================================================================
   CODE DE REPRISE ET DROIT SUR UN DOSSIER
   ----------------------------------------------------------------------
   Le numéro de dossier se suit (UEB-2026-000001, 000002…) : il identifie
   un dossier, il ne prouve rien. Lire, sauvegarder ou soumettre un dossier
   exige que l'appareil y ait droit :
     - la session PHP porte ce numéro (dossier ouvert ou repris ici) ;
     - ou le cookie porte « numéro.code », et le code est le bon
       (session expirée pendant la saisie, retour quelques jours après) ;
     - sinon, la reprise manuelle demande le numéro ET le code de reprise,
       tiré au hasard à la création et montré au candidat avec son numéro.
   Les dossiers commencés avant le code (colonne vide) se reprennent avec
   la date de naissance saisie dans le brouillon ; ils reçoivent alors un
   code, comme les autres.
   ====================================================================== */

/** Lettres et chiffres sans ambiguïté à la lecture (ni 0/O, ni 1/I/L). */
const UEB_CODE_REPRISE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

/** Code de reprise affichable, ex. « K7MQ-4XRA » (31^8 ≈ 850 milliards de possibilités). */
function ueb_generer_code_reprise() {
    $alphabet = UEB_CODE_REPRISE_ALPHABET;
    $code     = '';
    for ( $i = 0; $i < 8; $i++ ) {
        $code .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ];
    }
    return substr( $code, 0, 4 ) . '-' . substr( $code, 4 );
}

/** Code saisi → forme comparable : majuscules, sans tiret ni espace. */
function ueb_normaliser_code_reprise( $code ) {
    return preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) $code ) );
}

/** Empreinte stockée en base : le code lui-même n'y est jamais écrit. */
function ueb_hash_code_reprise( $numero_dossier, $code ) {
    return hash( 'sha256', 'ueb-reprise|' . $numero_dossier . '|' . ueb_normaliser_code_reprise( $code ) );
}

/** Empreinte du code d'un dossier : chaîne vide si le dossier date d'avant le code, null s'il n'existe pas. */
function ueb_empreinte_code_reprise( $numero_dossier ) {
    global $wpdb;
    $ligne = $wpdb->get_row( $wpdb->prepare(
        'SELECT code_reprise_hash FROM ueb_preinscriptions_progression WHERE numero_dossier = %s',
        $numero_dossier
    ), ARRAY_A );
    return null === $ligne ? null : (string) $ligne['code_reprise_hash'];
}

/** Vrai si le code est celui du dossier. */
function ueb_code_reprise_valide( $numero_dossier, $code ) {
    $empreinte = ueb_empreinte_code_reprise( $numero_dossier );
    return '' !== (string) $empreinte && '' !== ueb_normaliser_code_reprise( $code )
        && hash_equals( $empreinte, ueb_hash_code_reprise( $numero_dossier, $code ) );
}

/** Donne un code à un dossier qui n'en a pas encore (dossier d'avant le code). */
function ueb_attribuer_code_reprise( $numero_dossier ) {
    global $wpdb;
    $code = ueb_generer_code_reprise();
    $ok   = $wpdb->update(
        'ueb_preinscriptions_progression',
        array( 'code_reprise_hash' => ueb_hash_code_reprise( $numero_dossier, $code ) ),
        array( 'numero_dossier' => $numero_dossier ),
        array( '%s' ),
        array( '%s' )
    );
    return false === $ok ? false : $code;
}

/**
 * Ouvre le dossier sur cet appareil : session, et cookie « numéro.code »
 * de 30 jours (HttpOnly) pour revenir sans rien ressaisir.
 */
function ueb_memoriser_dossier( $numero_dossier, $code ) {
    $_SESSION['ueb_numero_dossier_en_cours'] = $numero_dossier;
    $_SESSION['ueb_code_reprise_en_cours']   = $code;
    if ( ! headers_sent() ) {
        setcookie( 'ueb_numero_dossier', $numero_dossier . '.' . ueb_normaliser_code_reprise( $code ), array(
            'expires'  => time() + 30 * DAY_IN_SECONDS,
            'path'     => COOKIEPATH,
            'domain'   => COOKIE_DOMAIN,
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ) );
    }
}

/** Ferme le dossier sur cet appareil (après soumission : poste partagé). */
function ueb_oublier_dossier() {
    unset( $_SESSION['ueb_numero_dossier_en_cours'], $_SESSION['ueb_code_reprise_en_cours'] );
    if ( ! headers_sent() ) {
        setcookie( 'ueb_numero_dossier', '', array(
            'expires'  => time() - 3600,
            'path'     => COOKIEPATH,
            'domain'   => COOKIE_DOMAIN,
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ) );
    }
}

/**
 * Dossier ouvert sur cet appareil, ou null. Session d'abord ; sinon le
 * cookie, dont le code est vérifié (un cookie qui ne porte qu'un numéro,
 * ou un mauvais code, ne donne droit à rien). Un dossier de session sans
 * code (ouvert avant le code) en reçoit un : la session prouve qu'il est
 * bien celui de cet appareil.
 *
 * @return array|null array( 'numero' => …, 'code' => … ) ; code vide si inconnu.
 */
function ueb_dossier_de_cet_appareil() {
    if ( ! empty( $_SESSION['ueb_numero_dossier_en_cours'] ) ) {
        $numero = (string) $_SESSION['ueb_numero_dossier_en_cours'];
        $code   = (string) ( $_SESSION['ueb_code_reprise_en_cours'] ?? '' );
        if ( '' === $code && '' === ueb_empreinte_code_reprise( $numero ) ) {
            $code = (string) ueb_attribuer_code_reprise( $numero );
            if ( '' !== $code ) {
                ueb_memoriser_dossier( $numero, $code );
            }
        }
        return array( 'numero' => $numero, 'code' => $code );
    }

    $cookie = isset( $_COOKIE['ueb_numero_dossier'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['ueb_numero_dossier'] ) ) : '';
    if ( preg_match( '/^(UEB-\d{4}-\d{6})\.([A-Z0-9]{8})$/', $cookie, $m ) && ueb_code_reprise_valide( $m[1], $m[2] ) ) {
        $code = substr( $m[2], 0, 4 ) . '-' . substr( $m[2], 4 );
        ueb_memoriser_dossier( $m[1], $code );
        return array( 'numero' => $m[1], 'code' => $code );
    }

    return null;
}

/** Vrai si cet appareil a le droit de lire, sauvegarder ou soumettre ce dossier. */
function ueb_dossier_autorise( $numero_dossier ) {
    $dossier = ueb_dossier_de_cet_appareil();
    return null !== $dossier && '' !== (string) $numero_dossier && hash_equals( $dossier['numero'], (string) $numero_dossier );
}

/**
 * Reprise manuelle : numéro + code de reprise, ou date de naissance pour un
 * dossier d'avant le code. Essais limités par adresse IP et par dossier.
 *
 * @return array|WP_Error array( 'numero' => …, 'code' => …, 'nouveau_code' => bool ).
 */
function ueb_reprendre_dossier( $numero_dossier, $preuve ) {
    $numero_dossier = strtoupper( trim( (string) $numero_dossier ) );
    $refus          = new WP_Error( 'ueb_reprise', 'Numéro de dossier ou code de reprise incorrect.' );
    $cles           = array(
        'ueb_reprise_ip_' . hash( 'sha256', $_SERVER['REMOTE_ADDR'] ?? 'local' ),
        'ueb_reprise_dossier_' . hash( 'sha256', $numero_dossier ),
    );
    $plafonds       = array( 10, 20 ); // 10 essais ratés par IP, 20 par dossier, sur 15 minutes.
    foreach ( $cles as $i => $cle ) {
        if ( (int) get_transient( $cle ) >= $plafonds[ $i ] ) {
            return new WP_Error( 'ueb_reprise_bloquee', 'Trop d’essais. Réessaie dans 15 minutes.' );
        }
    }

    $empreinte   = ueb_empreinte_code_reprise( $numero_dossier );
    $nouveau     = false;
    $code        = '';
    if ( null !== $empreinte && '' !== $empreinte && ueb_code_reprise_valide( $numero_dossier, $preuve ) ) {
        $n    = ueb_normaliser_code_reprise( $preuve );
        $code = substr( $n, 0, 4 ) . '-' . substr( $n, 4 );
    } elseif ( '' === $empreinte && ueb_date_naissance_du_brouillon_correspond( $numero_dossier, $preuve ) ) {
        $code    = (string) ueb_attribuer_code_reprise( $numero_dossier );
        $nouveau = '' !== $code;
    }

    if ( '' === $code ) {
        foreach ( $cles as $cle ) {
            set_transient( $cle, (int) get_transient( $cle ) + 1, 15 * MINUTE_IN_SECONDS );
        }
        return $refus;
    }

    ueb_memoriser_dossier( $numero_dossier, $code );
    return array( 'numero' => $numero_dossier, 'code' => $code, 'nouveau_code' => $nouveau );
}

/** Dossier d'avant le code : la date de naissance saisie (JJ/MM/AAAA ou AAAA-MM-JJ) est-elle celle du brouillon ? */
function ueb_date_naissance_du_brouillon_correspond( $numero_dossier, $saisie ) {
    $progression = ueb_recuperer_progression( $numero_dossier );
    $attendue    = (string) ( $progression['donnees']['date_naissance'] ?? '' );
    $saisie      = trim( (string) $saisie );
    if ( preg_match( '#^(\d{1,2})[/.\- ](\d{1,2})[/.\- ](\d{4})$#', $saisie, $m ) ) {
        $saisie = sprintf( '%04d-%02d-%02d', $m[3], $m[2], $m[1] );
    }
    return '' !== $attendue && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $attendue ) && hash_equals( $attendue, $saisie );
}
