<?php
/**
 * Template Name: Gestion des références
 *
 * Page de connexion + back-office dédié à l'administration des tables de
 * référence ueb_* (facultés, filières, diplômes, régions, départements,
 * communes, etc.). Volontairement SÉPARÉE de page-administration.php
 * (dashboard des dossiers) : les deux pages ont des publics différents et
 * ne doivent pas partager le même système d'autorisation.
 *
 * Accès réservé aux comptes ayant la capacité WordPress "manage_options"
 * (les administrateurs) — indépendamment de la capacité "voir_preinscriptions"
 * qui gouverne l'accès au dashboard des dossiers. Un administrateur peut
 * très bien ne pas avoir accès aux dossiers, et un gestionnaire de dossiers
 * ne doit jamais pouvoir modifier les tables de référence.
 *
 * @package Preinscriptions_UEB
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ================================================================
   TRAITEMENT DE LA CONNEXION (avant tout affichage)
   Formulaire et nonce dédiés ('ueb_ref_login'), distincts de ceux de
   page-administration.php : les deux écrans de connexion ne doivent rien
   se partager.
   ================================================================ */
$ueb_ref_login_error = '';

if ( isset( $_POST['ueb_ref_login'] ) ) {

    if ( ! isset( $_POST['ueb_ref_login_nonce'] ) ||
         ! wp_verify_nonce( $_POST['ueb_ref_login_nonce'], 'ueb_ref_login' ) ) {
        $ueb_ref_login_error = 'Erreur de sécurité, merci de réessayer.';
    } else {
        $creds = array(
            'user_login'    => sanitize_text_field( wp_unslash( $_POST['ueb_ref_username'] ?? '' ) ),
            'user_password' => $_POST['ueb_ref_password'] ?? '',
            'remember'      => true,
        );

        $user = wp_signon( $creds, is_ssl() );

        if ( is_wp_error( $user ) ) {
            $ueb_ref_login_error = 'Identifiant ou mot de passe incorrect.';
        } elseif ( ! user_can( $user, 'manage_options' ) ) {
            wp_logout();
            $ueb_ref_login_error = "Ce compte n'a pas accès à cette page : seuls les administrateurs peuvent gérer les données de référence.";
        } else {
            wp_safe_redirect( get_permalink() );
            exit;
        }
    }
}

$ueb_ref_is_authorized = is_user_logged_in() && current_user_can( 'manage_options' );
$ueb_ref_user          = wp_get_current_user();

get_header();
?>

<!-- Sprite d'icônes : sous-ensemble de celui de page-administration.php
     (même style de traits, pour rester visuellement cohérent), dupliqué
     ici car les deux pages ne partagent aucun gabarit. -->
<svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true" focusable="false">
    <symbol id="ueb-i-database" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0 0 18 0V5"/><path d="M3 12a9 3 0 0 0 18 0"/></symbol>
    <symbol id="ueb-i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
    <symbol id="ueb-i-edit" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4Z"/></symbol>
    <symbol id="ueb-i-trash" viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="m19 6-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></symbol>
    <symbol id="ueb-i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></symbol>
    <symbol id="ueb-i-close" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></symbol>
    <symbol id="ueb-i-alert" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></symbol>
    <symbol id="ueb-i-inbox" viewBox="0 0 24 24"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11Z"/></symbol>
    <symbol id="ueb-i-arrow-left" viewBox="0 0 24 24"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></symbol>
    <symbol id="ueb-i-arrow-right" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></symbol>
    <symbol id="ueb-i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></symbol>
    <symbol id="ueb-i-moon" viewBox="0 0 24 24"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></symbol>
    <symbol id="ueb-i-logout" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/></symbol>
</svg>

<div class="admin-page">

<?php if ( ! $ueb_ref_is_authorized ) : ?>

    <!-- ===== FORMULAIRE DE CONNEXION ===== -->
    <div class="admin-login-wrap">
        <h1>Gestion des références — Préinscriptions UEB</h1>

        <?php if ( is_user_logged_in() && ! current_user_can( 'manage_options' ) ) : ?>
            <p class="admin-error" role="alert">
                <svg class="admin-icon admin-icon--sm" aria-hidden="true"><use href="#ueb-i-alert"/></svg>
                Votre compte n'a pas les droits administrateur nécessaires pour accéder à cette page.
            </p>
        <?php endif; ?>

        <?php if ( $ueb_ref_login_error ) : ?>
            <p class="admin-error" role="alert">
                <svg class="admin-icon admin-icon--sm" aria-hidden="true"><use href="#ueb-i-alert"/></svg>
                <?php echo esc_html( $ueb_ref_login_error ); ?>
            </p>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( get_permalink() ); ?>" class="admin-login-form">
            <?php wp_nonce_field( 'ueb_ref_login', 'ueb_ref_login_nonce' ); ?>

            <div class="form-group">
                <label for="ueb_ref_username">Identifiant</label>
                <input type="text" id="ueb_ref_username" name="ueb_ref_username" required autofocus autocomplete="username">
            </div>

            <div class="form-group">
                <label for="ueb_ref_password">Mot de passe</label>
                <input type="password" id="ueb_ref_password" name="ueb_ref_password" required autocomplete="current-password">
            </div>

            <button type="submit" name="ueb_ref_login" value="1" class="btn btn-primary">Se connecter</button>
        </form>
    </div>

<?php else : ?>

    <!-- ===== PAGE DE GESTION DES RÉFÉRENCES ===== -->
    <div class="admin-shell admin-shell--simple">

        <div class="admin-main" id="admin-main">

            <header class="admin-topbar">
                <div>
                    <h1>Références</h1>
                    <p class="admin-subtitle">Facultés, filières, diplômes, régions… — Préinscriptions UEB</p>
                </div>

                <div class="admin-topbar-actions">
                    <button type="button" id="admin-theme-toggle" class="admin-tbtn admin-tbtn--icon"
                            aria-label="Basculer entre le thème clair et sombre" title="Thème clair / sombre">
                        <svg class="admin-icon admin-theme-icon--moon" aria-hidden="true"><use href="#ueb-i-moon"/></svg>
                        <svg class="admin-icon admin-theme-icon--sun" aria-hidden="true"><use href="#ueb-i-sun"/></svg>
                    </button>

                    <a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>" class="admin-tbtn">
                        <svg class="admin-icon admin-icon--sm" aria-hidden="true"><use href="#ueb-i-logout"/></svg>
                        Déconnexion
                    </a>
                </div>
            </header>

            <div class="admin-ref-layout">
                <nav class="admin-ref-nav" id="admin-ref-nav" aria-label="Tables de référence"></nav>

                <div class="admin-ref-content">
                    <div class="admin-liste-toolbar">
                        <div class="admin-search">
                            <svg class="admin-icon admin-icon--sm" aria-hidden="true"><use href="#ueb-i-search"/></svg>
                            <label class="admin-sr-only" for="admin-ref-recherche">Rechercher dans cette table</label>
                            <input type="search" id="admin-ref-recherche" class="admin-recherche-input"
                                   placeholder="Rechercher…" autocomplete="off">
                        </div>
                        <button type="button" id="admin-ref-add" class="admin-tbtn admin-tbtn--primary">
                            <svg class="admin-icon admin-icon--sm" aria-hidden="true"><use href="#ueb-i-plus"/></svg>
                            Ajouter
                        </button>
                    </div>

                    <div id="admin-ref-table-wrap"></div>
                    <div id="admin-ref-pagination" class="admin-pagination"></div>
                </div>
            </div>

        </div>
    </div>

    <!-- ===== MODALE AJOUT / MODIFICATION D'UNE RÉFÉRENCE ===== -->
    <div id="admin-ref-modal" class="admin-modal" role="dialog" aria-modal="true"
         aria-labelledby="admin-ref-modal-title" hidden>
        <div class="admin-modal-backdrop" data-close-ref-modal></div>
        <div class="admin-modal-box">
            <div class="admin-modal-header">
                <div>
                    <h2 id="admin-ref-modal-title">Ajouter</h2>
                    <p id="admin-ref-modal-sub"></p>
                </div>
                <button type="button" class="admin-filter-close" data-close-ref-modal aria-label="Fermer">
                    <svg class="admin-icon" aria-hidden="true"><use href="#ueb-i-close"/></svg>
                </button>
            </div>
            <form id="admin-ref-form" class="admin-modal-body">
                <div id="admin-ref-form-error" class="admin-error" role="alert" hidden></div>
                <div id="admin-ref-form-fields"></div>
                <div class="admin-ref-form-actions">
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                    <button type="button" class="btn btn-secondary" data-close-ref-modal>Annuler</button>
                </div>
            </form>
        </div>
    </div>

<?php endif; ?>

</div>

<?php get_footer(); ?>
