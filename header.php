<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php if ( ! is_page_template( 'page-preinscription.php' ) && ! ueb_est_dashboard_plein_ecran() ) : ?>
<nav class="bar" aria-label="Navigation principale"><div class="wrap">
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" class="brand">
        <img src="<?php echo esc_url( preinscriptions_img( 'logo-ueb.webp' ) ); ?>" alt="Logo de l'Université d'Ébolowa" width="247" height="236">
        <b>UEB</b>
    </a>
    <button class="burger" id="burger" aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="mnav">
        <span></span><span></span><span></span>
    </button>
    <div class="links" id="mnav">
        <a href="#atouts">Pourquoi l'UEB</a>
        <a href="#etapes">Préinscription</a>
        <a href="#facultes">Facultés</a>
        <a href="#campus">Campus</a>
        <?php
        /* Guide de préinscription : lien mis en évidence (icône + fond
           léger) pour le distinguer des ancres de la page d'accueil, sans
           concurrencer le bouton or qui reste l'action principale. Masqué
           tant qu'aucune page n'utilise le template, pour ne pas afficher
           une entrée de menu qui ne mène nulle part. */
        $ueb_guide_url = preinscriptions_guide_url();
        if ( $ueb_guide_url ) :
            ?>
            <a class="nav-guide" href="<?php echo esc_url( $ueb_guide_url ); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                </svg>
                Guide Préinscription
            </a>
        <?php endif; ?>
<a class="btn btn-primary" style="padding:.6rem 1.3rem" href="<?php echo esc_url( preinscriptions_bouton_url() ); ?>">Préinscription</a>    </div>
</div></nav>
<?php endif; ?>

<main id="content">