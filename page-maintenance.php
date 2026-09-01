<?php
/**
 * Template Name: Maintenance
 *
 * Page affichée à la place du formulaire de préinscription tant que le
 * site est en chantier. Tous les boutons "Commencer ma préinscription" /
 * "Continuer ma préinscription" pointent ici via preinscriptions_bouton_url(),
 * qui donne la priorité au mode maintenance dès qu'il est actif — avant
 * même la logique d'ouverture / clôture de campagne.
 *
 * Pour rouvrir le formulaire au public : repasser
 * PREINSCRIPTIONS_MAINTENANCE_MODE à false dans
 * inc/landing-page-functions.php.
 *
 * @package Preinscriptions_UEB
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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
                Plateforme en cours de mise à jour
            </p>

            <h1 class="cd-title">
                La préinscription en ligne revient<br>
                <span class="cd-title-accent">très bientôt</span>.
            </h1>

            <p class="cd-lead">
                Nous améliorons actuellement la plateforme de préinscription de l'Université d'Ébolowa
                pour t'offrir une meilleure expérience. Le formulaire est momentanément indisponible,
                le temps de finaliser ces changements. Reviens un peu plus tard.
            </p>

            <div class="cd-actions">
                <a class="cd-btn cd-btn-primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                    <svg class="cd-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path d="M19 12H5"></path><path d="m12 19-7-7 7-7"></path>
                    </svg>
                    Retour à l'accueil
                </a>
                <?php
                $ueb_guide_maintenance = preinscriptions_guide_url();
                if ( $ueb_guide_maintenance ) :
                ?>
                <a class="cd-btn cd-btn-ghost" href="<?php echo esc_url( $ueb_guide_maintenance ); ?>">
                    <svg class="cd-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                    </svg>
                    Consulter le guide de préinscription
                </a>
                <?php endif; ?>
            </div>
        </header>

        <section class="cd-section" aria-labelledby="cd-maint-title">
            <div class="cd-section-head">
                <h2 class="cd-section-title" id="cd-maint-title">En attendant</h2>
                <p class="cd-section-sub">Voici ce que tu peux déjà faire pour préparer ta préinscription.</p>
            </div>

            <ul class="cd-prep">
                <li class="cd-prep-item">
                    <span class="cd-prep-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                            <path d="M22 10 12 5 2 10l10 5 10-5Z"></path>
                            <path d="M6 12v5c0 1.7 2.7 3 6 3s6-1.3 6-3v-5"></path>
                        </svg>
                    </span>
                    <div>
                        <h3 class="cd-prep-title">Choisis ta formation</h3>
                        <p class="cd-prep-desc">Consulte les facultés et filières de l'UEB pour préparer ton choix.</p>
                    </div>
                </li>
                <li class="cd-prep-item">
                    <span class="cd-prep-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <path d="M14 2v6h6"></path><path d="M9 13h6"></path><path d="M9 17h4"></path>
                        </svg>
                    </span>
                    <div>
                        <h3 class="cd-prep-title">Réunis tes pièces</h3>
                        <p class="cd-prep-desc">Acte de naissance, diplôme et relevés de notes : prépare-les à l'avance.</p>
                    </div>
                </li>
            </ul>
        </section>

        <aside class="cd-note" role="note">
            <svg class="cd-note-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M12 16v-4"></path><path d="M12 8h.01"></path>
            </svg>
            <p>
                Besoin d'aide ou une question en attendant&nbsp;? Contacte-nous au
                <strong>+237 6 76 29 54 88</strong> ou par e-mail à
                <strong>info@unv-ebolowa.cm</strong>.
            </p>
        </aside>

    </div>
</div>

<?php get_footer(); ?>