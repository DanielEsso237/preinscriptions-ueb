<?php
/**
 * Template Name: Compte à rebours
 *
 * Page affichée tant que les préinscriptions ne sont pas encore ouvertes.
 * Redirige automatiquement vers le vrai formulaire (page-preinscription.php)
 * dès que la date d'ouverture (PREINSCRIPTIONS_DATE_OUVERTURE) est atteinte,
 * pour qu'il n'y ait rien à changer manuellement le jour J.
 *
 * @package Preinscriptions_UEB
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* Vérification côté serveur : la plus fiable, indépendante de l'heure du
   visiteur. Si l'ouverture est déjà passée, on renvoie directement vers le
   vrai formulaire sans jamais afficher le compte à rebours. */
if ( preinscriptions_ouverture_atteinte() ) {
    wp_safe_redirect( preinscriptions_inscription_url() );
    exit;
}

get_header();
?>

<div class="countdown-page">
    <div class="countdown-wrap">
        <span class="countdown-eyebrow">● Préinscriptions 2026–2027</span>
        <h1 class="countdown-title">Les préinscriptions ouvrent<br><span class="accent">bientôt</span>.</h1>
        <p class="countdown-subtitle">
            Rendez-vous le <strong>1er septembre 2026</strong> pour commencer ta préinscription à l'Université d'Ébolowa.
        </p>

        <div class="countdown-grid" id="countdown" data-target="<?php echo esc_attr( PREINSCRIPTIONS_DATE_OUVERTURE ); ?>">
            <div class="countdown-unit">
                <span class="countdown-num" id="cd-days">00</span>
                <span class="countdown-label">Jours</span>
            </div>
            <div class="countdown-unit">
                <span class="countdown-num" id="cd-hours">00</span>
                <span class="countdown-label">Heures</span>
            </div>
            <div class="countdown-unit">
                <span class="countdown-num" id="cd-minutes">00</span>
                <span class="countdown-label">Minutes</span>
            </div>
            <div class="countdown-unit">
                <span class="countdown-num" id="cd-seconds">00</span>
                <span class="countdown-label">Secondes</span>
            </div>
        </div>

        <div class="countdown-actions">
            <a class="btn btn-secondary" href="<?php echo esc_url( get_template_directory_uri() . '/assets/pdf/communique-recteur.pdf' ); ?>" target="_blank" rel="noopener noreferrer">
                Lire le communiqué du recteur (PDF)
            </a>
            <a class="btn btn-ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                &larr; Retour à l'accueil
            </a>
        </div>
    </div>
</div>

<?php get_footer(); ?>