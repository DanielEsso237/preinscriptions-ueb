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
                <li><a href="#">Facebook</a></li>
                <li><a href="#">Instagram</a></li>
                <li><a href="#">YouTube</a></li>
            </ul>
        </div>
    </div>
</div></footer>

<?php wp_footer(); ?>
</body>
</html>