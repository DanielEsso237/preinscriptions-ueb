<?php
/**
 * Template Name: Administration
 *
 * Page de connexion + tableau de bord pour les gestionnaires de
 * préinscriptions. Accès réservé aux comptes ayant la capacité
 * "voir_preinscriptions" (rôle gestionnaire_preinscriptions).
 *
 * Le dashboard a deux onglets (Liste des préinscrits / Statistiques) qui
 * partagent un même panneau de filtres (tous les champs à choix du
 * formulaire de préinscription) : les deux se recalculent en AJAX à
 * chaque changement de filtre, sans rechargement de page.
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

get_header();
?>

<div class="admin-page">

<?php if ( ! $ueb_is_authorized ) : ?>

    <!-- ===== FORMULAIRE DE CONNEXION ===== -->
    <div class="admin-login-wrap">
        <h1>Espace gestion — Préinscriptions UEB</h1>

        <?php if ( is_user_logged_in() && ! current_user_can( 'voir_preinscriptions' ) ) : ?>
            <p class="admin-error" role="alert">Votre compte n'a pas les droits nécessaires pour accéder à cette page.</p>
        <?php endif; ?>

        <?php if ( $ueb_login_error ) : ?>
            <p class="admin-error" role="alert"><?php echo esc_html( $ueb_login_error ); ?></p>
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
    <div class="admin-dashboard">
        <div class="admin-dashboard-header">
            <h1>Tableau de bord — Préinscriptions</h1>
            <a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>" class="btn btn-secondary">Déconnexion</a>
        </div>
        <p class="admin-welcome">Bienvenue, <?php echo esc_html( wp_get_current_user()->display_name ); ?>.</p>

        <!-- ===== FILTRES (partagés par les deux onglets) ===== -->
        <form id="admin-filter-form" class="admin-filter-form">
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
                <div class="admin-filter-field">
                    <label for="filter-sport_prefere">Sport préféré</label>
                    <select id="filter-sport_prefere"><option value="">Chargement…</option></select>
                </div>
                <div class="admin-filter-field">
                    <label for="filter-art_pratique">Art pratiqué</label>
                    <select id="filter-art_pratique"><option value="">Chargement…</option></select>
                </div>

            </div>

            <div class="admin-filter-actions">
                <button type="submit" class="btn btn-primary">Filtrer</button>
                <button type="button" id="admin-filter-reset" class="btn btn-secondary">Réinitialiser</button>
            </div>
        </form>

        <!-- ===== ONGLETS ===== -->
        <div class="admin-tabs" role="tablist">
            <button type="button" class="admin-tab-btn active" data-tab="liste" role="tab">Liste des préinscrits</button>
            <button type="button" class="admin-tab-btn" data-tab="stats" role="tab">Statistiques</button>
        </div>

        <!-- ===== ONGLET LISTE ===== -->
        <div id="admin-tab-liste" class="admin-tab-panel active" role="tabpanel">
            <div id="admin-results-count" class="admin-results-count">Chargement…</div>
            <div id="admin-liste-container"></div>
        </div>

        <!-- ===== ONGLET STATISTIQUES ===== -->
        <div id="admin-tab-stats" class="admin-tab-panel" role="tabpanel">
            <div id="admin-kpi-grid" class="admin-kpi-grid"></div>

            <div class="admin-charts-grid">
                <div class="admin-chart-card"><canvas id="chart-faculte"></canvas></div>
                <div class="admin-chart-card"><canvas id="chart-filiere"></canvas></div>
                <div class="admin-chart-card"><canvas id="chart-region"></canvas></div>
                <div class="admin-chart-card"><canvas id="chart-sexe"></canvas></div>
                <div class="admin-chart-card"><canvas id="chart-faculte-sexe"></canvas></div>
                <div class="admin-chart-card admin-chart-card--wide"><canvas id="chart-evolution"></canvas></div>
            </div>
        </div>
    </div>

<?php endif; ?>

</div>

<?php get_footer(); ?>