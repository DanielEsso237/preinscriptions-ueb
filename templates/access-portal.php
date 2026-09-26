<?php
/**
 * Template Name: Espace de gestion
 *
 * Espace de gestion — interface front du système de rôles.
 *
 * Sélectionnable comme n'importe quel autre template de page (Administration,
 * Gestion des références) : créer une page dans Pages > Ajouter, lui donner
 * ce modèle, la publier. Elle apparaît alors dans la liste des pages et a sa
 * propre adresse ; c'est cette adresse que reçoivent tous les comptes pour se
 * connecter, quel que soit leur rôle. Sans page créée, /?ueb_portal=login
 * continue de fonctionner à l'identique (voir ueb_portal_url()).
 *
 * Toute décision d'accès est prise AVANT le rendu (inc/access-portal.php a
 * déjà refusé la route si la capability manque) : ce fichier ne fait que
 * dessiner ce que le compte a le droit de voir. Une section absente ici
 * n'est pas « masquée en CSS », elle n'est pas produite.
 *
 * La coque (barre latérale, barre du haut, boutons, tableau, squelettes)
 * réutilise les classes admin-* du tableau de bord : même feuille, mêmes
 * tokens, même comportement clair/sombre. access-portal.css n'ajoute que ce
 * qui est propre au portail.
 *
 * @package Preinscriptions_UEB
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$view = ueb_portal_route();
$auth = in_array( $view, array( 'login', 'forgot', 'reset' ), true );
$user = wp_get_current_user();

/* Établissement consulté : l'identifiant d'URL a déjà été validé contre la
   portée par template_redirect ; on ne s'en sert ici que pour l'affichage. */
$establishment_id  = absint( $_GET['establishment'] ?? 0 );
$my_establishments = array();
if ( 'establishment' === $view ) {
    $my_establishments = ueb_access_establishments( 'ueb_view_stats' );
    if ( ! $establishment_id && $my_establishments ) {
        $establishment_id = (int) $my_establishments[0]['id'];
    }
}

$titres = array(
    'overview'       => 'Vue d’ensemble',
    'establishment'  => ueb_portal_establishment_name( $establishment_id ) ?: 'Mon établissement',
    'students'       => 'Dossiers de préinscription',
    'roles'          => 'Rôles et accès',
    'users'          => 'Comptes utilisateurs',
    'establishments' => 'Établissements',
    'empty'          => 'Mon espace',
);
$titre = $titres[ $view ] ?? 'Mon espace';

/* Contexte affiché sous le titre : portée globale ou établissement. */
$contexte = 'overview' === $view
    ? 'Université d’Ébolowa — toute la portée de votre rôle'
    : ( 'establishment' === $view ? 'Université d’Ébolowa' : 'Préinscriptions — Université d’Ébolowa' );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <?php wp_head(); ?>
</head>
<body class="ueb-portal<?php echo $auth ? ' ueb-portal--auth' : ''; ?>">
<a class="portal-skip" href="#portal-main">Aller au contenu</a>
<?php wp_body_open(); ?>
<?php ueb_icons_sprite(); ?>

<?php if ( $auth ) : /* ================= CONNEXION ================= */

    $intitules = array(
        'login'  => array( 'Connexion', 'Connectez-vous avec votre compte professionnel.', 'Se connecter' ),
        'forgot' => array( 'Retrouvez votre accès.', 'Saisissez votre identifiant ou votre adresse e-mail. Vous recevrez un lien pour choisir un nouveau mot de passe.', 'Envoyer le lien' ),
        'reset'  => array( 'Nouveau mot de passe.', 'Choisissez un mot de passe d’au moins 12 caractères, différent de vos autres comptes.', 'Enregistrer le mot de passe' ),
    );
    list( $titre_auth, $intro_auth, $action_auth ) = $intitules[ $view ];
    $message_auth = $GLOBALS['ueb_auth_message'] ?? ( isset( $_GET['changed'] ) ? 'Votre mot de passe est enregistré. Vous pouvez vous connecter.' : '' );
    ?>
    <main class="portal-auth portal-auth--<?php echo esc_attr( $view ); ?>" id="portal-main">

        <!-- Composition institutionnelle : marque et accueil à gauche,
             formulaire sur verre à droite. Le décor reste purement visuel. -->
        <section class="portal-auth-brand">
            <a class="portal-auth-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                <img src="<?php echo esc_url( preinscriptions_img( 'logo-ueb.webp' ) ); ?>" width="52" height="52" alt="">
                <span>Université d’Ébolowa<small>Plateforme de préinscription</small></span>
            </a>

            <div class="portal-auth-statement">
                <h1>
                    <span class="portal-auth-line"><span>Espace</span></span>
                    <span class="portal-auth-line"><span>de gestion</span></span>
                </h1>
                <span class="portal-auth-rule" aria-hidden="true"></span>
                <p class="portal-auth-lede">Le suivi des préinscriptions, établissement par établissement, réservé aux personnels habilités.</p>
                <a class="portal-auth-public" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                    <?php echo ueb_icon( 'arrow-left', 'admin-icon--sm' ); ?>
                    Retour au site public
                </a>
            </div>

            <p class="portal-auth-motto">Savoir · Savoir-faire · Savoir-être</p>
        </section>

        <!-- Formulaire. Il entre en un bloc : animer chaque champ séparément
             retarderait la saisie sans rien apprendre à personne. -->
        <section class="portal-auth-panel">
            <div class="portal-auth-inner">
                <h2><?php echo esc_html( $titre_auth ); ?></h2>
                <p><?php echo esc_html( $intro_auth ); ?></p>

                <?php if ( $message_auth ) : ?>
                <div class="portal-notice<?php echo isset( $_GET['changed'] ) ? ' portal-notice--ok' : ' portal-notice--error'; ?>" role="<?php echo isset( $_GET['changed'] ) ? 'status' : 'alert'; ?>">
                    <?php echo ueb_icon( isset( $_GET['changed'] ) ? 'check' : 'alert', 'admin-icon--sm' ); ?>
                    <span><?php echo esc_html( $message_auth ); ?></span>
                </div>
                <?php endif; ?>

                <form method="post" class="portal-form" novalidate>
                    <?php wp_nonce_field( 'ueb_front_auth', 'nonce' ); ?>

                    <?php if ( 'reset' !== $view ) : ?>
                    <label>
                        Identifiant ou adresse e-mail
                        <input name="login" autocomplete="username" autocapitalize="none" spellcheck="false" required>
                    </label>
                    <?php endif; ?>

                    <?php if ( 'forgot' !== $view ) : ?>
                    <label>
                        <?php echo 'reset' === $view ? 'Nouveau mot de passe' : 'Mot de passe'; ?>
                        <span class="portal-password">
                            <input name="password" type="password"
                                   autocomplete="<?php echo 'reset' === $view ? 'new-password' : 'current-password'; ?>"
                                   <?php echo 'reset' === $view ? 'minlength="12"' : ''; ?> required>
                            <button type="button" data-reveal aria-label="Afficher le mot de passe">
                                <?php echo ueb_icon( 'eye', 'admin-icon--sm' ); ?>
                            </button>
                        </span>
                    </label>
                    <?php endif; ?>

                    <?php if ( 'reset' === $view ) : ?>
                    <label>
                        Confirmer le mot de passe
                        <input name="confirm" type="password" autocomplete="new-password" minlength="12" required>
                    </label>
                    <?php endif; ?>

                    <?php if ( 'login' === $view ) : ?>
                    <div class="portal-auth-row">
                        <label class="portal-check"><input type="checkbox" name="remember" value="1"> Rester connecté</label>
                        <a href="<?php echo esc_url( ueb_portal_url( 'forgot' ) ); ?>">Mot de passe oublié ?</a>
                    </div>
                    <?php endif; ?>

                    <button class="admin-tbtn admin-tbtn--primary portal-auth-submit" type="submit">
                        <?php echo esc_html( $action_auth ); ?>
                    </button>
                </form>

                <?php if ( 'login' !== $view ) : ?>
                <a class="portal-auth-back" href="<?php echo esc_url( ueb_portal_url( 'login' ) ); ?>">
                    <?php echo ueb_icon( 'arrow-left', 'admin-icon--sm' ); ?>
                    Retour à la connexion
                </a>
                <?php endif; ?>

                <p class="portal-auth-trust">
                    <?php echo ueb_icon( 'shield', 'admin-icon--sm' ); ?>
                    <span>Utilisez le compte attribué par votre établissement.</span>
                </p>
            </div>
        </section>
    </main>

<?php else : /* ================= ESPACE CONNECTÉ ================= */ ?>

<div class="admin-shell">

    <aside class="admin-sidebar">
        <div class="admin-sidebar-brand">
            <!-- Le sceau remplace le carré « UEB » : posé sur une pastille
                 blanche, car ses traits foncés disparaissent sur le vert nuit.
                 Décoratif — la marque est déjà écrite en toutes lettres à côté. -->
            <span class="admin-sidebar-logo admin-sidebar-logo--image" aria-hidden="true">
                    <img src="<?php echo esc_url( preinscriptions_img( 'logo-ueb.webp' ) ); ?>" width="38" height="38" alt="" decoding="async">
                </span>
            <span class="admin-sidebar-brand-text">
                <span class="admin-sidebar-mark">Préinscriptions</span>
                <span class="admin-sidebar-title">Université d’Ébolowa</span>
            </span>
        </div>

        <nav class="admin-sidebar-nav" aria-label="Sections de mon espace">
            <span class="admin-sidebar-heading">Navigation</span>
            <?php
            /* Une entrée par permission. Le libellé décrit ce qu'on y fait,
               l'icône reprend celle de la même notion dans le tableau de
               bord : un même objet garde le même signe partout. */
            $nav = array(
                'overview'       => array( 'ueb_view_overview',        'Vue d’ensemble',     'overview' ),
                'establishment'  => array( 'ueb_view_stats',           'Mon établissement',  'building' ),
                'students'       => array( 'ueb_view_students',        'Dossiers',           'list' ),
                'roles'          => array( 'ueb_manage_roles',         'Rôles et accès',     'shield' ),
                'users'          => array( 'ueb_manage_users',         'Comptes',            'users' ),
                'establishments' => array( 'ueb_manage_establishments','Établissements',     'settings' ),
            );
            foreach ( $nav as $route => $item ) :
                if ( ! ueb_access_has( $item[0] ) && ! ( 'students' === $route && ueb_access_has( 'ueb_export_students' ) ) ) continue;
                // Une portée globale entre par la vue d'ensemble : proposer
                // « Mon établissement » en plus n'aurait pas de sens.
                if ( 'establishment' === $route && ueb_access_has( 'ueb_view_overview' ) ) continue;
                $actif = ( $view === $route );
                ?>
                <a class="admin-tab-btn<?php echo $actif ? ' active' : ''; ?>"
                   href="<?php echo esc_url( ueb_portal_url( $route ) ); ?>"
                   <?php echo $actif ? 'aria-current="page"' : ''; ?>>
                    <?php echo ueb_icon( $item[2] ); ?>
                    <?php echo esc_html( $item[1] ); ?>
                </a>
            <?php endforeach; ?>

            <?php
            $lien_admin = ueb_access_has_admin() ? ueb_access_template_url( 'page-administration.php' ) : '';
            $lien_refs  = ueb_access_has_refs() ? ueb_access_template_url( 'page-references.php' ) : '';
            if ( $lien_admin || $lien_refs ) : ?>
                <span class="admin-sidebar-heading">Outils détaillés</span>
                <?php if ( $lien_admin ) : ?>
                <a class="admin-tab-btn" href="<?php echo esc_url( $lien_admin ); ?>">
                    <?php echo ueb_icon( 'org' ); ?>Tableau de bord complet
                </a>
                <?php endif; ?>
                <?php if ( $lien_refs ) : ?>
                <a class="admin-tab-btn" href="<?php echo esc_url( $lien_refs ); ?>">
                    <?php echo ueb_icon( 'database' ); ?>Référentiels
                </a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>

        <div class="admin-sidebar-footer">
            <div class="admin-sidebar-user">
                <span class="admin-sidebar-user-avatar" aria-hidden="true"><?php echo esc_html( mb_substr( $user->display_name, 0, 1 ) ); ?></span>
                <span class="admin-sidebar-user-meta">
                    <span class="admin-sidebar-user-name"><?php echo esc_html( $user->display_name ); ?></span>
                    <span class="admin-sidebar-user-role"><?php echo esc_html( ueb_access_role_label() ); ?></span>
                </span>
            </div>
            <a class="admin-sidebar-logout" href="<?php echo esc_url( ueb_portal_url( 'logout', array( 'nonce' => wp_create_nonce( 'ueb_logout' ) ) ) ); ?>">
                <?php echo ueb_icon( 'logout', 'admin-icon--sm' ); ?>Déconnexion
            </a>
        </div>
    </aside>

    <div class="admin-main portal-main" id="portal-main">

        <header class="admin-topbar">
            <div class="portal-title-block">
                <?php
                $logo_entete = 'establishment' === $view
                    ? preinscriptions_logo_etablissement( ueb_portal_establishment_row( $establishment_id )['logo'] ?? '' )
                    : '';
                if ( $logo_entete ) : ?>
                <span class="portal-title-logo">
                    <img src="<?php echo esc_url( $logo_entete ); ?>" width="48" height="48" alt="" decoding="async">
                </span>
                <?php endif; ?>
                <div>
                <h1><?php echo esc_html( $titre ); ?></h1>
                <div class="portal-topbar-context">
                    <p class="admin-subtitle"><?php echo esc_html( $contexte ); ?></p>
                    <?php if ( ueb_access_role_label() ) : ?>
                    <span class="portal-role-tag"><?php echo ueb_icon( 'shield', 'admin-icon--sm' ); ?><?php echo esc_html( ueb_access_role_label() ); ?></span>
                    <?php endif; ?>
                </div>
                </div>
            </div>

            <div class="admin-topbar-actions">
                <span class="admin-subtitle portal-hint"><?php echo ueb_icon( 'calendar', 'admin-icon--sm' ); ?> <?php echo esc_html( wp_date( 'j F Y' ) ); ?></span>
                <button type="button" id="admin-theme-toggle" class="admin-tbtn admin-tbtn--icon"
                        aria-label="Basculer entre le thème clair et sombre" title="Thème clair / sombre">
                    <?php echo ueb_icon( 'moon', 'admin-theme-icon--moon' ); ?>
                    <?php echo ueb_icon( 'sun', 'admin-theme-icon--sun' ); ?>
                </button>
            </div>
        </header>

        <div id="portal-message" class="portal-notice" role="status" tabindex="-1" hidden></div>

        <?php if ( in_array( $view, array( 'overview', 'establishment' ), true ) ) : /* --- TABLEAUX DE BORD --- */ ?>

            <?php if ( 'establishment' === $view && ( count( $my_establishments ) > 1 || ueb_access_has( 'ueb_view_overview' ) ) ) : ?>
            <div class="portal-toolbar">
                <?php if ( ueb_access_has( 'ueb_view_overview' ) ) : ?>
                <a class="admin-tbtn" href="<?php echo esc_url( ueb_portal_url( 'overview' ) ); ?>">
                    <?php echo ueb_icon( 'arrow-left', 'admin-icon--sm' ); ?>Retour à la vue d’ensemble
                </a>
                <?php endif; ?>

                <?php if ( count( $my_establishments ) > 1 ) : ?>
                <div class="portal-switcher portal-toolbar-spacer">
                    <label for="portal-establishment">Établissement</label>
                    <select id="portal-establishment">
                        <?php foreach ( $my_establishments as $row ) : ?>
                        <option value="<?php echo (int) $row['id']; ?>" <?php selected( $establishment_id, $row['id'] ); ?>>
                            <?php echo esc_html( $row['nom_fr'] ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Coque des squelettes : la place est réservée dès le premier
                 rendu, l'arrivée des données ne décale rien. -->
            <div id="portal-dashboard" data-view="<?php echo esc_attr( $view ); ?>"
                 data-establishment="<?php echo (int) $establishment_id; ?>" aria-busy="true">
                <div class="portal-skeleton portal-skeleton--headline" role="status">
                    <span class="admin-sr-only">Chargement des statistiques…</span>
                </div>
                <div class="portal-est-grid">
                    <?php for ( $i = 0; $i < 4; $i++ ) : ?><div class="portal-skeleton portal-skeleton--card"></div><?php endfor; ?>
                </div>
            </div>

        <?php endif; ?>

        <?php
        /* --- DOSSIERS ---
           Affichés sur leur propre page, et sous le tableau de bord d'un
           établissement quand le compte a le droit de les lire. */
        $montrer_dossiers = 'students' === $view
            || ( 'establishment' === $view && ueb_access_contains( 'ueb_view_students', $establishment_id ) );

        if ( $montrer_dossiers && ( ueb_access_has( 'ueb_view_students' ) || ueb_access_has( 'ueb_export_students' ) ) ) :

            $peut_lister  = ueb_access_has( 'ueb_view_students' )
                && ( ! $establishment_id || ueb_access_contains( 'ueb_view_students', $establishment_id ) );
            $peut_exporter = ueb_access_has( 'ueb_export_students' )
                && ( ! $establishment_id || ueb_access_contains( 'ueb_export_students', $establishment_id ) );
            $etabs_filtre = ueb_access_establishments( $peut_lister ? 'ueb_view_students' : 'ueb_export_students' );

            // Filtres détaillés : même moteur que le tiroir de l'espace admin
            // (ueb_admin_build_where), options limitées à la portée du compte.
            $peut_filtrer = ( $peut_lister || $peut_exporter ) && ueb_access_peut_filtrer();
            if ( $peut_filtrer ) {
                global $wpdb;
                $refs_filtre   = ueb_admin_get_reference_lists();
                $cap_portee    = $peut_lister ? 'ueb_view_students' : 'ueb_export_students';
                $sql_filieres  = 'SELECT f.id, f.libelle, fa.code FROM ueb_filieres f JOIN ueb_facultes fa ON fa.id = f.faculte_id WHERE '
                    . ueb_access_sql( 'f.faculte_id', $cap_portee )
                    . ( $establishment_id ? $wpdb->prepare( ' AND f.faculte_id = %d', $establishment_id ) : '' )
                    . ' ORDER BY fa.code, f.libelle';
                $filieres_par_etab = array();
                foreach ( $wpdb->get_results( $sql_filieres, ARRAY_A ) as $row ) {
                    $filieres_par_etab[ $row['code'] ][] = $row;
                }
                $filtres_detail = array(
                    'niveau_lmd'        => array( 'Niveau LMD', $refs_filtre['niveaux_lmd'] ),
                    'type_formation'    => array( 'Type de formation', $refs_filtre['types_formation'] ),
                    'diplome_admission' => array( 'Diplôme d’admission', $refs_filtre['diplomes'] ),
                    'mention'           => array( 'Mention', $refs_filtre['mentions'] ),
                    'sexe'              => array( 'Sexe', $refs_filtre['sexes'] ),
                    'region_origine'    => array( 'Région d’origine', $refs_filtre['regions'] ),
                );
            }
            ?>
            <section class="portal-students" id="portal-students"
                     data-establishment="<?php echo (int) $establishment_id; ?>"
                     data-can-list="<?php echo $peut_lister ? '1' : '0'; ?>">

                <div class="portal-section">
                    <div>
                        <h2>Dossiers de préinscription</h2>
                        <p>Les candidatures des établissements de votre portée.</p>
                    </div>

                    <?php if ( $peut_exporter ) : ?>
                    <div class="admin-export" id="portal-export-wrap">
                        <button type="button" id="portal-export" class="admin-tbtn"
                                aria-haspopup="menu" aria-expanded="false" aria-controls="portal-export-menu">
                            <?php echo ueb_icon( 'download', 'admin-icon--sm' ); ?>Exporter
                            <?php echo ueb_icon( 'chevron-down', 'admin-icon--sm admin-export-caret' ); ?>
                        </button>
                        <div id="portal-export-menu" class="admin-export-menu" role="menu" aria-labelledby="portal-export" hidden>
                            <p class="admin-export-menu-title">Liste des préinscrits<span>Dossiers affichés à l’écran</span></p>
                            <button type="button" class="admin-export-item" role="menuitem" data-format="pdf">
                                <?php echo ueb_icon( 'file-pdf' ); ?>
                                <span class="admin-export-item-text">Document PDF<span>Prêt à imprimer</span></span>
                                <span class="admin-export-ext">PDF</span>
                            </button>
                            <button type="button" class="admin-export-item" role="menuitem" data-format="excel">
                                <?php echo ueb_icon( 'file-sheet' ); ?>
                                <span class="admin-export-item-text">Classeur Excel<span>Colonnes filtrables et triables</span></span>
                                <span class="admin-export-ext">XLSX</span>
                            </button>
                            <button type="button" class="admin-export-item" role="menuitem" data-format="word">
                                <?php echo ueb_icon( 'file-doc' ); ?>
                                <span class="admin-export-item-text">Document Word<span>Modifiable avant transmission</span></span>
                                <span class="admin-export-ext">DOCX</span>
                            </button>
                            <button type="button" class="admin-export-item" role="menuitem" data-format="csv">
                                <?php echo ueb_icon( 'file-csv' ); ?>
                                <span class="admin-export-item-text">Données CSV<span>Pour un autre logiciel</span></span>
                                <span class="admin-export-ext">CSV</span>
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <form id="portal-list-filters" class="portal-filters">
                    <label class="portal-field portal-field--grow">
                        <span>Rechercher</span>
                        <span class="portal-search">
                            <?php echo ueb_icon( 'search', 'admin-icon--sm' ); ?>
                            <input type="search" name="recherche" placeholder="Nom, prénom ou numéro de dossier" autocomplete="off">
                        </span>
                    </label>

                    <?php if ( ! $establishment_id ) : ?>
                    <label class="portal-field">
                        <span>Établissement</span>
                        <select name="faculte">
                            <option value="">Tous mes établissements</option>
                            <?php foreach ( $etabs_filtre as $row ) : ?>
                            <option value="<?php echo (int) $row['id']; ?>"><?php echo esc_html( $row['nom_fr'] ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <?php else : ?>
                    <input type="hidden" name="faculte" value="<?php echo (int) $establishment_id; ?>">
                    <?php endif; ?>

                    <label class="portal-field">
                        <span>Statut</span>
                        <select name="statut">
                            <option value="">Tous</option>
                            <option value="soumis">Soumis</option>
                            <option value="brouillon">Brouillon</option>
                        </select>
                    </label>

                    <?php if ( $peut_filtrer ) : ?>
                    <button type="button" class="admin-tbtn portal-more-toggle" id="portal-more-toggle"
                            aria-expanded="false" aria-controls="portal-more">
                        <?php echo ueb_icon( 'filter', 'admin-icon--sm' ); ?>Plus de filtres
                        <span class="admin-filter-badge" id="portal-more-count" hidden>0</span>
                    </button>

                    <!-- Panneau replié par défaut ; ses champs font partie du
                         formulaire, donc de la liste ET de l'export. -->
                    <fieldset class="portal-more" id="portal-more" hidden>
                        <legend class="admin-sr-only">Filtres détaillés</legend>
                        <label class="portal-field">
                            <span>Filière (1er, 2e ou 3e choix)</span>
                            <select name="filiere">
                                <option value="">Toutes</option>
                                <?php foreach ( $filieres_par_etab as $code => $liste_filieres ) : ?>
                                    <?php if ( count( $filieres_par_etab ) > 1 ) : ?><optgroup label="<?php echo esc_attr( $code ); ?>"><?php endif; ?>
                                    <?php foreach ( $liste_filieres as $fil ) : ?>
                                    <option value="<?php echo (int) $fil['id']; ?>"><?php echo esc_html( $fil['libelle'] ); ?></option>
                                    <?php endforeach; ?>
                                    <?php if ( count( $filieres_par_etab ) > 1 ) : ?></optgroup><?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <?php foreach ( $filtres_detail as $cle => $filtre ) : ?>
                        <label class="portal-field">
                            <span><?php echo esc_html( $filtre[0] ); ?></span>
                            <select name="<?php echo esc_attr( $cle ); ?>">
                                <option value="">Tous</option>
                                <?php foreach ( $filtre[1] as $opt ) : ?>
                                <option value="<?php echo esc_attr( $opt->id ); ?>"><?php echo esc_html( $opt->libelle ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <?php endforeach; ?>
                        <label class="portal-field">
                            <span>Déposé à partir du</span>
                            <input type="date" name="date_from">
                        </label>
                        <label class="portal-field">
                            <span>Déposé jusqu’au</span>
                            <input type="date" name="date_to">
                        </label>
                        <div class="portal-more-actions">
                            <button type="button" class="portal-more-reset" id="portal-more-reset" hidden>
                                <?php echo ueb_icon( 'close', 'admin-icon--sm' ); ?>Effacer ces filtres
                            </button>
                        </div>
                    </fieldset>
                    <?php endif; ?>

                    <!-- Le tri se fait en cliquant les en-têtes du tableau ;
                         ces champs le transportent jusqu'au serveur. -->
                    <input type="hidden" name="orderby" value="date_creation">
                    <input type="hidden" name="order" value="desc">
                </form>

                <?php if ( $peut_lister ) : ?>
                <div id="portal-list" aria-live="polite" aria-busy="true"></div>
                <div id="portal-pagination" class="portal-pagination"></div>
                <?php else : ?>
                <div class="portal-empty">
                    <?php echo ueb_icon( 'lock' ); ?>
                    <strong>Lecture des dossiers non autorisée</strong>
                    <p>Votre rôle permet l’export mais pas la consultation nominative. L’export reste disponible ci-dessus.</p>
                </div>
                <?php endif; ?>
            </section>

            <!-- Détail d'un dossier. Le contenu est peint à l'ouverture. -->
            <dialog id="portal-detail-dialog" class="portal-dialog" aria-labelledby="portal-detail-title">
                <div class="portal-dialog-head">
                    <div>
                        <h2 id="portal-detail-title">Dossier</h2>
                        <p id="portal-detail-sub"></p>
                    </div>
                    <button type="button" class="admin-tbtn admin-tbtn--icon" data-close-dialog aria-label="Fermer le dossier">
                        <?php echo ueb_icon( 'close', 'admin-icon--sm' ); ?>
                    </button>
                </div>
                <div class="portal-dialog-body" id="portal-detail-body"></div>
            </dialog>
        <?php endif; ?>

        <?php if ( 'roles' === $view ) : /* --- RÔLES --- */
            $catalogue     = ueb_access_catalogue();
            $roles         = ueb_access_roles();
            $etabs_roles   = ueb_access_establishments( 'ueb_manage_roles' );
            $portee_totale = ( null === ueb_access_scope( 'ueb_manage_roles' ) );
            $roles_visibles = array();
            foreach ( $roles as $key => $role ) {
                $mien = in_array( $key, $user->roles, true );
                if ( ! ueb_access_can_delegate( $role, 'ueb_manage_roles' ) && ! $mien ) continue;
                $role['key']     = $key;
                $role['locked']  = $mien || ! ueb_access_role_is_safe( $key );
                $role['mine']    = $mien;
                $role['users']   = count( get_users( array( 'role' => $key, 'fields' => 'ID' ) ) );
                $roles_visibles[] = $role;
            }
            // Le catalogue proposé ne peut pas dépasser les droits du compte.
            $catalogue_permis = array_filter( $catalogue, 'current_user_can', ARRAY_FILTER_USE_KEY );
            ?>
            <div class="portal-section">
                <div>
                    <h2>Les rôles de votre équipe</h2>
                    <p>Un rôle, c’est une portée (quels établissements) et des permissions (quoi y faire).</p>
                </div>
                <button type="button" class="admin-tbtn admin-tbtn--primary" id="portal-new-role">
                    <?php echo ueb_icon( 'plus', 'admin-icon--sm' ); ?>Créer un rôle
                </button>
            </div>

            <div class="portal-role-grid" id="portal-role-list"></div>

            <!-- Assistant en trois temps : nommer, délimiter, autoriser. -->
            <dialog id="portal-role-dialog" class="portal-dialog" aria-labelledby="portal-role-heading">
                <div class="portal-dialog-head">
                    <div>
                        <h2 id="portal-role-heading">Créer un rôle</h2>
                        <p>Trois étapes. Rien n’est enregistré avant la dernière.</p>
                    </div>
                    <button type="button" class="admin-tbtn admin-tbtn--icon" data-close-dialog aria-label="Fermer l’assistant">
                        <?php echo ueb_icon( 'close', 'admin-icon--sm' ); ?>
                    </button>
                </div>

                <form id="portal-role-form">
                    <div class="portal-dialog-body">
                        <ol class="portal-steps">
                            <li aria-current="step"><span>Nom</span></li>
                            <li><span>Portée</span></li>
                            <li><span>Permissions</span></li>
                        </ol>

                        <div id="portal-role-error" class="portal-notice portal-notice--error" role="alert" hidden></div>

                        <div class="portal-wizard">
                            <div>
                                <input type="hidden" name="key">
                                <input type="hidden" name="revision">

                                <fieldset data-step="0">
                                    <legend>Comment s’appelle ce rôle ?</legend>
                                    <div class="portal-form">
                                        <label>
                                            Nom du rôle
                                            <input name="name" maxlength="100" placeholder="Chef d’établissement, Supervision, Admin FS…" required>
                                        </label>
                                        <p class="portal-hint">Choisissez le nom que votre équipe emploie déjà. Il apparaîtra aussi dans la liste des rôles WordPress.</p>
                                    </div>
                                </fieldset>

                                <fieldset data-step="1" hidden>
                                    <legend>Sur quels établissements agit-il ?</legend>
                                    <div class="portal-scope-choices">
                                        <label class="portal-scope-option">
                                            <input type="radio" name="scope" value="single" checked>
                                            <span>Un seul établissement<small>Le rôle ne voit jamais les autres.</small></span>
                                        </label>
                                        <label class="portal-scope-option">
                                            <input type="radio" name="scope" value="multiple">
                                            <span>Plusieurs établissements<small>Choisissez-en au moins deux.</small></span>
                                        </label>
                                        <?php if ( $portee_totale ) : ?>
                                        <label class="portal-scope-option">
                                            <input type="radio" name="scope" value="all">
                                            <span>Tous les établissements<small>Y compris ceux créés plus tard.</small></span>
                                        </label>
                                        <?php endif; ?>
                                    </div>
                                    <div id="portal-role-establishments" class="portal-choice-list">
                                        <?php foreach ( $etabs_roles as $row ) : ?>
                                        <label class="portal-check">
                                            <input type="checkbox" name="establishments[]" value="<?php echo (int) $row['id']; ?>">
                                            <?php echo esc_html( $row['code'] . ' — ' . $row['nom_fr'] ); ?>
                                            <?php if ( ! $row['actif'] ) : ?><span class="portal-tag">Inactif</span><?php endif; ?>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </fieldset>

                                <fieldset data-step="2" hidden>
                                    <legend>Quels accès lui accorder ?</legend>
                                    <div class="portal-form">
                                        <label>
                                            Partir d’un modèle
                                            <select id="portal-role-preset">
                                                <option value="">Tout décocher et composer librement</option>
                                                <?php foreach ( ueb_access_presets() as $i => $preset ) : ?>
                                                <option value="<?php echo (int) $i; ?>"><?php echo esc_html( $preset['name'] ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <p class="portal-hint">Un modèle ne fait que cocher des cases : tout reste modifiable ensuite.</p>
                                    </div>

                                    <div class="portal-perm-groups">
                                        <?php
                                        /* Groupes dans l'ordre du catalogue ; les référentiels,
                                           beaucoup plus nombreux, sont repliés par défaut. */
                                        $groupe = '';
                                        foreach ( $catalogue_permis as $cap => $item ) :
                                            if ( $groupe !== $item[0] ) {
                                                if ( $groupe ) echo 'Référentiels' === $groupe ? '</div></details>' : '</div></div>';
                                                $groupe = $item[0];
                                                echo 'Référentiels' === $groupe
                                                    ? '<details><summary>Référentiels — accès rubrique par rubrique</summary><div class="portal-perm-group"><div>'
                                                    : '<div class="portal-perm-group"><h3>' . esc_html( $groupe ) . '</h3><div>';
                                            }
                                            ?>
                                            <label class="portal-check">
                                                <input type="checkbox" name="permissions[]" value="<?php echo esc_attr( $cap ); ?>">
                                                <?php echo esc_html( $item[1] ); ?>
                                            </label>
                                            <?php
                                        endforeach;
                                        if ( $groupe ) echo 'Référentiels' === $groupe ? '</div></details>' : '</div></div>';
                                        ?>
                                    </div>
                                </fieldset>
                            </div>

                            <!-- Aperçu : la barre latérale que verra le titulaire.
                                 On montre l'interface plutôt que de la décrire. -->
                            <aside class="portal-preview" aria-live="polite">
                                <p class="portal-preview-head">Ce que ce rôle verra</p>
                                <div class="portal-preview-screen">
                                    <span class="portal-preview-name" id="portal-preview-name">Nouveau rôle</span>
                                    <div class="portal-preview-nav" id="portal-preview-nav"></div>
                                </div>
                                <p class="portal-preview-foot" id="portal-preview-foot"></p>
                            </aside>
                        </div>
                    </div>

                    <div class="portal-dialog-actions">
                        <button type="button" class="admin-tbtn portal-spacer" id="portal-role-prev" hidden>
                            <?php echo ueb_icon( 'arrow-left', 'admin-icon--sm' ); ?>Précédent
                        </button>
                        <button type="button" class="admin-tbtn" data-close-dialog>Annuler</button>
                        <button type="button" class="admin-tbtn admin-tbtn--primary" id="portal-role-next">
                            Continuer<?php echo ueb_icon( 'arrow-right', 'admin-icon--sm' ); ?>
                        </button>
                        <button type="submit" class="admin-tbtn admin-tbtn--primary" id="portal-role-submit" hidden>
                            <?php echo ueb_icon( 'check', 'admin-icon--sm' ); ?>Enregistrer le rôle
                        </button>
                    </div>
                </form>
            </dialog>

            <dialog id="portal-delete-dialog" class="portal-dialog portal-dialog--small" aria-labelledby="portal-delete-heading">
                <div class="portal-dialog-head">
                    <h2 id="portal-delete-heading">Supprimer ce rôle ?</h2>
                    <button type="button" class="admin-tbtn admin-tbtn--icon" data-close-dialog aria-label="Fermer">
                        <?php echo ueb_icon( 'close', 'admin-icon--sm' ); ?>
                    </button>
                </div>
                <form id="portal-delete-form">
                    <div class="portal-dialog-body">
                        <p id="portal-delete-description"></p>
                        <input type="hidden" name="key">
                        <input type="hidden" name="revision">
                        <div class="portal-form" style="margin-top:1.1rem">
                            <label id="portal-delete-replacement">
                                Réaffecter les comptes à
                                <select name="replacement"><option value="">Choisir un rôle</option></select>
                            </label>
                            <label class="portal-check">
                                <input type="checkbox" name="confirmed" value="1" required>
                                Je confirme la suppression de ce rôle.
                            </label>
                        </div>
                        <div class="portal-notice portal-notice--error" id="portal-delete-error" role="alert" hidden></div>
                    </div>
                    <div class="portal-dialog-actions">
                        <button type="button" class="admin-tbtn" data-close-dialog>Annuler</button>
                        <button type="submit" class="admin-tbtn admin-tbtn--danger">
                            <?php echo ueb_icon( 'trash', 'admin-icon--sm' ); ?>Supprimer le rôle
                        </button>
                    </div>
                </form>
            </dialog>

            <script type="application/json" id="portal-role-data"><?php
                echo wp_json_encode( array(
                    'roles'          => $roles_visibles,
                    'catalogue'      => $catalogue_permis,
                    'establishments' => $etabs_roles,
                    'presets'        => ueb_access_presets(),
                    'scopeAll'       => $portee_totale,
                ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
            ?></script>
        <?php endif; ?>

        <?php if ( 'users' === $view ) : /* --- COMPTES --- */
            $roles_delegables = array_filter( ueb_access_roles(), function( $role, $key ) {
                return ueb_access_role_is_safe( $key ) && ueb_access_can_delegate( $role, 'ueb_manage_users' );
            }, ARRAY_FILTER_USE_BOTH );

            $page     = max( 1, absint( $_GET['p'] ?? 1 ) );
            $recherche = sanitize_text_field( wp_unslash( $_GET['search'] ?? '' ) );
            $query = new WP_User_Query( array(
                'role__in'       => $roles_delegables ? array_keys( $roles_delegables ) : array( '__ueb_aucun_role__' ),
                'number'         => 20,
                'paged'          => $page,
                'search'         => $recherche ? '*' . $recherche . '*' : '',
                'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
                'orderby'        => 'display_name',
                'order'          => 'ASC',
            ) );
            $comptes = array_filter( $query->get_results(), 'ueb_access_user_manageable' );
            ?>
            <div class="portal-split">
                <section class="portal-panel">
                    <div class="portal-panel-head">
                        <h2>Les comptes de votre équipe</h2>
                        <p>Seuls les comptes rattachés à un rôle que vous pouvez attribuer sont listés.</p>
                    </div>

                    <form method="get" class="portal-filters">
                        <input type="hidden" name="ueb_portal" value="users">
                        <label class="portal-field portal-field--grow">
                            <span>Rechercher</span>
                            <span class="portal-search">
                                <?php echo ueb_icon( 'search', 'admin-icon--sm' ); ?>
                                <input type="search" name="search" value="<?php echo esc_attr( $recherche ); ?>" placeholder="Nom, identifiant ou e-mail">
                            </span>
                        </label>
                        <button class="admin-tbtn" type="submit">Rechercher</button>
                    </form>

                    <?php if ( $comptes ) : ?>
                    <div class="portal-table-wrap">
                        <div class="portal-table-scroll">
                            <table class="portal-table">
                                <thead><tr><th>Compte</th><th>Rôle</th><th><span class="admin-sr-only">Action</span></th></tr></thead>
                                <tbody>
                                <?php foreach ( $comptes as $compte ) : ?>
                                    <tr class="portal-row-clickable">
                                        <td>
                                            <strong><?php echo esc_html( $compte->display_name ); ?></strong>
                                            <small><?php echo esc_html( $compte->user_email ); ?></small>
                                        </td>
                                        <td><?php
                                            $noms = array();
                                            foreach ( $compte->roles as $key ) {
                                                if ( isset( $roles_delegables[ $key ] ) ) $noms[] = $roles_delegables[ $key ]['name'];
                                            }
                                            echo esc_html( implode( ', ', $noms ) );
                                        ?></td>
                                        <td>
                                            <button type="button" class="admin-tbtn"
                                                    data-assign-user="<?php echo (int) $compte->ID; ?>"
                                                    data-user-name="<?php echo esc_attr( $compte->display_name ); ?>">
                                                Changer de rôle
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="portal-pagination">
                        <span><?php echo esc_html( number_format_i18n( $query->get_total() ) ); ?> compte(s)</span>
                        <div class="portal-pagination-actions">
                            <?php if ( $page > 1 ) : ?>
                            <a class="admin-tbtn" href="<?php echo esc_url( ueb_portal_url( 'users', array( 'p' => $page - 1, 'search' => $recherche ) ) ); ?>">
                                <?php echo ueb_icon( 'arrow-left', 'admin-icon--sm' ); ?>Précédent
                            </a>
                            <?php endif; ?>
                            <?php if ( $page * 20 < $query->get_total() ) : ?>
                            <a class="admin-tbtn" href="<?php echo esc_url( ueb_portal_url( 'users', array( 'p' => $page + 1, 'search' => $recherche ) ) ); ?>">
                                Suivant<?php echo ueb_icon( 'arrow-right', 'admin-icon--sm' ); ?>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else : ?>
                    <div class="portal-empty">
                        <?php echo ueb_icon( 'users' ); ?>
                        <strong>Aucun compte à afficher</strong>
                        <p><?php echo $recherche ? 'Aucun compte ne correspond à cette recherche.' : 'Créez un compte avec le formulaire à côté pour démarrer.'; ?></p>
                    </div>
                    <?php endif; ?>
                </section>

                <section class="portal-panel">
                    <div class="portal-panel-head">
                        <h2 id="portal-user-heading">Créer un compte</h2>
                        <p>Le titulaire pourra changer son mot de passe depuis l’écran de connexion.</p>
                    </div>

                    <?php if ( ! $roles_delegables ) : ?>
                    <div class="portal-empty">
                        <?php echo ueb_icon( 'shield' ); ?>
                        <strong>Aucun rôle attribuable</strong>
                        <p>Créez d’abord un rôle dans « Rôles et accès ».</p>
                    </div>
                    <?php else : ?>
                    <form id="portal-user-form" class="portal-form">
                        <input type="hidden" name="id">
                        <div id="portal-user-fields" class="portal-form">
                            <label>Nom complet<input name="name" required autocomplete="off"></label>
                            <label>Identifiant de connexion<input name="login" required autocomplete="off"></label>
                            <label>Adresse e-mail<input name="email" type="email" required autocomplete="off"></label>
                            <label>
                                Mot de passe initial
                                <span class="portal-password">
                                    <input name="password" type="password" minlength="12" required autocomplete="new-password">
                                    <button type="button" data-reveal aria-label="Afficher le mot de passe"><?php echo ueb_icon( 'eye', 'admin-icon--sm' ); ?></button>
                                </span>
                            </label>
                            <p class="portal-hint">Au moins 12 caractères, à transmettre au titulaire par votre canal habituel.</p>
                        </div>
                        <label>
                            Rôle
                            <select name="role" required>
                                <option value="">Choisir un rôle</option>
                                <?php foreach ( $roles_delegables as $key => $role ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $role['name'] ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div class="portal-notice portal-notice--error" id="portal-user-error" role="alert" hidden></div>
                        <div class="portal-role-actions">
                            <button class="admin-tbtn admin-tbtn--primary" type="submit">
                                <?php echo ueb_icon( 'user-plus', 'admin-icon--sm' ); ?>Enregistrer le compte
                            </button>
                            <button class="admin-tbtn" type="reset">Nouveau compte</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </section>
            </div>
        <?php endif; ?>

        <?php if ( 'establishments' === $view ) : /* --- ÉTABLISSEMENTS --- */
            $etabs = ueb_access_establishments( 'ueb_manage_establishments' );
            $peut_creer = ( null === ueb_access_scope( 'ueb_manage_establishments' ) );
            ?>
            <div class="portal-split">
                <section class="portal-panel">
                    <div class="portal-panel-head">
                        <h2>Le réseau des établissements</h2>
                        <p>Un établissement inactif conserve ses dossiers et n’accepte plus de nouvelles candidatures.</p>
                    </div>

                    <?php if ( $etabs ) : ?>
                    <div class="portal-table-wrap">
                        <div class="portal-table-scroll">
                            <table class="portal-table">
                                <thead><tr><th>Établissement</th><th>Statut</th><th><span class="admin-sr-only">Action</span></th></tr></thead>
                                <tbody>
                                <?php foreach ( $etabs as $row ) : ?>
                                    <?php $logo_ligne = preinscriptions_logo_etablissement( $row['logo'] ?? '' ); ?>
                                    <tr class="portal-row-clickable">
                                        <td>
                                            <span class="portal-est-row">
                                                <?php if ( $logo_ligne ) : ?>
                                                <span class="portal-est-logo portal-est-logo--sm">
                                                    <img src="<?php echo esc_url( $logo_ligne ); ?>" width="30" height="30" alt="" loading="lazy" decoding="async">
                                                </span>
                                                <?php endif; ?>
                                                <span>
                                                    <strong><?php echo esc_html( $row['code'] ); ?></strong>
                                                    <small><?php echo esc_html( $row['nom_fr'] ); ?></small>
                                                </span>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="portal-status portal-status--<?php echo $row['actif'] ? 'soumis' : 'brouillon'; ?>">
                                                <?php echo $row['actif'] ? 'Actif' : 'Inactif'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" class="admin-tbtn" data-edit-establishment="<?php echo (int) $row['id']; ?>">
                                                <?php echo ueb_icon( 'edit', 'admin-icon--sm' ); ?>Modifier
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php else : ?>
                    <div class="portal-empty">
                        <?php echo ueb_icon( 'building' ); ?>
                        <strong>Aucun établissement dans votre portée</strong>
                        <p>Créez-en un avec le formulaire à côté.</p>
                    </div>
                    <?php endif; ?>
                </section>

                <section class="portal-panel">
                    <div class="portal-panel-head">
                        <h2 id="portal-establishment-heading"><?php echo $peut_creer ? 'Ajouter un établissement' : 'Modifier un établissement'; ?></h2>
                        <p>Le sigle est ce que l’équipe lit en premier partout dans l’interface.</p>
                    </div>
                    <form id="portal-establishment-form" class="portal-form">
                        <input type="hidden" name="id">
                        <label>Nom<input name="nom_fr" maxlength="150" required></label>
                        <label>Nom en anglais<input name="nom_en" maxlength="150" placeholder="Repris du nom français si vide"></label>
                        <label>Sigle<input name="code" maxlength="10" required placeholder="FS, FSJP…"></label>
                        <label>Identifiant d’URL<input name="slug" maxlength="180" pattern="[a-z0-9\-]+" required placeholder="faculte-des-sciences"></label>
                        <label>
                            Statut
                            <select name="actif">
                                <option value="1">Actif — ouvert aux candidatures</option>
                                <option value="0">Inactif — historique conservé</option>
                            </select>
                        </label>
                        <div class="portal-notice portal-notice--error" id="portal-establishment-error" role="alert" hidden></div>
                        <div class="portal-role-actions">
                            <button type="submit" class="admin-tbtn admin-tbtn--primary">
                                <?php echo ueb_icon( 'check', 'admin-icon--sm' ); ?>Enregistrer
                            </button>
                            <?php if ( $peut_creer ) : ?>
                            <button type="reset" class="admin-tbtn">Nouvel établissement</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </section>
            </div>
            <script type="application/json" id="portal-establishment-data"><?php
                echo wp_json_encode( $etabs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
            ?></script>
        <?php endif; ?>

        <?php if ( 'empty' === $view ) : ?>
            <section class="portal-panel">
                <div class="portal-empty">
                    <?php echo ueb_icon( 'inbox' ); ?>
                    <strong>Votre compte est bien connecté</strong>
                    <p>Aucun espace ne correspond encore à vos permissions. Demandez à votre responsable d’ajuster votre rôle depuis « Rôles et accès ».</p>
                </div>
            </section>
        <?php endif; ?>

        <footer class="portal-footer">
            <span>Université d’Ébolowa</span>
            <span>Plateforme de préinscription</span>
        </footer>
    </div>
</div>
<?php endif; ?>

<noscript>
    <p class="portal-notice">Activez JavaScript pour consulter les tableaux de bord et gérer les accès. La connexion, elle, fonctionne sans.</p>
</noscript>
<?php wp_footer(); ?>
</body>
</html>
