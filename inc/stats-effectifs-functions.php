<?php
/**
 * Effectifs de préinscrits par palier de l'organigramme académique :
 * université > établissement > filière.
 *
 * Complètement distinct de inc/analytics-functions.php, qui alimente la
 * « Vue d'ensemble » : là-bas, tout est recalculé selon les filtres actifs
 * du dashboard ; ici, on lit l'effectif réel de chaque entité, sans filtre.
 * Un effectif qui changerait selon un filtre laissé ouvert dans un autre
 * onglet ne serait pas un effectif.
 *
 * RÈGLE DE COMPTAGE — un candidat compte pour une unité, sur son PREMIER
 * choix de filière. Les 2e et 3e choix mesurent une demande, pas un
 * effectif : les compter ferait qu'un même candidat pèse trois fois, et la
 * somme des filières dépasserait l'effectif de leur établissement.
 *
 * Conséquence à connaître : un dossier peut porter un établissement sans
 * porter encore de filière (formulaire en cours de remplissage). Il compte
 * alors dans son établissement, et apparaît au palier inférieur sous
 * « Filière non renseignée » — ce qui garde l'égalité « somme des lignes =
 * total du palier » à chaque niveau.
 *
 * @package Preinscriptions_UEB
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ventilation par sexe et par situation de handicap d'un ensemble de
 * dossiers, sous la forme attendue par les anneaux du dashboard.
 *
 * Les deux ventilations sont renvoyées ensemble parce qu'elles se lisent
 * ensemble, et surtout parce qu'une seule requête suffit à les produire.
 *
 * Les catégories absentes sont renvoyées à zéro plutôt qu'omises : sans
 * cela, un établissement sans aucune candidate afficherait un anneau à une
 * seule part, sans indiquer que l'autre vaut zéro.
 *
 * $where est toujours construit par ce fichier — soit un littéral, soit le
 * résultat d'un $wpdb->prepare() — jamais une valeur reçue du client.
 *
 * @param string $where  Clause WHERE déjà construite (alias p).
 * @param array  $params Paramètres à préparer.
 * @return array { sexe: array, handicap: array }
 */
function ueb_effectifs_ventilations( $where, $params = array() ) {
    global $wpdb;

    $sql = "SELECT
                SUM(CASE WHEN p.sexe = 'M' THEN 1 ELSE 0 END)        AS hommes,
                SUM(CASE WHEN p.sexe = 'F' THEN 1 ELSE 0 END)        AS femmes,
                SUM(CASE WHEN p.sexe IS NULL THEN 1 ELSE 0 END)      AS sexe_inconnu,
                SUM(CASE WHEN p.handicap = 'oui' THEN 1 ELSE 0 END)  AS handicap,
                SUM(CASE WHEN p.handicap = 'non' THEN 1 ELSE 0 END)  AS sans_handicap
            FROM ueb_preinscriptions p
            WHERE {$where}";

    if ( $params ) {
        $sql = $wpdb->prepare( $sql, $params );
    }

    $row = $wpdb->get_row( $sql );

    $hommes        = $row ? (int) $row->hommes : 0;
    $femmes        = $row ? (int) $row->femmes : 0;
    $sexe_inconnu  = $row ? (int) $row->sexe_inconnu : 0;
    $handicap      = $row ? (int) $row->handicap : 0;
    $sans_handicap = $row ? (int) $row->sans_handicap : 0;

    $sexe = array(
        array( 'label' => 'M', 'total' => $hommes ),
        array( 'label' => 'F', 'total' => $femmes ),
    );

    // Le « non précisé » n'est ajouté que s'il existe : une part fantôme à
    // zéro dans la légende ferait douter de la fiabilité du chiffre.
    if ( $sexe_inconnu > 0 ) {
        $sexe[] = array( 'label' => '?', 'total' => $sexe_inconnu );
    }

    return array(
        'sexe'     => $sexe,
        'handicap' => array(
            array( 'label' => 'non', 'total' => $sans_handicap ),
            array( 'label' => 'oui', 'total' => $handicap ),
        ),
    );
}

/* ==================================================================
   NIVEAU 1 — UNIVERSITÉ : effectif de chaque établissement
   ================================================================== */

/**
 * Vue du niveau université : les établissements, leurs effectifs, et la
 * ventilation sexe / handicap de l'université entière.
 *
 * Une seule requête groupée plutôt qu'une par établissement : le tableau et
 * les cartes se peignent d'un coup.
 *
 * Les établissements sans aucun dossier sont conservés (LEFT JOIN) : une
 * faculté à zéro candidat est une information, pas une ligne à masquer.
 *
 * @return array
 */
function ueb_effectifs_vue_universite() {
    global $wpdb;

    $rows = $wpdb->get_results(
        "SELECT f.id, f.code, f.nom_fr AS libelle,
                COUNT(p.id) AS total,
                SUM(CASE WHEN p.sexe = 'M' THEN 1 ELSE 0 END)       AS hommes,
                SUM(CASE WHEN p.sexe = 'F' THEN 1 ELSE 0 END)       AS femmes,
                SUM(CASE WHEN p.handicap = 'oui' THEN 1 ELSE 0 END) AS handicap
         FROM ueb_facultes f
         LEFT JOIN ueb_preinscriptions p ON p.faculte_id = f.id
         GROUP BY f.id, f.code, f.nom_fr
         ORDER BY total DESC, f.nom_fr ASC"
    );

    $lignes = array_map( 'ueb_effectifs_normaliser_ligne', $rows ? $rows : array() );
    $total  = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ueb_preinscriptions p' );

    // Les dossiers sans établissement n'apparaissent dans aucune ligne :
    // sans cette ligne d'appoint, le pied de tableau annoncerait un total
    // supérieur à la somme de ses lignes.
    $repartis = array_sum( wp_list_pluck( $lignes, 'total' ) );
    if ( $total > $repartis ) {
        $lignes[] = ueb_effectifs_ligne_reste(
            'Établissement non renseigné',
            $total - $repartis,
            'p.faculte_id IS NULL'
        );
    }

    return array(
        'niveau'      => 'universite',
        'id'          => 0,
        'code'        => '',
        'titre'       => "Université d'Ébolowa",
        'sousTitre'   => 'Effectifs par établissement',
        'colonne'     => 'Établissement',
        'total'       => $total,
        'lignes'      => $lignes,
        'ventilation' => ueb_effectifs_ventilations( '1=1' ),
        'fil'         => array(),
    );
}

/* ==================================================================
   NIVEAU 2 — ÉTABLISSEMENT : effectif de chaque filière
   ================================================================== */

/**
 * Vue d'un établissement : ses filières, leurs effectifs, et la ventilation
 * sexe / handicap de l'établissement entier.
 *
 * La jointure porte sur le premier choix ET sur l'établissement du dossier.
 * Les deux conditions sont nécessaires : un candidat qui aurait changé
 * d'établissement en cours de saisie sans mettre à jour ses vœux serait
 * sinon compté dans une filière qui n'appartient pas à son établissement.
 *
 * @param int $faculte_id
 * @return array|null
 */
function ueb_effectifs_vue_etablissement( $faculte_id ) {
    global $wpdb;

    $faculte_id = absint( $faculte_id );

    $faculte = $wpdb->get_row( $wpdb->prepare(
        'SELECT id, code, nom_fr AS libelle FROM ueb_facultes WHERE id = %d',
        $faculte_id
    ) );

    if ( ! $faculte ) {
        return null;
    }

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT fi.id, fi.code, fi.libelle, fi.type_formation,
                COUNT(p.id) AS total,
                SUM(CASE WHEN p.sexe = 'M' THEN 1 ELSE 0 END)       AS hommes,
                SUM(CASE WHEN p.sexe = 'F' THEN 1 ELSE 0 END)       AS femmes,
                SUM(CASE WHEN p.handicap = 'oui' THEN 1 ELSE 0 END) AS handicap
         FROM ueb_filieres fi
         LEFT JOIN ueb_preinscriptions p
                ON p.filiere_1_id = fi.id AND p.faculte_id = fi.faculte_id
         WHERE fi.faculte_id = %d
         GROUP BY fi.id, fi.code, fi.libelle, fi.type_formation
         ORDER BY total DESC, fi.libelle ASC",
        $faculte_id
    ) );

    $lignes = array();
    foreach ( $rows ? $rows : array() as $row ) {
        $ligne = ueb_effectifs_normaliser_ligne( $row );
        // La licence professionnelle se distingue du cursus classique dans
        // l'intitulé officiel : la marquer évite de lire deux filières
        // homonymes comme un doublon.
        $ligne['badge'] = ( 'pro' === $row->type_formation ) ? 'LP' : '';
        $lignes[]       = $ligne;
    }

    $total = (int) $wpdb->get_var( $wpdb->prepare(
        'SELECT COUNT(*) FROM ueb_preinscriptions p WHERE p.faculte_id = %d',
        $faculte_id
    ) );

    // Dossiers de l'établissement qu'aucune de ses filières ne couvre :
    // premier choix pas encore saisi.
    $repartis = array_sum( wp_list_pluck( $lignes, 'total' ) );
    if ( $total > $repartis ) {
        $lignes[] = ueb_effectifs_ligne_reste(
            'Filière non renseignée',
            $total - $repartis,
            $wpdb->prepare(
                'p.faculte_id = %d AND (p.filiere_1_id IS NULL OR p.filiere_1_id NOT IN (
                     SELECT fi.id FROM ueb_filieres fi WHERE fi.faculte_id = %d
                 ))',
                $faculte_id,
                $faculte_id
            )
        );
    }

    return array(
        'niveau'      => 'etablissement',
        'id'          => (int) $faculte->id,
        'code'        => $faculte->code,
        'titre'       => $faculte->libelle,
        'sousTitre'   => 'Effectifs par filière',
        'colonne'     => 'Filière',
        'total'       => $total,
        'lignes'      => $lignes,
        'ventilation' => ueb_effectifs_ventilations( 'p.faculte_id = %d', array( $faculte_id ) ),
        'fil'         => array(
            array( 'niveau' => 'universite', 'id' => 0, 'libelle' => "Université d'Ébolowa", 'code' => 'UEB' ),
        ),
    );
}

/* ==================================================================
   NIVEAU 3 — FILIÈRE : dernier palier
   ================================================================== */

/**
 * Vue d'une filière : ses candidats ventilés par sexe et par situation de
 * handicap, et répartis par niveau LMD — la seule subdivision qui reste
 * une fois arrivé à la filière.
 *
 * @param int $filiere_id
 * @return array|null
 */
function ueb_effectifs_vue_filiere( $filiere_id ) {
    global $wpdb;

    $filiere_id = absint( $filiere_id );

    $filiere = $wpdb->get_row( $wpdb->prepare(
        "SELECT fi.id, fi.code, fi.libelle, fi.type_formation,
                f.id AS faculte_id, f.nom_fr AS faculte_libelle, f.code AS faculte_code
         FROM ueb_filieres fi
         JOIN ueb_facultes f ON f.id = fi.faculte_id
         WHERE fi.id = %d",
        $filiere_id
    ) );

    if ( ! $filiere ) {
        return null;
    }

    $where  = 'p.filiere_1_id = %d';
    $params = array( $filiere_id );

    $total = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM ueb_preinscriptions p WHERE {$where}",
        $params
    ) );

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT n.id, n.code, n.libelle,
                COUNT(p.id) AS total,
                SUM(CASE WHEN p.sexe = 'M' THEN 1 ELSE 0 END)       AS hommes,
                SUM(CASE WHEN p.sexe = 'F' THEN 1 ELSE 0 END)       AS femmes,
                SUM(CASE WHEN p.handicap = 'oui' THEN 1 ELSE 0 END) AS handicap
         FROM ueb_niveaux_lmd n
         LEFT JOIN ueb_preinscriptions p
                ON p.niveau_lmd_id = n.id AND p.filiere_1_id = %d
         GROUP BY n.id, n.code, n.libelle, n.ordre
         HAVING total > 0
         ORDER BY n.ordre ASC",
        $filiere_id
    ) );

    $lignes = array_map( 'ueb_effectifs_normaliser_ligne', $rows ? $rows : array() );

    // Aucun palier en dessous du niveau LMD : ces lignes ne s'ouvrent pas.
    foreach ( $lignes as &$ligne ) {
        $ligne['feuille'] = true;
    }
    unset( $ligne );

    $repartis = array_sum( wp_list_pluck( $lignes, 'total' ) );
    if ( $total > $repartis ) {
        $lignes[] = ueb_effectifs_ligne_reste(
            'Niveau non renseigné',
            $total - $repartis,
            $wpdb->prepare( 'p.filiere_1_id = %d AND p.niveau_lmd_id IS NULL', $filiere_id )
        );
    }

    return array(
        'niveau'      => 'filiere',
        'id'          => (int) $filiere->id,
        'code'        => $filiere->code,
        'titre'       => $filiere->libelle,
        'sousTitre'   => 'Effectifs par niveau LMD',
        'colonne'     => 'Niveau LMD',
        'badge'       => ( 'pro' === $filiere->type_formation ) ? 'Licence professionnelle' : '',
        'total'       => $total,
        'lignes'      => $lignes,
        'ventilation' => ueb_effectifs_ventilations( $where, $params ),
        'fil'         => array(
            array( 'niveau' => 'universite',    'id' => 0,                          'libelle' => "Université d'Ébolowa",   'code' => 'UEB' ),
            array( 'niveau' => 'etablissement', 'id' => (int) $filiere->faculte_id, 'libelle' => $filiere->faculte_libelle, 'code' => $filiere->faculte_code ),
        ),
    );
}

/* ==================================================================
   OUTILS COMMUNS
   ================================================================== */

/**
 * Met une ligne de résultat SQL au format attendu par le JS : entiers
 * garantis (le pilote MySQL renvoie des chaînes), et clés stables quel que
 * soit le palier, pour que le tableau se peigne avec le même code à tous
 * les niveaux.
 *
 * @param object $row
 * @return array
 */
function ueb_effectifs_normaliser_ligne( $row ) {
    return array(
        'id'       => isset( $row->id ) ? (int) $row->id : 0,
        'code'     => isset( $row->code ) ? $row->code : '',
        'libelle'  => isset( $row->libelle ) ? $row->libelle : '',
        'total'    => (int) $row->total,
        'hommes'   => (int) $row->hommes,
        'femmes'   => (int) $row->femmes,
        'handicap' => (int) $row->handicap,
        'badge'    => '',
        'reste'    => false,
        'feuille'  => false,
    );
}

/**
 * Ligne d'appoint pour les dossiers qu'aucune entité du palier ne couvre
 * (établissement, filière ou niveau non renseigné).
 *
 * Elle porte ses propres ventilations, calculées sur la même condition que
 * son effectif : sans elles, la colonne « Filles » du pied de tableau ne
 * retomberait pas sur le total réel.
 *
 * $where vient toujours de ce fichier (littéral ou $wpdb->prepare()).
 *
 * @param string $libelle Intitulé affiché.
 * @param int    $total   Effectif, déjà calculé par différence.
 * @param string $where   Condition permettant de ventiler ces dossiers.
 * @return array
 */
function ueb_effectifs_ligne_reste( $libelle, $total, $where ) {
    global $wpdb;

    $row = $wpdb->get_row(
        "SELECT SUM(CASE WHEN p.sexe = 'M' THEN 1 ELSE 0 END)       AS hommes,
                SUM(CASE WHEN p.sexe = 'F' THEN 1 ELSE 0 END)       AS femmes,
                SUM(CASE WHEN p.handicap = 'oui' THEN 1 ELSE 0 END) AS handicap
         FROM ueb_preinscriptions p
         WHERE {$where}"
    );

    return array(
        'id'       => 0,
        'code'     => '',
        'libelle'  => $libelle,
        'total'    => (int) $total,
        'hommes'   => $row ? (int) $row->hommes : 0,
        'femmes'   => $row ? (int) $row->femmes : 0,
        'handicap' => $row ? (int) $row->handicap : 0,
        'badge'    => '',
        // Il n'y a pas d'entité derrière cette ligne, donc pas de palier à
        // ouvrir : elle ne se clique pas, et le tri la garde en bas.
        'reste'    => true,
        'feuille'  => true,
    );
}

/**
 * Aiguillage unique : renvoie la vue du palier demandé.
 *
 * Un seul point d'entrée pour les trois niveaux, afin que l'endpoint AJAX
 * et la page n'aient qu'un contrat à connaître.
 *
 * @param string $niveau universite | etablissement | filiere
 * @param int    $id     Identifiant de l'entité (ignoré au niveau université).
 * @return array|null
 */
function ueb_effectifs_vue( $niveau, $id = 0 ) {
    switch ( $niveau ) {
        case 'etablissement':
            return ueb_effectifs_vue_etablissement( $id );
        case 'filiere':
            return ueb_effectifs_vue_filiere( $id );
        case 'universite':
        default:
            return ueb_effectifs_vue_universite();
    }
}
