<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

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
        <a class="btn btn-primary" style="padding:.6rem 1.3rem" href="<?php echo esc_url( preinscriptions_inscription_url() ); ?>">Préinscription</a>
    </div>
</div></nav>

<main id="content">
