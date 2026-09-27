# Rôles dynamiques et espace de gestion UEB

## Audit préalable

Le projet est un thème WordPress personnalisé, sans plugin métier ni mu-plugin à ajouter. Les écrans utilisent des templates PHP, du CSS et du JavaScript natif ; Chart.js est déjà distribué localement. Les polices locales Sora / Inter, les verts `#1a4a2e` et `#123a24`, l’or `#c9a227`, les composants et les variables de l’administration existante sont réutilisés. Aucun framework ni dépendance de production supplémentaire n’est installé.

Les candidatures réelles sont stockées dans `ueb_preinscriptions`, avec `faculte_id`, `filiere_1_id` (et les choix suivants), `statut` et `date_creation`. Les établissements sont dans `ueb_facultes`, les filières dans `ueb_filieres`. Un ancien CPT existe mais ne pilote pas ce flux. Les tables métier ne portent pas le préfixe WordPress.

L’espace public repose sur `page-preinscription.php`, ses appels AJAX, la sauvegarde des brouillons et la génération du PDF. L’administration existante utilise `page-administration.php` et `page-references.php`, avec des endpoints `admin-ajax.php`. Elle fournit statistiques, évolution, effectifs par établissement/filière, dossiers, recherche, filtres, tri, pagination, détail et exports CSV/PDF/XLSX/DOCX.

Les référentiels couvrent établissements, diplômes, spécialités, filières, niveaux LMD, mentions, statuts étudiants, langues, situations matrimoniales, statuts socio-professionnels, nationalités, sports, arts, géographie et réseaux sociaux. Le catalogue des permissions les lit directement dans `ueb_admin_ref_registry()` : une nouvelle rubrique de ce registre reçoit automatiquement sa permission.

L’ancien accès métier reposait sur `voir_preinscriptions`, sans isolation par établissement. Les référentiels étaient réservés à `manage_options`. Ces contrôles sont remplacés par des capabilities métier et une portée contrôlée sur le serveur.

## Étapes réalisées

1. Auditer les données, routes, permissions et composants existants.
2. Ajouter la portée persistante, le catalogue de capabilities et la migration additive.
3. Appliquer les permissions et la portée aux anciens endpoints, détails, statistiques, référentiels et exports.
4. Créer la connexion front, les tableaux de bord et les interfaces de gestion guidées.
5. Vérifier l’isolation, les délégations, les parcours navigateur, les exports et le responsive ; fournir les exemples et ce guide.

La stratégie visuelle suit `ui-ux-pro-max`, puis `frontend-design`. Les principes de timing et de séquençage Remotion sont transposés en CSS et JavaScript : entrée des cartes à 420 ms avec décalage de 45 ms, barres à 650 ms, courbes à 800 ms, compteurs progressifs. Remotion n’est pas ajouté au runtime WordPress. `prefers-reduced-motion` désactive ces mouvements.

## Activation et migration

1. Déployer les fichiers du thème avec une sauvegarde de la base, y compris les tables `ueb_*` et les options WordPress.
2. Charger une page du site. La migration `ueb_access_version = 2` ajoute `slug` et `actif` à `ueb_facultes`, un index unique sur le slug et un index `(faculte_id, date_creation)` sur les candidatures. Les établissements existants restent actifs ; aucun dossier n’est supprimé.
3. Se connecter avec un administrateur technique existant à `/?ueb_portal=login`, puis ouvrir `/?ueb_portal=roles`.
4. Créer un rôle de nom libre, portée « Tous », modèle « Direction / Super gestionnaire ». Créer le compte du supérieur dans `/?ueb_portal=users` et lui affecter ce rôle. L’administrateur technique conserve ses capabilities système ; le compte de direction ne les reçoit pas.
5. La direction peut ensuite créer ses établissements, rôles et comptes depuis le front. Les rôles apparaissent aussi dans la liste des rôles de WordPress et peuvent y être affectés par un administrateur technique.

Les anciens rôles simples possédant `voir_preinscriptions` sont migrés avec une portée globale et leurs accès équivalents. Un rôle comportant d’autres capabilities système n’est pas transformé en rôle métier administrable. Il faut créer un profil métier adapté pour ce cas particulier.

Les routes fonctionnent sans création de pages ni réenregistrement des permaliens. Les pages d’administration déjà configurées gardent leurs URL ; à défaut, les routes `/?ueb_portal=administration` et `/?ueb_portal=references` chargent les templates existants. Le pied de page public contient « Espace de gestion ».

### Exemples installables

Depuis le répertoire du thème, avec PHP disposant de l’extension MySQL :

```sh
php tools/access-demo.php /chemin/wordpress/wp-load.php ID_ADMIN --install
```

`ID_ADMIN` doit être l’identifiant d’un administrateur technique existant. Cette commande crée une fois :

- **Direction** : portée tous, ensemble des permissions métier ;
- **Faculté des Sciences — Test**, sigle **FS-TEST**, initialement inactive pour ne pas apparaître dans le formulaire public ;
- **Admin FS** : portée limitée à cet établissement de test, statistiques, courbes, dossiers, exports, sections effectifs et dossiers.

Ces exemples ont été initialisés sur l’installation locale. Aucun compte personnel du supérieur n’a été créé : ses coordonnées et son mot de passe doivent être saisis dans l’espace de gestion. Le script ne crée pas de compte avec un mot de passe prédéfini et ne réécrit pas les rôles déjà modifiés. Les identifiants internes sont aléatoires ; aucun contrôle d’accès ne compare « Direction », « Admin FS », « Recteur » ou « DAAS ».

Les modèles proposés dans l’assistant sont **Lecture seule**, **Chef d’établissement**, **Supervision globale / Recteur**, **Suivi des admissions / DAAS** et **Direction / Super gestionnaire**. Ils préremplissent les permissions ; le nom, la portée et chaque case restent modifiables avant validation. Ils ne créent aucun rôle figé.

## Permissions et portée

| Capability | Accès |
| --- | --- |
| `ueb_view_overview` | Vue d’ensemble, toujours limitée à la portée |
| `ueb_view_stats` | Compteurs et répartitions d’établissement |
| `ueb_view_trends` | Courbes d’évolution, en complément d’une vue autorisée |
| `ueb_view_students` | Liste et détail des dossiers |
| `ueb_export_students` | Exports des dossiers |
| `ueb_section_stats` | Section statistiques de l’admin existant, avec `ueb_view_stats` |
| `ueb_section_effectifs` | Section effectifs, avec `ueb_view_stats` |
| `ueb_section_dossiers` | Section dossiers, avec `ueb_view_students` |
| `ueb_manage_establishments` | Gestion des établissements de la portée |
| `ueb_manage_roles` | Gestion des profils délégables |
| `ueb_manage_users` | Création et affectation des comptes délégables |
| `ueb_ref_<clé du registre>` | Une rubrique du référentiel existant |

La portée `single` exige un établissement, `multiple` au moins deux, `all` inclut aussi les établissements créés ultérieurement. L’option `ueb_access_roles` contient le nom, la portée, les identifiants d’établissements, les permissions et une révision. Les capabilities sont aussi persistées dans les rôles WordPress par `add_role`.

Un établissement inactif reste consultable dans les historiques par les personnes autorisées ; il disparaît du formulaire public. Sa désactivation ne supprime ni filière ni dossier. Un gestionnaire limité peut modifier les établissements déjà attribués, mais la création d’un établissement exige une portée globale.

Les dictionnaires sans `faculte_id` sont communs à l’université : leur modification exige une portée « Tous », explicitement indiquée dans le catalogue. Les filières, spécialités et diplômes rattachés à un établissement sont filtrés. Un diplôme commun peut être proposé dans un sélecteur sans devenir modifiable par un gestionnaire limité.

Si plusieurs rôles sont affectés via WordPress, les portées sont réunies **par capability**, puis croisées pour les opérations exigeant plusieurs permissions. Avoir les statistiques de B et les dossiers de A ne permet pas d’exporter les dossiers de B.

La direction ne peut ni modifier/supprimer son propre rôle, ni administrer `administrator`, ni attribuer des capabilities hors catalogue, ni dépasser ses propres permissions ou établissements. Les comptes techniques, privilégiés et son propre compte sont protégés. Une suppression exige une confirmation et, pour un rôle utilisé, une réaffectation autorisée. Les mutations sont sérialisées par verrou SQL et les modifications de rôles vérifient leur révision.

## Connexion et données

Les liens principaux sont `/?ueb_portal=login`, `home`, `overview`, `establishment`, `students`, `roles`, `users` et `establishments`. La connexion redirige vers la première vue autorisée. La déconnexion, l’oubli et le renouvellement du mot de passe restent dans l’interface front. Les rôles métier n’accèdent pas à wp-admin ; AJAX reste disponible avec ses propres contrôles.

Chaque endpoint privé vérifie la capability, le nonce et la portée. Les paramètres d’établissement ou de dossier ne définissent jamais l’autorisation. Les filtres SQL sont préparés ; les sorties HTML sont échappées ; les exports CSV neutralisent les débuts de cellule interprétables comme formules. Les graphiques ont des valeurs textuelles consultables, et les états vide/chargement sont prévus.

Les grands compteurs incluent les brouillons et les dossiers soumis, conformément au stockage existant ; le tableau permet de filtrer le statut. La semaine commence le lundi, les dates suivent le fuseau WordPress, les courbes couvrent 30 jours et la répartition par filière utilise le premier choix. La variation compare la semaine courante au même temps écoulé la semaine précédente. Les dossiers sans établissement sont signalés dans la vue globale et ne sont jamais attribués à un gestionnaire limité.

La connexion limite les échecs par adresse réseau et identifiant (5 échecs, fenêtre de 15 minutes), avec messages génériques. L’oubli de mot de passe est limité à trois demandes par adresse sur 15 minutes. Derrière un proxy, l’adresse réseau doit être configurée correctement au niveau serveur ; le code ne fait pas confiance à un en-tête client arbitraire. L’acheminement effectif des e-mails dépend de `wp_mail` / du transport installé et reste à vérifier sur l’hébergement cible.

### Reprise des brouillons publics

La reprise d’un dossier se fait avec le seul numéro de dossier (la clé de reprise confidentielle a été retirée). Un dossier déjà soumis reste modifiable : la reprise ramène au récapitulatif et la nouvelle soumission met à jour le même dossier.

## Vérifications automatisées

À exécuter sur une copie de développement, depuis le thème :

```sh
php tests/access-integration.php /chemin/wordpress/wp-load.php
php tests/access-integration.php /chemin/wordpress/wp-load.php --fixtures
python3 tests/access-ui.py
php tests/access-integration.php /chemin/wordpress/wp-load.php --cleanup
```

Les tests PHP utilisent WordPress et MySQL réels, avec transaction annulée en fin d’exécution. Les tests navigateur requièrent Python Playwright et Chromium déjà installés ; ce ne sont pas des dépendances du thème. Ils créent leurs propres comptes/dossiers et conservent temporairement leurs identifiants dans `/tmp/ueb-access-fixtures.json` (permissions 0600). `--cleanup` retire ces seules fixtures, y compris les comptes/rôles créés par les parcours. Toujours exécuter le nettoyage, même après un échec navigateur. Les exemples FS-TEST/Direction/Admin FS sont indépendants et restent en place.

Vérifiés sur l’environnement local : isolation des portées simple/multiple/tous, intersections multi-rôles, IDOR liste/détail/stats/référentiels, exports CSV/PDF/XLSX/DOCX, nonce invalide, plafonds de délégation, rôle propre et administrateur protégés, réaffectation, révision périmée, limitation de connexion, clé de récupération WordPress, possession d’un brouillon, parcours de gestion, absence d’erreur JavaScript, largeurs 375/768/1024 px et mouvement réduit.

### Checklist de recette

- [x] Un rôle limité à A ne reçoit aucun dossier, statistique ou export de B, même avec un identifiant forgé.
- [x] Un rôle A+B dispose d’un sélecteur limité à A et B.
- [x] Une portée tous affiche une carte par établissement et les totaux globaux autorisés.
- [x] Sans permission : menu absent, page refusée et endpoint refusé.
- [x] Ancien admin : sections conditionnelles, listes et référentiels isolés.
- [x] Connexion front, déconnexion, refus de wp-admin et absence d’admin bar.
- [x] Modèle, aperçu, création, modification, duplication et confirmation de suppression des rôles.
- [x] Réaffectation exigée pour un rôle utilisé ; protection des comptes privilégiés et des droits système.
- [x] Création et changement de rôle d’un compte depuis le front.
- [x] Désactivation d’un établissement et maintien de son historique.
- [x] Responsive sans débordement global à 375/768/1024 px ; animations réduites si demandé.
- [ ] Sur l’hébergement cible, réception réelle de l’e-mail, lien expiré et renouvellement complet du mot de passe.
- [ ] Recette humaine clavier/lecteur d’écran : ouverture/fermeture des dialogues, ordre du focus, aperçu et annonces des résultats.
- [ ] Recette métier sur une copie de la base de production : totaux, données historiques sans établissement, charge des exports volumineux et profils du supérieur.

## Refonte de l'interface (24/09/2026)

La logique d'accès n'a pas changé ; l'interface du portail a été refaite pour
qu'elle soit le prolongement du tableau de bord existant et non un second
design parallèle.

**Sprite d'icônes partagé.** Les trente icônes du tableau de bord vivaient en
dur dans `page-administration.php`, et `page-references.php` en recopiait
treize. Elles sont extraites dans `inc/icons.php` (`ueb_icons_sprite()` pour
le sprite, `ueb_icon( $nom, $classe )` pour un appel). Le portail n'emploie
plus aucun caractère Unicode en guise d'icône.

**Vue d'ensemble à chiffre dominant.** Le total occupe la tête de page en
Sora 6 rem, avec sa variation et la courbe des 30 jours à côté ; les autres
mesures deviennent des satellites séparés par des filets, plus quatre cartes
KPI concurrentes. Ensuite seulement viennent une carte par établissement, le
classement et la courbe.

**Écran de connexion.** Composition en deux panneaux avec une séquence
d'ouverture unique (sceau, nom révélé par masque, filet or tracé, sigles en
cascade), puis plus aucun mouvement. Les sigles affichés sont ceux des
établissements actifs, déjà publics dans le formulaire de préinscription.

**Corrections d'interaction.** Changement d'établissement, tri par clic sur
l'en-tête de colonne et recherche temporisée se font sans rechargement ; le
détail d'un dossier s'ouvre dans une modale (l'endpoint existait déjà sans
être utilisé) ; l'export reprend le menu à formats du tableau de bord ; le
bouton de thème clair/sombre est présent et partage la clé
`ueb-admin-theme`. Le rafraîchissement au retour sur l'onglet ne relance plus
toute l'animation : il n'a lieu qu'après deux minutes et sans rejouer les
compteurs.

**Performance.** `ueb_access_scope()` est mémoïsé par requête et
`ueb_access_has()` ne le calcule plus qu'une fois au lieu de deux ; un jeton
de génération invalide le cache après chaque écriture de rôle. Le portail
interrogeait la portée plusieurs dizaines de fois par page.

**Piège de spécificité CSS.** Les resets de base du portail passent par
`:where()`, donc avec une spécificité nulle. Sans cela `.ueb-portal a`
(0,1,1) écrasait `.admin-tbtn--primary` (0,1,0) et le bouton principal
s'affichait en vert sur vert. Même convention que la refonte du compte à
rebours : tout reset de base du portail s'écrit en `:where()`.

Vérifié au rendu, thèmes clair et sombre, à 1440 px et à 390 px : connexion,
vue d'ensemble, établissement, dossiers, assistant de rôle, comptes,
établissements.

## Revue des doublons — corrections du 24/09/2026

La détection livrée précédemment est conservée : index de clés hachées,
regroupement par propagation de labels en SQL, confiance « Certain » /
« Probable », frontière inter-établissements, plan de confirmation signé par
empreinte, journal d'audit et table d'état permettant le retour arrière. Le
filtrage statistique est bien centralisé : `ueb_stats_population_sql()` passe
par `ueb_admin_build_where()`, qu'utilisent les analyses, les effectifs, les
exports et le portail.

Corrections apportées :

- **Recherche combinée à un filtre** : `ueb_duplicates_list()` concaténait le
  motif `LIKE '%…%'` avant d'appeler `prepare()` sur les paramètres du filtre.
  Les `%` du motif étaient alors relus comme des marqueurs et la requête
  cassait. L'ordre est désormais celui de `ueb_duplicates_hidden_count()` :
  paramètres d'abord, recherche ensuite.
- **Migration jamais déclenchée** : accrochée à `admin_init`, elle ne pouvait
  pas s'exécuter pour les rôles métier, que le portail redirige hors de
  wp-admin dès `admin_init` priorité 1. Déplacée sur `init`, comme
  `ueb_access_migrate()`.
- **Requêtes N+1** : `ueb_duplicates_row_meta()` interrogeait la base une à
  deux fois par ligne, soit jusqu'à cinquante requêtes pour une page de
  vingt-cinq dossiers, y compris sur la liste ordinaire. Un préchargement
  (`ueb_duplicates_preload()`) charge désormais toute la page en deux
  requêtes.
- **Groupe coupé par la pagination** : la requête remonte le rang de chaque
  dossier dans son groupe. Une page qui s'ouvre au milieu d'un groupe affiche
  « Groupe N · suite · X dossiers sur Y · le début du groupe est en page
  précédente » au lieu de laisser croire que le groupe commence là.
- **Contraste des boutons principaux** : `.admin-page button` (0,1,1)
  écrasait `.admin-tbtn--primary` (0,1,0) dans `admin-dashboard.css`. Tous les
  boutons principaux du tableau de bord s'affichaient en texte sombre sur fond
  vert, soit environ 1,5:1. Reset passé en `:where()`, version des assets
  portée à 1.6.0.
- **Interface** : filets latéraux décoratifs supprimés au profit de bordures
  complètes, intitulé en capitales retiré, croix typographique et étoile
  recopiée remplacées par les icônes du sprite (`ueb-i-star` est la seule
  icône pleine du jeu), compteurs du bandeau animés avec un doublon textuel
  pour les lecteurs d'écran.

Limite connue : le critère « même numéro de pièce d'identité » n'est pas
implémentable — `ueb_preinscriptions` ne stocke aucun numéro de CNI ni de
passeport. Les critères disponibles sont l'adresse e-mail, le téléphone du
candidat et le triplet nom + prénom + date de naissance.

## Le portail comme vraie page WordPress (24/09/2026)

`templates/access-portal.php` est désormais un modèle sélectionnable comme
« Administration » ou « Gestion des références » : Pages > Ajouter, choisir
le modèle « Espace de gestion », publier. La page apparaît alors dans la
liste des pages, et **c'est cette seule adresse que reçoit tout le monde**
pour se connecter, quel que soit son rôle — la redirection après connexion
se charge d'envoyer chacun vers ce qu'il a le droit de voir.

`ueb_portal_url()` s'ancre automatiquement sur cette page dès qu'elle existe
(mémoïsé par requête, une seule lecture en base par page vue) ; sans elle,
tout continue de fonctionner via `/?ueb_portal=login` comme avant — aucune
étape n'est obligatoire, c'est un pur confort d'adresse. Ce site utilise des
permaliens simples : l'adresse réelle sera donc de la forme
`?page_id=25` (WordPress choisit l'identifiant), pas une jolie URL type
`/espace-de-gestion/` — ce format suppose des permaliens décoratifs
(Réglages > Permaliens), une décision distincte, sitewide, qui n'a pas été
prise ici.

## Écran de connexion premium (24/09/2026)

Refonte visuelle de l'écran de connexion (login/forgot/reset), sans nouvelle
police ni nouvel asset — toujours Sora/Inter et le sceau existant
(`logo-ueb.webp`). Trois ajouts :

- **Le formulaire devient une vraie carte** (bordure, ombre, filet or en
  tête) au lieu de flotter dans le vide sur grand écran ; fond du panneau en
  dégradé radial très doux plutôt qu'un blanc plat.
- **Filigrane du sceau** dans le panneau de marque : le sceau existant, agrandi,
  désaturé et très éclairci (`grayscale(1) brightness(2.6) contrast(.5)` +
  opacité `.14`), avec une dérive de rotation continue sur 140 s
  (`prefers-reduced-motion` la coupe). Dérive lente + filet or qui se trace
  restent les seuls mouvements après le chargement.
- **Ligne de confiance** sous le formulaire, ancrée sur des mécanismes
  réels du portail (limitation des tentatives, journal d'audit), pas une
  promesse de sécurité générique.

Piège rencontré et corrigé : `filter: brightness(0) invert(1)` (aplatir un
logo en silhouette) ne fonctionne QUE sur une image à fond transparent avec
un motif opaque dessus. Le sceau UEB est peint jusqu'au bord de son disque,
sans zone transparente interne — ce filtre le réduisait à un simple rond
blanc plein. Corrigé en gardant l'image en couleur, désaturée et éclaircie,
jamais aplatie. Voir la mémoire de session pour ce piège, réutilisable pour
tout futur filigrane à partir d'un logo plein.

## Écran de connexion « verre » (25/09/2026)

Nouvelle direction visuelle de l'écran de connexion, à partir d'une
référence fournie par l'utilisateur (glassmorphism, scène unique, biseaux
diagonaux, carte translucide, champs en pilule) — adaptée à la charte du
projet (vert/or, Sora/Inter, sceau existant), pas copiée telle quelle.

- **Scène unique** : les deux « panneaux » partagent maintenant un seul fond
  en dégradé (`.portal-auth`), au lieu d'un panneau vert et d'un panneau
  blanc accolés. Deux biseaux diagonaux et un halo or traversent toute la
  scène, passant derrière la carte.
- **Carte en verre** (`.portal-auth-inner`) : fond translucide +
  `backdrop-filter: blur(28px)`, texte blanc. Les CHAMPS restent en pilule
  quasi opaque (`rgba(255,255,255,.97)`) pour que le texte saisi garde un
  contraste plein — seule la carte est translucide, jamais ce qu'on tape.
- **Bouton principal** en dégradé vert → or (écho du CTA en dégradé de la
  référence, sur nos propres couleurs de marque).

Contrastes mesurés et corrigés (formule WCAG, calcul Python, pas une
estimation) : le lien « Mot de passe oublié ? » en or plein sur la carte ne
tenait que 4,11:1 (sous le seuil AA 4,5:1) — éclairci à
`color-mix(in srgb, var(--ueb-accent-bright) 75%, white)`, 5,21:1 mesuré.
Même correction sur `.portal-auth-trust` (4,55:1 → 5,16:1, marge réelle).
Ce mélange n'existe QUE dans ce contexte de carte translucide ; ailleurs sur
le site, `--ueb-accent-bright` plein reste correct sur fond clair.

## Fichiers livrés

Créés :

- `inc/access-control.php`, `inc/access-portal.php` : modèle d’accès, migration, délégation, routes, authentification et agrégats. `templates/access-portal.php` est un modèle de page sélectionnable (« Espace de gestion »).
- `inc/icons.php` : sprite d’icônes partagé par le portail, le tableau de bord et les référentiels.
- `templates/access-portal.php`, `assets/css/access-portal.css`, `assets/js/access-portal.js` : interfaces et comportements du portail.
- `tools/access-demo.php` : initialisation explicite des exemples modifiables.
- `tests/access-integration.php`, `tests/access-ui.py` : tests serveur et navigateur.
- `docs/roles-dynamiques.md` : audit, activation et recette.

Modifiés :

- `functions.php`, `footer.php`, `inc/landing-page-functions.php` : chargement et accès au portail.
- `page-administration.php`, `page-references.php`, `assets/js/admin-dashboard.js`, `assets/js/admin-references.js` : adaptation des espaces existants.
- `inc/admin-functions.php`, `inc/admin-ajax-functions.php`, `inc/analytics-functions.php`, `inc/stats-effectifs-functions.php`, `inc/export-functions.php` : isolation des données et exports.
- `inc/admin-references-functions.php`, `inc/admin-references-ajax.php` : permissions par rubrique et portée des référentiels.
- `inc/dossier-functions.php`, `inc/db-functions.php`, `inc/ajax-functions.php`, `page-preinscription.php`, `assets/js/form-preinscription.js`, `assets/css/form-preinscription.css` : établissements actifs.

La modification préexistante de `inc/quitus-pdf-functions.php` a été conservée et ne fait pas partie de cette livraison. Aucun déploiement distant n’a été effectué.
