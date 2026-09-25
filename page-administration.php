<?php
/**
 * Template Name: Administration
 *
 * Page de connexion + tableau de bord pour les gestionnaires de
 * préinscriptions. Accès réservé aux comptes ayant la capacité
 * métier correspondant aux sections et aux données demandées.
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

// La connexion et sa limitation sont centralisées dans le portail.
$ueb_login_error = '';
$ueb_is_authorized = is_user_logged_in() && ueb_access_has_admin();
$ueb_user = wp_get_current_user();
if ( ! ueb_access_has_admin() ) wp_die( 'Accès refusé.', '', array( 'response' => 403 ) );

get_header();
?>

<!-- Sprite d'icônes : défini une fois, référencé partout via <use>. -->
<?php
// Sprite d'icônes partagé avec le portail (inc/icons.php) :
// une seule définition par icône pour tout le back-office.
ueb_icons_sprite();
?>

<div class="admin-page">



    <!-- ===== TABLEAU DE BORD ===== -->
    <div class="admin-shell">

        <aside class="admin-sidebar">
            <div class="admin-sidebar-brand">
                <!-- Même sceau que le portail, sur pastille blanche. -->
                <span class="admin-sidebar-logo admin-sidebar-logo--image" aria-hidden="true">
                    <img src="<?php echo esc_url( preinscriptions_img( 'logo-ueb.webp' ) ); ?>" width="38" height="38" alt="" decoding="async">
                </span>
                <span class="admin-sidebar-brand-text">
                    <span class="admin-sidebar-mark">Préinscriptions</span>
                    <span class="admin-sidebar-title">Université d'Ébolowa</span>
                </span>
            </div>

            <nav class="admin-sidebar-nav" role="tablist" aria-label="Sections du tableau de bord">
                <span class="admin-sidebar-heading">Navigation</span>

                <?php if ( ueb_access_has( array( 'ueb_section_stats', 'ueb_view_stats' ) ) ) : ?><button type="button" class="admin-tab-btn active" data-tab="stats"
                        role="tab" aria-selected="true" aria-controls="admin-tab-stats" id="admin-tabbtn-stats">
                    <svg class="admin-icon" aria-hidden="true"><use href="#ueb-i-overview"/></svg>
                    Vue d'ensemble
                </button><?php endif; ?>

                <?php if ( ueb_access_has( array( 'ueb_section_effectifs', 'ueb_view_stats' ) ) ) : ?><button type="button" class="admin-tab-btn" data-tab="effectifs"
                        role="tab" aria-selected="false" aria-controls="admin-tab-effectifs" id="admin-tabbtn-effectifs">
                    <svg class="admin-icon" aria-hidden="true"><use href="#ueb-i-org"/></svg>
                    Effectifs
                </button><?php endif; ?>

                <?php if ( ueb_access_has( array( 'ueb_section_dossiers', 'ueb_view_students' ) ) ) : ?><button type="button" class="admin-tab-btn" data-tab="liste"
                        role="tab" aria-selected="false" aria-controls="admin-tab-liste" id="admin-tabbtn-liste">
                    <svg class="admin-icon" aria-hidden="true"><use href="#ueb-i-list"/></svg>
                    Dossiers
                </button><?php endif; ?>
            </nav>

            <div class="admin-sidebar-footer">
                <div class="admin-sidebar-user">
                    <span class="admin-sidebar-user-avatar" aria-hidden="true"><?php echo esc_html( mb_substr( $ueb_user->display_name, 0, 1 ) ); ?></span>
                    <span class="admin-sidebar-user-meta">
                        <span class="admin-sidebar-user-name"><?php echo esc_html( $ueb_user->display_name ); ?></span>
                        <span class="admin-sidebar-user-role"><?php echo esc_html( ueb_access_role_label() ); ?></span>
                    </span>
                </div>
                <a href="<?php echo esc_url( ueb_portal_url( 'logout', array( 'nonce' => wp_create_nonce( 'ueb_logout' ) ) ) ); ?>" class="admin-sidebar-logout">
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

                <div class="admin-topbar-actions"><a class="admin-tbtn" href="<?php echo esc_url( ueb_portal_home() ); ?>">Mon espace</a>
                    <button type="button" id="admin-theme-toggle" class="admin-tbtn admin-tbtn--icon"
                            aria-label="Basculer entre le thème clair et sombre" title="Thème clair / sombre">
                        <svg class="admin-icon admin-theme-icon--moon" aria-hidden="true"><use href="#ueb-i-moon"/></svg>
                        <svg class="admin-icon admin-theme-icon--sun" aria-hidden="true"><use href="#ueb-i-sun"/></svg>
                    </button>

                    <!-- Export de la liste filtrée : le format est un choix,
                         pas un réglage caché — les trois sont donnés d'emblée. -->
                    <?php if ( ueb_access_has( 'ueb_export_students' ) ) : ?><div class="admin-export" id="admin-export-wrap">
                        <button type="button" id="admin-export" class="admin-tbtn"
                                aria-haspopup="menu" aria-expanded="false" aria-controls="admin-export-menu">
                            <svg class="admin-icon admin-icon--sm" aria-hidden="true"><use href="#ueb-i-download"/></svg>
                            Exporter
                            <svg class="admin-icon admin-icon--sm admin-export-caret" aria-hidden="true"><use href="#ueb-i-chevron-down"/></svg>
                        </button>

                        <div id="admin-export-menu" class="admin-export-menu" role="menu"
                             aria-labelledby="admin-export" hidden>
                            <p class="admin-export-menu-title" id="admin-export-menu-title">
                                Liste des préinscrits
                                <span>Modèle officiel · sélection affichée</span>
                            </p>

                            <button type="button" class="admin-export-item" role="menuitem" data-format="pdf">
                                <svg class="admin-icon" aria-hidden="true"><use href="#ueb-i-file-pdf"/></svg>
                                <span class="admin-export-item-text">
                                    Document PDF
                                    <span>Prêt à imprimer et à signer</span>
                                </span>
                                <span class="admin-export-ext">PDF</span>
                            </button>

                            <button type="button" class="admin-export-item" role="menuitem" data-format="excel">
                                <svg class="admin-icon" aria-hidden="true"><use href="#ueb-i-file-sheet"/></svg>
                                <span class="admin-export-item-text">
                                    Classeur Excel
                                    <span>Colonnes filtrables et triables</span>
                                </span>
                                <span class="admin-export-ext">XLSX</span>
                            </button>

                            <button type="button" class="admin-export-item" role="menuitem" data-format="word">
                                <svg class="admin-icon" aria-hidden="true"><use href="#ueb-i-file-doc"/></svg>
                                <span class="admin-export-item-text">
                                    Document Word
                                    <span>Modifiable avant transmission</span>
                                </span>
                                <span class="admin-export-ext">DOCX</span>
                            </button>
                        </div>

                        <p id="admin-export-status" class="admin-export-status" role="status" aria-live="polite" hidden></p>
                    </div><?php endif; ?>

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
            <?php if ( ueb_access_has( array( 'ueb_section_stats', 'ueb_view_stats' ) ) ) : ?><div id="admin-tab-stats" class="admin-tab-panel active" role="tabpanel" aria-labelledby="admin-tabbtn-stats" tabindex="-1">

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

                    <?php if ( ueb_access_has( 'ueb_view_trends' ) ) : ?><section class="admin-chart-card admin-chart-card--full">
                        <div class="admin-chart-head">
                            <div>
                                <h2 class="admin-chart-title">Évolution des dépôts</h2>
                                <p class="admin-chart-sub">Nombre de dossiers créés par jour</p>
                            </div>
                            <span class="admin-chart-total" data-total-for="chart-evolution"></span>
                        </div>
                        <div class="admin-chart-body"><canvas id="chart-evolution"></canvas></div>
                    </section><?php endif; ?>

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
            </div><?php endif; ?>

            <!-- ===== EFFECTIFS =====
                 Un même gabarit sert les quatre paliers de l'organigramme
                 (université > établissement > département > filière) : seuls
                 les intitulés et les données changent d'un niveau à l'autre.
                 Tout le contenu est peint par admin-effectifs.js à partir de
                 la base ; ce balisage ne pose que la coque et les squelettes,
                 pour que la place soit réservée avant la première réponse. -->
            <?php if ( ueb_access_has( array( 'ueb_section_effectifs', 'ueb_view_stats' ) ) ) : ?><div id="admin-tab-effectifs" class="admin-tab-panel" role="tabpanel" aria-labelledby="admin-tabbtn-effectifs" tabindex="-1">

                <!-- Fil du parcours : chaque palier traversé garde son
                     effectif sous les yeux, pour que « 96 » se lise toujours
                     comme une part de « 380 », elle-même part de « 1 240 ». -->
                <nav id="eff-fil" class="eff-fil" aria-label="Niveau consulté"></nav>

                <header class="eff-entete">
                    <div class="eff-entete-texte">
                        <h2 id="eff-titre" class="eff-titre">Université d'Ébolowa</h2>
                        <p id="eff-soustitre" class="eff-soustitre">Effectifs par établissement</p>
                    </div>

                    <div class="eff-entete-total">
                        <span id="eff-total" class="eff-total-valeur">—</span>
                        <span class="eff-total-legende">préinscrits</span>
                    </div>

                    <button type="button" id="eff-refresh" class="admin-tbtn admin-tbtn--icon"
                            aria-label="Actualiser les effectifs" title="Actualiser">
                        <svg class="admin-icon" aria-hidden="true"><use href="#ueb-i-refresh"/></svg>
                    </button>
                </header>

                <p id="eff-maj" class="eff-maj" role="status" aria-live="polite"></p>

                <!-- Cartes du palier : raccourci de navigation vers l'échelon
                     du dessous. Le tableau juste en dessous porte les mêmes
                     effectifs au chiffre près ; ces cartes servent le geste,
                     pas la lecture fine. -->
                <div id="eff-cartes" class="eff-cartes admin-stagger">
                    <div class="admin-skeleton admin-skeleton--kpi"></div>
                    <div class="admin-skeleton admin-skeleton--kpi"></div>
                    <div class="admin-skeleton admin-skeleton--kpi"></div>
                    <div class="admin-skeleton admin-skeleton--kpi"></div>
                </div>

                <div id="eff-tableau" class="eff-tableau"></div>

                <div class="admin-charts-grid admin-stagger eff-graphes">

                    <section class="admin-chart-card admin-chart-card--half">
                        <div class="admin-chart-head">
                            <div>
                                <h3 class="admin-chart-title">Répartition par sexe</h3>
                                <p id="eff-sexe-sub" class="admin-chart-sub">Sur l'ensemble de l'université</p>
                            </div>
                        </div>
                        <div class="admin-chart-body"><canvas id="chart-eff-sexe"></canvas></div>
                    </section>

                    <section class="admin-chart-card admin-chart-card--half">
                        <div class="admin-chart-head">
                            <div>
                                <h3 class="admin-chart-title">Situation de handicap</h3>
                                <p id="eff-handicap-sub" class="admin-chart-sub">Sur l'ensemble de l'université</p>
                            </div>
                        </div>
                        <div class="admin-chart-body"><canvas id="chart-eff-handicap"></canvas></div>
                    </section>

                </div>
            </div><?php endif; ?>

            <!-- ===== DOSSIERS ===== -->
            <?php if ( ueb_access_has( array( 'ueb_section_dossiers', 'ueb_view_students' ) ) ) : ?><div id="admin-tab-liste" class="admin-tab-panel" role="tabpanel" aria-labelledby="admin-tabbtn-liste" tabindex="-1">

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

                <?php require get_template_directory() . '/templates/admin-duplicates.php'; ?>
                <div id="admin-liste-container"></div>
                <div id="admin-pagination" class="admin-pagination"></div>
            </div><?php endif; ?>

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



</div>

<?php get_footer(); ?>
