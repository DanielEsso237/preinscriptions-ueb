/*
 * Compte à rebours de la page d'attente (page-compte-rebours.php).
 *
 * Rôle : afficher le temps restant et recharger la page à l'échéance.
 * La vérité reste côté serveur — preinscriptions_ouverture_atteinte() —
 * car l'horloge du visiteur peut être fausse ou volontairement avancée.
 */
(function () {
    'use strict';

    var racine = document.getElementById('countdown');
    if (!racine) return;

    // Le navigateur interprète cette chaîne dans son propre fuseau horaire.
    // Cet affichage n'est donc qu'indicatif : c'est le PHP qui autorise ou non
    // l'accès au formulaire.
    var cible = new Date(racine.getAttribute('data-target').replace(' ', 'T')).getTime();
    if (isNaN(cible)) return;

    var champs = {
        days:    document.getElementById('cd-days'),
        hours:   document.getElementById('cd-hours'),
        minutes: document.getElementById('cd-minutes'),
        seconds: document.getElementById('cd-seconds')
    };
    var annonce = document.getElementById('cd-announce');
    var barre = document.getElementById('cd-progress');
    var remplissage = barre ? barre.querySelector('.cd-progress-fill') : null;
    var restantTexte = document.getElementById('cd-remaining');

    // Doit rester identique à $ueb_fenetre_jours dans page-compte-rebours.php.
    var FENETRE_MS = 60 * 86400000;

    var mouvementReduit = window.matchMedia
        ? window.matchMedia('(prefers-reduced-motion: reduce)')
        : null;

    var derniereAnnonceMin = null;
    var timer = null;

    var CLE_RECHARGE = 'ueb-cd-recharge';

    // sessionStorage peut être indisponible (navigation privée stricte) : dans
    // ce cas on considère le rechargement comme déjà fait, ce qui vaut mieux
    // qu'une page qui se recharge sans fin.
    function dejaRecharge() {
        try {
            return sessionStorage.getItem(CLE_RECHARGE) === '1';
        } catch (e) {
            return true;
        }
    }

    function marquerRecharge() {
        try {
            sessionStorage.setItem(CLE_RECHARGE, '1');
        } catch (e) {
            /* rien à faire : le garde-fou ci-dessus a déjà bloqué le rechargement */
        }
    }

    function pad(n) {
        return String(n).padStart(2, '0');
    }

    function accorde(n, mot) {
        return n + ' ' + mot + (n > 1 ? 's' : '');
    }

    /**
     * Écrit une valeur et ne déclenche l'animation que si elle a changé —
     * sinon les secondes rejoueraient l'effet à chaque tick pour rien.
     */
    function ecrire(el, valeur) {
        if (!el || el.textContent === valeur) return;

        el.textContent = valeur;

        if (mouvementReduit && mouvementReduit.matches) return;

        el.classList.remove('is-ticking');
        // Force un reflow pour que le retrait/ajout de classe relance bien
        // l'animation, y compris deux ticks de suite.
        void el.offsetWidth;
        el.classList.add('is-ticking');
    }

    function majProgression(ecart) {
        var pct = Math.max(0, Math.min(100, (1 - ecart / FENETRE_MS) * 100));

        if (remplissage) remplissage.style.width = pct.toFixed(2) + '%';
        if (barre) barre.setAttribute('aria-valuenow', String(Math.round(pct)));

        if (restantTexte) {
            // floor, comme côté PHP : la légende doit annoncer le même nombre
            // de jours que le grand chiffre juste au-dessus.
            var jours = Math.floor(ecart / 86400000);
            restantTexte.textContent = jours < 1
                ? 'Moins de 24 heures'
                : 'Plus que ' + accorde(jours, 'jour');
        }
    }

    /**
     * Résumé lisible pour les lecteurs d'écran, rafraîchi à la minute :
     * annoncer les secondes rendrait la page inutilisable à la voix.
     */
    function majAnnonce(j, h, m) {
        if (!annonce) return;

        var cle = j + ':' + h + ':' + m;
        if (cle === derniereAnnonceMin) return;
        derniereAnnonceMin = cle;

        var parties = [];
        if (j > 0) parties.push(accorde(j, 'jour'));
        if (h > 0) parties.push(accorde(h, 'heure'));
        parties.push(accorde(m, 'minute'));

        annonce.textContent = 'Ouverture des préinscriptions dans ' + parties.join(', ') + '.';
    }

    function tick() {
        var ecart = cible - Date.now();

        if (ecart <= 0) {
            clearInterval(timer);

            ecrire(champs.days, '00');
            ecrire(champs.hours, '00');
            ecrire(champs.minutes, '00');
            ecrire(champs.seconds, '00');
            majProgression(0);

            // Rechargement : le PHP redirige alors vers le formulaire dès que
            // le serveur confirme l'ouverture. Une seule tentative par onglet :
            // si l'horloge du visiteur est en avance, le serveur nous renvoie
            // la même page, et recharger en boucle la rendrait inutilisable.
            if (!dejaRecharge()) {
                marquerRecharge();
                window.location.reload();
            }
            return;
        }

        var jours    = Math.floor(ecart / 86400000);
        var heures   = Math.floor((ecart % 86400000) / 3600000);
        var minutes  = Math.floor((ecart % 3600000) / 60000);
        var secondes = Math.floor((ecart % 60000) / 1000);

        ecrire(champs.days, pad(jours));
        ecrire(champs.hours, pad(heures));
        ecrire(champs.minutes, pad(minutes));
        ecrire(champs.seconds, pad(secondes));

        majAnnonce(jours, heures, minutes);
        majProgression(ecart);
    }

    tick();
    timer = setInterval(tick, 1000);

    // Onglet caché : inutile de faire tourner un timer par seconde. Au retour,
    // on resynchronise immédiatement plutôt que d'attendre le prochain tick.
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            clearInterval(timer);
        } else {
            tick();
            timer = setInterval(tick, 1000);
        }
    });
})();
