# Base de données custom — `ueb_*`

Ce dossier (`inc/`) contient le système qui crée et peuple les tables MySQL du
projet. Contrairement à l'ancien système (CPT + postmeta WordPress), ces
tables sont **indépendantes** de la structure interne de WordPress — décision
prise avec le prof pour simplifier une future exportation.

## Les fichiers

| Fichier | Rôle |
|---|---|
| `db-schema.php` | Crée les 14 tables `ueb_*` (structure uniquement, aucune donnée) |
| `db-seed.php` | Insère les données de référence (régions, communes, facultés, etc.) |
| `dossier-functions.php` | Génération du numéro de dossier + sauvegarde/reprise de la progression |

**Ordre important** : dans `functions.php`, `db-schema.php` doit être chargé
avant `db-seed.php` (les tables doivent exister avant qu'on y insère des
données). `dossier-functions.php` dépend des tables créées par les deux
premiers.

## Comment ça se déclenche

Tout est **automatique**, il n'y a normalement rien à exécuter à la main :

- À l'activation du thème (hook `after_switch_theme`)
- À chaque chargement de l'admin WordPress (hook `admin_init`), pour que les
  futures mises à jour de schéma s'appliquent sans avoir à désactiver/
  réactiver le thème manuellement

Chaque exécution vérifie d'abord si c'est nécessaire (via un numéro de
version stocké dans les options WordPress), donc ça ne recrée pas les tables
ni ne réinsère les données à chaque page vue — le coût réel est une simple
comparaison de version.

## Forcer une réexécution (après une modification du schéma)

Si tu modifies une structure de table dans `db-schema.php` (nouvelle colonne,
nouvelle table, etc.) ou les données dans `db-seed.php` :

1. Incrémente `UEB_DB_SCHEMA_VERSION` en haut de `db-schema.php`
   (ex. `'1.0'` → `'1.1'`)
2. Recharge n'importe quelle page de l'admin WordPress (`wp-admin`)
3. La nouvelle version est détectée automatiquement, `ueb_create_tables()`
   et `ueb_seed_reference_data()` se relancent

**Pas besoin de désactiver/réactiver le thème** — le hook `admin_init` suffit.

## Réinitialiser complètement (install fraîche ou données corrompues)

Si tu veux repartir de zéro (ex. tu as modifié des données de référence
directement dans phpMyAdmin et tu veux revenir à l'état du code) :

```sql
-- Attention : supprime aussi tout ce qui dépend d'elles (préinscriptions
-- réelles incluses si elles existent). Respecter cet ordre à cause des
-- clés étrangères.
DROP TABLE IF EXISTS ueb_preinscriptions_telephones;
DROP TABLE IF EXISTS ueb_preinscriptions;
DROP TABLE IF EXISTS ueb_preinscriptions_progression;
DROP TABLE IF EXISTS ueb_dossier_sequence;
DROP TABLE IF EXISTS ueb_filieres;
DROP TABLE IF EXISTS ueb_specialites_diplome;
DROP TABLE IF EXISTS ueb_facultes;
DROP TABLE IF EXISTS ueb_diplomes_admission;
DROP TABLE IF EXISTS ueb_situations_matrimoniales;
DROP TABLE IF EXISTS ueb_statuts_socio_professionnels;
DROP TABLE IF EXISTS ueb_nationalites;
DROP TABLE IF EXISTS ueb_communes;
DROP TABLE IF EXISTS ueb_departements;
DROP TABLE IF EXISTS ueb_regions;
```

Puis, dans phpMyAdmin ou Beekeeper Studio :

```sql
DELETE FROM wp_options WHERE option_name IN ('ueb_db_version', 'ueb_data_version');
```

Enfin, désactive puis réactive le thème dans `wp-admin` → les 14 tables sont
recréées et repeuplées automatiquement.

## Installation depuis zéro (nouveau poste / réinstallation XAMPP)

Un nouveau membre de l'équipe qui clone le dépôt et installe WordPress n'a
**rien à faire manuellement** : dès qu'il active le thème `preinscriptions-ueb`
dans `wp-admin`, les tables et les données de référence se créent toutes
seules. C'est tout l'intérêt de ce système par rapport à des scripts SQL à
exécuter à la main.

## Vérifier que tout s'est bien passé

Effectifs attendus après le seed :

```sql
SELECT 'ueb_regions' AS table_name, COUNT(*) AS total FROM ueb_regions
UNION ALL SELECT 'ueb_departements', COUNT(*) FROM ueb_departements
UNION ALL SELECT 'ueb_communes', COUNT(*) FROM ueb_communes
UNION ALL SELECT 'ueb_facultes', COUNT(*) FROM ueb_facultes
UNION ALL SELECT 'ueb_diplomes_admission', COUNT(*) FROM ueb_diplomes_admission
UNION ALL SELECT 'ueb_specialites_diplome', COUNT(*) FROM ueb_specialites_diplome
UNION ALL SELECT 'ueb_filieres', COUNT(*) FROM ueb_filieres
UNION ALL SELECT 'ueb_situations_matrimoniales', COUNT(*) FROM ueb_situations_matrimoniales
UNION ALL SELECT 'ueb_statuts_socio_professionnels', COUNT(*) FROM ueb_statuts_socio_professionnels
UNION ALL SELECT 'ueb_nationalites', COUNT(*) FROM ueb_nationalites;
```

| Table | Effectif attendu |
|---|---|
| `ueb_regions` | 10 |
| `ueb_departements` | 58 |
| `ueb_communes` | 374 |
| `ueb_facultes` | 4 |
| `ueb_diplomes_admission` | 2 |
| `ueb_specialites_diplome` | 22 |
| `ueb_filieres` | 23 |
| `ueb_situations_matrimoniales` | 4 |
| `ueb_statuts_socio_professionnels` | 9 |
| `ueb_nationalites` | 15 |

Pas besoin de vérifier les orphelins manuellement : les clés étrangères
(`FOREIGN KEY`) empêchent MySQL lui-même d'accepter une ligne dont la
référence n'existe pas.

## Dépannage

Si une table ne s'est pas créée ou une donnée n'a pas été insérée, un message
est écrit dans le journal d'erreurs PHP (`error_log`), préfixé par
`[UEB DB]` ou `[UEB Dossier]`. Sur XAMPP, ce fichier se trouve généralement
dans `F:\LOGICIELS\Xampp\php\logs\php_error_log` (ou configuré différemment
selon `php.ini` → `error_log`).

## À propos des anciens scripts SQL manuels

Les fichiers `.sql` envoyés au fil du projet (régions/départements, communes,
tables métier, préinscriptions) restent dans le dépôt comme **référence
lisible** du contenu exact des tables, mais ne doivent plus être exécutés
manuellement — `db-schema.php` et `db-seed.php` les remplacent entièrement
et de façon automatique.