<?php
/**
 * Adresse inconnue : page « introuvable » avec le code HTTP 404. Sert aussi
 * aux portes de WordPress fermées aux visiteurs (wp-login.php, wp-admin,
 * API REST, flux… : inc/connexion-wp.php et inc/discretion.php).
 *
 * @package Preinscriptions_UEB
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

status_header( 404 );
nocache_headers();

get_header();
?>

<div class="cd">

    <div class="cd-decor" aria-hidden="true">
        <span class="cd-halo cd-halo-1"></span>
        <span class="cd-halo cd-halo-2"></span>
    </div>

    <div class="cd-wrap">

        <header class="cd-hero">
            <p class="cd-eyebrow">
                <span class="cd-dot" aria-hidden="true"></span>
                Erreur 404
            </p>

            <h1 class="cd-title">
                Page <span class="cd-title-accent">introuvable</span>.
            </h1>

            <p class="cd-lead">
                L'adresse demandée n'existe pas ou n'est plus disponible.
                Vérifie-la, ou reviens à l'accueil de la plateforme de préinscription.
            </p>

            <div class="cd-actions">
                <a class="cd-btn cd-btn-primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                    <svg class="cd-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path d="M19 12H5"></path><path d="m12 19-7-7 7-7"></path>
                    </svg>
                    Retour à l'accueil
                </a>
            </div>
        </header>

    </div>
</div>

<?php
get_footer();
