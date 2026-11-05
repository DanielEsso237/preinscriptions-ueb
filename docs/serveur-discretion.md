# Serveur : ne pas laisser voir WordPress

Le thème retire seul les traces de WordPress dans les pages (`inc/discretion.php`)
et ferme ses propres fichiers internes (`.htaccess` du thème). Le reste se règle
une fois sur le serveur, par FileZilla.

> FileZilla masque parfois les fichiers commençant par un point : activer
> **Serveur → Forcer l'affichage des fichiers cachés** pour voir et envoyer les
> `.htaccess` (celui de la racine et celui du thème).

## 1. `.htaccess` à la racine du site

Ajouter ce bloc **au-dessus** de `# BEGIN WordPress`, sans toucher au reste :

```apache
# BEGIN UEB discretion
# Pas de liste du contenu des dossiers (wp-includes/, wp-content/uploads/…).
Options -Indexes

<IfModule mod_headers.c>
	Header always unset X-Powered-By
	Header unset X-Powered-By
	Header always unset X-Pingback
</IfModule>

<IfModule mod_rewrite.c>
	RewriteEngine On
	# Fichiers qui annoncent WordPress ou exposent des informations :
	# confiés à WordPress, qui répond par la page 404 du thème.
	RewriteRule ^(readme\.html|license\.txt|wp-config\.php|wp-config-sample\.php|xmlrpc\.php|wp-content/debug\.log)$ index.php [L]
	RewriteRule ^wp-admin/(install|upgrade|setup-config|install-helper)\.php$ index.php [L]
	# Adresses neutres : /ressources/… (thème) et /fichiers/… (téléversements).
	RewriteRule ^ressources/(.*)$ wp-content/themes/preinscriptions-ueb/$1 [L]
	RewriteRule ^fichiers/(.*)$ wp-content/uploads/$1 [L]
</IfModule>
# END UEB discretion
```

Le thème détecte la ligne `# BEGIN UEB discretion` : tant qu'elle est absente,
il garde les adresses `/wp-content/…` et rien ne casse. Dès qu'elle est là, les
pages utilisent `/ressources/…` et `/fichiers/…`.

**Après l'ajout, vérifier :**

| Adresse | Attendu |
|---|---|
| `/` | la landing s'affiche avec ses styles et images |
| `/preinscriptions/` | le formulaire s'affiche, les listes (facultés, régions…) se remplissent |
| `/wp-includes/`, `/wp-content/uploads/` | plus de liste de fichiers (403) |
| `/readme.html`, `/license.txt`, `/xmlrpc.php`, `/wp-admin/install.php` | page « Page introuvable » |

En cas de problème (page blanche, styles absents), retirer le bloc : le site
revient aussitôt aux adresses d'avant.

## 2. `wp-config.php`

Ajouter au-dessus de la ligne `/* That's all, stop editing! */` (ou
`/* C'est tout, ne touchez pas à ce qui suit ! */`), en remplaçant les lignes
`WP_DEBUG` qui existent déjà :

```php
/* Pas de journal d'erreurs dans wp-content/debug.log, rien d'affiché. */
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_LOG', false );
define( 'WP_DEBUG_DISPLAY', false );

/* Éditeur de fichiers de wp-admin coupé (le thème le coupe aussi). */
define( 'DISALLOW_FILE_EDIT', true );

/* Cookies de connexion sans le préfixe « wordpress_ ». */
define( 'COOKIEHASH', md5( 'preinscription.unv-ebolowa.cm' ) );
define( 'USER_COOKIE', 'ueb_u_' . COOKIEHASH );
define( 'PASS_COOKIE', 'ueb_p_' . COOKIEHASH );
define( 'AUTH_COOKIE', 'ueb_a_' . COOKIEHASH );
define( 'SECURE_AUTH_COOKIE', 'ueb_sa_' . COOKIEHASH );
define( 'LOGGED_IN_COOKIE', 'ueb_l_' . COOKIEHASH );
define( 'TEST_COOKIE', 'ueb_t' );
define( 'RECOVERY_MODE_COOKIE', 'ueb_r_' . COOKIEHASH );
```

Le changement des cookies déconnecte une fois le personnel connecté. Les
candidats ne sont pas concernés : leur dossier en cours tient dans la session
du thème.

## 3. Fichiers à supprimer du serveur

Dans `wp-content/themes/preinscriptions-ueb/` :

| Fichier / dossier | Pourquoi |
|---|---|
| `_ap.html`, `_apm.html`, `5.5mm` | aperçus de travail oubliés (retirés du dépôt) |
| `tests/` | tests en ligne de commande, rien à faire en ligne |
| `tools/` | outils en ligne de commande (`seed-demo.php` crée des dossiers fictifs) |
| `docs/` | documentation interne (rôles, ce fichier) |
| `lib/tcpdf/examples/` | 75 scripts PHP d'exemple de TCPDF, exécutables par n'importe qui |
| `lib/tcpdf/tools/` | outil d'ajout de polices de TCPDF |
| `lib/tcpdf/README.md`, `CHANGELOG.TXT`, `LICENSE.TXT`, `composer.json`, `VERSION` | annoncent la bibliothèque et sa version exacte |
| `test-numero-dossier.php` (s'il existe) | ancien script de test |

À la racine du site :

| Fichier | Pourquoi |
|---|---|
| `readme.html`, `license.txt`, `wp-config-sample.php` | fichiers de présentation de WordPress (WordPress les remet à chaque mise à jour : le bloc `.htaccess` ci-dessus les bloque quand même) |
| `wp-content/debug.log` | journal d'erreurs (déjà « Forbidden » en ligne, mais inutile une fois `WP_DEBUG` coupé) |
| tout fichier `*.sql`, `*.zip`, `*.bak`, `*.old` | sauvegardes de base ou de site laissées sur le serveur |

Le `.htaccess` du thème bloque déjà l'accès web à tout ce qui précède dans le
thème : la suppression évite que ça reste là si le `.htaccess` saute un jour.

## 4. Hébergeur (si possible)

- `expose_php = Off` dans la configuration PHP (cPanel → « MultiPHP INI
  Editor », ou un fichier `.user.ini` à la racine) : retire la version de PHP
  des en-têtes.
- L'en-tête `Server:` (nom et version d'Apache) ne se règle que dans la
  configuration du serveur, pas dans `.htaccess`.

## 5. Contenu par défaut de WordPress

Dans wp-admin, supprimer définitivement (puis vider la corbeille) :

- l'article « Bonjour tout le monde ! » / « Hello world! » (Articles) ;
- la page « Page d'exemple » / « Sample Page » (Pages) ;
- le commentaire de « Un commentateur WordPress » (Commentaires).

## 6. Vérifier en ligne

Depuis un terminal (Linux, macOS, ou PowerShell avec `curl.exe`) :

```sh
S=https://preinscription.unv-ebolowa.cm

# En-têtes de l'accueil : cookie de session et en-têtes de sécurité.
curl -sI "$S/" | grep -iE "set-cookie|x-frame|x-content|referrer|strict-transport|server|x-powered|link"

# Codes de retour : 404 ou 403 attendus partout.
for u in wp-json/ wp-json/wp/v2/users "?author=1" feed/ wp-sitemap.xml xmlrpc.php \
         readme.html license.txt wp-includes/ wp-content/uploads/ wp-login.php wp-admin/ \
         wp-content/themes/preinscriptions-ueb/style.css \
         wp-content/themes/preinscriptions-ueb/tests/ \
         wp-content/themes/preinscriptions-ueb/lib/tcpdf/examples/example_001.php; do
  printf "%-70s %s\n" "$u" "$(curl -s -o /dev/null -w '%{http_code}' "$S/$u")"
done

# Plus de « WordPress » ni de « wp-json » dans la page d'accueil.
curl -s "$S/" | grep -oiE 'generator[^>]*|wp-json|wp-emoji|global-styles|ver=[0-9.]+' | sort -u
```

Attendu :

- `Set-Cookie: PHPSESSID=…; path=/; secure; HttpOnly; SameSite=Lax`
- `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`,
  `Referrer-Policy: …`, `Strict-Transport-Security: …`
- aucune ligne `Link: …wp-json…` ni `X-Powered-By`
- 404 (ou 403 pour les dossiers) sur toutes les adresses de la liste
- la dernière commande n'affiche rien (ou seulement des `ver=` à 10 chiffres,
  qui sont des dates de fichiers du thème).

Sans terminal : ouvrir le site dans Chrome, F12 → onglet **Réseau**, recharger,
cliquer sur la première ligne et lire les **en-têtes de réponse** ; le cookie
se voit dans **Application → Cookies** (colonnes HttpOnly et Secure cochées).
