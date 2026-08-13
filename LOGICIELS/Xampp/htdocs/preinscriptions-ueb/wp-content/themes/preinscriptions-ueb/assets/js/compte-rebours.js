(function () {
    'use strict';

    var el = document.getElementById('countdown');
    if (!el) return;

    // Le navigateur interprète cette chaîne dans son propre fuseau horaire
    // local. La vérification faisant foi reste côté serveur
    // (preinscriptions_ouverture_atteinte() dans page-compte-rebours.php) ;
    // ce compte à rebours n'est qu'un affichage indicatif.
    var target = new Date(el.getAttribute('data-target').replace(' ', 'T')).getTime();

    function pad(n) {
        return String(n).padStart(2, '0');
    }

    function tick() {
        var diff = target - Date.now();

        if (diff <= 0) {
            // Recharge la page : le PHP redirige alors vers le vrai
            // formulaire dès que le serveur confirme l'ouverture.
            window.location.reload();
            return;
        }

        var jours   = Math.floor(diff / 86400000);
        var heures  = Math.floor((diff % 86400000) / 3600000);
        var minutes = Math.floor((diff % 3600000) / 60000);
        var secondes = Math.floor((diff % 60000) / 1000);

        document.getElementById('cd-days').textContent = pad(jours);
        document.getElementById('cd-hours').textContent = pad(heures);
        document.getElementById('cd-minutes').textContent = pad(minutes);
        document.getElementById('cd-seconds').textContent = pad(secondes);
    }

    tick();
    setInterval(tick, 1000);
})();