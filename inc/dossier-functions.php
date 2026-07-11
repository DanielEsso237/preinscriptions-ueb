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
 * @return string|false Le numéro de dossier créé, ou false en cas d'échec.
 */
function ueb_initialiser_dossier() {
    global $wpdb;

    $numero_dossier = ueb_generer_numero_dossier();

    $result = $wpdb->insert(
        'ueb_preinscriptions_progression',
        array(
            'numero_dossier' => $numero_dossier,
            'etape_atteinte' => 1,
            'donnees_json'   => wp_json_encode( array() ),
        ),
        array( '%s', '%d', '%s' )
    );

    if ( false === $result ) {
        error_log( sprintf(
            '[UEB Dossier] Échec de création de la progression pour %s : %s',
            $numero_dossier,
            $wpdb->last_error
        ) );
        return false;
    }

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