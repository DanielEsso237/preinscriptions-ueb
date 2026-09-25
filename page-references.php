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
 * Chaque rubrique possède sa capability métier et sa portée, vérifiées
 * côté serveur par le catalogue dynamique.
 *
 * @package Preinscriptions_UEB
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// La connexion et sa limitation sont centralisées dans le portail.
$ueb_ref_login_error = '';
$ueb_ref_is_authorized = is_user_logged_in() && ueb_access_has_refs();
$ueb_ref_user = wp_get_current_user();
if ( ! ueb_access_has_refs() ) wp_die( 'Accès refusé.', '', array( 'response' => 403 ) );

get_header();
?>

<!-- Sprite d'icônes : sous-ensemble de celui de page-administration.php
     (même style de traits, pour rester visuellement cohérent), dupliqué
     ici car les deux pages ne partagent aucun gabarit. -->
<?php
// Sprite d'icônes partagé avec le portail (inc/icons.php) :
// une seule définition par icône pour tout le back-office.
ueb_icons_sprite();
?>

<div class="admin-page">



    <!-- ===== PAGE DE GESTION DES RÉFÉRENCES ===== -->
    <div class="admin-shell admin-shell--simple">

        <div class="admin-main" id="admin-main">

            <header class="admin-topbar">
                <div>
                    <h1>Références</h1>
                    <p class="admin-subtitle">Facultés, filières, diplômes, régions… — Préinscriptions UEB</p>
                </div>

                <div class="admin-topbar-actions"><a class="admin-tbtn" href="<?php echo esc_url( ueb_portal_home() ); ?>">Mon espace</a>
                    <button type="button" id="admin-theme-toggle" class="admin-tbtn admin-tbtn--icon"
                            aria-label="Basculer entre le thème clair et sombre" title="Thème clair / sombre">
                        <svg class="admin-icon admin-theme-icon--moon" aria-hidden="true"><use href="#ueb-i-moon"/></svg>
                        <svg class="admin-icon admin-theme-icon--sun" aria-hidden="true"><use href="#ueb-i-sun"/></svg>
                    </button>

                    <a href="<?php echo esc_url( ueb_portal_url( 'logout', array( 'nonce' => wp_create_nonce( 'ueb_logout' ) ) ) ); ?>" class="admin-tbtn">
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

                    <!-- Filtres de la table courante, construits par le JS a
                         partir du registre (colonnes a valeurs fermees) :
                         vide, et masque, pour les tables sans colonne
                         filtrable. -->
                    <div id="admin-ref-filtres" class="admin-ref-filtres" hidden></div>

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



</div>

<?php get_footer(); ?>
