<?php
/**
 * Template Name: Administration
 *
 * Page de connexion + tableau de bord pour les gestionnaires de
 * préinscriptions. Accès réservé aux comptes ayant la capacité
 * "voir_preinscriptions" (rôle gestionnaire_preinscriptions).
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

        <!-- ===== LISTE ET DÉTAIL DES DOSSIERS ===== -->
        <h2 class="admin-section-title">Dossiers</h2>

        <?php
        $ueb_filtre_faculte = isset( $_GET['faculte'] ) ? absint( $_GET['faculte'] ) : 0;
        $ueb_filtre_statut  = isset( $_GET['statut'] ) ? sanitize_text_field( wp_unslash( $_GET['statut'] ) ) : '';
        $ueb_numero_detail  = isset( $_GET['dossier'] ) ? sanitize_text_field( wp_unslash( $_GET['dossier'] ) ) : '';
        ?>
        <div id="admin-liste-dossiers">
        <?php if ( $ueb_numero_detail ) :
            $detail = ueb_admin_get_dossier_detail( $ueb_numero_detail );
            if ( ! $detail ) : ?>
                <p>Dossier introuvable.</p>
                <a class="admin-back-link" href="<?php echo esc_url( get_permalink() ); ?>">&larr; Retour à la liste</a>
            <?php else : $d = $detail['dossier']; ?>
                <a class="admin-back-link" href="<?php echo esc_url( get_permalink() ); ?>">&larr; Retour à la liste</a>
                <h3 class="admin-detail-header"><?php echo esc_html( $d->numero_dossier ); ?></h3>
                <table class="admin-detail-table">
                    <tr><th>Nom</th><td><?php echo esc_html( $d->nom . ' ' . $d->prenom ); ?></td></tr>
                    <tr><th>Statut</th><td><?php echo esc_html( $d->statut ); ?></td></tr>
                    <tr><th>Faculté</th><td><?php echo esc_html( $d->faculte_nom ); ?></td></tr>
                    <tr><th>Diplôme</th><td><?php echo esc_html( $d->diplome_libelle ); ?></td></tr>
                    <tr><th>Série</th><td><?php echo esc_html( $d->serie_libelle ); ?></td></tr>
                    <tr><th>1er choix filière</th><td><?php echo esc_html( $d->filiere1_libelle ); ?></td></tr>
                    <tr><th>2e choix filière</th><td><?php echo esc_html( $d->filiere2_libelle ); ?></td></tr>
                    <tr><th>Sexe</th><td><?php echo esc_html( $d->sexe === 'M' ? 'Masculin' : 'Féminin' ); ?></td></tr>
                    <tr><th>Nationalité</th><td><?php echo esc_html( $d->nationalite_nom ); ?></td></tr>
                    <tr><th>Email</th><td><?php echo esc_html( $d->email ); ?></td></tr>
                    <tr><th>Origine</th><td><?php echo esc_html( trim( $d->region_nom . ' / ' . $d->departement_nom . ' / ' . $d->commune_nom, ' /' ) ); ?></td></tr>
                    <tr><th>Téléphone(s)</th><td>
                        <?php foreach ( $detail['telephones'] as $tel ) : ?>
                            <?php echo esc_html( $tel->numero . ' (' . $tel->type . ')' ); ?><br>
                        <?php endforeach; ?>
                    </td></tr>
                </table>
            <?php endif; ?>
        <?php else :
            $facultes = ueb_admin_get_facultes_liste();
            $dossiers = ueb_admin_get_dossiers( $ueb_filtre_faculte, $ueb_filtre_statut ); ?>
            <form method="get" action="<?php echo esc_url( get_permalink() ); ?>" class="admin-filters">
                <select name="faculte">
                    <option value="">Toutes les facultés</option>
                    <?php foreach ( $facultes as $fac ) : ?>
                        <option value="<?php echo (int) $fac->id; ?>" <?php selected( $ueb_filtre_faculte, $fac->id ); ?>><?php echo esc_html( $fac->nom_fr ); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="statut">
                    <option value="">Tous les statuts</option>
                    <option value="brouillon" <?php selected( $ueb_filtre_statut, 'brouillon' ); ?>>Brouillon</option>
                    <option value="soumis" <?php selected( $ueb_filtre_statut, 'soumis' ); ?>>Soumis</option>
                </select>
                <button type="submit" class="btn btn-secondary">Filtrer</button>
            </form>
            <div class="admin-dossiers-table-wrap">
                <table class="admin-dossiers-table">
                    <thead><tr><th>N° Dossier</th><th>Nom</th><th>Prénom</th><th>Faculté</th><th>Statut</th><th>Date</th><th></th></tr></thead>
                    <tbody>
                    <?php if ( ! $dossiers ) : ?>
                        <tr><td colspan="7">Aucun dossier trouvé.</td></tr>
                    <?php else : foreach ( $dossiers as $row ) : ?>
                        <tr>
                            <td><?php echo esc_html( $row->numero_dossier ); ?></td>
                            <td><?php echo esc_html( $row->nom ); ?></td>
                            <td><?php echo esc_html( $row->prenom ); ?></td>
                            <td><?php echo esc_html( $row->faculte_nom ); ?></td>
                            <td><?php echo esc_html( $row->statut ); ?></td>
                            <td><?php echo esc_html( $row->date_creation ); ?></td>
                            <td><a href="<?php echo esc_url( add_query_arg( 'dossier', $row->numero_dossier, get_permalink() ) ); ?>">Voir →</a></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        </div>

        <!-- ===== STATISTIQUES ===== -->
        <h2 class="admin-section-title">Statistiques</h2>

        <?php $chiffres = ueb_admin_chiffres_cles(); ?>
        <div id="admin-analytics">
            <div class="admin-kpi-grid">
                <div class="admin-kpi">
                    <span class="admin-kpi-num"><?php echo (int) $chiffres['total']; ?></span>
                    <span>Dossiers au total</span>
                </div>
                <div class="admin-kpi">
                    <span class="admin-kpi-num"><?php echo (int) $chiffres['aujourdhui']; ?></span>
                    <span>Dossiers aujourd'hui</span>
                </div>
                <div class="admin-kpi admin-kpi--taux">
                    <span class="admin-kpi-label">Taux par faculté</span>
                    <?php $ueb_taux_facultes = ueb_admin_taux_par_faculte(); ?>
                    <ul class="admin-kpi-taux-list">
                        <?php if ( ! $ueb_taux_facultes ) : ?>
                            <li>Aucune donnée</li>
                        <?php else : foreach ( $ueb_taux_facultes as $taux ) : ?>
                            <li><span><?php echo esc_html( $taux->label ); ?></span> <strong><?php echo esc_html( $taux->pourcentage ); ?>%</strong></li>
                        <?php endforeach; endif; ?>
                    </ul>
                </div>
            </div>

            <div class="admin-charts-grid">
                <div class="admin-chart-card"><canvas id="chart-faculte"></canvas></div>
                <div class="admin-chart-card"><canvas id="chart-filiere"></canvas></div>
                <div class="admin-chart-card"><canvas id="chart-region"></canvas></div>
                <div class="admin-chart-card"><canvas id="chart-sexe"></canvas></div>
                <div class="admin-chart-card admin-chart-card--wide"><canvas id="chart-evolution"></canvas></div>
            </div>
        </div>
    </div>

<?php endif; ?>

</div>

<?php get_footer(); ?>