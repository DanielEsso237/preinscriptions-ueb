<?php
/**
 * Template Name: Gestion des références
 *
 * Back-office dédié à l'administration des tables de référence ueb_*
 * (facultés, filières, diplômes, régions, départements, communes, etc.).
 * Volontairement SÉPARÉE de page-administration.php (dashboard des
 * dossiers) : les deux pages ont des publics différents et ne doivent pas
 * partager le même système d'autorisation.
 *
 * Chaque rubrique possède sa capability métier et sa portée, vérifiées
 * côté serveur par le catalogue dynamique.
 *
 * Mise en page : la coque du back-office (barre latérale vert nuit, zone
 * principale claire). Ici la barre latérale EST l'index des tables — le
 * contenu de la page, pas une navigation vers d'autres écrans. Tout le
 * reste (en-tête de table, tableau, formulaire) est construit par
 * assets/js/admin-references.js à partir du registre.
 *
 * @package Preinscriptions_UEB
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// La connexion et sa limitation sont centralisées dans le portail.
if ( ! ueb_access_has_refs() ) wp_die( 'Accès refusé.', '', array( 'response' => 403 ) );
$ueb_ref_user = wp_get_current_user();

// Première table accessible, rendue côté serveur pour que le titre ne
// clignote pas avant que le JS prenne la main.
$ueb_ref_premiere = '';
foreach ( ueb_admin_ref_registry() as $ueb_ref_cle => $ueb_ref_cfg ) {
    if ( ueb_access_ref_allowed( $ueb_ref_cle ) ) { $ueb_ref_premiere = $ueb_ref_cle; break; }
}
$ueb_ref_registre = ueb_admin_ref_registry();
$ueb_ref_pres     = ueb_admin_ref_presentation();
$ueb_ref_titre    = $ueb_ref_premiere ? $ueb_ref_registre[ $ueb_ref_premiere ]['label'] : 'Référentiels';
$ueb_ref_desc     = isset( $ueb_ref_pres[ $ueb_ref_premiere ]['description'] ) ? $ueb_ref_pres[ $ueb_ref_premiere ]['description'] : '';

get_header();

// Sprite d'icônes partagé avec le portail (inc/icons.php).
ueb_icons_sprite();
?>

<div class="admin-page ref-page">

    <div class="admin-shell ref-shell">

        <!-- ===== INDEX DES TABLES ===== -->
        <aside class="admin-sidebar ref-sidebar" id="ref-sidebar">
            <div class="admin-sidebar-brand ref-brand">
                <span class="admin-sidebar-logo ref-brand-logo" aria-hidden="true">
                    <img src="<?php echo esc_url( preinscriptions_img( 'logo-ueb.webp' ) ); ?>" width="38" height="38" alt="" decoding="async">
                </span>
                <span class="admin-sidebar-brand-text">
                    <span class="admin-sidebar-mark">Référentiels</span>
                    <span class="admin-sidebar-title">Listes du formulaire</span>
                </span>
            </div>

            <!-- Téléphone : l'index se replie derrière la table courante. -->
            <button type="button" class="ref-nav-toggle" id="ref-nav-toggle"
                    aria-expanded="false" aria-controls="admin-ref-nav">
                <span class="ref-nav-toggle-text">
                    <span class="ref-nav-toggle-hint">Table</span>
                    <span class="ref-nav-toggle-label" id="ref-nav-toggle-label"><?php echo esc_html( $ueb_ref_titre ); ?></span>
                </span>
                <?php echo ueb_icon( 'chevron-down', 'admin-icon--sm' ); ?>
            </button>

            <nav class="ref-nav" id="admin-ref-nav" aria-label="Tables de référence"></nav>

            <!-- Compte : l'identité, puis la déconnexion, sur la même grille
                 que l'index (icône dans une case de la largeur de l'avatar,
                 libellé aligné sur le nom). -->
            <div class="admin-sidebar-footer ref-account">
                <div class="ref-account-user">
                    <span class="admin-sidebar-user-avatar" aria-hidden="true"><?php echo esc_html( mb_substr( $ueb_ref_user->display_name, 0, 1 ) ); ?></span>
                    <span class="ref-account-meta">
                        <span class="ref-account-name"><?php echo esc_html( $ueb_ref_user->display_name ); ?></span>
                        <?php
                        // Les rôles WordPress traduits s'écrivent « Administrateur/administratrice » :
                        // la coupure se fait après la barre oblique, jamais au milieu d'un mot.
                        $ueb_ref_role = ueb_access_role_label() ?: 'Gestion des références';
                        ?>
                        <span class="ref-account-role"><?php echo str_replace( '/', '/<wbr>', esc_html( $ueb_ref_role ) ); ?></span>
                    </span>
                </div>
                <nav class="ref-account-actions" aria-label="Compte">
                    <a class="ref-account-btn" href="<?php echo esc_url( ueb_portal_url( 'logout', array( 'nonce' => wp_create_nonce( 'ueb_logout' ) ) ) ); ?>">
                        <span class="ref-account-ico"><?php echo ueb_icon( 'logout', 'admin-icon--sm' ); ?></span>
                        <span class="ref-account-label">Déconnexion</span>
                    </a>
                </nav>
            </div>
        </aside>

        <!-- ===== TABLE COURANTE ===== -->
        <main class="admin-main ref-main" id="admin-main">

            <header class="ref-head">
                <div class="ref-head-text" id="ref-head-text">
                    <div class="ref-title-row">
                        <h1 id="ref-titre"><?php echo esc_html( $ueb_ref_titre ); ?></h1>
                        <span class="ref-total" id="ref-total" aria-live="polite"></span>
                    </div>
                    <p class="ref-desc" id="ref-desc"<?php echo $ueb_ref_desc ? '' : ' hidden'; ?>><?php echo esc_html( $ueb_ref_desc ); ?></p>
                    <div class="ref-liens" id="ref-liens" hidden></div>
                </div>

                <div class="admin-topbar-actions">
                    <button type="button" id="admin-theme-toggle" class="admin-tbtn admin-tbtn--icon"
                            aria-label="Basculer entre le thème clair et sombre" title="Thème clair / sombre">
                        <?php echo ueb_icon( 'moon', 'admin-theme-icon--moon' ); ?>
                        <?php echo ueb_icon( 'sun', 'admin-theme-icon--sun' ); ?>
                    </button>
                </div>
            </header>

            <section class="ref-panel" aria-labelledby="ref-titre">
                <div class="ref-toolbar">
                    <div class="admin-search ref-search">
                        <?php echo ueb_icon( 'search', 'admin-icon--sm' ); ?>
                        <label class="admin-sr-only" for="admin-ref-recherche">Rechercher dans cette table</label>
                        <input type="search" id="admin-ref-recherche" class="admin-recherche-input"
                               placeholder="Rechercher un code, un libellé…" autocomplete="off">
                    </div>

                    <!-- Filtres de la table courante, construits par le JS à
                         partir du registre (colonnes à valeurs fermées). -->
                    <div id="admin-ref-filtres" class="ref-filtres" hidden></div>

                    <button type="button" id="admin-ref-add" class="admin-tbtn admin-tbtn--primary ref-add">
                        <?php echo ueb_icon( 'plus', 'admin-icon--sm' ); ?>
                        <span id="admin-ref-add-label">Ajouter</span>
                    </button>
                </div>

                <div id="admin-ref-table-wrap" class="ref-table-zone"></div>
                <div id="admin-ref-pagination" class="ref-panel-foot"></div>
            </section>

        </main>
    </div>

    <!-- ===== MODALE AJOUT / MODIFICATION ===== -->
    <div id="admin-ref-modal" class="admin-modal ref-modal" role="dialog" aria-modal="true"
         aria-labelledby="admin-ref-modal-title" hidden>
        <div class="admin-modal-backdrop" data-close-ref-modal></div>
        <div class="admin-modal-box">
            <div class="admin-modal-header">
                <div>
                    <h2 id="admin-ref-modal-title">Ajouter</h2>
                    <p id="admin-ref-modal-sub"></p>
                </div>
                <button type="button" class="admin-filter-close" data-close-ref-modal aria-label="Fermer">
                    <?php echo ueb_icon( 'close' ); ?>
                </button>
            </div>
            <form id="admin-ref-form" class="ref-form" novalidate>
                <div class="admin-modal-body">
                    <div id="admin-ref-form-error" class="admin-error" role="alert" hidden></div>
                    <div id="admin-ref-form-fields" class="ref-form-fields"></div>
                </div>
                <div class="ref-modal-actions">
                    <button type="button" class="btn btn-secondary" data-close-ref-modal>Annuler</button>
                    <button type="submit" class="btn btn-primary" id="admin-ref-submit">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ===== CONFIRMATION DE SUPPRESSION ===== -->
    <div id="ref-confirm" class="admin-modal ref-confirm" role="alertdialog" aria-modal="true"
         aria-labelledby="ref-confirm-title" aria-describedby="ref-confirm-text" hidden>
        <div class="admin-modal-backdrop" data-close-ref-confirm></div>
        <div class="admin-modal-box">
            <div class="ref-confirm-body">
                <span class="ref-confirm-ico"><?php echo ueb_icon( 'trash' ); ?></span>
                <h2 id="ref-confirm-title">Supprimer cette ligne ?</h2>
                <p id="ref-confirm-text">Elle disparaît des listes du formulaire. Si un dossier ou une autre table l’utilise encore, la suppression sera refusée.</p>
                <div id="ref-confirm-error" class="admin-error" role="alert" hidden></div>
            </div>
            <div class="ref-modal-actions">
                <button type="button" class="btn btn-secondary" data-close-ref-confirm>Annuler</button>
                <button type="button" class="btn ref-btn-danger" id="ref-confirm-ok">Supprimer</button>
            </div>
        </div>
    </div>

    <!-- Messages de confirmation, annoncés sans déplacer le focus. -->
    <div class="ref-toasts" id="ref-toasts" role="status" aria-live="polite" aria-atomic="true"></div>

</div>

<?php get_footer(); ?>
