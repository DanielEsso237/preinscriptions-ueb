<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Moteur générique d'administration des tables de référence ueb_* (facultés,
 * filières, diplômes, régions, etc.) depuis la page dédiée page-references.php.
 *
 * Principe : un seul "registre" décrit chaque table gérable (nom de table,
 * colonnes, type de chaque colonne, éventuelle clé étrangère vers une autre
 * entrée du registre). Toutes les fonctions de listing/création/modification/
 * suppression sont génériques et lisent ce registre, plutôt que d'avoir une
 * fonction dédiée par table — ce qui évite de dupliquer la même logique
 * 17 fois pour 17 tables qui se ressemblent toutes (id + quelques colonnes
 * + parfois une FK).
 *
 * @package Preinscriptions_UEB
 */

/**
 * Registre des tables de référence administrables.
 *
 * Chaque entrée :
 * - table        : nom de la table MySQL (sans préfixe wp_, cf. db-schema.php)
 * - label        : nom affiché dans la navigation
 * - group        : regroupement visuel dans la navigation (Formation / Profil / Géographie / Site)
 * - order_by     : ORDER BY SQL (toujours préfixé "t." = alias de la table)
 * - search_columns : colonnes texte sur lesquelles la recherche porte
 * - columns      : colonnes éditables, dans l'ordre d'affichage du formulaire
 *     - label      : libellé du champ
 *     - type       : text | number | select | enum
 *     - required   : bool
 *     - maxlength  : pour les champs texte (correspond à la colonne SQL)
 *     - fk         : (type=select) clé du registre référencée par cette FK
 *     - options    : (type=enum) tableau valeur => libellé
 * - fk_options_sql : (optionnel) SQL personnalisé pour peupler les <select>
 *     d'autres tables qui référencent celle-ci. Sert à désambiguïser
 *     (ex : deux départements pourraient porter un nom proche, on affiche
 *     donc "Nom (Région)").
 *
 * @return array<string, array>
 */
function ueb_admin_ref_registry() {
    return array(

        /* ---------------------- Formation ---------------------- */

        'facultes' => array(
            'table'          => 'ueb_facultes',
            'label'          => 'Facultés',
            'group'          => 'Formation',
            'order_by'       => 't.nom_fr ASC',
            'search_columns' => array( 'code', 'nom_fr', 'nom_en' ),
            'columns'        => array(
                'code'   => array( 'label' => 'Code',        'type' => 'text', 'required' => true,  'maxlength' => 10 ),
                'nom_fr' => array( 'label' => 'Nom (FR)',     'type' => 'text', 'required' => true,  'maxlength' => 150 ),
                'nom_en' => array( 'label' => 'Nom (EN)',     'type' => 'text', 'required' => true,  'maxlength' => 150 ),
                'logo'   => array( 'label' => 'Logo (fichier)', 'type' => 'text', 'required' => false, 'maxlength' => 100 ),
            ),
            'label_col' => 'nom_fr',
        ),

        'diplomes_admission' => array(
            'table'          => 'ueb_diplomes_admission',
            'label'          => "Diplômes d'admission",
            'group'          => 'Formation',
            'order_by'       => 't.libelle ASC',
            'search_columns' => array( 'code', 'libelle' ),
            'columns'        => array(
                'code'    => array( 'label' => 'Code',    'type' => 'text', 'required' => true, 'maxlength' => 20 ),
                'libelle' => array( 'label' => 'Libellé',  'type' => 'text', 'required' => true, 'maxlength' => 100 ),
            ),
            'label_col' => 'libelle',
        ),

        'specialites_diplome' => array(
            'table'          => 'ueb_specialites_diplome',
            'label'          => 'Spécialités / séries',
            'group'          => 'Formation',
            'order_by'       => 't.libelle ASC',
            'search_columns' => array( 'code', 'libelle' ),
            'columns'        => array(
                'code'       => array( 'label' => 'Code',    'type' => 'text',   'required' => true, 'maxlength' => 30 ),
                'libelle'    => array( 'label' => 'Libellé', 'type' => 'text',   'required' => true, 'maxlength' => 150 ),
                'faculte_id' => array( 'label' => 'Faculté', 'type' => 'select', 'required' => true, 'fk' => 'facultes' ),
                'diplome_id' => array( 'label' => "Diplôme d'admission", 'type' => 'select', 'required' => true, 'fk' => 'diplomes_admission' ),
            ),
            'label_col' => 'libelle',
        ),

        'filieres' => array(
            'table'          => 'ueb_filieres',
            'label'          => 'Filières',
            'group'          => 'Formation',
            'order_by'       => 't.libelle ASC',
            'search_columns' => array( 'code', 'libelle' ),
            'columns'        => array(
                'code'           => array( 'label' => 'Code',    'type' => 'text',   'required' => true, 'maxlength' => 30 ),
                'libelle'        => array( 'label' => 'Libellé', 'type' => 'text',   'required' => true, 'maxlength' => 150 ),
                'faculte_id'     => array( 'label' => 'Faculté', 'type' => 'select', 'required' => true, 'fk' => 'facultes' ),
                'type_formation' => array(
                    'label'    => 'Type de formation',
                    'type'     => 'enum',
                    'required' => true,
                    'options'  => array( 'classique' => 'Classique', 'pro' => 'Licence Pro (LP)' ),
                ),
            ),
            'label_col' => 'libelle',
        ),

        'niveaux_lmd' => array(
            'table'          => 'ueb_niveaux_lmd',
            'label'          => 'Niveaux LMD',
            'group'          => 'Formation',
            'order_by'       => 't.ordre ASC',
            'search_columns' => array( 'code', 'libelle' ),
            'columns'        => array(
                'code'    => array( 'label' => 'Code',    'type' => 'text',   'required' => true, 'maxlength' => 20 ),
                'libelle' => array( 'label' => 'Libellé', 'type' => 'text',   'required' => true, 'maxlength' => 50 ),
                'ordre'   => array( 'label' => "Ordre d'affichage", 'type' => 'number', 'required' => false ),
            ),
            'label_col' => 'libelle',
        ),

        'mentions' => array(
            'table'          => 'ueb_mentions',
            'label'          => 'Mentions',
            'group'          => 'Formation',
            'order_by'       => 't.ordre ASC',
            'search_columns' => array( 'code', 'libelle' ),
            'columns'        => array(
                'code'    => array( 'label' => 'Code',    'type' => 'text',   'required' => true, 'maxlength' => 20 ),
                'libelle' => array( 'label' => 'Libellé', 'type' => 'text',   'required' => true, 'maxlength' => 50 ),
                'ordre'   => array( 'label' => "Ordre d'affichage", 'type' => 'number', 'required' => false ),
            ),
            'label_col' => 'libelle',
        ),

        'statuts_etudiants' => array(
            'table'          => 'ueb_statuts_etudiants',
            'label'          => 'Statuts étudiant',
            'group'          => 'Formation',
            'order_by'       => 't.libelle ASC',
            'search_columns' => array( 'code', 'libelle' ),
            'columns'        => array(
                'code'    => array( 'label' => 'Code',    'type' => 'text', 'required' => true, 'maxlength' => 20 ),
                'libelle' => array( 'label' => 'Libellé', 'type' => 'text', 'required' => true, 'maxlength' => 100 ),
            ),
            'label_col' => 'libelle',
        ),

        /* ------------------------ Profil ------------------------ */

        'langues' => array(
            'table'          => 'ueb_langues',
            'label'          => 'Langues',
            'group'          => 'Profil',
            'order_by'       => 't.libelle ASC',
            'search_columns' => array( 'code', 'libelle' ),
            'columns'        => array(
                'code'    => array( 'label' => 'Code',    'type' => 'text', 'required' => true, 'maxlength' => 10 ),
                'libelle' => array( 'label' => 'Libellé', 'type' => 'text', 'required' => true, 'maxlength' => 50 ),
            ),
            'label_col' => 'libelle',
        ),

        'situations_matrimoniales' => array(
            'table'          => 'ueb_situations_matrimoniales',
            'label'          => 'Situations matrimoniales',
            'group'          => 'Profil',
            'order_by'       => 't.libelle ASC',
            'search_columns' => array( 'code', 'libelle' ),
            'columns'        => array(
                'code'    => array( 'label' => 'Code',    'type' => 'text', 'required' => true, 'maxlength' => 20 ),
                'libelle' => array( 'label' => 'Libellé', 'type' => 'text', 'required' => true, 'maxlength' => 50 ),
            ),
            'label_col' => 'libelle',
        ),

        'statuts_socio' => array(
            'table'          => 'ueb_statuts_socio_professionnels',
            'label'          => 'Statuts socio-professionnels',
            'group'          => 'Profil',
            'order_by'       => 't.libelle ASC',
            'search_columns' => array( 'libelle' ),
            'columns'        => array(
                'libelle' => array( 'label' => 'Libellé', 'type' => 'text', 'required' => true, 'maxlength' => 100 ),
            ),
            'label_col' => 'libelle',
        ),

        'nationalites' => array(
            'table'          => 'ueb_nationalites',
            'label'          => 'Nationalités',
            'group'          => 'Profil',
            'order_by'       => 't.nom ASC',
            'search_columns' => array( 'nom' ),
            'columns'        => array(
                'nom' => array( 'label' => 'Nom', 'type' => 'text', 'required' => true, 'maxlength' => 100 ),
            ),
            'label_col' => 'nom',
        ),

        'sports' => array(
            'table'          => 'ueb_sports',
            'label'          => 'Sports',
            'group'          => 'Profil',
            'order_by'       => 't.libelle ASC',
            'search_columns' => array( 'libelle' ),
            'columns'        => array(
                'libelle' => array( 'label' => 'Libellé', 'type' => 'text', 'required' => true, 'maxlength' => 80 ),
            ),
            'label_col' => 'libelle',
        ),

        'arts' => array(
            'table'          => 'ueb_arts',
            'label'          => 'Arts pratiqués',
            'group'          => 'Profil',
            'order_by'       => 't.libelle ASC',
            'search_columns' => array( 'libelle' ),
            'columns'        => array(
                'libelle' => array( 'label' => 'Libellé', 'type' => 'text', 'required' => true, 'maxlength' => 80 ),
            ),
            'label_col' => 'libelle',
        ),

        /* --------------------- Géographie ------------------------ */

        'regions' => array(
            'table'          => 'ueb_regions',
            'label'          => 'Régions',
            'group'          => 'Géographie',
            'order_by'       => 't.nom ASC',
            'search_columns' => array( 'code', 'nom' ),
            'columns'        => array(
                'code' => array( 'label' => 'Code', 'type' => 'text', 'required' => true, 'maxlength' => 2 ),
                'nom'  => array( 'label' => 'Nom',  'type' => 'text', 'required' => true, 'maxlength' => 100 ),
            ),
            'label_col' => 'nom',
        ),

        'departements' => array(
            'table'          => 'ueb_departements',
            'label'          => 'Départements',
            'group'          => 'Géographie',
            'order_by'       => 't.nom ASC',
            'search_columns' => array( 'code', 'nom' ),
            'columns'        => array(
                'code'      => array( 'label' => 'Code',   'type' => 'text',   'required' => true, 'maxlength' => 4 ),
                'nom'       => array( 'label' => 'Nom',    'type' => 'text',   'required' => true, 'maxlength' => 100 ),
                'region_id' => array( 'label' => 'Région', 'type' => 'select', 'required' => true, 'fk' => 'regions' ),
            ),
            'label_col'      => 'nom',
            // Désambiguïse les options de <select> "Département" (utilisées
            // par la table des communes) en affichant la région entre parenthèses.
            'fk_options_sql' => "SELECT d.id, CONCAT(d.nom, ' (', r.nom, ')') AS libelle
                                  FROM ueb_departements d
                                  JOIN ueb_regions r ON r.id = d.region_id
                                  ORDER BY d.nom ASC",
        ),

        'communes' => array(
            'table'          => 'ueb_communes',
            'label'          => 'Communes',
            'group'          => 'Géographie',
            'order_by'       => 't.nom ASC',
            'search_columns' => array( 'code', 'nom' ),
            'columns'        => array(
                'code'           => array( 'label' => 'Code',       'type' => 'text',   'required' => true, 'maxlength' => 6 ),
                'nom'            => array( 'label' => 'Nom',        'type' => 'text',   'required' => true, 'maxlength' => 150 ),
                'departement_id' => array( 'label' => 'Département', 'type' => 'select', 'required' => true, 'fk' => 'departements' ),
            ),
            'label_col' => 'nom',
        ),

        /* ------------------------- Site --------------------------- */

        'reseaux_sociaux' => array(
            'table'          => 'ueb_reseaux_sociaux',
            'label'          => 'Réseaux sociaux',
            'group'          => 'Site',
            'order_by'       => 't.ordre ASC',
            'search_columns' => array( 'plateforme', 'url' ),
            'columns'        => array(
                'plateforme' => array( 'label' => 'Plateforme', 'type' => 'text',   'required' => true, 'maxlength' => 50 ),
                'url'        => array( 'label' => 'URL',        'type' => 'text',   'required' => true, 'maxlength' => 255 ),
                'icone'      => array( 'label' => 'Icône',      'type' => 'text',   'required' => false, 'maxlength' => 50 ),
                'ordre'      => array( 'label' => "Ordre d'affichage", 'type' => 'number', 'required' => false ),
                'actif'      => array(
                    'label'    => 'Actif',
                    'type'     => 'enum',
                    'required' => true,
                    'options'  => array( '1' => 'Oui', '0' => 'Non' ),
                ),
            ),
            'label_col' => 'plateforme',
        ),
    );
}

/**
 * Options id/libelle pour peupler un <select> qui référence la table $key
 * en clé étrangère. Utilise fk_options_sql si défini par l'entrée du
 * registre (désambiguïsation), sinon une requête simple id + label_col.
 *
 * @param string $key
 * @return array<object>
 */
function ueb_admin_ref_fk_options( $key ) {
    global $wpdb;

    $registry = ueb_admin_ref_registry();
    if ( ! isset( $registry[ $key ] ) ) {
        return array();
    }
    $cfg = $registry[ $key ];

    if ( ! empty( $cfg['fk_options_sql'] ) ) {
        return $wpdb->get_results( $cfg['fk_options_sql'] );
    }

    $sql = "SELECT id, {$cfg['label_col']} AS libelle FROM {$cfg['table']} ORDER BY {$cfg['label_col']} ASC";
    return $wpdb->get_results( $sql );
}

/**
 * Registre complet, prêt à être envoyé au JS via wp_localize_script :
 * chaque colonne "select" (FK ou enum) embarque directement ses options,
 * pour que le formulaire d'ajout/modification n'ait besoin d'aucun aller-
 * retour AJAX supplémentaire.
 *
 * @return array
 */
function ueb_admin_ref_get_registry_for_js() {
    $registry = ueb_admin_ref_registry();
    $out      = array();

    foreach ( $registry as $key => $cfg ) {
        $columns = array();

        foreach ( $cfg['columns'] as $col => $colcfg ) {
            $entry = array(
                'label'    => $colcfg['label'],
                'type'     => $colcfg['type'],
                'required' => ! empty( $colcfg['required'] ),
            );

            if ( isset( $colcfg['maxlength'] ) ) {
                $entry['maxlength'] = $colcfg['maxlength'];
            }

            if ( 'select' === $colcfg['type'] && ! empty( $colcfg['fk'] ) ) {
                $entry['options'] = ueb_admin_ref_fk_options( $colcfg['fk'] );
            }

            if ( 'enum' === $colcfg['type'] && ! empty( $colcfg['options'] ) ) {
                $opts = array();
                foreach ( $colcfg['options'] as $val => $label ) {
                    $opts[] = (object) array( 'id' => (string) $val, 'libelle' => $label );
                }
                $entry['options'] = $opts;
            }

            $columns[ $col ] = $entry;
        }

        $out[ $key ] = array(
            'label'   => $cfg['label'],
            'group'   => isset( $cfg['group'] ) ? $cfg['group'] : '',
            'columns' => $columns,
        );
    }

    return $out;
}

/**
 * Liste paginée + recherchée des lignes d'une table de référence, avec les
 * colonnes FK résolues en libellé (via LEFT JOIN) pour un affichage direct
 * dans le tableau sans requête supplémentaire côté JS.
 *
 * @param string $key
 * @param string $search
 * @param int    $page
 * @param int    $per_page
 * @return array { rows: array<array>, total: int, page: int, nb_pages: int }
 */
function ueb_admin_ref_list( $key, $search = '', $page = 1, $per_page = 20 ) {
    global $wpdb;

    $registry = ueb_admin_ref_registry();
    if ( ! isset( $registry[ $key ] ) ) {
        return array( 'rows' => array(), 'total' => 0, 'page' => 1, 'nb_pages' => 0 );
    }
    $cfg   = $registry[ $key ];
    $table = $cfg['table'];

    $select_cols = array( 't.id AS id' );
    $joins       = array();
    $i           = 0;

    foreach ( $cfg['columns'] as $col => $colcfg ) {
        if ( 'select' === $colcfg['type'] && ! empty( $colcfg['fk'] ) && isset( $registry[ $colcfg['fk'] ] ) ) {
            $i++;
            $alias  = 'j' . $i;
            $fk_cfg = $registry[ $colcfg['fk'] ];
            $joins[]       = "LEFT JOIN {$fk_cfg['table']} {$alias} ON {$alias}.id = t.{$col}";
            $select_cols[] = "t.{$col} AS {$col}";
            $select_cols[] = "{$alias}.{$fk_cfg['label_col']} AS {$col}__libelle";
        } else {
            $select_cols[] = "t.{$col} AS {$col}";
        }
    }

    $where  = '1=1';
    $params = array();

    $search = trim( (string) $search );
    if ( '' !== $search && ! empty( $cfg['search_columns'] ) ) {
        $likes = array();
        foreach ( $cfg['search_columns'] as $sc ) {
            $likes[]  = "t.{$sc} LIKE %s";
            $params[] = '%' . $wpdb->esc_like( $search ) . '%';
        }
        $where .= ' AND (' . implode( ' OR ', $likes ) . ')';
    }

    $sql_total = "SELECT COUNT(*) FROM {$table} t WHERE {$where}";
    if ( $params ) {
        $sql_total = $wpdb->prepare( $sql_total, $params );
    }
    $total = (int) $wpdb->get_var( $sql_total );

    $page     = max( 1, absint( $page ) );
    $per_page = max( 1, absint( $per_page ) );
    $offset   = ( $page - 1 ) * $per_page;

    $order_by = ! empty( $cfg['order_by'] ) ? $cfg['order_by'] : 't.id ASC';

    $sql = 'SELECT ' . implode( ', ', $select_cols ) . " FROM {$table} t " . implode( ' ', $joins )
         . " WHERE {$where} ORDER BY {$order_by} LIMIT %d OFFSET %d";

    $params_avec_limite   = $params;
    $params_avec_limite[] = $per_page;
    $params_avec_limite[] = $offset;

    $sql  = $wpdb->prepare( $sql, $params_avec_limite );
    $rows = $wpdb->get_results( $sql, ARRAY_A );

    return array(
        'rows'     => $rows,
        'total'    => $total,
        'page'     => $page,
        'nb_pages' => (int) ceil( $total / $per_page ),
    );
}

/**
 * Ligne brute (valeurs non résolues) d'une table de référence, pour
 * pré-remplir le formulaire de modification.
 *
 * @param string $key
 * @param int    $id
 * @return array|null
 */
function ueb_admin_ref_get( $key, $id ) {
    global $wpdb;

    $registry = ueb_admin_ref_registry();
    if ( ! isset( $registry[ $key ] ) ) {
        return null;
    }
    $cfg = $registry[ $key ];

    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$cfg['table']} WHERE id = %d", absint( $id ) ), ARRAY_A );

    return $row ?: null;
}

/**
 * Sanitise + valide les champs bruts reçus en POST selon la description du
 * registre. Ne connaît rien du contexte AJAX : réutilisable telle quelle.
 *
 * @param string $key
 * @param array  $raw Valeurs brutes indexées par nom de colonne.
 * @return array { data: array, errors: array<string> }
 */
function ueb_admin_ref_sanitize( $key, $raw ) {
    $registry = ueb_admin_ref_registry();
    $cfg      = $registry[ $key ];

    $data   = array();
    $errors = array();

    foreach ( $cfg['columns'] as $col => $colcfg ) {
        $val = isset( $raw[ $col ] ) ? wp_unslash( $raw[ $col ] ) : '';

        switch ( $colcfg['type'] ) {

            case 'number':
                // Toutes les colonnes numériques du registre (les "ordre"
                // d'affichage) sont NOT NULL DEFAULT 0 en base : un champ
                // laissé vide devient 0, jamais NULL (sinon l'INSERT échoue).
                $val = ( '' === trim( (string) $val ) ) ? 0 : absint( $val );
                break;

            case 'select':
                $val = absint( $val );
                if ( ! $val ) {
                    $val = null;
                }
                break;

            case 'enum':
                $val = sanitize_text_field( (string) $val );
                if ( ! isset( $colcfg['options'][ $val ] ) ) {
                    $val = '';
                }
                break;

            case 'text':
            default:
                $val = sanitize_text_field( (string) $val );
                if ( ! empty( $colcfg['maxlength'] ) ) {
                    $val = mb_substr( $val, 0, $colcfg['maxlength'] );
                }
                break;
        }

        if ( ! empty( $colcfg['required'] ) && ( '' === $val || null === $val ) ) {
            $errors[] = $colcfg['label'] . ' est obligatoire.';
        }

        $data[ $col ] = $val;
    }

    return array( 'data' => $data, 'errors' => $errors );
}

/**
 * Traduit une erreur MySQL brute en message compréhensible pour un
 * gestionnaire non technique.
 *
 * @param string $error
 * @return string
 */
function ueb_admin_ref_friendly_db_error( $error ) {
    if ( false !== stripos( $error, 'foreign key constraint' ) ) {
        return 'Impossible : cet élément est déjà utilisé ailleurs (dans un dossier de préinscription ou une autre table de référence). Il faut d’abord retirer ces usages.';
    }
    if ( false !== stripos( $error, 'Duplicate entry' ) ) {
        return 'Cette valeur existe déjà (code ou libellé en double).';
    }
    return 'Erreur base de données : ' . $error;
}

/**
 * Crée une ligne.
 *
 * @param string $key
 * @param array  $raw
 * @return array { success: bool, id?: int, message?: string }
 */
function ueb_admin_ref_create( $key, $raw ) {
    global $wpdb;

    $registry = ueb_admin_ref_registry();
    if ( ! isset( $registry[ $key ] ) ) {
        return array( 'success' => false, 'message' => 'Table inconnue.' );
    }
    $cfg = $registry[ $key ];

    $san = ueb_admin_ref_sanitize( $key, $raw );
    if ( $san['errors'] ) {
        return array( 'success' => false, 'message' => implode( ' ', $san['errors'] ) );
    }

    $ok = $wpdb->insert( $cfg['table'], $san['data'] );
    if ( false === $ok ) {
        return array( 'success' => false, 'message' => ueb_admin_ref_friendly_db_error( $wpdb->last_error ) );
    }

    return array( 'success' => true, 'id' => (int) $wpdb->insert_id );
}

/**
 * Modifie une ligne existante.
 *
 * @param string $key
 * @param int    $id
 * @param array  $raw
 * @return array { success: bool, message?: string }
 */
function ueb_admin_ref_update( $key, $id, $raw ) {
    global $wpdb;

    $registry = ueb_admin_ref_registry();
    if ( ! isset( $registry[ $key ] ) ) {
        return array( 'success' => false, 'message' => 'Table inconnue.' );
    }
    $cfg = $registry[ $key ];

    $san = ueb_admin_ref_sanitize( $key, $raw );
    if ( $san['errors'] ) {
        return array( 'success' => false, 'message' => implode( ' ', $san['errors'] ) );
    }

    $ok = $wpdb->update( $cfg['table'], $san['data'], array( 'id' => absint( $id ) ) );
    if ( false === $ok && $wpdb->last_error ) {
        return array( 'success' => false, 'message' => ueb_admin_ref_friendly_db_error( $wpdb->last_error ) );
    }

    return array( 'success' => true );
}

/**
 * Supprime une ligne. Les tables ueb_* n'ont pas toutes ON DELETE CASCADE
 * (ex : ueb_preinscriptions référence facultés/filières/etc. sans cascade,
 * volontairement, pour ne jamais perdre une FK silencieusement) : si la
 * ligne est utilisée ailleurs, MySQL refuse la suppression et on retourne
 * un message clair plutôt que l'erreur SQL brute.
 *
 * @param string $key
 * @param int    $id
 * @return array { success: bool, message?: string }
 */
function ueb_admin_ref_delete( $key, $id ) {
    global $wpdb;

    $registry = ueb_admin_ref_registry();
    if ( ! isset( $registry[ $key ] ) ) {
        return array( 'success' => false, 'message' => 'Table inconnue.' );
    }
    $cfg = $registry[ $key ];

    $ok = $wpdb->delete( $cfg['table'], array( 'id' => absint( $id ) ) );
    if ( false === $ok ) {
        return array( 'success' => false, 'message' => ueb_admin_ref_friendly_db_error( $wpdb->last_error ) );
    }

    return array( 'success' => true );
}
