/*
 * Page « Guide de préinscription » — améliorations progressives.
 *
 * La page est entièrement lisible et navigable sans ce script : le sommaire
 * n'est qu'une liste d'ancres, les trois listes de pièces sont toutes
 * affichées et le numéro de compte reste sélectionnable à la main. Ce
 * fichier n'ajoute que du confort :
 *   1. le sommaire suit la lecture (scrollspy) ;
 *   2. les listes de pièces deviennent de vrais onglets (WAI-ARIA) ;
 *   3. les références de paiement se copient en un clic.
 */
(function () {
    'use strict';

    /* ─────────────────────────────────────────────────────────
       1. SCROLLSPY DU SOMMAIRE
       ───────────────────────────────────────────────────────── */
    function initScrollspy() {
        var toc = document.getElementById('gp-toc');
        if (!toc || !('IntersectionObserver' in window)) return;

        var liens = Array.prototype.slice.call(toc.querySelectorAll('.gp-toc-link'));
        if (!liens.length) return;

        /* Associe chaque lien à sa section ; un lien orphelin (section
           supprimée du template) est simplement ignoré. */
        var entrees = liens.map(function (lien) {
            var id = (lien.getAttribute('href') || '').replace(/^#/, '');
            return { lien: lien, section: id ? document.getElementById(id) : null };
        }).filter(function (e) { return e.section; });

        if (!entrees.length) return;

        var actif = null;

        function marquer(section) {
            if (section === actif) return;
            actif = section;

            entrees.forEach(function (e) {
                var estActif = e.section === section;
                e.lien.classList.toggle('is-active', estActif);
                /* aria-current : le lecteur d'écran annonce « page actuelle »
                   sur l'entrée du sommaire correspondant à la lecture. */
                if (estActif) {
                    e.lien.setAttribute('aria-current', 'true');
                } else {
                    e.lien.removeAttribute('aria-current');
                }
            });
        }

        /* Marge haute négative sur la hauteur de la nav sticky : une section
           n'est considérée « en cours de lecture » qu'une fois passée sous la
           barre, pas quand elle affleure encore le bord de l'écran. */
        var navH = parseInt(
            getComputedStyle(document.querySelector('.gp')).getPropertyValue('--gp-nav-h'),
            10
        ) || 80;

        var visibles = [];

        var observer = new IntersectionObserver(function (mutations) {
            mutations.forEach(function (m) {
                var i = visibles.indexOf(m.target);
                if (m.isIntersecting && i === -1) {
                    visibles.push(m.target);
                } else if (!m.isIntersecting && i !== -1) {
                    visibles.splice(i, 1);
                }
            });

            if (!visibles.length) return;

            /* Plusieurs sections peuvent être visibles à la fois : on retient
               la plus haute dans le document, c'est-à-dire celle que le
               lecteur est en train de finir. */
            var premiere = visibles.reduce(function (a, b) {
                return a.getBoundingClientRect().top <= b.getBoundingClientRect().top ? a : b;
            });
            marquer(premiere);
        }, {
            rootMargin: '-' + (navH + 20) + 'px 0px -55% 0px',
            threshold: 0
        });

        entrees.forEach(function (e) { observer.observe(e.section); });
    }

    /* ─────────────────────────────────────────────────────────
       2. ONGLETS DES PIÈCES À FOURNIR
       ───────────────────────────────────────────────────────── */
    function initTabs() {
        var conteneurs = document.querySelectorAll('[data-gp-tabs]');

        Array.prototype.forEach.call(conteneurs, function (conteneur) {
            var onglets  = Array.prototype.slice.call(conteneur.querySelectorAll('[role="tab"]'));
            var panneaux = Array.prototype.slice.call(conteneur.querySelectorAll('[role="tabpanel"]'));
            if (onglets.length < 2 || onglets.length !== panneaux.length) return;

            /* Marque le passage en mode « onglets » : le CSS masque alors les
               titres de panneau, redondants avec l'onglet sélectionné. */
            conteneur.classList.add('is-enhanced');

            function activer(index, donnerLeFocus) {
                onglets.forEach(function (onglet, i) {
                    var actif = i === index;
                    onglet.setAttribute('aria-selected', actif ? 'true' : 'false');
                    onglet.setAttribute('tabindex', actif ? '0' : '-1');
                    panneaux[i].hidden = !actif;
                });

                if (donnerLeFocus) onglets[index].focus();
            }

            onglets.forEach(function (onglet, i) {
                onglet.addEventListener('click', function () { activer(i, false); });

                /* Navigation au clavier attendue d'un tablist : flèches pour
                   changer d'onglet, Début/Fin pour aller aux extrémités. */
                onglet.addEventListener('keydown', function (evt) {
                    var suivant = null;

                    switch (evt.key) {
                        case 'ArrowRight':
                        case 'ArrowDown':
                            suivant = (i + 1) % onglets.length;
                            break;
                        case 'ArrowLeft':
                        case 'ArrowUp':
                            suivant = (i - 1 + onglets.length) % onglets.length;
                            break;
                        case 'Home':
                            suivant = 0;
                            break;
                        case 'End':
                            suivant = onglets.length - 1;
                            break;
                        default:
                            return;
                    }

                    evt.preventDefault();
                    activer(suivant, true);
                });
            });

            /* État initial : le premier onglet, cohérent avec le HTML rendu
               par le template (aria-selected="true" sur le premier). */
            activer(0, false);
        });
    }

    /* ─────────────────────────────────────────────────────────
       3. COPIE DES RÉFÉRENCES DE PAIEMENT
       ───────────────────────────────────────────────────────── */
    function initCopy() {
        var boutons = document.querySelectorAll('[data-gp-copy]');
        if (!boutons.length) return;

        /* Une seule région d'annonce pour toute la page : le lecteur d'écran
           entend « Numéro copié » sans que le focus ne bouge. */
        var annonce = document.createElement('p');
        annonce.className = 'gp-sr-only';
        annonce.setAttribute('aria-live', 'polite');
        document.body.appendChild(annonce);

        /* navigator.clipboard n'existe qu'en contexte sécurisé : en HTTP
           simple, on retombe sur une zone de texte hors écran et
           execCommand, encore accepté partout pour ce cas précis. */
        function copier(texte) {
            if (navigator.clipboard && window.isSecureContext) {
                return navigator.clipboard.writeText(texte);
            }

            return new Promise(function (resoudre, rejeter) {
                var zone = document.createElement('textarea');
                zone.value = texte;
                zone.setAttribute('readonly', '');
                zone.style.cssText = 'position:fixed;top:-9999px;opacity:0';
                document.body.appendChild(zone);
                zone.select();

                try {
                    document.execCommand('copy') ? resoudre() : rejeter();
                } catch (e) {
                    rejeter(e);
                } finally {
                    document.body.removeChild(zone);
                }
            });
        }

        Array.prototype.forEach.call(boutons, function (bouton) {
            var libelle = bouton.querySelector('.gp-copy-label');
            var initial = libelle ? libelle.textContent : '';
            var minuteur = null;

            bouton.addEventListener('click', function () {
                var valeur = bouton.getAttribute('data-gp-copy') || '';

                copier(valeur).then(function () {
                    bouton.classList.add('is-done');
                    if (libelle) libelle.textContent = 'Copié';
                    annonce.textContent = 'Numéro copié : ' + valeur;

                    /* Retour à l'état initial au bout de 3 s, comme un toast :
                       le bouton doit redevenir une invitation à agir. */
                    window.clearTimeout(minuteur);
                    minuteur = window.setTimeout(function () {
                        bouton.classList.remove('is-done');
                        if (libelle) libelle.textContent = initial;
                        annonce.textContent = '';
                    }, 3000);
                }).catch(function () {
                    /* Copie refusée (permission, navigateur ancien) : on
                       sélectionne le numéro pour que la copie manuelle ne
                       demande qu'un raccourci clavier. */
                    var champ = bouton.parentNode.querySelector('.gp-pay-ref-val');
                    if (!champ || !window.getSelection) return;

                    var plage = document.createRange();
                    plage.selectNodeContents(champ);
                    var selection = window.getSelection();
                    selection.removeAllRanges();
                    selection.addRange(plage);

                    annonce.textContent = 'Copie automatique indisponible : le numéro est sélectionné.';
                });
            });
        });
    }

    /* ─────────────────────────────────────────────────────────
       4. AVANCEMENT DE LECTURE
       ───────────────────────────────────────────────────────── */
    function initProgress() {
        var barre = document.getElementById('gp-progress');
        var corps = document.querySelector('.gp-content');
        if (!barre || !corps) return;

        var enAttente = false;

        function majuster() {
            enAttente = false;

            var boite = corps.getBoundingClientRect();
            var hauteurVue = window.innerHeight;

            /* 0 % quand le haut du contenu atteint le bas de l'écran,
               100 % quand son bas le quitte : la barre suit la lecture du
               document, pas la hauteur totale de la page (hero et pied de
               page compris fausseraient le repère). */
            var parcouru = hauteurVue - boite.top;
            var total = boite.height + hauteurVue;
            var ratio = total > 0 ? parcouru / total : 0;

            barre.style.width = Math.max(0, Math.min(1, ratio)) * 100 + '%';
        }

        function planifier() {
            if (enAttente) return;
            enAttente = true;
            window.requestAnimationFrame(majuster);
        }

        window.addEventListener('scroll', planifier, { passive: true });
        window.addEventListener('resize', planifier);
        majuster();
    }

    function init() {
        initScrollspy();
        initTabs();
        initCopy();
        initProgress();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
