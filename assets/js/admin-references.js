/**
 * Page « Référentiels » (page-references.php) : index des tables ueb_*,
 * tableau générique, formulaire d'ajout/modification, suppression, via le
 * registre envoyé par PHP (ueb_admin_ref_get_registry_for_js(), cf.
 * inc/admin-references-functions.php).
 *
 * Page autonome : ce script ne dépend d'aucun autre fichier JS du thème
 * (pas de admin-dashboard.js sur cette page), il gère donc aussi lui-même
 * le petit nécessaire habituellement partagé (thème clair/sombre).
 *
 * @package Preinscriptions_UEB
 */
(function () {
    'use strict';

    if (typeof window.uebAdminReferences === 'undefined') return;
    var CFG = window.uebAdminReferences;
    var REGISTRY = CFG.registry || {};
    var CLES = Object.keys(REGISTRY);

    var reducedMotion = window.matchMedia &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ================================================================
       OUTILS
       ================================================================ */
    function $(id) { return document.getElementById(id); }

    function esc(str) {
        var div = document.createElement('div');
        div.textContent = str === null || str === undefined ? '' : str;
        return div.innerHTML;
    }

    function icone(nom, classe) {
        return '<svg class="admin-icon ' + (classe || '') + '" aria-hidden="true"><use href="#ueb-i-' + nom + '"/></svg>';
    }

    function nombre(n) { return Number(n || 0).toLocaleString('fr-FR'); }

    // En français, 0 et 1 prennent le singulier.
    function pluriel(n, singulier, plurielForme) {
        return nombre(n) + ' ' + (n > 1 ? plurielForme : singulier);
    }

    function guillemets(texte) { return '« ' + texte + ' »'; }

    function ajax(action, params) {
        var body = new URLSearchParams();
        body.set('action', action);
        body.set('nonce', CFG.nonce);
        Object.keys(params || {}).forEach(function (k) {
            body.set(k, params[k]);
        });

        return fetch(CFG.ajax_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (!json || !json.success) {
                    return { erreur: (json && json.data && json.data.message) || 'Erreur inconnue.' };
                }
                return json.data;
            })
            .catch(function (err) {
                console.error('Erreur réseau AJAX (' + action + ')', err);
                return { erreur: 'Erreur réseau, merci de réessayer.' };
            });
    }

    var estErreur = function (data) { return data && typeof data === 'object' && data.erreur; };

    /* Surlignage du terme recherché, insensible aux accents et à la casse
       comme la recherche SQL. Le texte est échappé morceau par morceau :
       seule la balise <mark> est ajoutée. */
    function sansAccents(s) {
        return String(s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    }

    function surligner(texte, terme) {
        texte = String(texte);
        if (!terme) return esc(texte);
        var base = sansAccents(texte);
        var cible = sansAccents(terme);
        // Texte déjà décomposé (rare) : les positions ne correspondraient plus.
        if (!cible || base.length !== texte.length) return esc(texte);

        var out = '';
        var i = 0;
        var pos;
        while ((pos = base.indexOf(cible, i)) !== -1) {
            out += esc(texte.slice(i, pos)) +
                   '<mark class="ref-mark">' + esc(texte.slice(pos, pos + cible.length)) + '</mark>';
            i = pos + cible.length;
        }
        return out + esc(texte.slice(i));
    }

    /* ================================================================
       THÈME CLAIR / SOMBRE
       Même clé localStorage que le dashboard des dossiers : un compte qui a
       accès aux deux pages garde la même préférence. Le clair est le
       défaut, le réglage système n'est pas suivi (cf. functions.php).
       ================================================================ */
    var CLE_THEME = 'ueb-admin-theme';

    function themeActif() {
        return document.documentElement.getAttribute('data-ueb-theme') === 'dark' ? 'dark' : 'light';
    }

    function appliquerTheme(mode) {
        var racine = document.documentElement;
        if (!reducedMotion) {
            racine.classList.add('ueb-theming');
            setTimeout(function () { racine.classList.remove('ueb-theming'); }, 320);
        }
        racine.setAttribute('data-ueb-theme', mode);
        try { localStorage.setItem(CLE_THEME, mode); } catch (e) { /* stockage indisponible */ }
    }

    var boutonTheme = $('admin-theme-toggle');
    if (boutonTheme) {
        boutonTheme.addEventListener('click', function () {
            appliquerTheme(themeActif() === 'dark' ? 'light' : 'dark');
        });
    }

    /* ================================================================
       ÉTAT
       La table ouverte est gardée dans l'adresse (#filieres) : un
       rechargement ou un lien partagé rouvre la même table.
       ================================================================ */
    function lireHash() {
        var h = '';
        try { h = decodeURIComponent((window.location.hash || '').slice(1)); } catch (e) { /* hash invalide */ }
        return REGISTRY[h] ? h : '';
    }

    var cleCourante      = lireHash() || CLES[0] || '';
    var pageCourante     = 1;
    var rechercheTimeout = null;
    var idEnEdition      = 0; // 0 = création
    var filtres          = {}; // colonne => valeur, pour la table courante
    var lignesParId      = {}; // lignes de la page affichée, pour les libellés
    var requeteCourante  = 0;  // seule la dernière réponse est affichée
    var titreBase        = document.title;

    function ecrireHash() {
        try { history.replaceState(null, '', '#' + encodeURIComponent(cleCourante)); } catch (e) { /* ignoré */ }
    }

    function termeRecherche() {
        var champ = $('admin-ref-recherche');
        return champ ? champ.value.trim() : '';
    }

    /* ================================================================
       INDEX DES TABLES (barre latérale)
       ================================================================ */
    var nav = $('admin-ref-nav');
    var sidebar = $('ref-sidebar');
    var toggleNav = $('ref-nav-toggle');

    function renderNav() {
        if (!nav) return;

        var groupes = {};
        var ordreGroupes = [];
        CLES.forEach(function (cle) {
            var groupe = REGISTRY[cle].group || 'Autres';
            if (!groupes[groupe]) { groupes[groupe] = []; ordreGroupes.push(groupe); }
            groupes[groupe].push(cle);
        });

        nav.innerHTML = '<span class="ref-nav-indicator" aria-hidden="true"></span>' +
            ordreGroupes.map(function (groupe) {
                var items = groupes[groupe].map(function (cle) {
                    var actif = cle === cleCourante;
                    return '<button type="button" class="ref-nav-item' + (actif ? ' active' : '') + '" data-ref="' + esc(cle) + '"' +
                           (actif ? ' aria-current="true"' : '') + '>' +
                           '<span class="ref-nav-label">' + esc(REGISTRY[cle].label) + '</span>' +
                           '<span class="ref-nav-count"><span class="admin-sr-only">, </span>' +
                           '<span class="ref-nav-count-n">' + nombre(REGISTRY[cle].total) + '</span>' +
                           '<span class="admin-sr-only"> lignes</span></span>' +
                           '</button>';
                }).join('');
                return '<div class="ref-nav-group" role="group" aria-label="' + esc(groupe) + '">' +
                       '<span class="ref-nav-heading" aria-hidden="true">' + esc(groupe) + '</span>' + items + '</div>';
            }).join('');
    }

    /* Pastille or : suit l'élément actif. La première pose se fait sans
       transition, pour qu'elle n'arrive pas en glissant du haut de la liste. */
    function placerIndicateur() {
        if (!nav) return;
        var indicateur = nav.querySelector('.ref-nav-indicator');
        var actif = nav.querySelector('.ref-nav-item.active');
        if (!indicateur || !actif || !actif.offsetHeight) return;

        indicateur.style.setProperty('--y', actif.offsetTop + 'px');
        indicateur.style.setProperty('--h', actif.offsetHeight + 'px');

        if (!nav.classList.contains('has-indicator')) {
            nav.classList.add('has-indicator');
            requestAnimationFrame(function () {
                requestAnimationFrame(function () { nav.classList.add('is-ready'); });
            });
        }
    }

    // Garde l'élément actif visible dans la liste, sans faire défiler la page.
    function montrerActif() {
        if (!nav) return;
        var actif = nav.querySelector('.ref-nav-item.active');
        if (!actif) return;
        var haut = actif.offsetTop;
        var bas = haut + actif.offsetHeight;
        if (haut < nav.scrollTop || bas > nav.scrollTop + nav.clientHeight) {
            nav.scrollTop = Math.max(0, haut - nav.clientHeight / 3);
        }
    }

    function activerNav() {
        if (!nav) return;
        nav.querySelectorAll('.ref-nav-item').forEach(function (btn) {
            var actif = btn.dataset.ref === cleCourante;
            btn.classList.toggle('active', actif);
            if (actif) btn.setAttribute('aria-current', 'true');
            else btn.removeAttribute('aria-current');
        });
        placerIndicateur();
    }

    function majCompte(cle) {
        if (nav) {
            var el = nav.querySelector('.ref-nav-item[data-ref="' + cle + '"] .ref-nav-count-n');
            if (el) el.textContent = nombre(REGISTRY[cle].total);
        }
        if (cle === cleCourante) renderTotal();
    }

    function ouvrirNavMobile(ouvrir) {
        if (!sidebar || !toggleNav) return;
        sidebar.classList.toggle('is-open', ouvrir);
        toggleNav.setAttribute('aria-expanded', ouvrir ? 'true' : 'false');
        if (ouvrir) {
            placerIndicateur();
            montrerActif();
        }
    }

    if (toggleNav) {
        toggleNav.addEventListener('click', function () {
            ouvrirNavMobile(!sidebar.classList.contains('is-open'));
        });
    }

    if (nav) {
        nav.addEventListener('click', function (e) {
            var btn = e.target.closest('.ref-nav-item');
            if (!btn) return;
            changerTable(btn.dataset.ref);
        });
    }

    // Fondu en bas de la liste tant qu'il reste des tables à faire défiler.
    function majFonduNav() {
        if (!nav) return;
        nav.classList.toggle('is-scrollable', nav.scrollHeight > nav.clientHeight + 1);
        nav.classList.toggle('is-end', nav.scrollTop + nav.clientHeight >= nav.scrollHeight - 4);
    }
    if (nav) nav.addEventListener('scroll', majFonduNav, { passive: true });

    var resizeFrame = null;
    window.addEventListener('resize', function () {
        if (resizeFrame) cancelAnimationFrame(resizeFrame);
        resizeFrame = requestAnimationFrame(function () {
            placerIndicateur();
            majFonduNav();
        });
    });

    /* ================================================================
       EN-TÊTE DE LA TABLE COURANTE
       ================================================================ */
    function renderTotal() {
        var el = $('ref-total');
        var cfg = REGISTRY[cleCourante];
        if (el && cfg) el.textContent = pluriel(cfg.total, 'ligne', 'lignes');
    }

    /* Liens entre tables, déduits des clés étrangères du registre : la
       table courante dépend de celles qu'elle cite, et elle est utilisée par
       celles qui la citent. Seules les tables accessibles au compte sont
       proposées. */
    function renderLiens() {
        var zone = $('ref-liens');
        var cfg = REGISTRY[cleCourante];
        if (!zone || !cfg) return;

        var depend = [];
        Object.keys(cfg.columns).forEach(function (col) {
            var fk = cfg.columns[col].fk;
            if (fk && REGISTRY[fk] && depend.indexOf(fk) === -1) depend.push(fk);
        });

        var utilisee = CLES.filter(function (cle) {
            if (cle === cleCourante) return false;
            var cols = REGISTRY[cle].columns;
            return Object.keys(cols).some(function (col) { return cols[col].fk === cleCourante; });
        });

        function groupe(titre, cles) {
            if (!cles.length) return '';
            return '<div class="ref-liens-groupe"><span class="ref-liens-label">' + titre + '</span>' +
                cles.map(function (cle) {
                    return '<button type="button" class="ref-lien" data-ref="' + esc(cle) + '">' +
                           icone('database', 'admin-icon--sm') + esc(REGISTRY[cle].label) + '</button>';
                }).join('') + '</div>';
        }

        var html = groupe('Dépend de', depend) + groupe('Utilisée par', utilisee);
        zone.innerHTML = html;
        zone.hidden = html === '';
    }

    function renderEntete(anime) {
        var cfg = REGISTRY[cleCourante];
        if (!cfg) return;

        $('ref-titre').textContent = cfg.label;
        var libelleToggle = $('ref-nav-toggle-label');
        if (libelleToggle) libelleToggle.textContent = cfg.label;
        document.title = cfg.label + ' – ' + titreBase;

        var desc = $('ref-desc');
        if (desc) {
            desc.textContent = cfg.description || '';
            desc.hidden = !cfg.description;
        }

        renderTotal();
        renderLiens();

        var ajout = $('admin-ref-add');
        if (ajout) ajout.hidden = !cfg.canCreate;
        var ajoutLibelle = $('admin-ref-add-label');
        if (ajoutLibelle) ajoutLibelle.textContent = cfg.singulier ? 'Ajouter ' + cfg.singulier : 'Ajouter';

        var bloc = $('ref-head-text');
        if (anime && bloc && !reducedMotion) {
            bloc.classList.remove('is-swapping');
            void bloc.offsetWidth; // relance l'animation
            bloc.classList.add('is-swapping');
        }
    }

    document.addEventListener('click', function (e) {
        var lien = e.target.closest('.ref-lien');
        if (lien) changerTable(lien.dataset.ref);
    });

    /* ================================================================
       FILTRES
       Une colonne est filtrable si PHP l'a marquée ainsi
       (ueb_admin_ref_filtrable_columns) : clés étrangères et énumérations,
       dont le registre transporte déjà les options.
       ================================================================ */
    function colonnesFiltrables(cfg) {
        if (!cfg) return [];
        return Object.keys(cfg.columns).filter(function (cle) {
            return cfg.columns[cle].filtrable && (cfg.columns[cle].options || []).length;
        });
    }

    function filtresActifs() {
        return Object.keys(filtres).filter(function (cle) { return filtres[cle] !== ''; });
    }

    function boutonEffacerFiltres() {
        return '<button type="button" class="ref-filtres-reset" id="admin-ref-filtres-reset">' +
               icone('close', 'admin-icon--sm') + 'Effacer les filtres</button>';
    }

    /* Chaque filtre est une pastille : au repos elle ne montre que son nom
       (« Faculté »), une fois choisi elle montre aussi la valeur. La liste
       native est posée, invisible, par-dessus : clavier, lecteurs d'écran et
       sélecteur mobile restent ceux du système. */
    function renderFiltres() {
        var zone = $('admin-ref-filtres');
        var barre = zone && zone.closest('.ref-toolbar');
        var cfg  = REGISTRY[cleCourante];
        if (!zone) return;

        var colonnes = colonnesFiltrables(cfg);
        // Plusieurs filtres : ils prennent leur propre ligne sous la recherche.
        if (barre) barre.classList.toggle('has-filtres-ligne', colonnes.length > 1);
        if (!colonnes.length) {
            zone.innerHTML = '';
            zone.hidden = true;
            return;
        }

        zone.innerHTML = colonnes.map(function (cle) {
            var colcfg = cfg.columns[cle];
            var valeur = String(filtres[cle] || '');
            var choisi = '';
            var options = (colcfg.options || []).map(function (o) {
                var sel = valeur === String(o.id);
                if (sel) choisi = o.libelle;
                return '<option value="' + esc(o.id) + '"' + (sel ? ' selected' : '') + '>' + esc(o.libelle) + '</option>';
            }).join('');

            return '<label class="ref-filtre' + (valeur ? ' is-active' : '') + '">' +
                   '<span class="ref-filtre-label">' + esc(colcfg.th || colcfg.label) + '</span>' +
                   '<span class="ref-filtre-valeur" aria-hidden="true">' + esc(choisi) + '</span>' +
                   icone('chevron-down', 'admin-icon--sm') +
                   '<select class="ref-filtre-select" data-filtre="' + esc(cle) + '">' +
                   '<option value="">Tout afficher</option>' + options + '</select></label>';
        }).join('') + (filtresActifs().length ? boutonEffacerFiltres() : '');
        zone.hidden = false;
    }

    // Mise à jour sans reconstruire les listes : le focus reste sur la
    // liste que l'utilisateur vient de changer.
    function majFiltresUI() {
        var zone = $('admin-ref-filtres');
        if (!zone) return;
        zone.querySelectorAll('.ref-filtre-select').forEach(function (select) {
            var pastille = select.closest('.ref-filtre');
            var actif = select.value !== '';
            pastille.classList.toggle('is-active', actif);
            pastille.querySelector('.ref-filtre-valeur').textContent =
                actif ? select.options[select.selectedIndex].text : '';
        });
        var reset = $('admin-ref-filtres-reset');
        if (filtresActifs().length && !reset) zone.insertAdjacentHTML('beforeend', boutonEffacerFiltres());
        if (!filtresActifs().length && reset) reset.remove();
    }

    document.addEventListener('change', function (e) {
        var select = e.target.closest('.ref-filtre-select');
        if (!select) return;
        filtres[select.dataset.filtre] = select.value;
        pageCourante = 1;
        majFiltresUI();
        chargerListe();
    });

    function effacerFiltresEtRecherche() {
        filtres = {};
        var champ = $('admin-ref-recherche');
        if (champ) champ.value = '';
        pageCourante = 1;
        renderFiltres();
        chargerListe();
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('#admin-ref-filtres-reset')) {
            filtres = {};
            pageCourante = 1;
            renderFiltres();
            chargerListe();
            var champ = $('admin-ref-recherche');
            if (champ) champ.focus();
        }
    });

    /* ================================================================
       TABLEAU
       ================================================================ */
    var TIRET = '<span class="ref-vide">—</span>';

    function cellule(cle, colcfg, row, cfg, terme) {
        var val = row[cle];
        var vide = val === null || val === undefined || val === '';

        if (colcfg.apercu === 'logo') {
            var url = row[cle + '__url'];
            if (url) return { html: '<span class="ref-logo"><img src="' + esc(url) + '" alt="" width="28" height="28" loading="lazy"></span>' };
            return { html: vide ? TIRET : '<span class="ref-muted">' + esc(val) + '</span>' };
        }

        if (colcfg.etats) {
            var etat = colcfg.etats[String(val)];
            if (!etat) return { html: TIRET };
            return { html: '<span class="ref-etat ref-etat--' + (String(val) === '1' ? 'on' : 'off') + '">' + esc(etat) + '</span>' };
        }

        if ('select' === colcfg.type) {
            var lib = row[cle + '__libelle'];
            var sigle = colcfg.sigle && row[cle + '__code'];
            if (sigle) return { html: '<span class="ref-sigle" title="' + esc(lib) + '">' + esc(sigle) + '</span>', classe: 'ref-td-court' };
            if (lib) return { html: esc(lib) };
            return { html: colcfg.vide ? '<span class="ref-vide">' + esc(colcfg.vide) + '</span>' : TIRET };
        }

        if ('enum' === colcfg.type) {
            var opt = (colcfg.options || []).filter(function (o) { return String(o.id) === String(val); })[0];
            return { html: opt ? esc(opt.libelle) : TIRET, classe: 'ref-td-court' };
        }

        if (vide) return { html: TIRET };

        if ('code' === cle) return { html: '<span class="ref-code">' + surligner(val, terme) + '</span>' };
        if ('slug' === cle) return { html: surligner(val, terme), classe: 'ref-td-court ref-muted' };
        if ('url' === cle) return { html: '<span class="ref-url" title="' + esc(val) + '">' + surligner(val, terme) + '</span>' };
        if ('number' === colcfg.type) return { html: esc(val), classe: 'ref-td-num' };
        if (cle === cfg.labelCol) return { html: surligner(val, terme), classe: 'ref-td-main' };
        return { html: surligner(val, terme) };
    }

    function skeletons() {
        var zone = $('admin-ref-table-wrap');
        if (!zone) return;
        zone.innerHTML = '<div class="ref-skeleton" aria-hidden="true">' +
            new Array(7).fill('<div class="admin-skeleton admin-skeleton--row"></div>').join('') + '</div>';
        var pied = $('admin-ref-pagination');
        if (pied) pied.innerHTML = '';
    }

    function etat(icone_, titre, texte, variante, action) {
        return '<div class="admin-state ' + (variante || '') + '">' +
               '<span class="admin-state-ico">' + icone(icone_) + '</span>' +
               '<h3>' + esc(titre) + '</h3><p>' + esc(texte) + '</p>' + (action || '') + '</div>';
    }

    function renderTable(data) {
        var zone = $('admin-ref-table-wrap');
        var pied = $('admin-ref-pagination');
        var cfg = REGISTRY[cleCourante];
        if (!zone || !cfg) return;

        lignesParId = {};

        if (estErreur(data)) {
            zone.innerHTML = etat('alert', 'Chargement impossible', data.erreur, 'admin-state--error',
                '<button type="button" class="admin-tbtn ref-state-action" data-action="recharger">' +
                icone('refresh', 'admin-icon--sm') + 'Réessayer</button>');
            pied.innerHTML = '';
            return;
        }

        var terme = termeRecherche();
        var filtre = filtresActifs().length > 0;

        if (!data.rows.length) {
            if (filtre || terme) {
                var texte = terme
                    ? 'Aucune ligne ne correspond à ' + guillemets(terme) + (filtre ? ' avec ces filtres.' : '.')
                    : 'Aucune ligne ne correspond à ces filtres.';
                zone.innerHTML = etat('search', 'Aucun résultat', texte, '',
                    '<button type="button" class="admin-tbtn ref-state-action" data-action="effacer">' +
                    icone('close', 'admin-icon--sm') + (terme && filtre ? 'Effacer la recherche et les filtres' : terme ? 'Effacer la recherche' : 'Effacer les filtres') + '</button>');
            } else {
                zone.innerHTML = etat('inbox', 'Cette table est vide', 'Aucune ligne pour le moment.', '',
                    cfg.canCreate ? '<button type="button" class="admin-tbtn admin-tbtn--primary ref-state-action" data-action="ajouter">' +
                    icone('plus', 'admin-icon--sm') + 'Ajouter ' + esc(cfg.singulier || 'une ligne') + '</button>' : '');
            }
            pied.innerHTML = '';
            return;
        }

        var cols = cfg.columns;
        var thead = Object.keys(cols).map(function (cle) {
            return '<th scope="col">' + esc(cols[cle].th || cols[cle].label) + '</th>';
        }).join('') + '<th scope="col"><span class="admin-sr-only">Actions</span></th>';

        var tbody = data.rows.map(function (row, index) {
            lignesParId[row.id] = row;
            var nom = row[cfg.labelCol] || ('n° ' + row.id);

            var tds = Object.keys(cols).map(function (cle) {
                var c = cellule(cle, cols[cle], row, cfg, terme);
                return '<td data-label="' + esc(cols[cle].th || cols[cle].label) + '"' +
                       (c.classe ? ' class="' + c.classe + '"' : '') + '>' + c.html + '</td>';
            }).join('');

            return '<tr class="admin-row-open" data-id="' + row.id + '" style="--i:' + index + '">' + tds +
                '<td class="ref-actions"><span class="ref-actions-inner">' +
                    '<button type="button" class="ref-act" data-edit="' + row.id + '" aria-label="Modifier ' + esc(guillemets(nom)) + '" title="Modifier">' +
                    icone('edit', 'admin-icon--sm') + '</button>' +
                    (cfg.canDelete ? '<button type="button" class="ref-act ref-act--danger" data-delete="' + row.id + '" aria-label="Supprimer ' + esc(guillemets(nom)) + '" title="Supprimer">' +
                    icone('trash', 'admin-icon--sm') + '</button>' : '') +
                '</span></td></tr>';
        }).join('');

        zone.innerHTML =
            '<div class="admin-table-scroll"><table class="admin-table ref-table">' +
            '<caption class="admin-sr-only">' + esc(cfg.label) + '</caption>' +
            '<thead><tr>' + thead + '</tr></thead><tbody>' + tbody + '</tbody></table></div>';

        // Pied : où l'on est dans la table, puis la pagination.
        var parPage = data.per_page || 20;
        var debut = (data.page - 1) * parPage + 1;
        var fin = debut + data.rows.length - 1;
        var unite = (filtre || terme) ? ['résultat', 'résultats'] : ['ligne', 'lignes'];
        var plage = data.nb_pages > 1
            ? '<strong>' + nombre(debut) + '–' + nombre(fin) + '</strong> sur ' + pluriel(data.total, unite[0], unite[1])
            : '<strong>' + nombre(data.total) + '</strong> ' + (data.total > 1 ? unite[1] : unite[0]);

        var pager = '';
        if (data.nb_pages > 1) {
            pager = '<div class="ref-pager">' +
                '<button type="button" class="ref-page-btn" data-page="' + (data.page - 1) + '" aria-label="Page précédente"' +
                (data.page <= 1 ? ' disabled' : '') + '>' + icone('arrow-left', 'admin-icon--sm') + '</button>' +
                '<span class="ref-pager-info" aria-label="Page ' + data.page + ' sur ' + data.nb_pages + '">' +
                data.page + ' / ' + data.nb_pages + '</span>' +
                '<button type="button" class="ref-page-btn" data-page="' + (data.page + 1) + '" aria-label="Page suivante"' +
                (data.page >= data.nb_pages ? ' disabled' : '') + '>' + icone('arrow-right', 'admin-icon--sm') + '</button>' +
                '</div>';
        }
        pied.innerHTML = '<span class="ref-range">' + plage + '</span>' + pager;
    }

    /* options.squelette : squelette complet (changement de table). Sinon le
       tableau en place s'estompe le temps de la requête, sans disparaître. */
    function chargerListe(options) {
        var cfg = REGISTRY[cleCourante];
        if (!cfg) return Promise.resolve();

        var zone = $('admin-ref-table-wrap');
        var numero = ++requeteCourante;
        if ((options && options.squelette) || !zone.querySelector('.ref-table')) skeletons();
        else zone.classList.add('is-loading');

        var cle = cleCourante;
        var params = {
            ref_key: cle,
            recherche: termeRecherche(),
            page: pageCourante
        };
        // PHP reconstitue $_POST['filtres'] à partir de ces clés.
        filtresActifs().forEach(function (col) {
            params['filtres[' + col + ']'] = filtres[col];
        });

        return ajax('ueb_admin_ref_list', params).then(function (data) {
            if (numero !== requeteCourante) return; // une requête plus récente est partie
            zone.classList.remove('is-loading');

            // Sans recherche ni filtre, le total renvoyé est celui de la
            // table : on en profite pour garder l'index à jour.
            if (!estErreur(data) && !params.recherche && !filtresActifs().length && REGISTRY[cle].total !== data.total) {
                REGISTRY[cle].total = data.total;
                majCompte(cle);
            }
            renderTable(data);
        });
    }

    function changerTable(cle) {
        if (!REGISTRY[cle]) return;
        ouvrirNavMobile(false);
        if (cle === cleCourante) return;

        cleCourante = cle;
        pageCourante = 1;
        filtres = {}; // les colonnes diffèrent d'une table à l'autre
        var champ = $('admin-ref-recherche');
        if (champ) champ.value = '';

        ecrireHash();
        activerNav();
        renderEntete(true);
        renderFiltres();
        chargerListe({ squelette: true });

        // Sur téléphone, l'index est au-dessus : on remonte au titre.
        var main = $('admin-main');
        if (main && main.getBoundingClientRect().top < 0) {
            window.scrollTo({ top: 0, behavior: reducedMotion ? 'auto' : 'smooth' });
        }
    }

    document.addEventListener('click', function (e) {
        var pageBtn = e.target.closest('#admin-ref-pagination .ref-page-btn');
        if (pageBtn && !pageBtn.disabled) {
            pageCourante = parseInt(pageBtn.dataset.page, 10);
            chargerListe();
            return;
        }

        var action = e.target.closest('[data-action]');
        if (!action) return;
        if ('recharger' === action.dataset.action) chargerListe({ squelette: true });
        if ('effacer' === action.dataset.action) effacerFiltresEtRecherche();
        if ('ajouter' === action.dataset.action) {
            dernierDeclencheur = action;
            ouvrirFormulaire(cleCourante, null);
        }
    });

    var champRecherche = $('admin-ref-recherche');
    if (champRecherche) {
        champRecherche.addEventListener('input', function () {
            if (rechercheTimeout) clearTimeout(rechercheTimeout);
            rechercheTimeout = setTimeout(function () {
                pageCourante = 1;
                chargerListe();
            }, 300);
        });
    }

    /* ================================================================
       MESSAGES (toasts)
       ================================================================ */
    function toast(message, type) {
        var zone = $('ref-toasts');
        if (!zone) return;

        var el = document.createElement('div');
        el.className = 'ref-toast' + ('error' === type ? ' ref-toast--error' : '');
        el.innerHTML = icone('error' === type ? 'alert' : 'check') + '<span>' + esc(message) + '</span>';
        zone.appendChild(el);

        // Pas plus de trois à la fois : le plus ancien laisse sa place.
        while (zone.children.length > 3) zone.removeChild(zone.firstChild);

        setTimeout(function () {
            if (reducedMotion) { el.remove(); return; }
            el.classList.add('is-leaving');
            setTimeout(function () { el.remove(); }, 160);
        }, 'error' === type ? 6000 : 3400);
    }

    /* ================================================================
       DIALOGUES (formulaire, confirmation)
       Focus piégé dans la fenêtre ouverte, Échap pour fermer, retour du
       focus sur le bouton qui l'a ouverte.
       ================================================================ */
    var dialogueOuvert = null;
    var dernierDeclencheur = null;

    function focusables(el) {
        return Array.prototype.filter.call(
            el.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'),
            function (n) { return n.offsetParent !== null || n.type === 'radio'; }
        );
    }

    function ouvrirDialogue(el, cibleFocus) {
        el.hidden = false;
        dialogueOuvert = el;
        document.body.style.overflow = 'hidden';
        var cible = cibleFocus || focusables(el)[0];
        if (cible) cible.focus();
    }

    function fermerDialogue(el) {
        if (!el || el.hidden) return;
        var termine = function () {
            el.classList.remove('is-closing');
            el.hidden = true;
            if (dialogueOuvert === el) dialogueOuvert = null;
            document.body.style.overflow = '';
            if (dernierDeclencheur && document.contains(dernierDeclencheur)) dernierDeclencheur.focus();
        };
        if (reducedMotion) { termine(); return; }
        el.classList.add('is-closing');
        setTimeout(termine, 150);
    }

    document.addEventListener('keydown', function (e) {
        if (dialogueOuvert) {
            if ('Escape' === e.key) {
                e.preventDefault();
                fermerDialogue(dialogueOuvert);
                return;
            }
            if ('Tab' === e.key) {
                var liste = focusables(dialogueOuvert);
                if (!liste.length) return;
                var premier = liste[0];
                var dernier = liste[liste.length - 1];
                if (e.shiftKey && document.activeElement === premier) { e.preventDefault(); dernier.focus(); }
                else if (!e.shiftKey && document.activeElement === dernier) { e.preventDefault(); premier.focus(); }
            }
            return;
        }

        if ('Escape' === e.key && sidebar && sidebar.classList.contains('is-open')) {
            ouvrirNavMobile(false);
            if (toggleNav) toggleNav.focus();
            return;
        }

        // « / » place le curseur dans la recherche, comme dans la plupart
        // des outils de gestion — sauf si l'on est déjà en train d'écrire.
        if ('/' === e.key && !e.ctrlKey && !e.metaKey && !e.altKey) {
            var t = e.target;
            var saisie = t && (t.isContentEditable || /^(input|textarea|select)$/i.test(t.tagName));
            if (!saisie && champRecherche) {
                e.preventDefault();
                champRecherche.focus();
                champRecherche.select();
            }
        }
    });

    /* ================================================================
       FORMULAIRE D'AJOUT / MODIFICATION
       ================================================================ */
    var modal = $('admin-ref-modal');
    var form = $('admin-ref-form');

    function champHtml(cle, colcfg, valeur) {
        var id = 'admin-ref-champ-' + cle;
        var requis = colcfg.required ? ' required aria-required="true"' : '';
        var facultatif = colcfg.required ? '' : '<span class="ref-opt">facultatif</span>';
        var aValeur = valeur !== undefined && valeur !== null && valeur !== '';
        var court = 'number' === colcfg.type || ('text' === colcfg.type && colcfg.maxlength && colcfg.maxlength <= 20);
        var erreur = '<p class="ref-field-error" id="' + id + '-err" hidden></p>';

        // Choix fermé : boutons radio visibles plutôt qu'une liste.
        if ('enum' === colcfg.type) {
            // Une nouvelle ligne naît active (Oui) : c'est le cas courant.
            var courant = aValeur ? String(valeur) : (colcfg.etats && !aValeur ? '1' : '');
            var radios = (colcfg.options || []).map(function (o) {
                var coche = courant === String(o.id) ? ' checked' : '';
                return '<label class="ref-seg-opt"><input type="radio" class="admin-sr-only" name="' + esc(cle) + '" value="' + esc(o.id) + '"' +
                       coche + requis + '><span>' + esc(o.libelle) + '</span></label>';
            }).join('');
            return '<fieldset class="ref-field" id="' + id + '" data-champ="' + esc(cle) + '" aria-describedby="' + id + '-err">' +
                   '<legend class="ref-field-label">' + esc(colcfg.label) + facultatif + '</legend>' +
                   '<div class="ref-seg">' + radios + '</div>' + erreur + '</fieldset>';
        }

        var label = '<label class="ref-field-label" for="' + id + '">' + esc(colcfg.label) + facultatif + '</label>';

        if ('select' === colcfg.type) {
            var options = (colcfg.options || []).map(function (o) {
                var sel = aValeur && String(valeur) === String(o.id) ? ' selected' : '';
                return '<option value="' + esc(o.id) + '"' + sel + '>' + esc(o.libelle) + '</option>';
            }).join('');
            var premier = colcfg.required ? 'Choisir…' : (colcfg.vide || 'Aucun');
            return '<div class="ref-field" data-champ="' + esc(cle) + '">' + label +
                   '<div class="ref-select"><select class="ref-input" id="' + id + '" name="' + esc(cle) + '"' + requis +
                   ' aria-describedby="' + id + '-err"><option value="">' + esc(premier) + '</option>' + options + '</select>' +
                   icone('chevron-down', 'admin-icon--sm') + '</div>' + erreur + '</div>';
        }

        var attributs = 'number' === colcfg.type
            ? 'type="number" inputmode="numeric"'
            : 'type="text"' + (colcfg.maxlength ? ' maxlength="' + colcfg.maxlength + '"' : '') + ('url' === cle ? ' inputmode="url" spellcheck="false"' : '');

        return '<div class="ref-field' + (court ? ' ref-field--court' : '') + '" data-champ="' + esc(cle) + '">' + label +
               '<input class="ref-input" ' + attributs + ' id="' + id + '" name="' + esc(cle) + '" value="' + esc(aValeur ? valeur : '') + '"' +
               requis + ' autocomplete="off" aria-describedby="' + id + '-err">' + erreur + '</div>';
    }

    function ouvrirFormulaire(cle, row) {
        var cfg = REGISTRY[cle];
        if (!cfg || !modal) return;

        idEnEdition = row ? row.id : 0;

        $('admin-ref-modal-title').textContent = row
            ? 'Modifier ' + guillemets(row[cfg.labelCol] || ('n° ' + row.id))
            : 'Ajouter ' + (cfg.singulier || 'une ligne');
        $('admin-ref-modal-sub').textContent = cfg.label;

        $('admin-ref-form-fields').innerHTML = Object.keys(cfg.columns).map(function (col) {
            return champHtml(col, cfg.columns[col], row ? row[col] : undefined);
        }).join('');

        var erreur = $('admin-ref-form-error');
        erreur.hidden = true;
        erreur.textContent = '';

        ouvrirDialogue(modal, modal.querySelector('.ref-form-fields .ref-input, .ref-form-fields input'));
    }

    function valeurChamp(col) {
        var coche = form.querySelector('input[type="radio"][name="' + col + '"]:checked');
        if (coche) return coche.value;
        var el = $('admin-ref-champ-' + col);
        return el && el.tagName !== 'FIELDSET' ? el.value.trim() : '';
    }

    function marquerErreur(col, message) {
        var err = $('admin-ref-champ-' + col + '-err');
        var champ = $('admin-ref-champ-' + col);
        if (err) { err.textContent = message || ''; err.hidden = !message; }
        if (champ && champ.tagName !== 'FIELDSET') {
            if (message) champ.setAttribute('aria-invalid', 'true');
            else champ.removeAttribute('aria-invalid');
        }
    }

    // Validation locale des champs obligatoires, avant l'aller-retour serveur.
    function validerFormulaire(cfg) {
        var premierInvalide = null;
        Object.keys(cfg.columns).forEach(function (col) {
            var manque = cfg.columns[col].required && valeurChamp(col) === '';
            marquerErreur(col, manque ? 'Ce champ est obligatoire.' : '');
            if (manque && !premierInvalide) premierInvalide = col;
        });
        if (premierInvalide) {
            var el = $('admin-ref-champ-' + premierInvalide);
            var cible = el && el.tagName === 'FIELDSET' ? el.querySelector('input') : el;
            if (cible) cible.focus();
            return false;
        }
        return true;
    }

    if (form) {
        form.addEventListener('input', function (e) {
            var bloc = e.target.closest('[data-champ]');
            if (bloc) marquerErreur(bloc.dataset.champ, '');
        });
        form.addEventListener('change', function (e) {
            var bloc = e.target.closest('[data-champ]');
            if (bloc) marquerErreur(bloc.dataset.champ, '');
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var cle = cleCourante;
            var cfg = REGISTRY[cle];
            if (!cfg || !validerFormulaire(cfg)) return;

            var params = { ref_key: cle, id: idEnEdition };
            Object.keys(cfg.columns).forEach(function (col) {
                params['champs[' + col + ']'] = valeurChamp(col);
            });

            var envoyer = $('admin-ref-submit');
            var creation = !idEnEdition;
            envoyer.disabled = true;
            envoyer.classList.add('is-busy');
            envoyer.textContent = 'Enregistrement…';

            ajax('ueb_admin_ref_save', params).then(function (res) {
                envoyer.disabled = false;
                envoyer.classList.remove('is-busy');
                envoyer.textContent = 'Enregistrer';

                if (estErreur(res)) {
                    var erreur = $('admin-ref-form-error');
                    erreur.textContent = res.erreur;
                    erreur.hidden = false;
                    return;
                }

                fermerDialogue(modal);
                if (creation) {
                    REGISTRY[cle].total += 1;
                    majCompte(cle);
                }
                toast(creation ? 'Nouvelle ligne enregistrée dans ' + cfg.label + '.' : 'Modifications enregistrées.');
                chargerListe();
            });
        });
    }

    /* ================================================================
       CONFIRMATION DE SUPPRESSION
       ================================================================ */
    var confirmation = $('ref-confirm');
    var confirmOk = $('ref-confirm-ok');
    var idASupprimer = 0;

    function ouvrirConfirmation(id) {
        var cfg = REGISTRY[cleCourante];
        var row = lignesParId[id];
        if (!confirmation || !cfg) return;

        idASupprimer = id;
        var nom = row && row[cfg.labelCol] ? row[cfg.labelCol] : 'cette ligne';
        $('ref-confirm-title').textContent = 'Supprimer ' + (row && row[cfg.labelCol] ? guillemets(nom) : nom) + ' ?';
        var err = $('ref-confirm-error');
        err.hidden = true;
        err.textContent = '';
        confirmOk.disabled = false;
        confirmOk.textContent = 'Supprimer';

        // Le focus va sur « Annuler » : l'action irréversible ne doit
        // jamais être celle qu'une touche Entrée déclenche par réflexe.
        ouvrirDialogue(confirmation, confirmation.querySelector('[data-close-ref-confirm].btn'));
    }

    if (confirmOk) {
        confirmOk.addEventListener('click', function () {
            var cle = cleCourante;
            var cfg = REGISTRY[cle];
            confirmOk.disabled = true;
            confirmOk.textContent = 'Suppression…';

            ajax('ueb_admin_ref_delete', { ref_key: cle, id: idASupprimer }).then(function (res) {
                if (estErreur(res)) {
                    var err = $('ref-confirm-error');
                    err.textContent = res.erreur;
                    err.hidden = false;
                    confirmOk.disabled = false;
                    confirmOk.textContent = 'Supprimer';
                    return;
                }

                fermerDialogue(confirmation);
                REGISTRY[cle].total = Math.max(0, REGISTRY[cle].total - 1);
                majCompte(cle);
                toast('Ligne supprimée de ' + cfg.label + '.');

                // Dernière ligne d'une page : on recule d'une page.
                if (Object.keys(lignesParId).length === 1 && pageCourante > 1) pageCourante -= 1;
                chargerListe();
            });
        });
    }

    /* ================================================================
       CLICS : lignes, ajout, modification, suppression, fermetures
       ================================================================ */
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-close-ref-modal]')) { fermerDialogue(modal); return; }
        if (e.target.closest('[data-close-ref-confirm]')) { fermerDialogue(confirmation); return; }

        // Cliquer n'importe où sur une ligne l'ouvre en modification, comme
        // le bouton crayon ; une sélection de texte en cours est respectée.
        var ligne = e.target.closest('tr.admin-row-open');
        if (ligne && !e.target.closest('a, button, input, select, textarea, label, summary')) {
            var selection = window.getSelection && window.getSelection().toString();
            if (!selection) {
                var editer = ligne.querySelector('[data-edit]');
                if (editer) editer.click();
            }
            return;
        }

        var ajout = e.target.closest('#admin-ref-add');
        if (ajout) {
            dernierDeclencheur = ajout;
            ouvrirFormulaire(cleCourante, null);
            return;
        }

        var edit = e.target.closest('[data-edit]');
        if (edit) {
            dernierDeclencheur = edit;
            edit.disabled = true;
            ajax('ueb_admin_ref_get', { ref_key: cleCourante, id: edit.dataset.edit }).then(function (row) {
                edit.disabled = false;
                if (estErreur(row)) {
                    toast(row.erreur, 'error');
                    return;
                }
                ouvrirFormulaire(cleCourante, row);
            });
            return;
        }

        var suppr = e.target.closest('[data-delete]');
        if (suppr) {
            dernierDeclencheur = suppr;
            ouvrirConfirmation(parseInt(suppr.dataset.delete, 10));
            return;
        }

        // Clic hors de l'index replié (téléphone) : il se referme.
        if (sidebar && sidebar.classList.contains('is-open') && !e.target.closest('#ref-sidebar')) {
            ouvrirNavMobile(false);
        }
    });

    /* ================================================================
       CHARGEMENT INITIAL
       ================================================================ */
    if (!cleCourante) return;
    renderNav();
    renderEntete(false);
    renderFiltres();
    placerIndicateur();
    montrerActif();
    majFonduNav();
    chargerListe({ squelette: true });

}());
