<?php
/**
 * Sprite d'icônes partagé.
 *
 * Jeu de traits 1.75 (style Lucide), dessiné à la main : pas de dépendance
 * externe, pas d'emoji, et une seule définition par icône réutilisée via
 * <use href="#ueb-i-…">. Le sprite vivait auparavant en dur dans
 * page-administration.php ; il est extrait ici pour que le portail
 * (templates/access-portal.php) affiche exactement les mêmes icônes que le
 * tableau de bord, sans recopier 30 <symbol>.
 *
 * Les couleurs et les tailles viennent de .admin-icon (admin-dashboard.css) :
 * stroke: currentColor, fill: none. Une icône hérite donc de la couleur du
 * texte qui l'entoure, y compris en thème sombre.
 *
 * @package Preinscriptions_UEB
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Imprime le sprite une seule fois par page.
 *
 * Appelé par les templates qui utilisent ueb_icon(). Le deuxième appel ne
 * fait rien : deux <symbol> de même id sur une page, et le navigateur ne
 * sait plus lequel servir.
 */
function ueb_icons_sprite() {
    static $imprime = false;
    if ( $imprime ) {
        return;
    }
    $imprime = true;
    ?>
<svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true" focusable="false">
    <!-- Navigation et sections -->
    <symbol id="ueb-i-overview" viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m7 14 3-4 3 3 5-7"/></symbol>
    <symbol id="ueb-i-list" viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></symbol>
    <symbol id="ueb-i-org" viewBox="0 0 24 24"><rect x="9" y="2" width="6" height="5" rx="1"/><rect x="2" y="17" width="6" height="5" rx="1"/><rect x="16" y="17" width="6" height="5" rx="1"/><path d="M12 7v4M5 17v-2h14v2"/><path d="M12 11v4"/></symbol>
    <symbol id="ueb-i-building" viewBox="0 0 24 24"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4M9 6h.01M15 6h.01M9 10h.01M15 10h.01M9 14h.01M15 14h.01"/></symbol>
    <symbol id="ueb-i-users" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></symbol>
    <symbol id="ueb-i-database" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0 0 18 0V5"/><path d="M3 12a9 3 0 0 0 18 0"/></symbol>
    <symbol id="ueb-i-settings" viewBox="0 0 24 24"><path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3"/><path d="M1 14h6M9 8h6M17 16h6"/></symbol>

    <!-- Rôles et accès : un badge, pas un cadenas — on accorde des droits,
         on ne verrouille pas une porte. -->
    <symbol id="ueb-i-shield" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></symbol>
    <symbol id="ueb-i-user-plus" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></symbol>
    <symbol id="ueb-i-key" viewBox="0 0 24 24"><circle cx="7.5" cy="15.5" r="4.5"/><path d="m10.7 12.3 9.3-9.3M17 6l3 3M14 9l3 3"/></symbol>

    <!-- Actions -->
    <symbol id="ueb-i-filter" viewBox="0 0 24 24"><path d="M3 5h18l-7 8v6l-4 2v-8Z"/></symbol>
    <symbol id="ueb-i-download" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/></symbol>
    <symbol id="ueb-i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></symbol>
    <symbol id="ueb-i-sort" viewBox="0 0 24 24"><path d="m7 15 5 5 5-5"/><path d="m7 9 5-5 5 5"/></symbol>
    <symbol id="ueb-i-close" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></symbol>
    <symbol id="ueb-i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
    <!-- Étoile pleine : marque le dossier conservé dans un groupe de doublons.
         Seule icône du jeu à être remplie plutôt que tracée — c'est une
         distinction, pas une action. La classe .dup-star bascule le rendu. -->
    <symbol id="ueb-i-star" viewBox="0 0 24 24"><path d="m12 2.6 2.9 5.9 6.5.9-4.7 4.6 1.1 6.4-5.8-3-5.8 3 1.1-6.4L2.6 9.4l6.5-.9Z"/></symbol>
    <symbol id="ueb-i-check" viewBox="0 0 24 24"><path d="m5 13 4 4 10-10"/></symbol>
    <symbol id="ueb-i-edit" viewBox="0 0 24 24"><path d="M11 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></symbol>
    <symbol id="ueb-i-copy" viewBox="0 0 24 24"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></symbol>
    <symbol id="ueb-i-trash" viewBox="0 0 24 24"><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M10 11v6M14 11v6"/></symbol>
    <symbol id="ueb-i-refresh" viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></symbol>
    <symbol id="ueb-i-eye" viewBox="0 0 24 24"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></symbol>
    <symbol id="ueb-i-logout" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/></symbol>
    <symbol id="ueb-i-external" viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6M10 14 21 3"/></symbol>

    <!-- Directions -->
    <symbol id="ueb-i-arrow-right" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></symbol>
    <symbol id="ueb-i-arrow-left" viewBox="0 0 24 24"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></symbol>
    <symbol id="ueb-i-chevron-down" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></symbol>
    <symbol id="ueb-i-chevron-right" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></symbol>
    <symbol id="ueb-i-up" viewBox="0 0 24 24"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></symbol>
    <symbol id="ueb-i-down" viewBox="0 0 24 24"><path d="M12 5v14"/><path d="m19 12-7 7-7-7"/></symbol>
    <symbol id="ueb-i-minus" viewBox="0 0 24 24"><path d="M5 12h14"/></symbol>
    <symbol id="ueb-i-trend" viewBox="0 0 24 24"><path d="m22 7-8.5 8.5-5-5L2 17"/><path d="M16 7h6v6"/></symbol>
    <!-- Tendance à la baisse : le miroir exact de ueb-i-trend, pour que les
         deux flèches aient le même poids visuel dans une carte. -->
    <symbol id="ueb-i-trend-down" viewBox="0 0 24 24"><path d="m22 17-8.5-8.5-5 5L2 7"/><path d="M16 17h6v-6"/></symbol>

    <!-- États et repères -->
    <symbol id="ueb-i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></symbol>
    <symbol id="ueb-i-moon" viewBox="0 0 24 24"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></symbol>
    <symbol id="ueb-i-calendar" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></symbol>
    <symbol id="ueb-i-inbox" viewBox="0 0 24 24"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11Z"/></symbol>
    <symbol id="ueb-i-alert" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></symbol>
    <symbol id="ueb-i-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></symbol>
    <symbol id="ueb-i-lock" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></symbol>
    <symbol id="ueb-i-accessibility" viewBox="0 0 24 24"><circle cx="12" cy="4.5" r="1.8"/><path d="M4.5 8.5 12 10l7.5-1.5"/><path d="M12 10v4.5"/><path d="m8.5 21 3.5-6.5 3.5 6.5"/></symbol>

    <!-- Formats d'export : une feuille commune, un signe distinctif par format. -->
    <symbol id="ueb-i-file-pdf" viewBox="0 0 24 24"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z"/><path d="M14 3v5h5"/><path d="M9 17v-4h1.5a1.5 1.5 0 0 1 0 3H9"/><path d="M14 13h2.5"/><path d="M14 17v-4"/></symbol>
    <symbol id="ueb-i-file-sheet" viewBox="0 0 24 24"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z"/><path d="M14 3v5h5"/><path d="M8 12h9M8 16h9M11.5 12v7"/></symbol>
    <symbol id="ueb-i-file-doc" viewBox="0 0 24 24"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z"/><path d="M14 3v5h5"/><path d="M8 13h8M8 17h5"/></symbol>
    <symbol id="ueb-i-file-csv" viewBox="0 0 24 24"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z"/><path d="M14 3v5h5"/><path d="M11 13H9.5a1.5 1.5 0 0 0 0 3h1a1.5 1.5 0 0 1 0 3H9"/><path d="M14 13v4.5a1.5 1.5 0 0 0 3 0V13"/></symbol>
</svg>
    <?php
}

/**
 * Rend une icône du sprite.
 *
 * Toujours décorative : le sens est porté par le texte à côté, ou par un
 * aria-label sur le bouton qui la contient. Une icône seule dans un bouton
 * sans libellé reste donc interdite — voir la règle d'accessibilité du
 * tableau de bord.
 *
 * @param string $nom    Nom court de l'icône, sans le préfixe « ueb-i- ».
 * @param string $classe Classes CSS additionnelles (admin-icon--sm, …).
 * @return string Balise <svg> prête à être imprimée.
 */
function ueb_icon( $nom, $classe = '' ) {
    return sprintf(
        '<svg class="admin-icon%s" aria-hidden="true" focusable="false"><use href="#ueb-i-%s"/></svg>',
        $classe ? ' ' . esc_attr( $classe ) : '',
        esc_attr( $nom )
    );
}
