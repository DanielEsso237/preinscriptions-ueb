/**
 * Onglet « Effectifs » : parcours de l'organigramme académique, palier par
 * palier — université > établissement > filière.
 *
 * Un seul gabarit peint les trois niveaux (fil, cartes, tableau, anneaux) :
 * seules les données changent en descendant. Ce qui varie d'un palier à
 * l'autre est porté par la réponse du serveur (intitulé de la colonne,
 * sous-titre, fil), jamais par une branche de code par niveau.
 *
 * Les chiffres viennent de la base à chaque ouverture de palier : rien n'est
 * mis en cache côté page, et un rafraîchissement automatique reprend la main
 * quand l'écran redevient visible, pour qu'une préinscription enregistrée
 * pendant la consultation n'échappe pas au compteur.
 *
 * Expose window.uebEffectifs.{ouvrir, actualiser, redessiner}.
 *
 * @package Preinscriptions_UEB
 */
(function () {
    'use strict';

    var reducedMotion = window.matchMedia &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* Intervalle de rafraîchissement automatique. Une minute : assez court
       pour qu'un chiffre affiché ne soit jamais vieux d'une consultation,
       assez long pour ne pas interroger la base en boucle. Le compteur ne
       tourne que si l'onglet est réellement affiché (cf. planifier()). */
    var PERIODE_MAJ = 60000;

    var etat = {
        niveau: 'universite',
        id: 0,
        donnees: null,
        tri: { colonne: 'total', sens: 'desc' },
        chargement: false,
        visible: false
    };

    var minuteur = null;

    /* ================================================================
       OUTILS
       ================================================================ */
    function $(id) { return document.getElementById(id); }

    function esc(str) {
        var div = document.createElement('div');
        div.textContent = str == null ? '' : str;
        return div.innerHTML;
    }

    function nf(n) { return new Intl.NumberFormat('fr-FR').format(n); }

    function icone(nom, classe) {
        return '<svg class="admin-icon ' + (classe || '') + '" aria-hidden="true"><use href="#ueb-i-' + nom + '"/></svg>';
    }

    /** Part d'un total, arrondie, sans division par zéro. */
    function part(valeur, total) {
        return total > 0 ? Math.round((valeur / total) * 100) : 0;
    }

    /**
     * Configuration posée par wp_localize_script (URL d'admin-ajax et nonce).
     *
     * Lue à l'appel et non au chargement : ce fichier est enfilé avant
     * admin-dashboard.js, et une lecture au chargement le rendait muet dès
     * que le bloc de données passait après lui dans la page.
     */
    function config() {
        return window.uebAdminDashboard || null;
    }

    function ajax(action, params) {
        var CFG = config();

        if (!CFG) {
            console.error('uebAdminDashboard absent : configuration du dashboard non chargée.');
            return Promise.resolve(null);
        }

        var body = new URLSearchParams(Object.assign({
            action: action,
            nonce: CFG.nonce
        }, params || {}));

        return fetch(CFG.ajax_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (!json || !json.success) {
                    console.error('Erreur AJAX (' + action + ')', json);
                    return null;
                }
                return json.data;
            })
            .catch(function (err) {
                console.error('Erreur réseau AJAX (' + action + ')', err);
                return null;
            });
    }

    /* ================================================================
       CHARGEMENT D'UN PALIER
       ================================================================ */

    /**
     * Ouvre un palier et redessine tout l'onglet.
     *
     * @param {string} niveau universite | etablissement | filiere
     * @param {number} id     Identifiant de l'entité (0 au niveau université).
     * @param {boolean} silencieux Recharge sans squelettes ni annonce (rafraîchissement automatique).
     */
    function ouvrir(niveau, id, silencieux) {
        if (etat.chargement) return;
        etat.chargement = true;

        if (!silencieux) {
            squelettes();
            // Descendre dans l'organigramme remet le tri par défaut : le
            // classement par effectif décroissant est ce qu'on cherche en
            // arrivant sur un palier, pas l'ordre choisi au palier précédent.
            etat.tri = { colonne: 'total', sens: 'desc' };
        }

        return ajax('ueb_admin_get_effectifs', { niveau: niveau, id: id || 0 })
            .then(function (data) {
                etat.chargement = false;

                if (!data) {
                    if (!silencieux) erreur();
                    return;
                }

                etat.niveau  = data.niveau;
                etat.id      = data.id || 0;
                etat.donnees = data;

                peindre(data);
                memoriserUrl();
                annoncerMaj();
                planifier();
            });
    }

    /** Recharge le palier courant sans rien réinitialiser. */
    function actualiser(silencieux) {
        return ouvrir(etat.niveau, etat.id, silencieux);
    }

    /* ================================================================
       PEINTURE
       ================================================================ */
    function peindre(data) {
        peindreFil(data);
        peindreEntete(data);
        peindreCartes(data);
        peindreTableau(data);
        peindreAnneaux(data);
    }

    /**
     * Fil du parcours. Chaque palier traversé reste cliquable et garde son
     * intitulé : c'est le seul chemin de remontée, et il dit à tout moment
     * d'où vient le chiffre affiché.
     */
    function peindreFil(data) {
        var fil = $('eff-fil');
        if (!fil) return;

        var etapes = (data.fil || []).slice();

        // Sans étape parente, le fil ne dirait que ce que le titre dit déjà.
        if (!etapes.length) {
            fil.innerHTML = '';
            return;
        }

        var html = '';

        etapes.forEach(function (etape) {
            html += '<button type="button" class="eff-fil-etape" ' +
                        'data-niveau="' + esc(etape.niveau) + '" data-id="' + (etape.id || 0) + '">' +
                        esc(etape.code || etape.libelle) +
                    '</button>' +
                    '<span class="eff-fil-sep" aria-hidden="true">' + icone('chevron-right', 'admin-icon--sm') + '</span>';
        });

        html += '<span class="eff-fil-courant" aria-current="page">' + esc(data.titre) + '</span>';

        fil.innerHTML = html;
    }

    function peindreEntete(data) {
        var titre = $('eff-titre');
        var sous  = $('eff-soustitre');
        var total = $('eff-total');

        if (titre) {
            titre.textContent = data.titre;
            if (data.badge) {
                var b = document.createElement('span');
                b.className = 'eff-badge';
                b.textContent = data.badge;
                titre.appendChild(document.createTextNode(' '));
                titre.appendChild(b);
            }
        }
        if (sous)  sous.textContent = data.sousTitre;
        if (total) animerNombre(total, data.total);

        var sexeSub = $('eff-sexe-sub');
        var handSub = $('eff-handicap-sub');
        var portee  = 'Sur ' + nf(data.total) + (data.total > 1 ? ' préinscrits' : ' préinscrit') +
                      (data.niveau === 'universite' ? '' : ' — ' + data.titre);
        if (sexeSub) sexeSub.textContent = portee;
        if (handSub) handSub.textContent = portee;
    }

    /**
     * Compteur animé, repris du dashboard : la valeur monte jusqu'à sa cible
     * plutôt que d'apparaître, ce qui rend visible qu'elle vient de changer.
     */
    function animerNombre(el, cible) {
        if (reducedMotion) {
            el.textContent = nf(cible);
            return;
        }

        var depart = parseInt(String(el.textContent).replace(/\D/g, ''), 10) || 0;
        if (depart === cible) {
            el.textContent = nf(cible);
            return;
        }

        var debut = null;
        var duree = 520;

        function pas(ts) {
            if (debut === null) debut = ts;
            var t = Math.min(1, (ts - debut) / duree);
            // Sortie amortie : la valeur ralentit en approchant, comme le
            // reste du mouvement du dashboard (--ueb-ease).
            var e = 1 - Math.pow(1 - t, 3);
            el.textContent = nf(Math.round(depart + (cible - depart) * e));
            if (t < 1) requestAnimationFrame(pas);
        }

        requestAnimationFrame(pas);
    }

    /**
     * Cartes du palier : une par entité, portant l'effectif, sa part du
     * total et une jauge proportionnelle. Ce sont les portes d'entrée du
     * palier suivant — le tableau juste en dessous porte les mêmes chiffres
     * pour la lecture précise.
     */
    function peindreCartes(data) {
        var hote = $('eff-cartes');
        if (!hote) return;

        var lignes = (data.lignes || []).filter(function (l) { return !l.reste; });

        if (!lignes.length) {
            hote.innerHTML = '';
            hote.hidden = true;
            return;
        }

        hote.hidden = false;
        var max = Math.max.apply(null, lignes.map(function (l) { return l.total; })) || 1;

        hote.innerHTML = lignes.map(function (ligne, i) {
            var pct       = part(ligne.total, data.total);
            var cliquable = !ligne.feuille && ligne.id > 0;
            var balise    = cliquable ? 'button' : 'div';
            var attrs     = cliquable
                ? ' type="button" data-id="' + ligne.id + '"'
                : '';

            return '<' + balise + ' class="eff-carte' + (cliquable ? ' eff-carte--ouvrable' : '') + '"' + attrs +
                        ' style="--eff-i:' + i + '; --eff-serie:var(--ueb-chart-' + ((i % 8) + 1) + ')">' +
                        '<span class="eff-carte-nom">' + esc(ligne.libelle) + '</span>' +
                        '<span class="eff-carte-valeur">' + nf(ligne.total) + '</span>' +
                        '<span class="eff-carte-jauge" aria-hidden="true">' +
                            '<span style="width:' + Math.round((ligne.total / max) * 100) + '%"></span>' +
                        '</span>' +
                        '<span class="eff-carte-part">' + pct + '% des effectifs' +
                            (cliquable ? icone('arrow-right', 'admin-icon--sm eff-carte-fleche') : '') +
                        '</span>' +
                    '</' + balise + '>';
        }).join('');
    }

    /* ---------------- Tableau ---------------- */

    /** Colonnes triables : clé de tri => extracteur de valeur. */
    var TRIS = {
        libelle: function (l) { return l.libelle || ''; },
        total:   function (l) { return l.total; }
    };

    function trier(lignes) {
        var extraire = TRIS[etat.tri.colonne] || TRIS.total;
        var sens     = etat.tri.sens === 'asc' ? 1 : -1;

        return lignes.slice().sort(function (a, b) {
            // Les lignes d'appoint (« non renseigné ») restent en bas quel
            // que soit le tri : ce ne sont pas des entités comparables aux
            // autres, seulement le reliquat du palier.
            if (a.reste !== b.reste) return a.reste ? 1 : -1;

            var va = extraire(a);
            var vb = extraire(b);

            if (typeof va === 'string') {
                return va.localeCompare(vb, 'fr') * sens;
            }
            return (va - vb) * sens;
        });
    }

    function peindreTableau(data) {
        var hote = $('eff-tableau');
        if (!hote) return;

        var lignes = trier(data.lignes || []);

        if (!lignes.length) {
            hote.innerHTML = '<div class="admin-table-wrap"><div class="admin-state">' +
                '<span class="admin-state-ico">' + icone('inbox') + '</span>' +
                '<h3>Rien à ventiler à ce niveau</h3>' +
                '<p>' + esc(sousNiveauAbsent(data.niveau)) + '</p>' +
                '</div></div>';
            return;
        }

        // Seuls les deux premiers paliers ouvrent sur un niveau inférieur.
        var ouvrable = niveauSuivant(data.niveau) !== null;

        var th = function (cle, libelle, classe) {
            var actif = etat.tri.colonne === cle;
            var sort  = actif ? (etat.tri.sens === 'asc' ? 'ascending' : 'descending') : 'none';
            return '<th scope="col" class="' + (classe || '') + '" aria-sort="' + sort + '">' +
                       '<button type="button" class="admin-th-sort" data-tri="' + cle + '">' +
                           esc(libelle) + icone('sort', 'admin-icon--sm') +
                       '</button>' +
                   '</th>';
        };

        var corps = lignes.map(function (ligne) {
            var cliquable = ouvrable && !ligne.feuille && ligne.id > 0;

            return '<tr class="' + (cliquable ? 'eff-ligne--ouvrable' : '') + '"' +
                       (cliquable ? ' data-id="' + ligne.id + '"' : '') + '>' +
                   '<td class="eff-cell-nom">' +
                       '<span class="eff-nom">' +
                           '<span class="eff-nom-texte">' +
                               esc(ligne.libelle) +
                               (ligne.badge ? ' <span class="eff-badge">' + esc(ligne.badge) + '</span>' : '') +
                               (ligne.reste ? ' <span class="eff-cell-note">à compléter</span>' : '') +
                           '</span>' +
                           (cliquable
                               ? '<button type="button" class="eff-ouvrir" data-id="' + ligne.id + '" ' +
                                 'aria-label="Voir les effectifs de ' + esc(ligne.libelle) + '">' +
                                     icone('chevron-right', 'admin-icon--sm') +
                                 '</button>'
                               : '') +
                       '</span>' +
                   '</td>' +
                   '<td class="eff-cell-nombre">' + nf(ligne.total) + '</td>' +
                   '<td class="eff-col-reste"></td>' +
                   '</tr>';
        }).join('');

        // Pied de tableau : le total du palier, en face de la somme de ses
        // lignes. Les deux doivent toujours coïncider — c'est la raison
        // d'être des lignes « non renseigné ».
        var somme = lignes.reduce(function (s, l) { return s + l.total; }, 0);

        hote.innerHTML =
            '<div class="admin-table-wrap">' +
                '<div class="admin-table-scroll">' +
                    '<table class="admin-table eff-table">' +
                        '<caption class="admin-sr-only">' +
                            esc(data.sousTitre) + ' — ' + esc(data.titre) +
                        '</caption>' +
                        '<thead><tr>' +
                            th('libelle', data.colonne) +
                            th('total', 'Préinscrits', 'eff-col-nombre') +
                            '<td class="eff-col-reste"></td>' +
                        '</tr></thead>' +
                        '<tbody>' + corps + '</tbody>' +
                        '<tfoot><tr>' +
                            '<td>Total ' + esc(libelleNiveau(data.niveau)) + '</td>' +
                            '<td class="eff-cell-nombre">' + nf(somme) + '</td>' +
                            '<td class="eff-col-reste"></td>' +
                        '</tr></tfoot>' +
                    '</table>' +
                '</div>' +
            '</div>';
    }

    /**
     * Ce qui manque quand un palier n'a aucune subdivision à montrer.
     * Chaque cas indique où le corriger, plutôt que de constater le vide.
     */
    function sousNiveauAbsent(niveau) {
        switch (niveau) {
            case 'universite':
                return 'Aucun établissement enregistré. Ajoutez-les depuis la gestion des références.';
            case 'etablissement':
                return "Aucune filière n'est rattachée à cet établissement. Créez-les depuis la gestion des références.";
            default:
                return "Aucun candidat de cette filière n'a encore indiqué son niveau LMD.";
        }
    }

    /** Intitulé du périmètre couvert par le total, pour le pied de tableau. */
    function libelleNiveau(niveau) {
        switch (niveau) {
            case 'etablissement': return "de l'établissement";
            case 'filiere':       return 'de la filière';
            default:              return "de l'université";
        }
    }

    /* ---------------- Anneaux ---------------- */

    /**
     * Les deux anneaux du bas : sexe et situation de handicap, tous deux au
     * périmètre du palier affiché.
     *
     * Le rendu est délégué à admin-analytics.js, qui lit déjà ses couleurs
     * dans les tokens CSS et inscrit le pourcentage dans la légende — la
     * part ne dépend donc jamais de la seule couleur.
     */
    function peindreAnneaux(data) {
        if (!window.uebCharts || !window.uebCharts.anneau) return;

        var v = data.ventilation || {};

        var libelleSexe = function (x) {
            if (x.label === 'M') return 'Garçons';
            if (x.label === 'F') return 'Filles';
            return 'Non précisé';
        };

        window.uebCharts.anneau('chart-eff-sexe', v.sexe || [], {
            mapLabel: libelleSexe,
            mapComplet: libelleSexe,
            // Bleu / or : le couple reste distinguable en deutéranopie,
            // contrairement à un rouge/vert. Rangs et non valeurs : la
            // palette est résolue au rendu depuis les tokens du thème actif.
            series: [3, 2, 8],
            centreLegende: data.total > 1 ? 'préinscrits' : 'préinscrit',
            tailleLegende: 14,
            longueurLegende: 34,
            echelleCentre: 1.35
        });

        var libelleHandicap = function (x) {
            return x.label === 'oui' ? 'En situation de handicap' : 'Sans handicap déclaré';
        };

        window.uebCharts.anneau('chart-eff-handicap', v.handicap || [], {
            mapLabel: libelleHandicap,
            mapComplet: libelleHandicap,
            // La part « handicap » est presque toujours minoritaire : elle
            // prend la teinte la plus saturée pour rester repérable même
            // réduite à quelques degrés d'angle.
            series: [1, 4],
            centreLegende: data.total > 1 ? 'préinscrits' : 'préinscrit',
            tailleLegende: 14,
            // « En situation de handicap » fait 25 caractères : sans cette
            // marge, la légende s'arrêtait sur « En situation de handi… ».
            longueurLegende: 34,
            echelleCentre: 1.35
        });
    }

    /* ================================================================
       ÉTATS D'ATTENTE
       ================================================================ */
    function squelettes() {
        var cartes = $('eff-cartes');
        if (cartes) {
            cartes.hidden = false;
            cartes.innerHTML = new Array(4).join('|').split('|')
                .map(function () { return '<div class="admin-skeleton admin-skeleton--kpi"></div>'; })
                .join('');
        }

        var tableau = $('eff-tableau');
        if (tableau) {
            tableau.innerHTML = '<div class="admin-table-wrap"><div class="eff-squelette-table">' +
                new Array(7).join('|').split('|')
                    .map(function () { return '<div class="admin-skeleton admin-skeleton--row"></div>'; })
                    .join('') +
                '</div></div>';
        }
    }

    function erreur() {
        var tableau = $('eff-tableau');
        if (!tableau) return;
        tableau.innerHTML = '<div class="admin-table-wrap"><div class="admin-state admin-state--error">' +
            '<span class="admin-state-ico">' + icone('alert') + '</span>' +
            '<h3>Les effectifs n\'ont pas pu être chargés</h3>' +
            '<p>Vérifiez la connexion au serveur, puis relancez le chargement.</p>' +
            '<button type="button" class="btn btn-secondary" data-eff-reessayer>Relancer le chargement</button>' +
            '</div></div>';
    }

    /* ================================================================
       MISE À JOUR
       ================================================================ */

    /** Horodate la dernière lecture de la base, sous l'en-tête. */
    function annoncerMaj() {
        var el = $('eff-maj');
        if (!el) return;
        var h = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
        el.textContent = 'Chiffres lus en base à ' + h;
    }

    /**
     * Programme la relecture suivante.
     *
     * Le minuteur ne tourne que quand l'onglet est à l'écran ET que la page
     * est au premier plan : interroger la base pour un écran que personne ne
     * regarde n'apporte rien, et vide la batterie d'un portable.
     */
    function planifier() {
        if (minuteur) clearTimeout(minuteur);
        if (!etat.visible || document.hidden) return;

        minuteur = setTimeout(function () {
            actualiser(true);
        }, PERIODE_MAJ);
    }

    /* ================================================================
       NAVIGATION
       ================================================================ */

    /** Palier suivant, en descendant l'organigramme. */
    function niveauSuivant(niveau) {
        switch (niveau) {
            case 'universite':    return 'etablissement';
            case 'etablissement': return 'filiere';
            default:              return null; // La filière est le dernier palier.
        }
    }

    /**
     * Inscrit le palier courant dans l'URL, pour que le bouton « Précédent »
     * du navigateur remonte l'organigramme au lieu de quitter le dashboard,
     * et qu'un écran ouvert sur un département puisse être partagé tel quel.
     */
    function memoriserUrl(remplacer) {
        var url = new URL(window.location.href);
        url.searchParams.set('onglet', 'effectifs');
        url.searchParams.set('niveau', etat.niveau);

        if (etat.id) {
            url.searchParams.set('entite', etat.id);
        } else {
            url.searchParams.delete('entite');
        }

        var etatHisto = { ueb: 'effectifs', niveau: etat.niveau, id: etat.id };

        var memeEtat = window.history.state &&
                       window.history.state.ueb    === 'effectifs' &&
                       window.history.state.niveau === etat.niveau &&
                       window.history.state.id     === etat.id;

        // Un rafraîchissement du même palier ne doit pas empiler une entrée
        // d'historique de plus : sinon « Précédent » rejouerait la minute
        // écoulée au lieu de remonter d'un cran dans l'organigramme.
        if (remplacer || memeEtat) {
            window.history.replaceState(etatHisto, '', url);
        } else {
            window.history.pushState(etatHisto, '', url);
        }
    }

    function descendre(id) {
        var suivant = niveauSuivant(etat.niveau);
        if (!suivant || !id) return;
        ouvrir(suivant, id);
    }

    /* ================================================================
       ÉVÉNEMENTS
       ================================================================ */

    // Cartes : un clic ouvre le palier du dessous.
    var hoteCartes = $('eff-cartes');
    if (hoteCartes) {
        hoteCartes.addEventListener('click', function (e) {
            var carte = e.target.closest('.eff-carte--ouvrable');
            if (carte) descendre(parseInt(carte.dataset.id, 10));
        });
    }

    // Tableau : clic sur la ligne, tri sur l'en-tête, et reprise après erreur.
    var hoteTableau = $('eff-tableau');
    if (hoteTableau) {
        hoteTableau.addEventListener('click', function (e) {
            var tri = e.target.closest('[data-tri]');
            if (tri) {
                var colonne = tri.dataset.tri;
                if (etat.tri.colonne === colonne) {
                    etat.tri.sens = etat.tri.sens === 'asc' ? 'desc' : 'asc';
                } else {
                    etat.tri.colonne = colonne;
                    // Un libellé se lit de A à Z, un effectif du plus grand
                    // au plus petit : chaque colonne s'ouvre dans le sens
                    // qu'on en attend.
                    etat.tri.sens = colonne === 'libelle' ? 'asc' : 'desc';
                }
                if (etat.donnees) peindreTableau(etat.donnees);
                return;
            }

            if (e.target.closest('[data-eff-reessayer]')) {
                ouvrir(etat.niveau, etat.id);
                return;
            }

            // Le bouton d'ouverture porte le même identifiant que sa
            // ligne : un seul chemin de code, que le clic vienne du bouton
            // (souris ou clavier) ou de la ligne entière (souris).
            var cible = e.target.closest('.eff-ouvrir') || e.target.closest('.eff-ligne--ouvrable');
            if (cible) descendre(parseInt(cible.dataset.id, 10));
        });
    }

    // Fil : remontée directe à n'importe quel palier déjà traversé.
    var hoteFil = $('eff-fil');
    if (hoteFil) {
        hoteFil.addEventListener('click', function (e) {
            var etape = e.target.closest('.eff-fil-etape');
            if (etape) ouvrir(etape.dataset.niveau, parseInt(etape.dataset.id, 10));
        });
    }

    var boutonMaj = $('eff-refresh');
    if (boutonMaj) {
        boutonMaj.addEventListener('click', function () {
            boutonMaj.classList.add('eff-refresh--tourne');
            actualiser().then(function () {
                setTimeout(function () { boutonMaj.classList.remove('eff-refresh--tourne'); }, 200);
            });
        });
    }

    // Bouton « Précédent » du navigateur : remonte l'organigramme.
    window.addEventListener('popstate', function (e) {
        var s = e.state;
        if (!s || s.ueb !== 'effectifs') return;
        if (s.niveau === etat.niveau && s.id === etat.id) return;
        etat.niveau = s.niveau;
        etat.id     = s.id;
        ouvrir(s.niveau, s.id);
    });

    // Retour au premier plan : les chiffres peuvent avoir vieilli pendant
    // que l'écran était masqué.
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            if (minuteur) clearTimeout(minuteur);
        } else if (etat.visible) {
            actualiser(true);
        }
    });

    // Le thème redéfinit les couleurs des anneaux : Chart.js les fige à la
    // création, il faut donc les redessiner.
    document.addEventListener('uebThemeChange', function () {
        if (etat.visible && etat.donnees) peindreAnneaux(etat.donnees);
    });

    /* ================================================================
       CONTRAT AVEC LE DASHBOARD
       ================================================================ */

    /**
     * Appelé par admin-dashboard.js à chaque bascule d'onglet.
     * Le premier affichage déclenche le chargement ; les suivants ne font
     * que redessiner les anneaux, que Chart.js ne sait pas dimensionner
     * dans un conteneur masqué.
     */
    function afficher(visible) {
        etat.visible = visible;

        if (!visible) {
            if (minuteur) clearTimeout(minuteur);
            return;
        }

        if (!etat.donnees) {
            var url    = new URL(window.location.href);
            var niveau = url.searchParams.get('niveau') || 'universite';
            var entite = parseInt(url.searchParams.get('entite'), 10) || 0;
            ouvrir(niveau, entite);
            return;
        }

        // Retour sur un onglet déjà chargé : rien à recharger, mais l'URL
        // doit redire quel onglet et quel palier sont ouverts, sinon un
        // rafraîchissement de la page rouvrirait l'onglet précédent.
        memoriserUrl(true);
        requestAnimationFrame(function () { peindreAnneaux(etat.donnees); });
        planifier();
    }

    window.uebEffectifs = {
        ouvrir: ouvrir,
        actualiser: actualiser,
        afficher: afficher
    };

}());
