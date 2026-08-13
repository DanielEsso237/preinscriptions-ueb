<?php
/**
 * Template Name: Administration
 *
 * Page de connexion + tableau de bord pour les gestionnaires de
 * préinscriptions. Accès réservé aux comptes ayant la capacité
 * "voir_preinscriptions" (rôle gestionnaire_preinscriptions).
 *
 * Le dashboard a deux vues (Vue d'ensemble / Dossiers) qui partagent un même
 * panneau de filtres (tous les champs à choix du formulaire de
 * préinscription) : les deux se recalculent en AJAX à chaque changement de
 * filtre, sans rechargement de page.
 *
 * Les icônes sont un sprite SVG local (jeu de traits 1.75, style Lucide) :
 * pas de dépendance externe, pas d'emoji, et une seule définition par icône
 * réutilisée via <use>.
 *
 * @package Preinscriptions_UEB
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ================================================================
   TRAITEMENT DE LA CONNEXION (avant tout affichage)
   ================================================================ */
$ueb_login_error = '';

if ( isset( $_POST['ueb_admin_login'] ) ) {

    if ( ! isset( $_POST['ueb_admin_login_nonce'] ) ||
         ! wp_verify_nonce( $_POST['ueb_admin_login_nonce'], 'ueb_admin_login' ) ) {
        $ueb_login_error = 'Erreur de sécurité, merci de réessayer.';
    } else {
        $creds = array(
            'user_login'    => sanitize_text_field( wp_unslash( $_POST['ueb_username'] ?? '' ) ),
            'user_password' => $_POST['ueb_password'] ?? '',
            'remember'      => true,
        );

        $user = wp_signon( $creds, is_ssl() );

        if ( is_wp_error( $user ) ) {
            $ueb_login_error = 'Identifiant ou mot de passe incorrect.';
        } elseif ( ! user_can( $user, 'voir_preinscriptions' ) ) {
            wp_logout();
            $ueb_login_error = "Ce compte n'a pas accès au tableau de bord.";
        } else {
            wp_safe_redirect( get_permalink() );
            exit;
        }
    }
}

$ueb_is_authorized = is_user_logged_in() && current_user_can( 'voir_preinscriptions' );
$ueb_user          = wp_get_current_user();

get_header();
?>

<!-- Sprite d'icônes : défini une fois, référencé partout via <use>. -->
<svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true" focusable="false">
    <symbol id="ueb-i-overview" viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m7 14 3-4 3 3 5-7"/></symbol>
    <symbol id="ueb-i-list" viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></symbol>
    <symbol id="ueb-i-filter" viewBox="0 0 24 24"><path d="M3 5h18l-7 8v6l-4 2v-8Z"/></symbol>
    <symbol id="ueb-i-download" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/></symbol>
    <symbol id="ueb-i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></symbol>
    <symbol id="ueb-i-moon" viewBox="0 0 24 24"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></symbol>
    <symbol id="ueb-i-close" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></symbol>
    <symbol id="ueb-i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></symbol>
    <symbol id="ueb-i-sort" viewBox="0 0 24 24"><path d="m7 15 5 5 5-5"/><path d="m7 9 5-5 5 5"/></symbol>
    <symbol id="ueb-i-arrow-right" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></symbol>
    <symbol id="ueb-i-arrow-left" viewBox="0 0 24 24"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></symbol>
    <symbol id="ueb-i-users" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></symbol>
    <symbol id="ueb-i-calendar" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></symbol>
    <symbol id="ueb-i-trend" viewBox="0 0 24 24"><path d="m22 7-8.5 8.5-5-5L2 17"/><path d="M16 7h6v6"/></symbol>
    <symbol id="ueb-i-building" viewBox="0 0 24 24"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4M9 6h.01M15 6h.01M9 10h.01M15 10h.01M9 14h.01M15 14h.01"/></symbol>
    <symbol id="ueb-i-inbox" viewBox="0 0 24 24"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11Z"/></symbol>
    <symbol id="ueb-i-alert" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></symbol>
    <symbol id="ueb-i-logout" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/></symbol>
    <symbol id="ueb-i-up" viewBox="0 0 24 24"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></symbol>
    <symbol id="ueb-i-down" viewBox="0 0 24 24"><path d="M12 5v14"/><path d="m19 12-7 7-7-7"/></symbol>
    <symbol id="ueb-i-minus" viewBox="0 0 24 24"><path d="M5 12h14"/></symbol>
    <symbol id="ueb-i-lock" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></symbol>
</svg>

<div class="admin-page">

<?php if ( ! $ueb_is_authorized ) : ?>

    <!-- ===== FORMULAIRE DE CONNEXION ===== -->
    <div class="admin-login-wrap">
        <h1>Espace gestion — Préinscriptions UEB</h1>

        <?php if ( is_user_logged_in() && ! current_user_can( 'voir_preinscriptions' ) ) : ?>
            <p class="admin-error" role="alert">
                <svg class="admin-icon admin-icon--sm" aria-hidden="true"><use href="#ueb-i-alert"/></svg>
                Votre compte n'a pas les droits nécessaires pour accéder à cette page.
            </p>
        <?php endif; ?>

        <?php if ( $ueb_login_error ) : ?>
            <p class="admin-error" role="alert">
                <svg class="admin-icon admin-icon--sm" aria-hidden="true"><use href="#ueb-i-alert"/></svg>
                <?php echo esc_html( $ueb_login_error ); ?>
            </p>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( get_permalink() ); ?>" class="admin-login-form">
            <?php wp_nonce_field( 'ueb_admin_login', 'ueb_admin_login_nonce' ); ?>

            <div class="form-group">
                <label for="ueb_username">Identifiant</label>
                <input type="text" id="ueb_username" name="ueb_username" required autofocus autocomplete="username">
            </div>

            <div class="form-group">
                <label for="ueb_password">Mot de passe</label>
                <input type="password" id="ueb_password" name="ueb_password" required autocomplete="current-password">
            </div>

            <button type="submit" name="ueb_admin_login" value="1" class="btn btn-primary">Se connecter</button>
        </form>
    </div>

<?php else : ?>

    <!-- ===== TABLEAU DE BORD ===== -->
    <div class="admin-shell">

        <aside class="admin-sidebar">
            <div class="admin-sidebar-brand">
                <span class="admin-sidebar-logo" aria-hidden="true">UEB</span>
                <span class="admin-sidebar-brand-text">
                    <span class="admin-sidebar-mark">Préinscriptions</span>
                    <span class="admin-sidebar-title">Université d'Ébolowa</span>
                </span>
            </div>

            <nav class="admin-sidebar-nav" role="tablist" aria-label="Sections du tableau de bord">
                <span class="admin-sidebar-heading">Navigation</span>

                <button type="button" class="admin-tab-btn active" data-tab="stats"
                        role="tab" aria-selected="true" aria-controls="admin-tab-stats" id="admin-tabbtn-stats">
                    <svg class="admin-icon" aria-hidden="true"><use href="#ueb-i-overview"/></svg>
                    Vue d'ensemble
                </button>

                <button type="button" class="admin-tab-btn" data-tab="liste"
                        role="tab" aria-selected="false" aria-controls="admin-tab-liste" id="admin-tabbtn-liste">
                    <svg class="admin-icon" aria-hidden="true"><use href="#ueb-i-list"/></svg>
                    Dossiers
                </button>
            </nav>

            <div class="admin-sidebar-footer">
                <div class="admin-sidebar-user">
                    <span class="admin-sidebar-user-avatar" aria-hidden="true"><?php echo esc_html( mb_substr( $ueb_user->display_name, 0, 1 ) ); ?></span>
                    <span class="admin-sidebar-user-meta">
                        <span class="admin-sidebar-user-name"><?php echo esc_html( $ueb_user->display_name ); ?></span>
                        <span class="admin-sidebar-user-role">Gestionnaire</span>
                    </span>
                </div>
                <a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>" class="admin-sidebar-logout">
                    <svg class="admin-icon admin-icon--sm" aria-hidden="true"><use href="#ueb-i-logout"/></svg>
                    Déconnexion
                </a>
            </div>
        </aside>

        <div class="admin-main" id="admin-main">

            <header class="admin-topbar">
                <div>
                    <h1 id="admin-page-title">Vue d'ensemble</h1>
                    <p class="admin-subtitle">Préinscriptions — Université d'Ébolowa</p>
                </div>

                <div class="admin-topbar-actions">
                    <button type="button" id="admin-theme-toggle" class="admin-tbtn admin-tbtn--icon"
                            aria-label="Basculer entre le thème clair et sombre" title="Thème clair / sombre">
                        <svg class="admin-icon admin-theme-icon--moon" aria-hidden="true"><use href="#ueb-i-moon"/></svg>
                        <svg class="admin-icon admin-theme-icon--sun" aria-hidden="true"><use href="#ueb-i-sun"/></svg>
                    </button>

                    <button type="button" id="admin-export" class="admin-tbtn">
                        <svg class="admin-icon admin-icon--sm" aria-hidden="true"><use href="#ueb-i-download"/></svg>
                        Exporter
                    </button>

                    <button type="button" id="admin-filter-toggle" class="admin-tbtn admin-tbtn--primary"
                            aria-controls="admin-filter-drawer" aria-expanded="false">
                        <svg class="admin-icon admin-icon--sm" aria-hidden="true"><use href="#ueb-i-filter"/></svg>
                        Filtres
                        <span id="admin-filter-count" class="admin-filter-badge" hidden>0</span>
                    </button>
                </div>
            </header>

            <!-- Rappel des filtres actifs, cliquables pour retrait unitaire. -->
            <div id="admin-active-filters" class="admin-active-filters" aria-live="polite"></div>

            <!-- ===== VUE D'ENSEMBLE ===== -->
            <div id="admin-tab-stats" class="admin-tab-panel active" role="tabpanel" aria-labelledby="admin-tabbtn-stats" tabindex="-1">

                <div id="admin-kpi-grid" class="admin-kpi-grid admin-stagger">
                    <!-- Squelettes : la place est réservée dès le premier rendu,
                         le remplacement par les vraies cartes ne décale rien. -->
                    <div class="admin-skeleton admin-skeleton--kpi"></div>
                    <div class="admin-skeleton admin-skeleton--kpi"></div>
                    <div class="admin-skeleton admin-skeleton--kpi"></div>
                    <div class="admin-skeleton admin-skeleton--kpi"></div>
                    <div class="admin-skeleton admin-skeleton--kpi"></div>
                </div>

                <div class="admin-charts-grid admin-stagger">

                    <section class="admin-chart-card admin-chart-card--full">
                        <div class="admin-chart-head">
                            <div>
                                <h2 class="admin-chart-title">Évolution des dépôts</h2>
                                <p class="admin-chart-sub">Nombre de dossiers créés par jour</p>
                            </div>
                            <span class="admin-chart-total" data-total-for="chart-evolution"></span>
                        </div>
                        <div class="admin-chart-body"><canvas id="chart-evolution"></canvas></div>
                    </section>

                    <section class="admin-chart-card admin-chart-card--third">
                        <div class="admin-chart-head">
                            <div>
                                <h2 class="admin-chart-title">Facultés</h2>
                                <p class="admin-chart-sub">Répartition des dossiers</p>
                            </div>
                        </div>
                        <div class="admin-chart-body"><canvas id="chart-faculte"></canvas></div>
                    </section>

                    <section class="admin-chart-card admin-chart-card--third">
                        <div class="admin-chart-head">
                            <div>
                                <h2 class="admin-chart-title">Sexe</h2>
                                <p class="admin-chart-sub">Part des candidates et candidats</p>
                            </div>
                        </div>
                        <div class="admin-chart-body"><canvas id="chart-sexe"></canvas></div>
                    </section>

                    <section class="admin-chart-card admin-chart-card--third">
                        <div class="admin-chart-head">
                            <div>
                                <h2 class="admin-chart-title">Régions d'origine</h2>
                                <p class="admin-chart-sub">Provenance des candidats</p>
                            </div>
                        </div>
                        <div class="admin-chart-body"><canvas id="chart-region"></canvas></div>
                    </section>

                    <section class="admin-chart-card admin-chart-card--twothird">
                        <div class="admin-chart-head">
                            <div>
                                <h2 class="admin-chart-title">Filières les plus demandées</h2>
                                <p class="admin-chart-sub">Premier choix, 15 premières filières</p>
                            </div>
                        </div>
                        <div class="admin-chart-body"><canvas id="chart-filiere"></canvas></div>
                    </section>

                    <section class="admin-chart-card admin-chart-card--third">
                        <div class="admin-chart-head">
                            <div>
                                <h2 class="admin-chart-title">Faculté et sexe</h2>
                                <p class="admin-chart-sub">Répartition croisée</p>
                            </div>
                        </div>
                        <div class="admin-chart-body"><canvas id="chart-faculte-sexe"></canvas></div>
                    </section>

                </div>
            </div>

            <!-- ===== DOSSIERS ===== -->
            <div id="admin-tab-liste" class="admin-tab-panel" role="tabpanel" aria-labelledby="admin-tabbtn-liste" tabindex="-1">

                <div class="admin-liste-toolbar">
                    <div class="admin-search">
                        <svg class="admin-icon admin-icon--sm" aria-hidden="true"><use href="#ueb-i-search"/></svg>
                        <label class="admin-sr-only" for="admin-recherche">Rechercher un dossier</label>
                        <input type="search" id="admin-recherche" class="admin-recherche-input"
                               placeholder="Rechercher un nom, prénom ou numéro de dossier…"
                               autocomplete="off">
                    </div>
                    <div id="admin-results-count" class="admin-results-count" aria-live="polite">Chargement…</div>
                </div>

                <div id="admin-liste-container"></div>
                <div id="admin-pagination" class="admin-pagination"></div>
            </div>

        </div>
    </div>

    <!-- ===== TIROIR DE FILTRES (partagé par les deux vues) ===== -->
    <div id="admin-filter-overlay" class="admin-filter-overlay" hidden></div>
    <aside id="admin-filter-drawer" class="admin-filter-drawer" role="dialog" aria-modal="true"
           aria-labelledby="admin-filter-title" aria-hidden="true">

        <div class="admin-filter-drawer-header">
            <h2 id="admin-filter-title">Filtres</h2>
            <button type="button" id="admin-filter-close" class="admin-filter-close" aria-label="Fermer les filtres">
                <svg class="admin-icon" aria-hidden="true"><use href="#ueb-i-close"/></svg>
            </button>
        </div>

        <form id="admin-filter-form" class="admin-filter-form">

            <div class="admin-filter-section">
                <h3>Formation</h3>
                <div class="admin-filter-grid">
                    <div class="admin-filter-field">
                        <label for="filter-faculte">Faculté</label>
                        <select id="filter-faculte"><option value="">Chargement…</option></select>
                    </div>
                    <div class="admin-filter-field">
                        <label for="filter-diplome_admission">Diplôme d'admission</label>
                        <select id="filter-diplome_admission"><option value="">Chargement…</option></select>
                    </div>
                    <div class="admin-filter-field">
                        <label for="filter-specialite_diplome">Série / Spécialité</label>
                        <select id="filter-specialite_diplome" disabled><option value="">— Choisir faculté et diplôme —</option></select>
                    </div>
                    <div class="admin-filter-field">
                        <label for="filter-type_formation">Type de formation</label>
                        <select id="filter-type_formation"><option value="">Chargement…</option></select>
                    </div>
                    <div class="admin-filter-field">
                        <label for="filter-filiere">Filière (1er, 2e ou 3e choix)</label>
                        <select id="filter-filiere" disabled><option value="">— Choisir d'abord une faculté —</option></select>
                    </div>
                    <div class="admin-filter-field">
                        <label for="filter-niveau_lmd">Niveau LMD</label>
                        <select id="filter-niveau_lmd"><option value="">Chargement…</option></select>
                    </div>
                    <div class="admin-filter-field">
                        <label for="filter-mention">Mention</label>
                        <select id="filter-mention"><option value="">Chargement…</option></select>
                    </div>
                    <div class="admin-filter-field">
                        <label for="filter-statut_etudiant">Statut étudiant</label>
                        <select id="filter-statut_etudiant"><option value="">Chargement…</option></select>
                    </div>
                </div>
            </div>

            <div class="admin-filter-section">
                <h3>Profil</h3>
                <div class="admin-filter-grid">
                    <div class="admin-filter-field">
                        <label for="filter-sexe">Sexe</label>
                        <select id="filter-sexe"><option value="">Chargement…</option></select>
                    </div>
                    <div class="admin-filter-field">
                        <label for="filter-handicap">Situation de handicap</label>
                        <select id="filter-handicap"><option value="">Chargement…</option></select>
                    </div>
                    <div class="admin-filter-field">
                        <label for="filter-nationalite">Nationalité</label>
                        <select id="filter-nationalite"><option value="">Chargement…</option></select>
                    </div>
                    <div class="admin-filter-field">
                        <label for="filter-premiere_langue">Première langue</label>
                        <select id="filter-premiere_langue"><option value="">Chargement…</option></select>
                    </div>
                    <div class="admin-filter-field">
                        <label for="filter-situation_matrimoniale">Situation matrimoniale</label>
                        <select id="filter-situation_matrimoniale"><option value="">Chargement…</option></select>
                    </div>
                    <div class="admin-filter-field">
                        <label for="filter-statut_socio_professionnel">Statut socio-professionnel</label>
                        <select id="filter-statut_socio_professionnel"><option value="">Chargement…</option></select>
                    </div>
                </div>
            </div>

            <div class="admin-filter-section">
                <h3>Origine géographique</h3>
                <div class="admin-filter-grid">
                    <div class="admin-filter-field">
                        <label for="filter-region_origine">Région d'origine</label>
                        <select id="filter-region_origine"><option value="">Chargement…</option></select>
                    </div>
                    <div class="admin-filter-field">
                        <label for="filter-departement_origine">Département d'origine</label>
                        <select id="filter-departement_origine" disabled><option value="">— Choisir d'abord une région —</option></select>
                    </div>
                    <div class="admin-filter-field">
                        <label for="filter-commune_origine">Commune d'origine</label>
                        <select id="filter-commune_origine" disabled><option value="">— Choisir d'abord un département —</option></select>
                    </div>
                </div>
            </div>

            <div class="admin-filter-section">
                <h3>Centres d'intérêt</h3>
                <div class="admin-filter-grid">
                    <div class="admin-filter-field">
                        <label for="filter-sport_prefere">Sport préféré</label>
                        <select id="filter-sport_prefere"><option value="">Chargement…</option></select>
                    </div>
                    <div class="admin-filter-field">
                        <label for="filter-art_pratique">Art pratiqué</label>
                        <select id="filter-art_pratique"><option value="">Chargement…</option></select>
                    </div>
                </div>
            </div>

            <div class="admin-filter-actions">
                <button type="submit" class="btn btn-primary">Appliquer</button>
                <button type="button" id="admin-filter-reset" class="btn btn-secondary">Réinitialiser</button>
            </div>
        </form>
    </aside>

    <!-- ===== MODALE DE DÉTAIL D'UN DOSSIER ===== -->
    <div id="admin-detail-modal" class="admin-modal" role="dialog" aria-modal="true"
         aria-labelledby="admin-detail-title" hidden>
        <div class="admin-modal-backdrop" data-close-modal></div>
        <div class="admin-modal-box">
            <div class="admin-modal-header">
                <div>
                    <h2 id="admin-detail-title">Dossier</h2>
                    <p id="admin-detail-sub"></p>
                </div>
                <button type="button" class="admin-filter-close" data-close-modal aria-label="Fermer le détail">
                    <svg class="admin-icon" aria-hidden="true"><use href="#ueb-i-close"/></svg>
                </button>
            </div>
            <div class="admin-modal-body" id="admin-detail-body"></div>
        </div>
    </div>

<?php endif; ?>

</div>

<?php get_footer(); ?>
