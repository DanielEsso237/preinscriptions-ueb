<?php
/**
 * seed-demo.php — Générateur de préinscriptions de DÉMONSTRATION.
 *
 * But : peupler ueb_preinscriptions (+ ueb_preinscriptions_telephones) avec
 * des dossiers réalistes, uniquement pour tester visuellement le dashboard
 * admin (graphiques, filtres, liste). Ce script n'est JAMAIS chargé par le
 * thème : il ne s'exécute qu'en ligne de commande.
 *
 * ---------------------------------------------------------------------------
 * CONVENTION DE MARQUAGE DES DOSSIERS DE DÉMO  (important)
 * ---------------------------------------------------------------------------
 * Tous les dossiers créés ici ont un numero_dossier préfixé "DEMO-" :
 *
 *     DEMO-UEB-<année>-<6 chiffres>      ex. DEMO-UEB-2026-000042
 *
 * (20 caractères exactement = la taille de la colonne VARCHAR(20), et le
 * format reste lisible à côté des vrais numéros "UEB-2026-000001".)
 *
 * Pourquoi ce choix :
 *  - aucune colonne "is_demo" n'existe et on ne modifie PAS le schéma ;
 *  - numero_dossier est NOT NULL + UNIQUE, donc c'est le seul marqueur
 *    garanti présent et non ambigu sur chaque ligne ;
 *  - le préfixe est impossible à produire par l'application réelle
 *    (ueb_generer_numero_dossier() produit toujours "UEB-…"), donc
 *    --purge ne peut pas supprimer un vrai dossier de candidat.
 *
 * --purge fait donc simplement :
 *     DELETE FROM ueb_preinscriptions WHERE numero_dossier LIKE 'DEMO-%'
 * les téléphones partant en cascade (FK ON DELETE CASCADE), avec en plus un
 * nettoyage explicite des téléphones orphelins par sécurité.
 *
 * Le compteur de séquence applicatif (ueb_dossier_sequence) n'est PAS touché :
 * la numérotation de démo est indépendante (reprise à max(demo)+1), ce qui
 * rend --install rejouable autant de fois qu'on veut.
 *
 * ---------------------------------------------------------------------------
 * UTILISATION
 * ---------------------------------------------------------------------------
 *   /opt/lampp/bin/php tools/seed-demo.php --install       # 150 dossiers
 *   /opt/lampp/bin/php tools/seed-demo.php --install 400   # 400 dossiers
 *   /opt/lampp/bin/php tools/seed-demo.php --purge         # supprime la démo
 *
 * Toutes les valeurs de clés étrangères sont lues en base au démarrage
 * (aucun ID codé en dur) : le script suit automatiquement les évolutions
 * des tables de référence.
 *
 * @package Preinscriptions_UEB
 */

if ( PHP_SAPI !== 'cli' ) {
    exit( "Ce script s'exécute uniquement en ligne de commande.\n" );
}

define( 'WP_USE_THEMES', false );
require '/opt/lampp/htdocs/preinscriptions-ueb/wp-load.php';

global $wpdb;

/* ==========================================================================
 * 0. Constantes de configuration
 * ========================================================================== */

const DEMO_PREFIX     = 'DEMO-';                 // marqueur des dossiers de démo
const DEMO_NUM_FORMAT = 'DEMO-UEB-%d-%06d';      // <= 20 caractères
const DEMO_JOURS      = 60;                      // étalement de date_creation

/* ==========================================================================
 * 1. Petites aides
 * ========================================================================== */

function demo_out( $msg = '' ) {
    fwrite( STDOUT, $msg . PHP_EOL );
}

function demo_die( $msg ) {
    fwrite( STDERR, 'ERREUR : ' . $msg . PHP_EOL );
    exit( 1 );
}

/** Tirage pondéré : array( valeur => poids ) => valeur. */
function demo_weighted( array $weights ) {
    $total = array_sum( $weights );
    if ( $total <= 0 ) {
        return array_key_first( $weights );
    }
    $tirage = mt_rand( 1, (int) round( $total * 1000 ) ) / 1000;
    $cumul  = 0;
    foreach ( $weights as $valeur => $poids ) {
        $cumul += $poids;
        if ( $tirage <= $cumul ) {
            return $valeur;
        }
    }
    return array_key_last( $weights );
}

/** Vrai avec une probabilité $p (0..1). */
function demo_chance( $p ) {
    return ( mt_rand( 0, 10000 ) / 10000 ) < $p;
}

/** Élément au hasard dans un tableau indexé. */
function demo_pick( array $arr ) {
    return $arr[ array_rand( $arr ) ];
}

/** Tirage gaussien tronqué (Box-Muller), utile pour les moyennes. */
function demo_gauss( $moyenne, $ecart, $min, $max ) {
    $u1 = max( 1e-9, mt_rand( 1, 999999 ) / 1000000 );
    $u2 = mt_rand( 1, 999999 ) / 1000000;
    $z  = sqrt( -2 * log( $u1 ) ) * cos( 2 * M_PI * $u2 );
    return min( $max, max( $min, $moyenne + $z * $ecart ) );
}

/** "Éyoum'" => "eyoum" : base propre pour construire un email. */
function demo_slug( $texte ) {
    $texte = iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $texte );
    $texte = strtolower( (string) $texte );
    $texte = preg_replace( '/[^a-z0-9]+/', '', $texte );
    return $texte;
}

/** Numéro de téléphone camerounais mobile : 6XXXXXXXX (9 chiffres). */
function demo_telephone() {
    return '6' . demo_pick( array( '5', '6', '7', '8', '9' ) ) . sprintf( '%07d', mt_rand( 0, 9999999 ) );
}

/* ==========================================================================
 * 2. Jeux de données "humaines" (noms camerounais plausibles)
 * ========================================================================== */

$NOMS = array(
    'Abanda', 'Abega', 'Atangana', 'Awono', 'Bekolo', 'Belinga', 'Bidias', 'Biya',
    'Djoumessi', 'Ebode', 'Edimo', 'Efoua', 'Ekani', 'Elounda', 'Essomba', 'Etoga',
    'Fokou', 'Fotso', 'Kamdem', 'Kamga', 'Kana', 'Kemajou', 'Kengne', 'Kouam',
    'Mbala', 'Mbarga', 'Mbassi', 'Mbida', 'Mengue', 'Meva\'a', 'Moukoko', 'Mouliom',
    'Nana', 'Ndam', 'Ndjock', 'Ndongo', 'Ngo Bassong', 'Ngassa', 'Ngoumou', 'Nguele',
    'Njike', 'Njoya', 'Nkolo', 'Nkoulou', 'Nomo', 'Nsangou', 'Ntamack', 'Ondoua',
    'Onana', 'Owona', 'Sadjo', 'Sali', 'Simo', 'Sohaing', 'Talla', 'Tchakounte',
    'Tchamba', 'Tchoumi', 'Tedjou', 'Tsafack', 'Wandji', 'Yebga', 'Zambo', 'Zoa',
);

$PRENOMS_M = array(
    'Achille', 'Alain', 'Alphonse', 'Armand', 'Arnaud', 'Aurélien', 'Blaise', 'Boris',
    'Brice', 'Cédric', 'Charles', 'Christian', 'Cyrille', 'Daniel', 'Didier', 'Emmanuel',
    'Eric', 'Ernest', 'Fabrice', 'Franck', 'Gaston', 'Georges', 'Gérard', 'Gilbert',
    'Guy', 'Hervé', 'Ibrahim', 'Idriss', 'Jacques', 'Jean', 'Joseph', 'Landry',
    'Laurent', 'Ludovic', 'Marcel', 'Martin', 'Maurice', 'Michel', 'Moussa', 'Narcisse',
    'Nathan', 'Olivier', 'Parfait', 'Pascal', 'Patrick', 'Paul', 'Pierre', 'Roger',
    'Samuel', 'Serge', 'Simon', 'Stéphane', 'Sylvain', 'Thierry', 'Ulrich', 'Valery',
    'Vincent', 'Wilfried', 'Yannick', 'Yves',
);

$PRENOMS_F = array(
    'Adèle', 'Adeline', 'Aimée', 'Alice', 'Amina', 'Angèle', 'Anne', 'Ariane',
    'Armelle', 'Aurélie', 'Bernadette', 'Blandine', 'Brigitte', 'Carine', 'Cécile', 'Céline',
    'Chantal', 'Christelle', 'Claire', 'Clarisse', 'Danielle', 'Delphine', 'Diane', 'Edwige',
    'Elise', 'Emilienne', 'Estelle', 'Fadimatou', 'Flore', 'Francine', 'Gaëlle', 'Georgette',
    'Grâce', 'Hortense', 'Ingrid', 'Irène', 'Joséphine', 'Judith', 'Julienne', 'Larissa',
    'Laure', 'Léonie', 'Linda', 'Lucie', 'Madeleine', 'Marguerite', 'Marie', 'Mireille',
    'Nadège', 'Nathalie', 'Odile', 'Pauline', 'Rachel', 'Rosine', 'Sandrine', 'Solange',
    'Sylvie', 'Thérèse', 'Viviane', 'Yolande',
);

$PROFESSIONS = array(
    'Enseignant', 'Commerçant', 'Agriculteur', 'Fonctionnaire', 'Infirmier',
    'Chauffeur', 'Mécanicien', 'Comptable', 'Ingénieur', 'Couturier',
    'Menuisier', 'Militaire', 'Médecin', 'Technicien', 'Retraité', 'Sans emploi',
    'Cadre de banque', 'Restaurateur', 'Transporteur', 'Éleveur',
);

$QUARTIERS = array(
    'Bastos', 'Biyem-Assi', 'Mvog-Ada', 'Nkolbisson', 'Emana', 'Nsam', 'Mendong',
    'Akwa', 'Bonabéri', 'Bonamoussadi', 'Deido', 'Makepe', 'New-Bell', 'Logpom',
    'Tsinga', 'Mokolo', 'Ndogbong', 'Nylon', 'Djeuga', 'Camp-Sic',
);

$LIEUX_CERTIF = array(
    'Hôpital Central de Yaoundé', 'Hôpital Général de Douala', 'CHU de Yaoundé',
    'Hôpital de District de Bafoussam', 'Centre Médico-Social de l\'UEB',
    'Hôpital Régional de Bamenda', 'Hôpital Laquintinie', 'Clinique de la Cathédrale',
);

/* ==========================================================================
 * 3. Chargement des tables de référence (aucun ID en dur)
 * ========================================================================== */

function demo_charger_referentiels() {
    global $wpdb;

    $ref = array();

    $ref['facultes'] = $wpdb->get_results( 'SELECT id, code, nom_fr FROM ueb_facultes ORDER BY id', ARRAY_A );

    // Filières regroupées par faculté.
    $ref['filieres_par_faculte'] = array();
    foreach ( $wpdb->get_results( 'SELECT id, code, libelle, faculte_id, type_formation FROM ueb_filieres ORDER BY id', ARRAY_A ) as $f ) {
        $ref['filieres_par_faculte'][ (int) $f['faculte_id'] ][] = $f;
    }

    // Diplômes indexés par code.
    $ref['diplomes'] = array();
    foreach ( $wpdb->get_results( 'SELECT id, code, libelle FROM ueb_diplomes_admission', ARRAY_A ) as $d ) {
        $ref['diplomes'][ $d['code'] ] = (int) $d['id'];
    }

    // Spécialités indexées [diplome_id][faculte_id] => liste.
    $ref['specialites'] = array();
    foreach ( $wpdb->get_results( 'SELECT id, code, faculte_id, diplome_id FROM ueb_specialites_diplome', ARRAY_A ) as $s ) {
        $ref['specialites'][ (int) $s['diplome_id'] ][ (int) $s['faculte_id'] ][] = $s;
    }

    // Niveaux LMD indexés par code.
    $ref['niveaux'] = array();
    foreach ( $wpdb->get_results( 'SELECT id, code FROM ueb_niveaux_lmd', ARRAY_A ) as $n ) {
        $ref['niveaux'][ $n['code'] ] = (int) $n['id'];
    }

    // Mentions indexées par code.
    $ref['mentions'] = array();
    foreach ( $wpdb->get_results( 'SELECT id, code FROM ueb_mentions', ARRAY_A ) as $m ) {
        $ref['mentions'][ $m['code'] ] = (int) $m['id'];
    }

    // Statuts étudiants indexés par code (cemac / hors_cemac).
    $ref['statuts_etudiants'] = array();
    foreach ( $wpdb->get_results( 'SELECT id, code FROM ueb_statuts_etudiants', ARRAY_A ) as $s ) {
        $ref['statuts_etudiants'][ $s['code'] ] = (int) $s['id'];
    }

    // Langues indexées par code.
    $ref['langues'] = array();
    foreach ( $wpdb->get_results( 'SELECT id, code FROM ueb_langues', ARRAY_A ) as $l ) {
        $ref['langues'][ $l['code'] ] = (int) $l['id'];
    }

    // Nationalités indexées par nom.
    $ref['nationalites'] = array();
    foreach ( $wpdb->get_results( 'SELECT id, nom FROM ueb_nationalites', ARRAY_A ) as $n ) {
        $ref['nationalites'][ $n['nom'] ] = (int) $n['id'];
    }

    // Situations matrimoniales par code.
    $ref['situations'] = array();
    foreach ( $wpdb->get_results( 'SELECT id, code FROM ueb_situations_matrimoniales', ARRAY_A ) as $s ) {
        $ref['situations'][ $s['code'] ] = (int) $s['id'];
    }

    $ref['statuts_socio'] = $wpdb->get_results( 'SELECT id, libelle FROM ueb_statuts_socio_professionnels', ARRAY_A );
    $ref['sports']        = $wpdb->get_col( 'SELECT id FROM ueb_sports' );
    $ref['arts']          = $wpdb->get_col( 'SELECT id FROM ueb_arts' );

    // Géographie : régions -> départements -> communes.
    $ref['regions'] = array();
    foreach ( $wpdb->get_results( 'SELECT id, code, nom FROM ueb_regions', ARRAY_A ) as $r ) {
        $ref['regions'][ (int) $r['id'] ] = $r;
    }

    $ref['departements_par_region'] = array();
    foreach ( $wpdb->get_results( 'SELECT id, nom, region_id FROM ueb_departements', ARRAY_A ) as $d ) {
        $ref['departements_par_region'][ (int) $d['region_id'] ][] = $d;
    }

    $ref['communes_par_departement'] = array();
    foreach ( $wpdb->get_results( 'SELECT id, nom, departement_id FROM ueb_communes', ARRAY_A ) as $c ) {
        $ref['communes_par_departement'][ (int) $c['departement_id'] ][] = $c;
    }

    return $ref;
}

/* ==========================================================================
 * 4. Pondérations statistiques
 * ========================================================================== */

/** Répartition cible des facultés (~40 / 28 / 20 / 12 %). */
function demo_poids_facultes( array $facultes ) {
    $par_code = array( 'FS' => 40, 'FSEG' => 28, 'FALSH' => 20, 'FSJP' => 12 );
    $poids    = array();
    $rang     = array( 40, 28, 20, 12 );

    foreach ( array_values( $facultes ) as $i => $f ) {
        // Poids par code si connu, sinon on retombe sur l'ordre d'affichage,
        // et enfin sur un poids résiduel pour toute faculté supplémentaire.
        if ( isset( $par_code[ $f['code'] ] ) ) {
            $poids[ (int) $f['id'] ] = $par_code[ $f['code'] ];
        } elseif ( isset( $rang[ $i ] ) ) {
            $poids[ (int) $f['id'] ] = $rang[ $i ];
        } else {
            $poids[ (int) $f['id'] ] = 5;
        }
    }

    return $poids;
}

/**
 * Répartit $n unités entre des catégories pondérées (méthode du plus fort
 * reste). Utilisé pour les facultés et le sexe : un tirage purement aléatoire
 * dévie facilement de 5 points sur 150 dossiers, ce qui rend les graphiques du
 * dashboard peu représentatifs. Les quotas garantissent les proportions
 * annoncées, et le mélange final (shuffle) casse tout ordre visible.
 *
 * @param int   $n     Total à répartir.
 * @param array $poids array( clé => poids ).
 * @return array array( clé => nombre d'unités ).
 */
function demo_repartir_quota( $n, array $poids ) {
    $total = array_sum( $poids );
    if ( $total <= 0 ) {
        return array_fill_keys( array_keys( $poids ), 0 );
    }

    $quotas   = array();
    $restes   = array();
    $attribue = 0;

    foreach ( $poids as $cle => $p ) {
        $exact          = $n * $p / $total;
        $quotas[ $cle ] = (int) floor( $exact );
        $restes[ $cle ] = $exact - $quotas[ $cle ];
        $attribue      += $quotas[ $cle ];
    }

    arsort( $restes );
    foreach ( array_keys( $restes ) as $cle ) {
        if ( $attribue >= $n ) {
            break;
        }
        $quotas[ $cle ]++;
        $attribue++;
    }

    return $quotas;
}

/** Probabilité d'être un homme, variable selon la faculté (moyenne ~53 %). */
function demo_proba_homme( $code_faculte ) {
    $map = array(
        'FS'    => 0.62,  // sciences : plus d'hommes
        'FSEG'  => 0.50,
        'FALSH' => 0.40,  // lettres : plus de femmes
        'FSJP'  => 0.55,
    );
    return isset( $map[ $code_faculte ] ) ? $map[ $code_faculte ] : 0.53;
}

/**
 * Courbe de dépôt sur DEMO_JOURS jours : tendance croissante (la campagne
 * s'accélère à l'approche de la clôture) + bruit + creux le week-end.
 * Retourne un tableau [offset_jour_depuis_debut] => poids.
 */
function demo_courbe_journaliere( $jours ) {
    $poids = array();
    $debut = strtotime( '-' . ( $jours - 1 ) . ' days midnight' );

    for ( $i = 0; $i < $jours; $i++ ) {
        $jour     = strtotime( "+{$i} days", $debut );
        $tendance = 1 + 2.2 * ( $i / max( 1, $jours - 1 ) );        // x1 -> x3.2
        $bruit    = mt_rand( 70, 130 ) / 100;                        // +/- 30 %
        $dow      = (int) date( 'N', $jour );                        // 1=lundi ... 7=dimanche
        $weekend  = ( 6 === $dow ) ? 0.55 : ( ( 7 === $dow ) ? 0.35 : 1.0 );

        $poids[ $i ] = $tendance * $bruit * $weekend;
    }

    return $poids;
}

/** Répartit $n dossiers sur les jours selon la courbe, puis horodate. */
function demo_generer_dates( $n, $jours ) {
    $poids = demo_courbe_journaliere( $jours );
    $total = array_sum( $poids );
    $debut = strtotime( '-' . ( $jours - 1 ) . ' days midnight' );

    $dates = array();
    foreach ( $poids as $i => $p ) {
        $nb = (int) floor( $n * $p / $total );
        for ( $k = 0; $k < $nb; $k++ ) {
            $dates[] = $i;
        }
    }
    // Complément (arrondis) réparti sur les jours les plus récents.
    while ( count( $dates ) < $n ) {
        $dates[] = mt_rand( (int) ( $jours * 0.6 ), $jours - 1 );
    }
    shuffle( $dates );
    $dates = array_slice( $dates, 0, $n );

    $horodates = array();
    foreach ( $dates as $offset ) {
        // Heure de dépôt : plage 07h-22h, pic en milieu de journée.
        $heure  = (int) round( demo_gauss( 13, 3.2, 7, 22 ) );
        $minute = mt_rand( 0, 59 );
        $sec    = mt_rand( 0, 59 );
        $ts     = strtotime( "+{$offset} days", $debut ) + $heure * 3600 + $minute * 60 + $sec;
        // Jamais dans le futur.
        $horodates[] = min( $ts, time() - mt_rand( 60, 3600 ) );
    }
    sort( $horodates );

    return $horodates;
}

/* ==========================================================================
 * 5. Génération d'un dossier
 * ========================================================================== */

/**
 * Construit la liste des "plans" (faculté + sexe) des n dossiers à créer,
 * en respectant les quotas cibles, puis la mélange.
 */
function demo_planifier( $n, array $facultes ) {
    $codes = array();
    foreach ( $facultes as $f ) {
        $codes[ (int) $f['id'] ] = $f['code'];
    }

    $quota_fac = demo_repartir_quota( $n, demo_poids_facultes( $facultes ) );
    $plans     = array();

    foreach ( $quota_fac as $faculte_id => $nb ) {
        if ( $nb < 1 ) {
            continue;
        }

        $p_homme = demo_proba_homme( $codes[ $faculte_id ] );

        // Quota d'hommes + un léger bruit (l'écart-type binomial), pour que le
        // ratio ne tombe pas systématiquement sur la valeur théorique exacte.
        $bruit = demo_gauss( 0, sqrt( $nb * $p_homme * ( 1 - $p_homme ) ) * 0.6, -$nb, $nb );
        $nb_h  = (int) min( $nb, max( 0, round( $nb * $p_homme + $bruit ) ) );

        for ( $i = 0; $i < $nb; $i++ ) {
            $plans[] = array(
                'faculte_id' => (int) $faculte_id,
                'code_fac'   => $codes[ $faculte_id ],
                'sexe'       => ( $i < $nb_h ) ? 'M' : 'F',
            );
        }
    }

    shuffle( $plans );

    return $plans;
}

function demo_generer_dossier( array $ref, $numero_dossier, $timestamp, array $plan ) {
    global $NOMS, $PRENOMS_M, $PRENOMS_F, $PROFESSIONS, $QUARTIERS, $LIEUX_CERTIF;

    /* --- Faculté & sexe : imposés par le plan (quotas) ---------------- */
    $faculte_id = (int) $plan['faculte_id'];
    $code_fac   = $plan['code_fac'];
    $sexe       = $plan['sexe'];

    /* --- Filières (cohérentes avec la faculté) ----------------------- */
    $filieres = isset( $ref['filieres_par_faculte'][ $faculte_id ] )
        ? $ref['filieres_par_faculte'][ $faculte_id ]
        : array();

    if ( ! $filieres ) {
        return null; // faculté sans filière : dossier impossible, on saute.
    }

    // On privilégie les filières classiques (les LP "pro" restent minoritaires).
    $poids_fil = array();
    foreach ( $filieres as $idx => $fil ) {
        $poids_fil[ $idx ] = ( 'pro' === $fil['type_formation'] ) ? 0.35 : 1.0;
    }
    $idx1     = (int) demo_weighted( $poids_fil );
    $filiere1 = $filieres[ $idx1 ];

    $restantes = $filieres;
    unset( $restantes[ $idx1 ] );
    $restantes = array_values( $restantes );

    $filiere2 = ( $restantes && demo_chance( 0.75 ) ) ? demo_pick( $restantes ) : null;
    if ( $filiere2 ) {
        $restantes = array_values( array_filter(
            $restantes,
            function ( $f ) use ( $filiere2 ) { return $f['id'] !== $filiere2['id']; }
        ) );
    }
    $filiere3 = ( $restantes && demo_chance( 0.35 ) ) ? demo_pick( $restantes ) : null;

    // Le type de formation du dossier suit celui du 1er choix.
    $type_formation = $filiere1['type_formation'];

    /* --- Diplôme d'admission + niveau LMD cohérents ------------------ */
    $poids_dip = array(
        'bac'       => 66,
        'gce_ol'    => 10,
        'releve_n1' => 6,
        'releve_n2' => 5,
        'licence'   => 7,
        'releve_m1' => 3,
        'master'    => 3,
    );
    // On ne garde que les diplômes réellement présents en base.
    $poids_dip = array_intersect_key( $poids_dip, $ref['diplomes'] );
    $code_dip  = demo_weighted( $poids_dip );
    $diplome_id = $ref['diplomes'][ $code_dip ];

    $niveau_par_diplome = array(
        'bac'       => 'L1',
        'gce_ol'    => 'L1',
        'releve_n1' => 'L2',
        'releve_n2' => 'L3',
        'licence'   => 'M1',
        'releve_m1' => 'M2',
        'master'    => 'DOC',
    );
    $code_niv   = isset( $niveau_par_diplome[ $code_dip ] ) ? $niveau_par_diplome[ $code_dip ] : 'L1';
    $niveau_id  = isset( $ref['niveaux'][ $code_niv ] ) ? $ref['niveaux'][ $code_niv ] : null;

    /* --- Spécialité : seuls bac et gce_ol en ont ---------------------- */
    $specialite_id = null;
    if ( isset( $ref['specialites'][ $diplome_id ][ $faculte_id ] ) ) {
        $liste = $ref['specialites'][ $diplome_id ][ $faculte_id ];

        // Pondération par série : les séries scientifiques dominent en FS,
        // les séries littéraires/économiques ailleurs.
        $poids_spe = array();
        foreach ( $liste as $i => $spe ) {
            $serie = strtoupper( substr( $spe['code'], 0, 1 ) );
            if ( 'FS' === $code_fac ) {
                $p = array( 'C' => 30, 'D' => 35, 'E' => 12, 'F' => 8, 'G' => 5, 'T' => 10 );
            } elseif ( 'FSEG' === $code_fac ) {
                $p = array( 'A' => 15, 'B' => 25, 'C' => 12, 'D' => 15, 'G' => 25, 'T' => 8 );
            } elseif ( 'FALSH' === $code_fac ) {
                $p = array( 'A' => 45, 'B' => 20, 'C' => 8, 'D' => 15, 'G' => 8, 'T' => 4 );
            } else {
                $p = array( 'A' => 35, 'B' => 25, 'C' => 10, 'D' => 15, 'G' => 12, 'T' => 3 );
            }
            $poids_spe[ $i ] = isset( $p[ $serie ] ) ? $p[ $serie ] : 10;
        }
        $specialite_id = (int) $liste[ (int) demo_weighted( $poids_spe ) ]['id'];
    }
    // Si le diplôme n'a pas de spécialité (relevés, licence, master), ou si
    // la faculté n'en propose pas pour ce diplôme, on laisse NULL : la
    // colonne est nullable et l'appli fait pareil.

    /* --- Année d'obtention + moyenne + mention ----------------------- */
    $recul_par_niveau = array( 'L1' => 0, 'L2' => 1, 'L3' => 2, 'M1' => 3, 'M2' => 4, 'DOC' => 5 );
    $recul            = isset( $recul_par_niveau[ $code_niv ] ) ? $recul_par_niveau[ $code_niv ] : 0;
    $annee_obtention  = (int) date( 'Y', $timestamp ) - $recul - (int) demo_weighted( array( 0 => 70, 1 => 22, 2 => 8 ) );

    $moyenne = round( demo_gauss( 12.6, 1.9, 10.0, 19.5 ), 2 );

    if ( $moyenne >= 18 )      { $code_mention = 'excellent'; }
    elseif ( $moyenne >= 16 )  { $code_mention = 'tres_bien'; }
    elseif ( $moyenne >= 14 )  { $code_mention = 'bien'; }
    elseif ( $moyenne >= 12 )  { $code_mention = 'assez_bien'; }
    else                       { $code_mention = 'passable'; }
    $mention_id = isset( $ref['mentions'][ $code_mention ] ) ? $ref['mentions'][ $code_mention ] : null;

    /* --- Identité ----------------------------------------------------- */
    $nom    = demo_pick( $NOMS );
    $prenom = ( 'M' === $sexe ) ? demo_pick( $PRENOMS_M ) : demo_pick( $PRENOMS_F );

    // Âge cohérent avec le niveau visé (18 ans en L1, un peu plus au-delà).
    $age_base      = 18 + $recul + (int) demo_weighted( array( 0 => 45, 1 => 30, 2 => 15, 3 => 7, 4 => 3 ) );
    $annee_naiss   = (int) date( 'Y', $timestamp ) - $age_base;
    $date_naissance = sprintf( '%04d-%02d-%02d', $annee_naiss, mt_rand( 1, 12 ), mt_rand( 1, 28 ) );

    /* --- Géographie : région -> département -> commune ---------------- */
    // Régions les plus peuplées légèrement sur-représentées.
    $poids_region = array();
    foreach ( $ref['regions'] as $rid => $r ) {
        $gros = array( 'CENTRE' => 22, 'LITTORAL' => 18, 'OUEST' => 15, 'NORD-OUEST' => 10, 'EXTREME-NORD' => 9 );
        $poids_region[ $rid ] = isset( $gros[ strtoupper( $r['nom'] ) ] ) ? $gros[ strtoupper( $r['nom'] ) ] : 5;
    }
    $region_id = (int) demo_weighted( $poids_region );

    $departement_id = null;
    $commune_id     = null;
    $nom_commune    = 'Yaoundé';

    if ( ! empty( $ref['departements_par_region'][ $region_id ] ) ) {
        $dep            = demo_pick( $ref['departements_par_region'][ $region_id ] );
        $departement_id = (int) $dep['id'];

        if ( ! empty( $ref['communes_par_departement'][ $departement_id ] ) ) {
            $com         = demo_pick( $ref['communes_par_departement'][ $departement_id ] );
            $commune_id  = (int) $com['id'];
            $nom_commune = $com['nom'];
        }
    }

    /* --- Nationalité / statut étudiant (CEMAC ou non) ----------------- */
    $poids_nat = array();
    foreach ( $ref['nationalites'] as $nom_nat => $nid ) {
        $poids_nat[ $nid ] = ( 'Camerounaise' === $nom_nat ) ? 88 : 1;
    }
    $nationalite_id  = (int) demo_weighted( $poids_nat );
    $nom_nationalite = array_search( $nationalite_id, $ref['nationalites'], true );

    $cemac = array( 'Camerounaise', 'Tchadienne', 'Centrafricaine', 'Congolaise', 'Gabonaise', 'Équato-Guinéenne' );
    $code_statut_etu = in_array( $nom_nationalite, $cemac, true ) ? 'cemac' : 'hors_cemac';
    $statut_etudiant_id = isset( $ref['statuts_etudiants'][ $code_statut_etu ] )
        ? $ref['statuts_etudiants'][ $code_statut_etu ]
        : null;

    /* --- Divers ------------------------------------------------------- */
    $langue_id = null;
    if ( $ref['langues'] ) {
        $poids_langue = array();
        foreach ( $ref['langues'] as $code_lg => $lid ) {
            $poids_langue[ $lid ] = ( 'fr' === $code_lg ) ? 76 : 24;
        }
        $langue_id = (int) demo_weighted( $poids_langue );
    }

    $situation_id = null;
    if ( $ref['situations'] ) {
        $poids_sit = array();
        foreach ( $ref['situations'] as $code_sit => $sid ) {
            $map = array( 'celibataire' => 91, 'marie' => 6.5, 'divorce' => 1.5, 'veuf' => 1 );
            $poids_sit[ $sid ] = isset( $map[ $code_sit ] ) ? $map[ $code_sit ] : 1;
        }
        $situation_id = (int) demo_weighted( $poids_sit );
    }

    $statut_socio_id = null;
    if ( $ref['statuts_socio'] ) {
        $poids_socio = array();
        foreach ( $ref['statuts_socio'] as $s ) {
            $poids_socio[ (int) $s['id'] ] = ( false !== stripos( $s['libelle'], 'tudiant' ) ) ? 72 : 4;
        }
        $statut_socio_id = (int) demo_weighted( $poids_socio );
    }

    $sport_id = ( $ref['sports'] && demo_chance( 0.88 ) ) ? (int) demo_pick( $ref['sports'] ) : null;
    $art_id   = ( $ref['arts'] && demo_chance( 0.70 ) ) ? (int) demo_pick( $ref['arts'] ) : null;

    /* --- Contact ------------------------------------------------------ */
    $base_email = demo_slug( $prenom ) . '.' . demo_slug( $nom );
    $domaine    = demo_weighted( array( 'gmail.com' => 70, 'yahoo.fr' => 15, 'hotmail.com' => 8, 'outlook.com' => 7 ) );
    $email      = $base_email . mt_rand( 1, 99 ) . '@' . $domaine;
    $adresse    = demo_pick( $QUARTIERS ) . ', ' . $nom_commune;

    /* --- Filiation ---------------------------------------------------- */
    $nom_pere  = $nom . ' ' . demo_pick( $PRENOMS_M );
    $nom_mere  = demo_pick( $PRENOMS_F ) . ' ' . demo_pick( $NOMS );
    $a_tuteur  = demo_chance( 0.35 );

    /* --- Certificat médical ------------------------------------------- */
    $a_certif = demo_chance( 0.6 );

    $data = array(
        'numero_dossier'                => $numero_dossier,
        'statut'                        => 'soumis',
        'faculte_id'                    => $faculte_id,
        'diplome_admission_id'          => $diplome_id,
        'specialite_diplome_id'         => $specialite_id,
        'niveau_lmd_id'                 => $niveau_id,
        'type_formation'                => $type_formation,
        'filiere_1_id'                  => (int) $filiere1['id'],
        'filiere_2_id'                  => $filiere2 ? (int) $filiere2['id'] : null,
        'filiere_3_id'                  => $filiere3 ? (int) $filiere3['id'] : null,
        'annee_obtention'               => $annee_obtention,
        'moyenne_diplome'               => $moyenne,
        'mention_id'                    => $mention_id,
        'statut_etudiant_id'            => $statut_etudiant_id,
        'nom'                           => $nom,
        'prenom'                        => $prenom,
        'sexe'                          => $sexe,
        'date_naissance'                => $date_naissance,
        'lieu_naissance'                => $nom_commune,
        'nationalite_id'                => $nationalite_id,
        'premiere_langue_id'            => $langue_id,
        'situation_matrimoniale_id'     => $situation_id,
        'statut_socio_professionnel_id' => $statut_socio_id,
        'handicap'                      => demo_chance( 0.035 ) ? 'oui' : 'non',
        'email'                         => $email,
        'adresse'                       => $adresse,
        'region_origine_id'             => $region_id,
        'departement_origine_id'        => $departement_id,
        'commune_origine_id'            => $commune_id,
        'arrondissement_origine'        => $nom_commune . ' ' . mt_rand( 1, 6 ),
        'nom_pere'                      => $nom_pere,
        'numero_pere'                   => demo_telephone(),
        'profession_pere'               => demo_pick( $PROFESSIONS ),
        'nom_mere'                      => $nom_mere,
        'numero_mere'                   => demo_telephone(),
        'profession_mere'               => demo_pick( $PROFESSIONS ),
        'nom_tuteur'                    => $a_tuteur ? ( demo_pick( $PRENOMS_M ) . ' ' . demo_pick( $NOMS ) ) : null,
        'numero_tuteur'                 => $a_tuteur ? demo_telephone() : null,
        'sport_prefere_id'              => $sport_id,
        'art_pratique_id'               => $art_id,
        'numero_certificat_medical'     => $a_certif ? sprintf( 'CM-%d-%05d', date( 'Y', $timestamp ), mt_rand( 1, 99999 ) ) : null,
        'lieu_obtention_certificat'     => $a_certif ? demo_pick( $LIEUX_CERTIF ) : null,
        'date_creation'                 => date( 'Y-m-d H:i:s', $timestamp ),
        'date_modification'             => date( 'Y-m-d H:i:s', $timestamp + mt_rand( 0, 900 ) ),
    );

    return $data;
}

/** Formats $wpdb->insert(), alignés sur l'ordre des clés de $data. */
function demo_formats() {
    return array(
        '%s', '%s',                                     // numero_dossier, statut
        '%d', '%d', '%d', '%d', '%s', '%d', '%d', '%d', // formation
        '%d', '%f', '%d', '%d',                         // annee, moyenne, mention, statut étudiant
        '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', // état civil
        '%s', '%s', '%d', '%d', '%d', '%s',             // contact & origine
        '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', // filiation
        '%d', '%d', '%s', '%s',                         // divers / visite médicale
        '%s', '%s',                                     // dates
    );
}

/* ==========================================================================
 * 6. Commandes
 * ========================================================================== */

function demo_prochain_numero_libre() {
    global $wpdb;

    $annee = (int) date( 'Y' );
    $max   = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(numero_dossier, '-', -1) AS UNSIGNED)), 0)
         FROM ueb_preinscriptions
         WHERE numero_dossier LIKE %s",
        DEMO_PREFIX . 'UEB-' . $annee . '-%'
    ) );

    return $max + 1;
}

function demo_install( $n ) {
    global $wpdb;

    $ref = demo_charger_referentiels();

    if ( ! $ref['facultes'] ) {
        demo_die( 'Aucune faculté en base : lance d\'abord le seed des tables de référence (charge une page de wp-admin).' );
    }

    $annee     = (int) date( 'Y' );
    $sequence  = demo_prochain_numero_libre();
    $dates     = demo_generer_dates( $n, DEMO_JOURS );
    $plans     = demo_planifier( $n, $ref['facultes'] );
    $formats   = demo_formats();

    $crees      = 0;
    $erreurs    = 0;
    $nb_tel     = 0;
    $par_fac    = array();
    $par_sexe   = array( 'M' => 0, 'F' => 0 );

    demo_out( sprintf( 'Génération de %d dossiers de démonstration (préfixe "%s")…', $n, DEMO_PREFIX ) );

    // Une transaction : en cas de pépin, la base reste propre.
    $wpdb->query( 'START TRANSACTION' );

    foreach ( $dates as $i => $timestamp ) {
        if ( ! isset( $plans[ $i ] ) ) {
            break; // sécurité : jamais atteint (plans et dates ont la même taille).
        }

        $numero  = sprintf( DEMO_NUM_FORMAT, $annee, $sequence++ );
        $dossier = demo_generer_dossier( $ref, $numero, $timestamp, $plans[ $i ] );

        if ( null === $dossier ) {
            $erreurs++;
            continue;
        }

        $ok = $wpdb->insert( 'ueb_preinscriptions', $dossier, $formats );

        if ( false === $ok ) {
            $erreurs++;
            demo_out( '  ! Échec insertion ' . $numero . ' : ' . $wpdb->last_error );
            continue;
        }

        $preinscription_id = (int) $wpdb->insert_id;
        $crees++;

        $fac_id = (int) $dossier['faculte_id'];
        $par_fac[ $fac_id ] = isset( $par_fac[ $fac_id ] ) ? $par_fac[ $fac_id ] + 1 : 1;
        $par_sexe[ $dossier['sexe'] ]++;

        // 1 à 2 téléphones candidat, plus celui du tuteur le cas échéant.
        $nb_candidat = demo_chance( 0.45 ) ? 2 : 1;
        for ( $t = 0; $t < $nb_candidat; $t++ ) {
            $res = $wpdb->insert(
                'ueb_preinscriptions_telephones',
                array(
                    'preinscription_id' => $preinscription_id,
                    'type'              => 'candidat',
                    'numero'            => demo_telephone(),
                ),
                array( '%d', '%s', '%s' )
            );
            if ( false === $res ) {
                $erreurs++;
                demo_out( '  ! Échec insertion téléphone pour ' . $numero . ' : ' . $wpdb->last_error );
            } else {
                $nb_tel++;
            }
        }

        if ( ! empty( $dossier['numero_tuteur'] ) ) {
            $res = $wpdb->insert(
                'ueb_preinscriptions_telephones',
                array(
                    'preinscription_id' => $preinscription_id,
                    'type'              => 'tuteur',
                    'numero'            => $dossier['numero_tuteur'],
                ),
                array( '%d', '%s', '%s' )
            );
            if ( false === $res ) {
                $erreurs++;
            } else {
                $nb_tel++;
            }
        }
    }

    if ( $erreurs > 0 ) {
        $wpdb->query( 'ROLLBACK' );
        demo_die( sprintf( '%d erreur(s) SQL : aucune donnée insérée (ROLLBACK).', $erreurs ) );
    }

    $wpdb->query( 'COMMIT' );

    /* --- Résumé ------------------------------------------------------- */
    $noms_fac = array();
    foreach ( $ref['facultes'] as $f ) {
        $noms_fac[ (int) $f['id'] ] = $f['code'] . ' — ' . $f['nom_fr'];
    }

    demo_out( '' );
    demo_out( '=== Résumé ===' );
    demo_out( sprintf( 'Dossiers créés   : %d', $crees ) );
    demo_out( sprintf( 'Téléphones créés : %d', $nb_tel ) );
    demo_out( sprintf( 'Erreurs SQL      : %d', $erreurs ) );
    demo_out( '' );
    demo_out( 'Répartition par faculté :' );
    arsort( $par_fac );
    foreach ( $par_fac as $fid => $nb ) {
        demo_out( sprintf(
            '  %-55s %4d  (%5.1f %%)',
            isset( $noms_fac[ $fid ] ) ? $noms_fac[ $fid ] : ( 'Faculté #' . $fid ),
            $nb,
            $crees ? $nb * 100 / $crees : 0
        ) );
    }
    demo_out( '' );
    demo_out( 'Répartition par sexe :' );
    foreach ( array( 'M' => 'Hommes', 'F' => 'Femmes' ) as $code => $libelle ) {
        demo_out( sprintf(
            '  %-10s %4d  (%5.1f %%)',
            $libelle,
            $par_sexe[ $code ],
            $crees ? $par_sexe[ $code ] * 100 / $crees : 0
        ) );
    }
    demo_out( '' );
    demo_out( sprintf( 'Période couverte : %s -> %s', date( 'Y-m-d', $dates[0] ), date( 'Y-m-d', end( $dates ) ) ) );
    demo_out( 'Pour tout supprimer : php tools/seed-demo.php --purge' );
}

function demo_purge() {
    global $wpdb;

    $like = DEMO_PREFIX . '%';

    $nb_dossiers = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM ueb_preinscriptions WHERE numero_dossier LIKE %s",
        $like
    ) );

    $nb_tel = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM ueb_preinscriptions_telephones t
         JOIN ueb_preinscriptions p ON p.id = t.preinscription_id
         WHERE p.numero_dossier LIKE %s",
        $like
    ) );

    if ( 0 === $nb_dossiers ) {
        demo_out( 'Aucun dossier de démonstration à supprimer.' );
        return;
    }

    // Les téléphones partent en cascade (FK ON DELETE CASCADE), mais on les
    // supprime explicitement d'abord : c'est plus lisible et ça reste correct
    // même si quelqu'un retire la cascade un jour.
    $wpdb->query( $wpdb->prepare(
        "DELETE t FROM ueb_preinscriptions_telephones t
         JOIN ueb_preinscriptions p ON p.id = t.preinscription_id
         WHERE p.numero_dossier LIKE %s",
        $like
    ) );

    $supprimes = $wpdb->query( $wpdb->prepare(
        "DELETE FROM ueb_preinscriptions WHERE numero_dossier LIKE %s",
        $like
    ) );

    if ( false === $supprimes ) {
        demo_die( 'Échec de la suppression : ' . $wpdb->last_error );
    }

    // Les brouillons de démo (si un jour on en crée) suivent la même règle.
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM ueb_preinscriptions_progression WHERE numero_dossier LIKE %s",
        $like
    ) );

    demo_out( sprintf( '%d dossier(s) de démonstration supprimé(s) (+ %d téléphone(s)).', $supprimes, $nb_tel ) );

    $restants = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ueb_preinscriptions' );
    demo_out( sprintf( 'Dossiers restants en base (vrais candidats) : %d', $restants ) );
}

/* ==========================================================================
 * 7. Point d'entrée CLI
 * ========================================================================== */

$args = array_slice( $argv, 1 );

if ( ! $args ) {
    demo_out( 'Usage :' );
    demo_out( '  php tools/seed-demo.php --install [n]   (défaut : 150)' );
    demo_out( '  php tools/seed-demo.php --purge' );
    exit( 0 );
}

switch ( $args[0] ) {
    case '--install':
        $n = isset( $args[1] ) ? (int) $args[1] : 150;
        if ( $n < 1 || $n > 100000 ) {
            demo_die( 'Nombre de dossiers invalide (1 à 100000).' );
        }
        demo_install( $n );
        break;

    case '--purge':
        demo_purge();
        break;

    default:
        demo_die( 'Option inconnue : ' . $args[0] . ' (attendu --install ou --purge).' );
}
