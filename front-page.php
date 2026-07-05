<?php
/**
 * Template de la page d'accueil (landing page) — direction « Parcours guidé ».
 *
 * front-page.php est la convention WordPress : ce template est utilise
 * automatiquement pour la page d'accueil du site, prioritaire sur home.php
 * et page.php. La navbar est dans header.php, le footer dans footer.php.
 *
 * @package Preinscriptions_UEB
 */

get_header();

$inscription = esc_url( preinscriptions_inscription_url() );
?>

<!-- HERO -->
<section class="hero"><div class="wrap">
    <h1 class="hero-title">Ta place à l'<span class="hl">Université d'Ébolowa</span> commence ici.</h1>

    <div class="hero-visual">
        <div class="photo hero-slider">
            <img class="on" src="<?php echo esc_url( preinscriptions_img( 'vie-etudiante-1.webp' ) ); ?>" alt="Étudiants de l'UEB en sortie pédagogique" fetchpriority="high">
            <img src="<?php echo esc_url( preinscriptions_img( 'vie-etudiante-3.webp' ) ); ?>" alt="Rassemblement d'étudiants de l'UEB">
            <img src="<?php echo esc_url( preinscriptions_img( 'vie-etudiante-2.webp' ) ); ?>" alt="Cérémonie d'excellence académique">
        </div>
        <div class="chip c1"><span class="dot" style="background:var(--green)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg></span>Dossier validé</div>
        <div class="chip c2"><span class="dot" style="background:var(--blue)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M12 2v20M2 12h20"/></svg></span>+8 000 étudiants</div>
    </div>

    <div class="hero-rest">
        <p class="lead">Préinscris-toi en ligne en quelques minutes. On t'accompagne à chaque étape, du premier clic jusqu'à la rentrée.</p>
        <div class="cta">
            <a class="btn btn-primary" href="<?php echo $inscription; ?>">Commencer ma préinscription
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
            <a class="btn btn-ghost" href="#facultes">Voir les filières</a>
        </div>
        <div class="mini">
            <div><b>9</b><span>Facultés &amp; écoles</span></div>
            <div><b>4</b><span>Campus</span></div>
            <div><b>100%</b><span>En ligne</span></div>
        </div>
    </div>
</div></section>

<!-- ATOUTS -->
<section id="atouts"><div class="wrap">
    <div class="head reveal"><span class="pill">Pourquoi l'UEB</span><h2>Une université qui te tire vers le haut</h2></div>
    <div class="atouts">
        <div class="acard reveal"><div class="ic"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 10 12 5 2 10l10 5 10-5ZM6 12v5c0 1 3 3 6 3s6-2 6-3v-5"/></svg></div><h3>Formations reconnues</h3><p>Des diplômes du DUT au Doctorat, adossés à la recherche.</p></div>
        <div class="acard reveal"><div class="ic"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-8 0v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/></svg></div><h3>Accompagnement</h3><p>Un suivi personnalisé de ta candidature à ta réussite.</p></div>
        <div class="acard reveal"><div class="ic"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 21h18M5 21V7l8-4 8 4v14M9 9h.01M9 13h.01M9 17h.01"/></svg></div><h3>4 campus modernes</h3><p>Des sites à taille humaine, proches de chez toi.</p></div>
        <div class="acard reveal"><div class="ic"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 2 2 7l10 5 10-5-10-5ZM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div><h3>Employabilité</h3><p>Des filières connectées au monde professionnel.</p></div>
    </div>
</div></section>

<!-- ETAPES -->
<section class="etapes" id="etapes"><div class="wrap">
    <div class="head reveal"><span class="pill" style="background:rgba(212,160,23,.18);color:var(--gold)">L'essentiel</span><h2>Ta préinscription en 4 étapes</h2><p>Simple, rapide et 100 % en ligne.</p></div>
    <div class="progress reveal"><i></i></div>
    <div class="estrip">
        <?php $n = 1; foreach ( preinscriptions_etapes() as $etape ) : ?>
            <div class="estep reveal">
                <div class="n"><?php echo (int) $n; ?></div>
                <h3><?php echo esc_html( $etape['title'] ); ?></h3>
                <p><?php echo esc_html( $etape['desc'] ); ?></p>
            </div>
        <?php $n++; endforeach; ?>
    </div>
</div></section>

<!-- CHIFFRES CLES -->
<section class="ribbon"><div class="wrap"><div class="grid">
    <?php foreach ( preinscriptions_stats() as $stat ) : ?>
        <div class="reveal">
            <div class="num" data-count="<?php echo (int) $stat['count']; ?>"<?php echo $stat['suffix'] ? ' data-suffix="' . esc_attr( $stat['suffix'] ) . '"' : ''; ?>><?php echo esc_html( number_format_i18n( $stat['count'] ) . $stat['suffix'] ); ?></div>
            <div class="lbl"><?php echo esc_html( $stat['label'] ); ?></div>
        </div>
    <?php endforeach; ?>
</div></div></section>

<!-- FACULTES -->
<section id="facultes"><div class="wrap">
    <div class="head reveal"><span class="pill">Nos filières</span><h2>Trouve ta faculté</h2><p>Fais défiler pour explorer nos 9 établissements.</p></div>
    <div class="facscroll">
        <?php foreach ( preinscriptions_facultes() as $fac ) : ?>
            <div class="fcard reveal">
                <div class="top"><span class="lw"><img src="<?php echo esc_url( preinscriptions_img( $fac['logo'] ) ); ?>" alt="Logo <?php echo esc_attr( $fac['abbr'] ); ?>" loading="lazy" decoding="async" width="84" height="84"></span></div>
                <div class="bd"><div class="ab"><?php echo esc_html( $fac['abbr'] ); ?></div><h3><?php echo esc_html( $fac['name'] ); ?></h3></div>
            </div>
        <?php endforeach; ?>
    </div>
</div></section>

<!-- CAMPUS -->
<section id="campus" style="background:#eef4f1"><div class="wrap">
    <div class="head reveal"><span class="pill">Nos campus</span><h2>Choisis ton site</h2></div>
    <div class="tabs reveal">
        <?php $i = 0; foreach ( preinscriptions_campus() as $c ) : ?>
            <button class="tab<?php echo 0 === $i ? ' active' : ''; ?>" data-t="<?php echo (int) $i; ?>"><?php echo esc_html( $c['name'] ); ?></button>
        <?php $i++; endforeach; ?>
    </div>
    <?php $i = 0; foreach ( preinscriptions_campus() as $c ) : ?>
        <div class="panel<?php echo 0 === $i ? ' show' : ''; ?>">
            <div class="img"><img src="<?php echo esc_url( preinscriptions_img( $c['img'] ) ); ?>" alt="<?php echo esc_attr( $c['alt'] ); ?>" loading="lazy" decoding="async" width="1000" height="500"></div>
            <div class="txt"><h3><?php echo esc_html( $c['name'] ); ?></h3><p><?php echo esc_html( $c['desc'] ); ?></p></div>
        </div>
    <?php $i++; endforeach; ?>
</div></section>

<!-- VIE ETUDIANTE -->
<section class="vie"><div class="wrap">
    <div class="head reveal"><span class="pill">Au quotidien</span><h2>La vie sur le campus</h2></div>
    <div class="vgrid">
        <?php foreach ( preinscriptions_vie() as $v ) : ?>
            <div class="vt<?php echo ! empty( $v['big'] ) ? ' big' : ''; ?> reveal"><img src="<?php echo esc_url( preinscriptions_img( $v['img'] ) ); ?>" alt="<?php echo esc_attr( $v['alt'] ); ?>" loading="lazy" decoding="async"></div>
        <?php endforeach; ?>
    </div>
</div></section>

<!-- TEMOIGNAGES (defilement automatique) -->
<section>
    <div class="wrap"><div class="head reveal"><span class="pill">Ils l'ont fait</span><h2>Paroles d'étudiants</h2></div></div>
    <div class="temos-vp">
        <div class="temos">
            <?php
            $temoignages = preinscriptions_temoignages();
            // Affiches deux fois pour une boucle de defilement continue.
            foreach ( array( false, true ) as $is_dup ) :
                foreach ( $temoignages as $t ) : ?>
                    <div class="temo<?php echo $is_dup ? ' dup' : ''; ?>"<?php echo $is_dup ? ' aria-hidden="true"' : ''; ?>>
                        <p>«&nbsp;<?php echo esc_html( $t['quote'] ); ?>&nbsp;»</p>
                        <div class="who">
                            <div class="av"><?php echo esc_html( $t['initials'] ); ?></div>
                            <div><b><?php echo esc_html( $t['name'] ); ?></b><span><?php echo esc_html( $t['info'] ); ?></span></div>
                        </div>
                    </div>
                <?php endforeach;
            endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA FINAL -->
<section class="ctafin"><div class="wrap reveal">
    <h2>On t'attend à l'UEB.</h2>
    <p>Les places sont limitées par filière. Lance ta préinscription dès maintenant.</p>
    <a class="btn btn-primary" href="<?php echo $inscription; ?>">Commencer ma préinscription</a>
</div></section>

<?php
get_footer();
