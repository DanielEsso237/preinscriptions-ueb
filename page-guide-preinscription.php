<?php
/**
 * Template Name: Guide de préinscription
 *
 * Transcription structurée de la note de procédure du Recteur : ce que le
 * candidat doit faire, dans quel ordre, avec quelles pièces et selon quel
 * calendrier. Page de référence, consultée avant et pendant la saisie du
 * formulaire — d'où le sommaire persistant et le PDF téléchargeable, qui
 * permet de continuer hors connexion.
 *
 * Tout le contenu vient de inc/guide-preinscription-content.php (partagé
 * avec le PDF) ; ce fichier ne fait que le mettre en page.
 *
 * @package Preinscriptions_UEB
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$gp_dates           = ueb_guide_dates();
$gp_sections        = ueb_guide_sections();
$gp_etapes          = ueb_guide_etapes();
$gp_paiements       = ueb_guide_paiements();
$gp_dossiers        = ueb_guide_dossiers();
$gp_depot           = ueb_guide_depot_pieces();
$gp_savoir          = ueb_guide_bon_a_savoir();
$gp_etablissements  = ueb_guide_etablissements();
$gp_pdf_url         = ueb_guide_pdf_url();
$gp_inscription_url = preinscriptions_bouton_url();

get_header();
?>

<div class="gp">

    <!-- ===== HERO =====
         Deux colonnes : le texte à gauche, le document à emporter isolé à
         droite. Le téléchargement n'est pas une action parmi d'autres —
         c'est un objet, et il est présenté comme tel. -->
    <header class="gp-hero">
        <div class="gp-wrap gp-hero-grid">

            <div class="gp-hero-main">
                <p class="gp-eyebrow">Année académique <?php echo esc_html( $gp_dates['annee_academique'] ); ?></p>

                <h1 class="gp-title">Guide de préinscription</h1>

                <p class="gp-lead">
                    La procédure officielle de préinscription et de réinscription à l'Université
                    d'Ébolowa, étape par étape&nbsp;: comment s'inscrire en ligne, comment régler
                    ses droits, quelles pièces réunir et où déposer son dossier.
                </p>

                <div class="gp-actions">
                    <a class="gp-btn gp-btn-primary" href="<?php echo esc_url( $gp_inscription_url ); ?>">
                        Commencer ma préinscription
                        <svg class="gp-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path>
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Le guide à emporter : le bouton, rien d'autre. Le format est
                 porté par le libellé lecteur d'écran plutôt que par une ligne
                 de texte supplémentaire. -->
            <div class="gp-doc">
                <a class="gp-btn gp-btn-primary" href="<?php echo esc_url( $gp_pdf_url ); ?>">
                    <svg class="gp-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <path d="M7 10l5 5 5-5"></path><path d="M12 15V3"></path>
                    </svg>
                    Télécharger le guide
                    <span class="gp-sr-only"> au format PDF</span>
                </a>
            </div>

        </div>

        <div class="gp-wrap">
            <dl class="gp-facts">
                <div class="gp-fact">
                    <dt>Période</dt>
                    <dd><?php echo esc_html( ucfirst( $gp_dates['periode'] ) ); ?></dd>
                </div>
                <div class="gp-fact">
                    <dt>Droits de préinscription</dt>
                    <dd><?php echo wp_kses_post( ueb_guide_frais() ); ?></dd>
                </div>
                <div class="gp-fact">
                    <dt>Dépôt du dossier</dt>
                    <dd>Sur place, à l'établissement choisi</dd>
                </div>
            </dl>
        </div>
    </header>

    <div class="gp-wrap gp-layout">

        <!-- ===== SOMMAIRE ===== -->
        <nav class="gp-toc" id="gp-toc" aria-labelledby="gp-toc-title">
            <p class="gp-toc-title" id="gp-toc-title">Sommaire</p>

            <?php
            /* Repère d'avancement dans un document long. Purement visuel :
               le sommaire lui-même indique déjà où l'on se trouve, d'où
               l'aria-hidden — annoncer un pourcentage qui bouge au
               défilement n'apporterait rien à un lecteur d'écran. */
            ?>
            <div class="gp-toc-progress" aria-hidden="true">
                <span class="gp-toc-progress-fill" id="gp-progress"></span>
            </div>
            <ol class="gp-toc-list">
                <?php foreach ( $gp_sections as $gp_i => $gp_section ) : ?>
                    <li>
                        <a href="#<?php echo esc_attr( $gp_section['id'] ); ?>" class="gp-toc-link">
                            <span class="gp-toc-num" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', $gp_i + 1 ) ); ?></span>
                            <span class="gp-toc-label"><?php echo esc_html( $gp_section['titre'] ); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>

            <a class="gp-toc-pdf" href="<?php echo esc_url( $gp_pdf_url ); ?>">
                <svg class="gp-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <path d="M14 2v6h6"></path><path d="M9 13h6"></path><path d="M9 17h4"></path>
                </svg>
                Version PDF imprimable
            </a>
        </nav>

        <!-- ===== CONTENU ===== -->
        <div class="gp-content">

            <!-- §1 — PROCÉDURE -->
            <section class="gp-section" id="procedure" aria-labelledby="gp-procedure-title">
                <header class="gp-section-head">
                    <p class="gp-section-num" aria-hidden="true">01</p>
                    <h2 class="gp-section-title" id="gp-procedure-title">La procédure en ligne</h2>
                </header>
                <p class="gp-section-intro">
                    La préinscription commence sur la plateforme en ligne et se termine au guichet
                    de votre établissement. Six étapes, à suivre dans l'ordre.
                </p>

                <ol class="gp-steps">
                    <?php foreach ( $gp_etapes as $gp_i => $gp_etape ) : ?>
                        <li class="gp-step">
                            <span class="gp-step-num" aria-hidden="true"><?php echo esc_html( $gp_i + 1 ); ?></span>
                            <div class="gp-step-body">
                                <h3 class="gp-step-title"><?php echo esc_html( $gp_etape['titre'] ); ?></h3>
                                <p class="gp-step-desc"><?php echo esc_html( $gp_etape['desc'] ); ?></p>
                                <?php if ( $gp_etape['aide'] !== '' ) : ?>
                                    <p class="gp-step-aide"><?php echo esc_html( $gp_etape['aide'] ); ?></p>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>

                <p class="gp-cta-inline">
                    <a class="gp-btn gp-btn-primary" href="<?php echo esc_url( $gp_inscription_url ); ?>">
                        Accéder au formulaire
                        <svg class="gp-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path>
                        </svg>
                    </a>
                </p>
            </section>

            <!-- §2 — PAIEMENT -->
            <section class="gp-section" id="paiement" aria-labelledby="gp-paiement-title">
                <header class="gp-section-head">
                    <p class="gp-section-num" aria-hidden="true">02</p>
                    <h2 class="gp-section-title" id="gp-paiement-title">Modalités de paiement</h2>
                </header>
                <p class="gp-section-intro">
                    Les droits de préinscription ou de réinscription, et le cas échéant les frais
                    médicaux, se règlent par l'un des trois canaux officiels ci-dessous.
                </p>

                <p class="gp-amount">
                    <span class="gp-amount-label">Droits de préinscription</span>
                    <strong class="gp-amount-value"><?php echo wp_kses_post( ueb_guide_frais() ); ?></strong>
                </p>

                <ul class="gp-pays">
                    <?php foreach ( $gp_paiements as $gp_pay ) : ?>
                        <li class="gp-pay">
                            <h3 class="gp-pay-nom"><?php echo esc_html( $gp_pay['nom'] ); ?></h3>
                            <p class="gp-pay-detail"><?php echo esc_html( $gp_pay['detail'] ); ?></p>
                            <?php if ( $gp_pay['ref'] !== '' ) : ?>
                                <?php
                                /* Le numéro de compte se recopie au guichet : une erreur de
                                   saisie coûte un déplacement. Un bouton de copie évite la
                                   sélection à la main sur mobile ; sans JavaScript, le
                                   numéro reste lisible et sélectionnable normalement. */
                                ?>
                                <div class="gp-pay-ref">
                                    <code class="gp-pay-ref-val"><?php echo esc_html( $gp_pay['ref'] ); ?></code>
                                    <button type="button"
                                            class="gp-copy"
                                            data-gp-copy="<?php echo esc_attr( $gp_pay['ref'] ); ?>"
                                            aria-label="Copier <?php echo esc_attr( $gp_pay['ref'] ); ?>">
                                        <svg class="gp-copy-ico gp-copy-ico-idle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                            <rect x="9" y="9" width="13" height="13" rx="2"></rect>
                                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                        </svg>
                                        <svg class="gp-copy-ico gp-copy-ico-done" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                            <path d="M20 6 9 17l-5-5"></path>
                                        </svg>
                                        <span class="gp-copy-label">Copier</span>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <aside class="gp-note gp-note-alerte" role="note">
                    <svg class="gp-note-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"></path>
                        <path d="M12 9v4"></path><path d="M12 17h.01"></path>
                    </svg>
                    <p>
                        Précisez <strong>clairement l'objet du paiement</strong> au moment du
                        versement, et conservez votre reçu&nbsp;: il est exigé lors du dépôt du
                        dossier physique.
                    </p>
                </aside>

                <div class="gp-link-card">
                    <div>
                        <h3 class="gp-link-title">Frais médicaux et certificat médical</h3>
                        <p class="gp-link-desc">
                            Le paiement des frais médicaux et l'obtention du certificat médical se
                            font sur le service dédié de l'Université.
                        </p>
                    </div>
                    <a class="gp-btn gp-btn-ghost" href="<?php echo esc_url( UEB_URL_CERTIFICAT_MEDICAL ); ?>" target="_blank" rel="noopener noreferrer">
                        Ouvrir le service
                        <span class="gp-sr-only"> (nouvel onglet)</span>
                        <svg class="gp-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path d="M15 3h6v6"></path><path d="M10 14 21 3"></path>
                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                        </svg>
                    </a>
                </div>
            </section>

            <!-- §3 — PIÈCES À FOURNIR -->
            <section class="gp-section" id="pieces" aria-labelledby="gp-pieces-title">
                <header class="gp-section-head">
                    <p class="gp-section-num" aria-hidden="true">03</p>
                    <h2 class="gp-section-title" id="gp-pieces-title">Pièces à fournir</h2>
                </header>
                <p class="gp-section-intro">
                    Les pièces demandées dépendent du niveau auquel vous vous inscrivez.
                    Sélectionnez le vôtre.
                </p>

                <!-- Onglets : liste sans JS (toutes les listes visibles), le
                     script ne fait qu'ajouter le filtrage. -->
                <div class="gp-tabs" id="gp-tabs" data-gp-tabs>
                    <div class="gp-tablist" role="tablist" aria-label="Niveau d'inscription">
                        <?php foreach ( $gp_dossiers as $gp_i => $gp_dossier ) : ?>
                            <button type="button"
                                    class="gp-tab"
                                    role="tab"
                                    id="gp-tab-<?php echo esc_attr( $gp_dossier['cle'] ); ?>"
                                    aria-controls="gp-panel-<?php echo esc_attr( $gp_dossier['cle'] ); ?>"
                                    aria-selected="<?php echo 0 === $gp_i ? 'true' : 'false'; ?>"
                                    tabindex="<?php echo 0 === $gp_i ? '0' : '-1'; ?>">
                                <?php echo esc_html( $gp_dossier['niveau'] ); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <?php foreach ( $gp_dossiers as $gp_i => $gp_dossier ) : ?>
                        <div class="gp-panel"
                             role="tabpanel"
                             id="gp-panel-<?php echo esc_attr( $gp_dossier['cle'] ); ?>"
                             aria-labelledby="gp-tab-<?php echo esc_attr( $gp_dossier['cle'] ); ?>"
                             tabindex="0">
                            <h3 class="gp-panel-title"><?php echo esc_html( $gp_dossier['niveau'] ); ?></h3>
                            <p class="gp-panel-contexte"><?php echo esc_html( $gp_dossier['contexte'] ); ?></p>

                            <ul class="gp-checklist">
                                <?php foreach ( $gp_dossier['pieces'] as $gp_piece ) : ?>
                                    <li>
                                        <svg class="gp-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                            <path d="M20 6 9 17l-5-5"></path>
                                        </svg>
                                        <span><?php echo esc_html( $gp_piece ); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <?php if ( $gp_dossier['note'] !== '' ) : ?>
                                <p class="gp-panel-note"><?php echo esc_html( $gp_dossier['note'] ); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- §4 — DÉPÔT PHYSIQUE -->
            <section class="gp-section" id="depot" aria-labelledby="gp-depot-title">
                <header class="gp-section-head">
                    <p class="gp-section-num" aria-hidden="true">04</p>
                    <h2 class="gp-section-title" id="gp-depot-title">Dépôt du dossier physique</h2>
                </header>
                <p class="gp-section-intro">
                    Présentez-vous à l'établissement choisi le jour du rendez-vous, muni des
                    pièces suivantes.
                </p>

                <ul class="gp-checklist gp-checklist-plain">
                    <?php foreach ( $gp_depot as $gp_piece ) : ?>
                        <li>
                            <svg class="gp-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                <path d="M20 6 9 17l-5-5"></path>
                            </svg>
                            <span><?php echo esc_html( $gp_piece ); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <aside class="gp-note" role="note">
                    <svg class="gp-note-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M12 16v-4"></path><path d="M12 8h.01"></path>
                    </svg>
                    <p>
                        <strong>Cas particulier — FSJP, niveau 1&nbsp;:</strong> la photocopie
                        certifiée de la capacité en Droit avec une moyenne d'au moins 13/20, ainsi
                        qu'une attestation de classe de première avec une moyenne égale ou
                        supérieure à 10/20, sont acceptées.
                    </p>
                </aside>
            </section>

            <!-- §5 — VALIDATION -->
            <section class="gp-section" id="validation" aria-labelledby="gp-validation-title">
                <header class="gp-section-head">
                    <p class="gp-section-num" aria-hidden="true">05</p>
                    <h2 class="gp-section-title" id="gp-validation-title">Validation du dossier</h2>
                </header>

                <div class="gp-duo">
                    <div class="gp-duo-item gp-duo-oui">
                        <p class="gp-duo-head">
                            <svg class="gp-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                <path d="M20 6 9 17l-5-5"></path>
                            </svg>
                            À faire
                        </p>
                        <p>
                            Faites <strong>absolument valider votre dossier à la scolarité</strong>
                            qui vous sera indiquée lors du dépôt, dans l'établissement choisi.
                        </p>
                    </div>
                    <div class="gp-duo-item gp-duo-non">
                        <p class="gp-duo-head">
                            <svg class="gp-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
                            </svg>
                            À ne jamais faire
                        </p>
                        <p>
                            Ne prenez <strong>pas le risque</strong> de faire valider votre dossier
                            hors du service de la scolarité de l'établissement sollicité.
                        </p>
                    </div>
                </div>
            </section>

            <!-- §6 — BON À SAVOIR -->
            <section class="gp-section" id="bon-a-savoir" aria-labelledby="gp-savoir-title">
                <header class="gp-section-head">
                    <p class="gp-section-num" aria-hidden="true">06</p>
                    <h2 class="gp-section-title" id="gp-savoir-title">Bon à savoir</h2>
                </header>

                <ul class="gp-facts-list">
                    <?php foreach ( $gp_savoir as $gp_item ) : ?>
                        <li class="gp-fact-item<?php echo 'alerte' === $gp_item['ton'] ? ' is-alerte' : ''; ?>">
                            <h3 class="gp-fact-title"><?php echo esc_html( $gp_item['titre'] ); ?></h3>
                            <p><?php echo esc_html( $gp_item['texte'] ); ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <!-- §7 — ÉTABLISSEMENTS -->
            <section class="gp-section" id="etablissements" aria-labelledby="gp-etabs-title">
                <header class="gp-section-head">
                    <p class="gp-section-num" aria-hidden="true">07</p>
                    <h2 class="gp-section-title" id="gp-etabs-title">Nos établissements</h2>
                </header>
                <p class="gp-section-intro">
                    L'Université d'Ébolowa (UEb) est un établissement public, scientifique et
                    culturel, doté de la personnalité morale et de l'autonomie financière. Créée
                    par le décret n°&nbsp;2022/0009 du 06 janvier 2022, elle est constituée des
                    établissements suivants.
                </p>

                <ul class="gp-etabs">
                    <?php foreach ( $gp_etablissements as $gp_etab ) : ?>
                        <li>
                            <a class="gp-etab" href="<?php echo esc_url( $gp_inscription_url ); ?>">
                                <span class="gp-etab-abbr"><?php echo esc_html( $gp_etab['abbr'] ); ?></span>
                                <span class="gp-etab-nom"><?php echo esc_html( $gp_etab['nom'] ); ?></span>
                                <svg class="gp-etab-fleche" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                    <path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path>
                                </svg>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <!-- ===== CTA FINAL ===== -->
            <section class="gp-final" aria-labelledby="gp-final-title">
                <h2 class="gp-final-title" id="gp-final-title">Tout est clair&nbsp;?</h2>
                <p class="gp-final-desc">
                    Emportez le guide avec vous, ou lancez votre préinscription dès maintenant.
                </p>
                <div class="gp-actions">
                    <a class="gp-btn gp-btn-primary" href="<?php echo esc_url( $gp_inscription_url ); ?>">
                        Commencer ma préinscription
                        <svg class="gp-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path>
                        </svg>
                    </a>
                    <a class="gp-btn gp-btn-ghost" href="<?php echo esc_url( $gp_pdf_url ); ?>">
                        <svg class="gp-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <path d="M7 10l5 5 5-5"></path><path d="M12 15V3"></path>
                        </svg>
                        Télécharger le guide (PDF)
                    </a>
                </div>
            </section>

        </div><!-- /.gp-content -->
    </div><!-- /.gp-layout -->
</div>

<?php get_footer(); ?>
