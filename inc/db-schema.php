<?php
/**
 * Schéma de la base de données custom du thème (tables ueb_*).
 *
 * Indépendant du système CPT/postmeta de WordPress : chaque table est une
 * table MySQL classique, avec des colonnes explicites et des clés
 * étrangères, pour rester simple à exporter (cf. décision du prof).
 *
 * IMPORTANT : dbDelta() n'est volontairement PAS utilisé ici. dbDelta()
 * est fait pour gérer les index, mais il ignore/casse les contraintes
 * FOREIGN KEY (limitation connue de WordPress). On exécute donc les
 * CREATE TABLE directement via $wpdb->query(), avec IF NOT EXISTS pour
 * rester idempotent (ré-exécutable sans erreur si les tables existent déjà).
 *
 * @package Preinscriptions_UEB
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Version du schéma de base de données. À incrémenter à chaque fois que
 * la structure des tables ueb_* change (nouvelle colonne, nouvelle table,
 * etc.). C'est ce qui permet à ueb_maybe_upgrade_db() de savoir qu'une
 * mise à jour est nécessaire.
 *
 * Historique :
 * - 1.0 : version initiale (régions, départements, communes, facultés,
 *         diplômes, spécialités, filières, situations matrimoniales,
 *         statuts socio-professionnels, nationalités, dossier de
 *         préinscription, téléphones, progression).
 */
if ( ! defined( 'UEB_DB_SCHEMA_VERSION' ) ) {
    define( 'UEB_DB_SCHEMA_VERSION', '1.0' );
}

/**
 * Retourne la liste des instructions CREATE TABLE, dans l'ordre de
 * dépendance (une table ne peut être créée qu'après celles qu'elle
 * référence en clé étrangère). Ne PAS réordonner sans vérifier les FK.
 *
 * @return array<string, string> table => SQL
 */
function ueb_get_table_schemas() {
    return array(
        'ueb_regions' => <<<SQL
CREATE TABLE IF NOT EXISTS ueb_regions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(2) NOT NULL,
    nom VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_region_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
        'ueb_departements' => <<<SQL
CREATE TABLE IF NOT EXISTS ueb_departements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(4) NOT NULL,
    nom VARCHAR(100) NOT NULL,
    region_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_departement_code (code),
    KEY idx_region (region_id),
    CONSTRAINT fk_departement_region FOREIGN KEY (region_id) REFERENCES ueb_regions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
        'ueb_communes' => <<<SQL
CREATE TABLE IF NOT EXISTS ueb_communes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(6) NOT NULL,
    nom VARCHAR(150) NOT NULL,
    departement_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_commune_code (code),
    KEY idx_departement (departement_id),
    CONSTRAINT fk_commune_departement FOREIGN KEY (departement_id) REFERENCES ueb_departements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
        'ueb_facultes' => <<<SQL
CREATE TABLE IF NOT EXISTS ueb_facultes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(10) NOT NULL,
    nom_fr VARCHAR(150) NOT NULL,
    nom_en VARCHAR(150) NOT NULL,
    logo VARCHAR(100) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_faculte_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
        'ueb_diplomes_admission' => <<<SQL
CREATE TABLE IF NOT EXISTS ueb_diplomes_admission (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(20) NOT NULL,
    libelle VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_diplome_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
        'ueb_specialites_diplome' => <<<SQL
CREATE TABLE IF NOT EXISTS ueb_specialites_diplome (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(30) NOT NULL,
    libelle VARCHAR(150) NOT NULL,
    faculte_id INT UNSIGNED NOT NULL,
    diplome_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_specialite (code, faculte_id, diplome_id),
    KEY idx_faculte (faculte_id),
    KEY idx_diplome (diplome_id),
    CONSTRAINT fk_specialite_faculte FOREIGN KEY (faculte_id) REFERENCES ueb_facultes(id) ON DELETE CASCADE,
    CONSTRAINT fk_specialite_diplome FOREIGN KEY (diplome_id) REFERENCES ueb_diplomes_admission(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
        'ueb_filieres' => <<<SQL
CREATE TABLE IF NOT EXISTS ueb_filieres (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(30) NOT NULL,
    libelle VARCHAR(150) NOT NULL,
    faculte_id INT UNSIGNED NOT NULL,
    type_formation ENUM('classique','pro') NOT NULL DEFAULT 'classique',
    PRIMARY KEY (id),
    UNIQUE KEY uq_filiere (code, faculte_id, type_formation),
    KEY idx_faculte (faculte_id),
    CONSTRAINT fk_filiere_faculte FOREIGN KEY (faculte_id) REFERENCES ueb_facultes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
        'ueb_situations_matrimoniales' => <<<SQL
CREATE TABLE IF NOT EXISTS ueb_situations_matrimoniales (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(20) NOT NULL,
    libelle VARCHAR(50) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_situation_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
        'ueb_statuts_socio_professionnels' => <<<SQL
CREATE TABLE IF NOT EXISTS ueb_statuts_socio_professionnels (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    libelle VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_statut_libelle (libelle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
        'ueb_nationalites' => <<<SQL
CREATE TABLE IF NOT EXISTS ueb_nationalites (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_nationalite_nom (nom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
        'ueb_dossier_sequence' => <<<SQL
CREATE TABLE IF NOT EXISTS ueb_dossier_sequence (
    annee SMALLINT UNSIGNED NOT NULL,
    dernier_numero INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (annee)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
        'ueb_preinscriptions' => <<<SQL
CREATE TABLE IF NOT EXISTS ueb_preinscriptions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    numero_dossier VARCHAR(20) NOT NULL,
    statut ENUM('brouillon', 'soumis') NOT NULL DEFAULT 'brouillon',

    -- Formation --
    faculte_id INT UNSIGNED DEFAULT NULL,
    diplome_admission_id INT UNSIGNED DEFAULT NULL,
    specialite_diplome_id INT UNSIGNED DEFAULT NULL,
    niveau_lmd VARCHAR(20) NOT NULL DEFAULT 'Licence 1',
    type_formation ENUM('classique', 'pro') NOT NULL DEFAULT 'classique',
    filiere_1_id INT UNSIGNED DEFAULT NULL,
    filiere_2_id INT UNSIGNED DEFAULT NULL,
    annee_obtention SMALLINT UNSIGNED DEFAULT NULL,

    -- État civil --
    nom VARCHAR(100) DEFAULT NULL,
    prenom VARCHAR(100) DEFAULT NULL,
    sexe ENUM('M', 'F') DEFAULT NULL,
    date_naissance DATE DEFAULT NULL,
    lieu_naissance VARCHAR(150) DEFAULT NULL,
    nationalite_id INT UNSIGNED DEFAULT NULL,
    situation_matrimoniale_id INT UNSIGNED DEFAULT NULL,
    statut_socio_professionnel_id INT UNSIGNED DEFAULT NULL,
    handicap VARCHAR(255) DEFAULT NULL,

    -- Contact & origine --
    email VARCHAR(150) DEFAULT NULL,
    adresse VARCHAR(255) DEFAULT NULL,
    region_origine_id INT UNSIGNED DEFAULT NULL,
    departement_origine_id INT UNSIGNED DEFAULT NULL,
    commune_origine_id INT UNSIGNED DEFAULT NULL,
    arrondissement_origine VARCHAR(150) DEFAULT NULL,

    -- Filiation --
    nom_pere VARCHAR(150) DEFAULT NULL,
    nom_mere VARCHAR(150) DEFAULT NULL,
    profession_pere VARCHAR(150) DEFAULT NULL,

    -- Métadonnées --
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_modification DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_numero_dossier (numero_dossier),

    CONSTRAINT fk_pi_faculte       FOREIGN KEY (faculte_id)                 REFERENCES ueb_facultes(id),
    CONSTRAINT fk_pi_diplome       FOREIGN KEY (diplome_admission_id)       REFERENCES ueb_diplomes_admission(id),
    CONSTRAINT fk_pi_specialite    FOREIGN KEY (specialite_diplome_id)      REFERENCES ueb_specialites_diplome(id),
    CONSTRAINT fk_pi_filiere1      FOREIGN KEY (filiere_1_id)               REFERENCES ueb_filieres(id),
    CONSTRAINT fk_pi_filiere2      FOREIGN KEY (filiere_2_id)               REFERENCES ueb_filieres(id),
    CONSTRAINT fk_pi_nationalite   FOREIGN KEY (nationalite_id)             REFERENCES ueb_nationalites(id),
    CONSTRAINT fk_pi_situation     FOREIGN KEY (situation_matrimoniale_id)  REFERENCES ueb_situations_matrimoniales(id),
    CONSTRAINT fk_pi_statut_socio  FOREIGN KEY (statut_socio_professionnel_id) REFERENCES ueb_statuts_socio_professionnels(id),
    CONSTRAINT fk_pi_region        FOREIGN KEY (region_origine_id)          REFERENCES ueb_regions(id),
    CONSTRAINT fk_pi_departement   FOREIGN KEY (departement_origine_id)     REFERENCES ueb_departements(id),
    CONSTRAINT fk_pi_commune       FOREIGN KEY (commune_origine_id)         REFERENCES ueb_communes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
        'ueb_preinscriptions_telephones' => <<<SQL
CREATE TABLE IF NOT EXISTS ueb_preinscriptions_telephones (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    preinscription_id INT UNSIGNED NOT NULL,
    type ENUM('candidat', 'tuteur') NOT NULL,
    numero VARCHAR(20) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_preinscription (preinscription_id),
    CONSTRAINT fk_tel_preinscription FOREIGN KEY (preinscription_id) REFERENCES ueb_preinscriptions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
        'ueb_preinscriptions_progression' => <<<SQL
CREATE TABLE IF NOT EXISTS ueb_preinscriptions_progression (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    numero_dossier VARCHAR(20) NOT NULL,
    etape_atteinte TINYINT UNSIGNED NOT NULL DEFAULT 1,
    donnees_json LONGTEXT DEFAULT NULL,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_derniere_modification DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_progression_dossier (numero_dossier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL,
    );
}

/**
 * Crée (ou vérifie l'existence de) toutes les tables ueb_*.
 * Exécuté dans l'ordre retourné par ueb_get_table_schemas().
 */
function ueb_create_tables() {
    global $wpdb;

    foreach ( ueb_get_table_schemas() as $table => $sql ) {
        $result = $wpdb->query( $sql );

        if ( false === $result ) {
            error_log( sprintf(
                '[UEB DB] Échec de création de la table "%s" : %s',
                $table,
                $wpdb->last_error
            ) );
        }
    }
}

/**
 * Vérifie si le schéma en base correspond à UEB_DB_SCHEMA_VERSION.
 * Si non (première installation, ou nouvelle version du thème avec un
 * schéma modifié), relance la création des tables et enregistre la
 * nouvelle version dans les options WordPress.
 *
 * Accroché à 'after_switch_theme' (déclenché à l'activation du thème)
 * ET à 'admin_init' (vérification légère à chaque chargement de l'admin,
 * pour que les futures mises à jour de schéma s'appliquent sans avoir à
 * désactiver/réactiver le thème manuellement).
 */
function ueb_maybe_upgrade_db() {
    $version_installee = get_option( 'ueb_db_version' );

    if ( $version_installee === UEB_DB_SCHEMA_VERSION ) {
        return; // Déjà à jour, rien à faire.
    }

    ueb_create_tables();
    update_option( 'ueb_db_version', UEB_DB_SCHEMA_VERSION );
}
add_action( 'after_switch_theme', 'ueb_maybe_upgrade_db' );
add_action( 'admin_init', 'ueb_maybe_upgrade_db' );