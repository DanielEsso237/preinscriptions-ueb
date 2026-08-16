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

/* Dates de la campagne, formatées en français sans dépendre de la locale
   installée sur WordPress : tout le thème est rédigé en français en dur, et
   un « 1 September 2026 » sur cette page serait un accident visible. */
$ueb_ouverture = new DateTimeImmutable( PREINSCRIPTIONS_DATE_OUVERTURE );
$ueb_cloture   = new DateTimeImmutable( PREINSCRIPTIONS_DATE_CLOTURE );

$ueb_format_date_fr = static function ( DateTimeImmutable $date ) {
    $mois = array(
        1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
    );
    $jour = (int) $date->format( 'j' );

    return ( 1 === $jour ? '1er' : $jour ) . ' ' . $mois[ (int) $date->format( 'n' ) ] . ' ' . $date->format( 'Y' );
};

$ueb_ouverture_libelle = $ueb_format_date_fr( $ueb_ouverture );
$ueb_cloture_libelle   = $ueb_format_date_fr( $ueb_cloture );

/* Progression de l'attente : la barre se remplit sur les 60 derniers jours
   avant l'ouverture. Purement indicatif, mais cela donne au visiteur un
   repère visuel que quatre nombres qui défilent ne donnent pas. */
$ueb_fenetre_jours = 60;
$ueb_restant_sec   = max( 0, $ueb_ouverture->getTimestamp() - time() );
/* floor, et non ceil : la légende doit dire la même chose que le grand
   nombre de jours du compte à rebours, sinon l'écart saute aux yeux. */
$ueb_restant_jours = (int) floor( $ueb_restant_sec / DAY_IN_SECONDS );
$ueb_progression   = (int) round( max( 0, min( 100, ( 1 - $ueb_restant_sec / ( $ueb_fenetre_jours * DAY_IN_SECONDS ) ) * 100 ) ) );

/* Ce qui attend le candidat une fois le formulaire ouvert : on réutilise les
   étapes officielles de la landing page pour ne décrire qu'une seule vérité. */
$ueb_etapes = preinscriptions_etapes();

get_header();
?>

<div class="cd">

    <!-- Décor de fond : deux halos et une grille très légère. aria-hidden car
         purement décoratif, rien à annoncer à un lecteur d'écran. -->
    <div class="cd-decor" aria-hidden="true">
        <span class="cd-halo cd-halo-1"></span>
        <span class="cd-halo cd-halo-2"></span>
    </div>

    <div class="cd-wrap">

        <!-- ===== HERO : statut, promesse, compte à rebours ===== -->
        <header class="cd-hero">
            <p class="cd-eyebrow">
                <span class="cd-dot" aria-hidden="true"></span>
                Campagne 2026&#8202;–&#8202;2027 · Ouverture imminente
            </p>

            <h1 class="cd-title">
                Les préinscriptions ouvrent le<br>
                <span class="cd-title-accent"><?php echo esc_html( $ueb_ouverture_libelle ); ?></span>
            </h1>

            <p class="cd-lead">
                Le formulaire en ligne de l'Université d'Ébolowa n'est pas encore accessible.
                Reviens le jour J&nbsp;: la page s'ouvrira toute seule, sans que tu aies rien à faire.
            </p>

            <!-- role="timer" + résumé sr-only : le lecteur d'écran reçoit une
                 phrase lisible mise à jour à la minute, pas quatre nombres
                 qui changent chaque seconde. -->
            <section class="cd-timer" id="countdown" role="timer"
                     data-target="<?php echo esc_attr( PREINSCRIPTIONS_DATE_OUVERTURE ); ?>"
                     aria-labelledby="cd-timer-title">
                <h2 class="cd-sr-only" id="cd-timer-title">Temps restant avant l'ouverture</h2>
                <p class="cd-sr-only" id="cd-announce" aria-live="polite"></p>

                <div class="cd-units" aria-hidden="true">
                    <div class="cd-unit">
                        <span class="cd-num" id="cd-days">--</span>
                        <span class="cd-unit-label">Jours</span>
                    </div>
                    <span class="cd-sep">:</span>
                    <div class="cd-unit">
                        <span class="cd-num" id="cd-hours">--</span>
                        <span class="cd-unit-label">Heures</span>
                    </div>
                    <span class="cd-sep">:</span>
                    <div class="cd-unit">
                        <span class="cd-num" id="cd-minutes">--</span>
                        <span class="cd-unit-label">Minutes</span>
                    </div>
                    <span class="cd-sep">:</span>
                    <div class="cd-unit">
                        <span class="cd-num" id="cd-seconds">--</span>
                        <span class="cd-unit-label">Secondes</span>
                    </div>
                </div>

                <div class="cd-progress">
                    <div class="cd-progress-track"
                         role="progressbar"
                         id="cd-progress"
                         aria-labelledby="cd-timer-title"
                         aria-valuemin="0"
                         aria-valuemax="100"
                         aria-valuenow="<?php echo esc_attr( $ueb_progression ); ?>">
                        <span class="cd-progress-fill" style="width: <?php echo esc_attr( $ueb_progression ); ?>%"></span>
                    </div>
                    <p class="cd-progress-legend">
                        <span id="cd-remaining"><?php
                            echo esc_html(
                                $ueb_restant_jours < 1
                                    ? 'Moins de 24 heures'
                                    : sprintf(
                                        'Plus que %d jour%s',
                                        $ueb_restant_jours,
                                        $ueb_restant_jours > 1 ? 's' : ''
                                    )
                            );
                        ?></span>
                        <span class="cd-progress-sep" aria-hidden="true">·</span>
                        <span>Dépôt des dossiers jusqu'au <?php echo esc_html( $ueb_cloture_libelle ); ?></span>
                    </p>
                </div>
            </section>

            <div class="cd-actions">
                <a class="cd-btn cd-btn-primary"
                   href="<?php echo esc_url( get_template_directory_uri() . '/assets/pdf/communique-recteur.pdf' ); ?>"
                   target="_blank" rel="noopener noreferrer">
                    <svg class="cd-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <path d="M14 2v6h6"></path><path d="M9 13h6"></path><path d="M9 17h4"></path>
                    </svg>
                    Communiqué du recteur
                    <span class="cd-sr-only"> (PDF, nouvel onglet)</span>
                </a>
                <a class="cd-btn cd-btn-ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                    <svg class="cd-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path d="M19 12H5"></path><path d="m12 19-7-7 7-7"></path>
                    </svg>
                    Retour à l'accueil
                </a>
            </div>
        </header>

        <!-- ===== CE QUI T'ATTEND : les étapes du parcours ===== -->
        <section class="cd-section" aria-labelledby="cd-etapes-title">
            <div class="cd-section-head">
                <h2 class="cd-section-title" id="cd-etapes-title">Ce qui t'attend le jour J</h2>
                <p class="cd-section-sub">De la plateforme au guichet de ta faculté. Rien à imprimer avant de commencer.</p>
            </div>

            <ol class="cd-steps">
                <?php foreach ( $ueb_etapes as $ueb_i => $ueb_etape ) : ?>
                    <li class="cd-step">
                        <span class="cd-step-num" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', $ueb_i + 1 ) ); ?></span>
                        <h3 class="cd-step-title"><?php echo esc_html( $ueb_etape['title'] ); ?></h3>
                        <p class="cd-step-desc"><?php echo esc_html( $ueb_etape['desc'] ); ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>

        <!-- ===== À PRÉPARER : ce qui fait gagner du temps le jour J ===== -->
        <section class="cd-section" aria-labelledby="cd-prep-title">
            <div class="cd-section-head">
                <h2 class="cd-section-title" id="cd-prep-title">À rassembler d'ici là</h2>
                <p class="cd-section-sub">Le formulaire demandera ces informations. Les avoir sous la main évite d'avoir à le reprendre plus tard.</p>
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
                        <h3 class="cd-prep-title">Ton diplôme d'admission</h3>
                        <p class="cd-prep-desc">Intitulé, série ou spécialité, année d'obtention et moyenne obtenue.</p>
                    </div>
                </li>
                <li class="cd-prep-item">
                    <span class="cd-prep-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                            <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </span>
                    <div>
                        <h3 class="cd-prep-title">Ton état civil exact</h3>
                        <p class="cd-prep-desc">Noms, date et lieu de naissance, tels qu'ils figurent sur ton acte de naissance.</p>
                    </div>
                </li>
                <li class="cd-prep-item">
                    <span class="cd-prep-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                            <path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8.1 9.8a16 16 0 0 0 6 6l1.3-1.2a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.8 2.1Z"></path>
                        </svg>
                    </span>
                    <div>
                        <h3 class="cd-prep-title">Un contact qui répond</h3>
                        <p class="cd-prep-desc">Un numéro de téléphone et une adresse e-mail que tu consultes vraiment.</p>
                    </div>
                </li>
                <li class="cd-prep-item">
                    <span class="cd-prep-ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                            <path d="M12 2 4 6v6c0 5 3.4 9.3 8 10 4.6-.7 8-5 8-10V6l-8-4Z"></path>
                            <path d="m9 12 2 2 4-4"></path>
                        </svg>
                    </span>
                    <div>
                        <h3 class="cd-prep-title">Ton choix de formation</h3>
                        <p class="cd-prep-desc">La faculté ou l'école visée, et la filière qui t'intéresse en priorité.</p>
                    </div>
                </li>
            </ul>
        </section>

        <!-- ===== RASSURANCE FINALE ===== -->
        <aside class="cd-note" role="note">
            <svg class="cd-note-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M12 16v-4"></path><path d="M12 8h.01"></path>
            </svg>
            <p>
                Aucune préinscription n'est enregistrée avant le <strong><?php echo esc_html( $ueb_ouverture_libelle ); ?></strong>.
                Méfie-toi de toute personne qui te proposerait de «&nbsp;réserver&nbsp;» ta place contre paiement&nbsp;:
                la préinscription se fait uniquement sur ce site, et elle est gratuite.
            </p>
        </aside>

    </div>
</div>

<?php get_footer(); ?>
