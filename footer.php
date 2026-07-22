<?php
/**
 * Footer du thème — affiche les réseaux sociaux depuis la base de données.
 *
 * Les liens sont stockés dans ueb_reseaux_sociaux et peuplés via db-seed.php.
 * Pour ajouter/modifier un réseau : mettre à jour db-seed.php, vider la table,
 * puis réinitialiser ueb_data_version pour forcer le reseed.
 */

// Récupération des réseaux sociaux actifs, ordonnés
// La fonction est définie dans inc/social-medias-functions.php (inclus via functions.php)
$reseaux = array();
if ( function_exists( 'ueb_get_reseaux_sociaux' ) ) {
    $reseaux = ueb_get_reseaux_sociaux();
}

// Fallback si la fonction n'existe pas encore ou retourne vide
$reseaux_fallback = array(
    'youtube'  => array( 'url' => 'https://www.youtube.com/@universiteebolowa', 'label' => 'YouTube', 'icone' => 'youtube' ),
    'facebook' => array( 'url' => 'https://facebook.com/share/18i1CQ2QuY', 'label' => 'Facebook', 'icone' => 'facebook' ),
    'site_web' => array( 'url' => 'https://unv-ebolowa.cm', 'label' => 'Site web', 'icone' => 'globe' ),
);

if ( empty( $reseaux ) ) {
    $reseaux = $reseaux_fallback;
}
?>

</main>

<footer class="site-footer"><div class="wrap">
    <div class="fgrid">
        <div>
            <div class="brand">
                <img src="<?php echo esc_url( preinscriptions_img( 'logo-ueb.webp' ) ); ?>" alt="Logo de l'Université d'Ébolowa" width="247" height="236" loading="lazy">
                <b>Université d'Ébolowa</b>
            </div>
            <p style="margin-top:1rem;font-size:.92rem">Plateforme officielle de préinscription en ligne — 2026-2027.</p>
        </div>
        <div>
            <h4>Liens</h4>
            <ul>
                <li><a href="#atouts">Pourquoi l'UEB</a></li>
                <?php if ( ! is_page_template( 'page-preinscription.php' ) ) : ?>
                    <li><a href="#etapes">Préinscription</a></li>
                <?php endif; ?>
                <li><a href="#facultes">Facultés</a></li>
                <li><a href="#campus">Campus</a></li>
            </ul>
        </div>
        <div>
            <h4>Contact</h4>
            <ul>
                <li>Ébolowa, Région du Sud</li>
                <li><a href="mailto:contact@ueb.cm">contact@ueb.cm</a></li>
                <li>+237 6 00 00 00 00</li>
            </ul>
        </div>
        <div>
            <h4>Suivez-nous</h4>
            <ul>
                <?php foreach ( $reseaux as $key => $reseau ) : ?>
                    <li>
                        <a href="<?php echo esc_url( $reseau['url'] ); ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo esc_html( $reseau['label'] ); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div></footer>

<?php wp_footer(); ?>
</body>
</html>