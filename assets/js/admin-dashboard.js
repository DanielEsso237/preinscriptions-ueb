/**
 * Pilotage du dashboard admin : filtres, liste des dossiers, KPI, thème.
 *
 * Le rendu des graphiques est délégué à admin-analytics.js (window.uebCharts),
 * ce fichier ne s'occupe que des données et de l'interface autour.
 *
 * @package Preinscriptions_UEB
 */
(function () {
    'use strict';

    if (typeof window.uebAdminDashboard === 'undefined') return;
    var CFG = window.uebAdminDashboard;

    var reducedMotion = window.matchMedia &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ================================================================
       OUTILS
       ================================================================ */
    function $(id) { return document.getElementById(id); }

    function esc(str) {
        var div = document.createElement('div');
        div.textContent = str == null ? '' : str;
        return div.innerHTML;
    }

    function nf(n) {
        return new Intl.NumberFormat('fr-FR').format(n);
    }

    function icone(nom, classe) {
        return '<svg class="admin-icon ' + (classe || '') + '" aria-hidden="true"><use href="#ueb-i-' + nom + '"/></svg>';
    }

    function ajax(action, params) {
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
       THÈME CLAIR / SOMBRE
       La préférence explicite est mémorisée ; en son absence on suit le
       réglage système (et on continue de le suivre s'il change).
       ================================================================ */
    var CLE_THEME = 'ueb-admin-theme';

    function themeActif() {
        var force = document.documentElement.getAttribute('data-ueb-theme');
        if (force) return force;
        return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)
            ? 'dark' : 'light';
    }

    var minuteurTheme = null;

    function appliquerTheme(mode) {
        var racine = document.documentElement;

        // La classe active un fondu des couleurs le temps du basculement
        // seulement. La laisser en permanence imposerait une transition à
        // chaque survol de ligne du tableau, pour rien.
        if (!reducedMotion) {
            racine.classList.add('ueb-theming');
            if (minuteurTheme) clearTimeout(minuteurTheme);
            minuteurTheme = setTimeout(function () {
                racine.classList.remove('ueb-theming');
            }, 320);
        }

        racine.setAttribute('data-ueb-theme', mode);
        try { localStorage.setItem(CLE_THEME, mode); } catch (e) { /* stockage indisponible */ }
        document.dispatchEvent(new CustomEvent('uebThemeChange', { detail: { mode: mode } }));
    }

    var boutonTheme = $('admin-theme-toggle');
    if (boutonTheme) {
        boutonTheme.addEventListener('click', function () {
            appliquerTheme(themeActif() === 'dark' ? 'light' : 'dark');
        });
    }

    if (window.matchMedia) {
        var mqSombre = window.matchMedia('(prefers-color-scheme: dark)');
        var onSystemChange = function () {
            // Ne s'applique que si l'utilisateur n'a jamais tranché lui-même.
            var choix = null;
            try { choix = localStorage.getItem(CLE_THEME); } catch (e) { /* ignore */ }
            if (!choix) document.dispatchEvent(new CustomEvent('uebThemeChange'));
        };
        if (mqSombre.addEventListener) mqSombre.addEventListener('change', onSystemChange);
        else if (mqSombre.addListener) mqSombre.addListener(onSystemChange);
    }

    /* ================================================================
       PEUPLEMENT DES FILTRES
       ================================================================ */
    function fillSelect(select, items, placeholder, enable) {
        if (!select) return;
        select.innerHTML = '';
        var opt0 = document.createElement('option');
        opt0.value = '';
        opt0.textContent = placeholder;
        select.appendChild(opt0);

        (items || []).forEach(function (item) {
            var opt = document.createElement('option');
            opt.value = item.id;
            opt.textContent = item.libelle;
            select.appendChild(opt);
        });

        select.disabled = !enable;
    }

    var refs = CFG.refs || {};

    fillSelect($('filter-faculte'), refs.facultes, 'Toutes', true);
    fillSelect($('filter-diplome_admission'), refs.diplomes, 'Tous', true);
    fillSelect($('filter-type_formation'), refs.types_formation, 'Tous', true);
    fillSelect($('filter-niveau_lmd'), refs.niveaux_lmd, 'Tous', true);
    fillSelect($('filter-mention'), refs.mentions, 'Toutes', true);
    fillSelect($('filter-statut_etudiant'), refs.statuts_etudiant, 'Tous', true);
    fillSelect($('filter-sexe'), refs.sexes, 'Tous', true);
    fillSelect($('filter-handicap'), refs.handicaps, 'Tous', true);
    fillSelect($('filter-nationalite'), refs.nationalites, 'Toutes', true);
    fillSelect($('filter-premiere_langue'), refs.langues, 'Toutes', true);
    fillSelect($('filter-situation_matrimoniale'), refs.situations_matrimoniales, 'Toutes', true);
    fillSelect($('filter-statut_socio_professionnel'), refs.statuts_socio, 'Tous', true);
    fillSelect($('filter-region_origine'), refs.regions, 'Toutes', true);
    fillSelect($('filter-sport_prefere'), refs.sports, 'Tous', true);
    fillSelect($('filter-art_pratique'), refs.arts, 'Tous', true);

    var selFaculte    = $('filter-faculte');
    var selDiplome    = $('filter-diplome_admission');
    var selSpecialite = $('filter-specialite_diplome');
    var selFiliere    = $('filter-filiere');
    var selRegion     = $('filter-region_origine');
    var selDept       = $('filter-departement_origine');
    var selCommune    = $('filter-commune_origine');

    function updateSpecialite() {
        var facId = selFaculte.value, dipId = selDiplome.value;
        if (!facId || !dipId) {
            fillSelect(selSpecialite, [], '— Choisir faculté et diplôme —', false);
            return;
        }
        fillSelect(selSpecialite, [], 'Chargement…', false);
        ajax('ueb_admin_get_specialites', { faculte_id: facId, diplome_id: dipId }).then(function (data) {
            fillSelect(selSpecialite, data, data && data.length ? 'Toutes' : '— Aucune spécialité —', !!(data && data.length));
        });
    }

    function updateFiliere() {
        var facId = selFaculte.value;
        if (!facId) {
            fillSelect(selFiliere, [], "— Choisir d'abord une faculté —", false);
            return;
        }
        fillSelect(selFiliere, [], 'Chargement…', false);
        ajax('ueb_admin_get_filieres', { faculte_id: facId }).then(function (data) {
            fillSelect(selFiliere, data, 'Toutes', true);
        });
    }

    selFaculte.addEventListener('change', function () { updateSpecialite(); updateFiliere(); });
    selDiplome.addEventListener('change', updateSpecialite);

    selRegion.addEventListener('change', function () {
        var regionId = selRegion.value;
        fillSelect(selDept, [], "— Choisir d'abord une région —", false);
        fillSelect(selCommune, [], "— Choisir d'abord un département —", false);
        if (!regionId) return;
        fillSelect(selDept, [], 'Chargement…', false);
        ajax('ueb_admin_get_departements', { region_id: regionId }).then(function (data) {
            fillSelect(selDept, data, 'Tous', true);
        });
    });

    selDept.addEventListener('change', function () {
        var deptId = selDept.value;
        fillSelect(selCommune, [], "— Choisir d'abord un département —", false);
        if (!deptId) return;
        fillSelect(selCommune, [], 'Chargement…', false);
        ajax('ueb_admin_get_communes', { departement_id: deptId }).then(function (data) {
            fillSelect(selCommune, data, 'Toutes', true);
        });
    });

    /* ================================================================
       COLLECTE DES FILTRES
       ================================================================ */
    var FILTER_IDS = [
        'faculte', 'diplome_admission', 'specialite_diplome', 'type_formation',
        'filiere', 'niveau_lmd', 'mention', 'statut_etudiant', 'sexe', 'handicap',
        'nationalite', 'premiere_langue', 'situation_matrimoniale',
        'statut_socio_professionnel', 'region_origine', 'departement_origine',
        'commune_origine', 'sport_prefere', 'art_pratique'
    ];

    // Intitulés courts pour les pastilles de filtres actifs.
    var FILTER_LABELS = {
        faculte: 'Faculté', diplome_admission: 'Diplôme', specialite_diplome: 'Série',
        type_formation: 'Formation', filiere: 'Filière', niveau_lmd: 'Niveau',
        mention: 'Mention', statut_etudiant: 'Statut', sexe: 'Sexe',
        handicap: 'Handicap', nationalite: 'Nationalité', premiere_langue: 'Langue',
        situation_matrimoniale: 'Situation', statut_socio_professionnel: 'Socio-pro',
        region_origine: 'Région', departement_origine: 'Département',
        commune_origine: 'Commune', sport_prefere: 'Sport', art_pratique: 'Art'
    };

    function collectFilters() {
        var out = {};
        FILTER_IDS.forEach(function (key) {
            var el = $('filter-' + key);
            out[key] = el ? el.value : '';
        });
        return out;
    }

    function filtresActifs() {
        return FILTER_IDS.filter(function (key) {
            var el = $('filter-' + key);
            return el && el.value;
        });
    }

    /* ================================================================
       PASTILLES DE FILTRES ACTIFS
       ================================================================ */
    function renderChips() {
        var zone = $('admin-active-filters');
        if (!zone) return;

        var actifs = filtresActifs();
        if (!actifs.length) {
            zone.innerHTML = '';
            return;
        }

        var html = actifs.map(function (key) {
            var el = $('filter-' + key);
            var texte = el.options[el.selectedIndex] ? el.options[el.selectedIndex].textContent : '';
            return '<span class="admin-chip">' +
                   esc(FILTER_LABELS[key] || key) + ' : <b>' + esc(texte) + '</b>' +
                   '<button type="button" class="admin-chip-x" data-remove-filter="' + key + '" ' +
                   'aria-label="Retirer le filtre ' + esc(FILTER_LABELS[key] || key) + '">' +
                   icone('close', 'admin-icon--sm') + '</button></span>';
        }).join('');

        html += '<button type="button" id="admin-clear-all" class="admin-chip" style="cursor:pointer">' +
                'Tout effacer</button>';

        zone.innerHTML = html;
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-remove-filter]');
        if (btn) {
            var key = btn.dataset.removeFilter;
            var el = $('filter-' + key);
            if (el) {
                el.value = '';
                // Les listes dépendantes doivent suivre le retrait du parent.
                if (key === 'faculte') { updateSpecialite(); updateFiliere(); }
                if (key === 'diplome_admission') { updateSpecialite(); }
                if (key === 'region_origine') { selRegion.dispatchEvent(new Event('change')); }
                if (key === 'departement_origine') { selDept.dispatchEvent(new Event('change')); }
            }
            appliquerFiltres();
            return;
        }
        if (e.target.closest('#admin-clear-all')) {
            reinitialiser();
        }
    });

    /* ================================================================
       CARTES KPI
       ================================================================ */

    /** Compteur animé : la valeur monte de 0 à sa cible en ~700 ms. */
    function animerNombre(el, cible, suffixe) {
        var final = Number(cible) || 0;

        if (reducedMotion || final === 0) {
            el.textContent = nf(final) + (suffixe || '');
            return;
        }

        var debut = null;
        var duree = 700;

        function pas(ts) {
            if (debut === null) debut = ts;
            var p = Math.min((ts - debut) / duree, 1);
            // easeOutCubic : démarrage franc, arrivée douce sur la valeur.
            var e = 1 - Math.pow(1 - p, 3);
            var courant = final % 1 === 0
                ? Math.round(final * e)
                : Math.round(final * e * 10) / 10;
            el.textContent = nf(courant) + (suffixe || '');
            if (p < 1) requestAnimationFrame(pas);
        }
        requestAnimationFrame(pas);
    }

    /** Construit le tracé SVG d'une mini-courbe à partir d'une série. */
    function sparkline(serie) {
        if (!serie || serie.length < 2) return '';

        var l = 100, h = 30, max = Math.max.apply(null, serie), min = Math.min.apply(null, serie);
        var amplitude = max - min || 1;
        var pasX = l / (serie.length - 1);

        var points = serie.map(function (v, i) {
            var x = i * pasX;
            // 3 px de marge haute et basse pour que le trait ne soit pas rogné.
            var y = h - 3 - ((v - min) / amplitude) * (h - 6);
            return [x, y];
        });

        var d = points.map(function (p, i) {
            return (i === 0 ? 'M' : 'L') + p[0].toFixed(1) + ' ' + p[1].toFixed(1);
        }).join(' ');

        var aire = d + ' L' + l + ' ' + h + ' L0 ' + h + ' Z';

        return '<svg class="admin-kpi-spark" viewBox="0 0 ' + l + ' ' + h + '" preserveAspectRatio="none" aria-hidden="true">' +
               '<path class="spark-area" d="' + aire + '"/>' +
               '<path class="spark-line" d="' + d + '"/></svg>';
    }

    /** Rend une variation en pourcentage avec sa flèche et son sens. */
    function delta(actuel, precedent) {
        if (!precedent) {
            return actuel > 0
                ? '<span class="admin-kpi-delta admin-kpi-delta--up">' + icone('up', 'admin-icon--sm') + 'nouveau</span>'
                : '<span class="admin-kpi-delta admin-kpi-delta--flat">' + icone('minus', 'admin-icon--sm') + 'stable</span>';
        }
        var variation = Math.round(((actuel - precedent) / precedent) * 100);
        if (variation === 0) {
            return '<span class="admin-kpi-delta admin-kpi-delta--flat">' + icone('minus', 'admin-icon--sm') + 'stable</span>';
        }
        var sens = variation > 0 ? 'up' : 'down';
        return '<span class="admin-kpi-delta admin-kpi-delta--' + sens + '">' +
               icone(sens, 'admin-icon--sm') + Math.abs(variation) + '%</span>';
    }

    function carteKpi(o) {
        return '<article class="admin-kpi-card" style="--ueb-kpi-accent:' + o.accent + ';--ueb-kpi-wash:' + o.wash + '">' +
               '<div class="admin-kpi-head">' +
                   '<span class="admin-kpi-label">' + esc(o.label) + '</span>' +
                   '<span class="admin-kpi-ico">' + icone(o.icone, 'admin-icon--sm') + '</span>' +
               '</div>' +
               '<span class="admin-kpi-value" data-count="' + o.valeur + '"' +
                     (o.suffixe ? ' data-suffixe="' + o.suffixe + '"' : '') + '>0</span>' +
               (o.pied ? '<div class="admin-kpi-foot">' + o.pied + '</div>' : '') +
               (o.barre != null ? '<div class="admin-kpi-bar"><span data-width="' + o.barre + '"></span></div>' : '') +
               (o.spark || '') +
               '</article>';
    }

    function renderKpis(k) {
        var grid = $('admin-kpi-grid');
        if (!grid || !k) return;

        var css = getComputedStyle(document.documentElement);
        var c = function (nom) { return css.getPropertyValue(nom).trim(); };

        var html =
            carteKpi({
                label: 'Dossiers (filtre actif)',
                valeur: k.total,
                icone: 'users',
                accent: c('--ueb-chart-1'),
                wash: c('--ueb-primary-wash'),
                pied: delta(k.semaine, k.semainePrecedente) + '<span>sur 7 jours</span>',
                spark: sparkline(k.sparkline)
            }) +
            carteKpi({
                label: "Créés aujourd'hui",
                valeur: k.aujourdhui,
                icone: 'calendar',
                accent: c('--ueb-chart-3'),
                wash: 'rgba(47,111,143,.12)',
                pied: delta(k.aujourdhui, k.hier) + '<span>vs hier</span>'
            }) +
            carteKpi({
                label: 'Moyenne par jour',
                valeur: k.moyenneJour,
                icone: 'trend',
                accent: c('--ueb-chart-5'),
                wash: 'rgba(92,138,58,.14)',
                pied: '<span>' + nf(k.semaine) + ' dossiers sur 7 jours</span>'
            }) +
            carteKpi({
                label: 'Part de candidates',
                valeur: k.partFemmes,
                suffixe: '%',
                icone: 'users',
                accent: c('--ueb-chart-2'),
                wash: c('--ueb-accent-wash'),
                pied: '<span>' + nf(k.femmes) + ' F · ' + nf(k.hommes) + ' H</span>',
                barre: k.partFemmes
            });

        if (k.topFaculte) {
            html += carteKpi({
                label: 'Faculté la plus demandée',
                valeur: k.topFacultePart,
                suffixe: '%',
                icone: 'building',
                accent: c('--ueb-chart-4'),
                wash: 'rgba(138,75,42,.13)',
                pied: '<span>' + esc(k.topFaculte) + '</span>',
                barre: k.topFacultePart
            });
        }

        grid.innerHTML = html;

        // Lance les compteurs après insertion dans le DOM.
        grid.querySelectorAll('[data-count]').forEach(function (el) {
            animerNombre(el, el.dataset.count, el.dataset.suffixe || '');
        });

        // Longueur réelle de chaque sparkline, posée AVANT que l'animation
        // de tracé ne démarre (elle a 160 ms de retard) : sans cette mesure,
        // stroke-dashoffset partirait d'une valeur approximative et la
        // courbe apparaîtrait déjà à moitié dessinée.
        grid.querySelectorAll('.admin-kpi-spark .spark-line').forEach(function (path) {
            var len = path.getTotalLength ? path.getTotalLength() : 200;
            path.style.setProperty('--ueb-len', Math.ceil(len));
        });

        // Les barres partent de scaleX(0) : il faut que cet état soit peint
        // une fois avant de viser la valeur finale, d'où le rAF.
        requestAnimationFrame(function () {
            grid.querySelectorAll('.admin-kpi-bar span').forEach(function (el) {
                var pct = Math.min(100, Number(el.dataset.width) || 0);
                el.style.setProperty('--ueb-part', pct / 100);
            });
        });
    }

    function skeletonsKpi() {
        var grid = $('admin-kpi-grid');
        if (!grid) return;
        grid.innerHTML = new Array(5).fill('<div class="admin-skeleton admin-skeleton--kpi"></div>').join('');
    }

    /* ================================================================
       LISTE DES DOSSIERS
       ================================================================ */
    var currentPage = 1;
    var searchTimeout = null;
    var tri = { orderby: 'date_creation', order: 'desc' };

    var COLONNES = [
        { cle: 'numero_dossier', titre: 'N° dossier', classe: 'cell-dossier' },
        { cle: 'nom',            titre: 'Nom',        classe: 'cell-nom' },
        { cle: 'prenom',         titre: 'Prénom',     classe: '' },
        { cle: 'sexe',           titre: 'Sexe',       classe: '' },
        { cle: 'faculte',        titre: 'Faculté',    classe: '' },
        { cle: 'filiere',        titre: 'Filière',    classe: '' },
        { cle: 'date_creation',  titre: 'Déposé le',  classe: 'cell-date' }
    ];

    function formatDate(iso) {
        if (!iso) return '';
        var d = new Date(iso.replace(' ', 'T'));
        if (isNaN(d.getTime())) return iso;
        return d.toLocaleDateString('fr-FR') + ' · ' +
               d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    }

    function etat(icone_, titre, texte, variante) {
        return '<div class="admin-state ' + (variante || '') + '">' +
               '<span class="admin-state-ico">' + icone(icone_) + '</span>' +
               '<h3>' + esc(titre) + '</h3><p>' + esc(texte) + '</p></div>';
    }

    function skeletonsListe() {
        var container = $('admin-liste-container');
        if (!container) return;
        var lignes = new Array(8).fill('<div class="admin-skeleton admin-skeleton--row"></div>').join('');
        container.innerHTML = '<div class="admin-table-wrap" style="padding:1rem">' + lignes + '</div>';
    }

    function renderListe(data) {
        var container = $('admin-liste-container');
        var countEl   = $('admin-results-count');
        var pagEl     = $('admin-pagination');

        if (!data) {
            container.innerHTML = '<div class="admin-table-wrap">' +
                etat('alert', 'Chargement impossible',
                     "Les dossiers n'ont pas pu être récupérés. Vérifiez votre connexion puis réessayez.",
                     'admin-state--error') + '</div>';
            countEl.textContent = '';
            pagEl.innerHTML = '';
            return;
        }

        countEl.textContent = nf(data.total) + ' dossier' + (data.total !== 1 ? 's' : '');

        if (!data.rows.length) {
            var aDesFiltres = filtresActifs().length > 0 || ($('admin-recherche').value || '') !== '';
            container.innerHTML = '<div class="admin-table-wrap">' +
                etat('inbox',
                     aDesFiltres ? 'Aucun résultat' : 'Aucun dossier pour le moment',
                     aDesFiltres
                        ? 'Aucun dossier ne correspond à ces critères. Élargissez ou réinitialisez les filtres.'
                        : 'Les préinscriptions déposées depuis le formulaire public apparaîtront ici.') +
                '</div>';
            pagEl.innerHTML = '';
            return;
        }

        var thead = COLONNES.map(function (col) {
            var estTri = tri.orderby === col.cle;
            var ariaSort = estTri ? (tri.order === 'asc' ? 'ascending' : 'descending') : 'none';
            return '<th aria-sort="' + ariaSort + '">' +
                   '<button type="button" class="admin-th-sort" data-sort="' + col.cle + '">' +
                   esc(col.titre) + icone('sort', 'admin-icon--sm') + '</button></th>';
        }).join('') + '<th><span class="admin-sr-only">Actions</span></th>';

        var tbody = data.rows.map(function (row, index) {
            var sexe = row.sexe === 'M' ? '<span class="admin-pill admin-pill--m">M</span>'
                     : row.sexe === 'F' ? '<span class="admin-pill admin-pill--f">F</span>'
                     : '<span class="admin-pill">—</span>';

            // --i porte le rang de la ligne : le CSS en déduit son retard
            // d'entrée dans la cascade.
            return '<tr style="--i:' + index + '">' +
                '<td class="cell-dossier">' + esc(row.numero_dossier) + '</td>' +
                '<td class="cell-nom">' + esc(row.nom) + '</td>' +
                '<td>' + esc(row.prenom) + '</td>' +
                '<td>' + sexe + '</td>' +
                '<td>' + esc(row.faculte || '—') + '</td>' +
                '<td>' + esc(row.filiere || '—') + '</td>' +
                '<td class="cell-date">' + esc(formatDate(row.date_creation)) + '</td>' +
                '<td class="cell-actions">' +
                    '<button type="button" class="admin-btn-voir" data-numero="' + esc(row.numero_dossier) + '">' +
                    'Voir' + icone('arrow-right', 'admin-icon--sm') + '</button>' +
                '</td></tr>';
        }).join('');

        container.innerHTML =
            '<div class="admin-table-wrap"><div class="admin-table-scroll">' +
            '<table class="admin-table"><thead><tr>' + thead + '</tr></thead>' +
            '<tbody>' + tbody + '</tbody></table></div></div>';

        // Pagination
        var pag = '';
        if (data.nb_pages > 1) {
            pag += '<button type="button" class="admin-page-btn" data-page="' + (data.page - 1) + '"' +
                   (data.page <= 1 ? ' disabled' : '') + '>' + icone('arrow-left', 'admin-icon--sm') + 'Précédent</button>';
            pag += '<span class="admin-page-info">Page ' + data.page + ' sur ' + data.nb_pages + '</span>';
            pag += '<button type="button" class="admin-page-btn" data-page="' + (data.page + 1) + '"' +
                   (data.page >= data.nb_pages ? ' disabled' : '') + '>Suivant' + icone('arrow-right', 'admin-icon--sm') + '</button>';
        }
        pagEl.innerHTML = pag;
    }

    // Pagination et tri : délégation, le tableau étant reconstruit à chaque rendu.
    document.addEventListener('click', function (e) {
        var pageBtn = e.target.closest('.admin-page-btn');
        if (pageBtn && !pageBtn.disabled) {
            currentPage = parseInt(pageBtn.dataset.page, 10);
            loadListe();
            document.querySelector('.admin-liste-toolbar').scrollIntoView({
                behavior: reducedMotion ? 'auto' : 'smooth', block: 'nearest'
            });
            return;
        }

        var sortBtn = e.target.closest('[data-sort]');
        if (sortBtn) {
            var cle = sortBtn.dataset.sort;
            if (tri.orderby === cle) {
                tri.order = tri.order === 'asc' ? 'desc' : 'asc';
            } else {
                tri.orderby = cle;
                // Les dates démarrent en décroissant (le plus récent d'abord),
                // le texte en croissant : c'est ce qu'on attend spontanément.
                tri.order = cle === 'date_creation' ? 'desc' : 'asc';
            }
            currentPage = 1;
            loadListe();
        }
    });

    function loadListe() {
        skeletonsListe();

        var params = collectFilters();
        params.recherche = $('admin-recherche').value || '';
        params.page = currentPage;
        params.orderby = tri.orderby;
        params.order = tri.order;

        return ajax('ueb_admin_get_dossiers', params).then(renderListe);
    }

    var searchInput = $('admin-recherche');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            if (searchTimeout) clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function () {
                currentPage = 1;
                loadListe();
            }, 350);
        });
    }

    /* ================================================================
       MODALE DE DÉTAIL
       ================================================================ */
    var modal = $('admin-detail-modal');
    var dernierDeclencheur = null;

    function ouvrirModal() {
        if (!modal) return;
        modal.hidden = false;
        var fermer = modal.querySelector('[data-close-modal]');
        if (fermer) fermer.focus();
        document.body.style.overflow = 'hidden';
    }

    function fermerModal() {
        if (!modal || modal.hidden) return;

        var termine = function () {
            modal.classList.remove('is-closing');
            modal.hidden = true;
            document.body.style.overflow = '';
            // Le focus revient sur le bouton qui a ouvert la fiche.
            if (dernierDeclencheur) dernierDeclencheur.focus();
        };

        if (reducedMotion) {
            termine();
            return;
        }

        // Laisse l'animation de sortie se jouer avant de masquer la boîte.
        modal.classList.add('is-closing');
        setTimeout(termine, 150);
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-close-modal]')) fermerModal();

        var btn = e.target.closest('.admin-btn-voir');
        if (!btn) return;

        dernierDeclencheur = btn;
        var corps = $('admin-detail-body');
        $('admin-detail-title').textContent = 'Dossier ' + btn.dataset.numero;
        $('admin-detail-sub').textContent = 'Chargement…';
        corps.innerHTML = '<div class="admin-skeleton" style="height:180px"></div>';
        ouvrirModal();

        ajax('ueb_admin_get_dossier_detail', { numero_dossier: btn.dataset.numero }).then(function (d) {
            if (!d) {
                $('admin-detail-sub').textContent = '';
                corps.innerHTML = etat('alert', 'Dossier introuvable',
                    "Ce dossier n'a pas pu être chargé.", 'admin-state--error');
                return;
            }

            $('admin-detail-title').textContent = (d.prenom || '') + ' ' + (d.nom || '');
            $('admin-detail-sub').textContent = 'Dossier n° ' + d.numero_dossier;

            function groupe(titre, lignes) {
                var dl = lignes.filter(function (l) { return l[1]; })
                    .map(function (l) {
                        return '<dt>' + esc(l[0]) + '</dt><dd>' + esc(l[1]) + '</dd>';
                    }).join('');
                if (!dl) return '';
                return '<section class="admin-detail-group"><h3>' + esc(titre) + '</h3>' +
                       '<dl class="admin-detail-list">' + dl + '</dl></section>';
            }

            corps.innerHTML =
                groupe('Identité', [
                    ['Nom', d.nom], ['Prénom', d.prenom], ['Sexe', d.sexe],
                    ['Nationalité', d.nationalite], ['Statut', d.statut]
                ]) +
                groupe('Formation demandée', [
                    ['Faculté', d.faculte], ['Diplôme', d.diplome], ['Série', d.serie],
                    ['1er choix', d.filiere1], ['2e choix', d.filiere2]
                ]) +
                groupe('Contact', [
                    ['Email', d.email],
                    ['Téléphones', (d.telephones || []).join(', ')],
                    ['Origine', d.origine]
                ]);
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (modal && !modal.hidden) { fermerModal(); return; }
        if (drawer && drawer.classList.contains('is-open')) closeDrawer();
    });

    /* ================================================================
       STATISTIQUES
       ================================================================ */
    function loadStats() {
        return ajax('ueb_admin_get_stats', collectFilters()).then(function (data) {
            if (!data) return;
            renderKpis(data.kpis);
            window.uebAnalytics = data;
            if (window.uebCharts) window.uebCharts.render(data);
        });
    }

    /* ================================================================
       APPLICATION DES FILTRES
       ================================================================ */
    var main = $('admin-main');

    function appliquerFiltres() {
        currentPage = 1;
        updateFilterBadge();
        renderChips();
        skeletonsKpi();

        if (main) main.classList.add('is-refreshing');
        Promise.all([loadListe(), loadStats()]).then(function () {
            if (main) main.classList.remove('is-refreshing');
        });
    }

    function reinitialiser() {
        filterForm.querySelectorAll('select').forEach(function (s) { s.value = ''; });
        $('admin-recherche').value = '';
        fillSelect(selSpecialite, [], '— Choisir faculté et diplôme —', false);
        fillSelect(selFiliere, [], "— Choisir d'abord une faculté —", false);
        fillSelect(selDept, [], "— Choisir d'abord une région —", false);
        fillSelect(selCommune, [], "— Choisir d'abord un département —", false);
        appliquerFiltres();
    }

    var filterForm = $('admin-filter-form');
    filterForm.addEventListener('submit', function (e) {
        e.preventDefault();
        closeDrawer();
        appliquerFiltres();
    });

    var resetBtn = $('admin-filter-reset');
    if (resetBtn) resetBtn.addEventListener('click', reinitialiser);

    /* ================================================================
       EXPORT CSV
       ================================================================ */
    var exportBtn = $('admin-export');
    if (exportBtn) {
        exportBtn.addEventListener('click', function () {
            var params = collectFilters();
            params.action = 'ueb_admin_export_csv';
            params.nonce = CFG.nonce;
            params.recherche = $('admin-recherche').value || '';
            params.orderby = tri.orderby;
            params.order = tri.order;

            // Navigation directe : c'est le navigateur qui doit recevoir les
            // en-têtes de téléchargement, pas fetch().
            window.location.href = CFG.ajax_url + '?' + new URLSearchParams(params).toString();
        });
    }

    /* ================================================================
       TIROIR DE FILTRES
       ================================================================ */
    var drawer   = $('admin-filter-drawer');
    var overlay  = $('admin-filter-overlay');
    var toggle   = $('admin-filter-toggle');
    var closeBtn = $('admin-filter-close');
    var badge    = $('admin-filter-count');

    function openDrawer() {
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        if (toggle) toggle.setAttribute('aria-expanded', 'true');
        overlay.hidden = false;
        requestAnimationFrame(function () { overlay.classList.add('is-visible'); });
        var premier = drawer.querySelector('select, button');
        if (premier) premier.focus();
    }

    function closeDrawer() {
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
            toggle.focus();
        }
        overlay.classList.remove('is-visible');
        // Attend la fin du fondu avant de retirer l'overlay du flux.
        setTimeout(function () { overlay.hidden = true; }, reducedMotion ? 0 : 220);
    }

    if (toggle) toggle.addEventListener('click', openDrawer);
    if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
    if (overlay) overlay.addEventListener('click', closeDrawer);

    function updateFilterBadge() {
        var actifs = filtresActifs().length;
        if (badge) {
            badge.textContent = actifs;
            badge.hidden = actifs === 0;
        }
    }

    /* ================================================================
       ONGLETS
       ================================================================ */
    var tabs   = document.querySelectorAll('.admin-tab-btn');
    var panels = document.querySelectorAll('.admin-tab-panel');
    var pageTitle = $('admin-page-title');

    // Ordre des onglets dans la navigation : sert à déduire le sens du
    // glissement (on avance vers la droite, on revient vers la gauche).
    var ORDRE_ONGLETS = ['stats', 'liste'];
    var ongletCourant = 'stats';

    function activerOnglet(cible) {
        var depart  = ORDRE_ONGLETS.indexOf(ongletCourant);
        var arrivee = ORDRE_ONGLETS.indexOf(cible);
        var sens    = arrivee > depart ? '18px' : '-18px';
        ongletCourant = cible;

        tabs.forEach(function (t) {
            var actif = t.dataset.tab === cible;
            t.classList.toggle('active', actif);
            t.setAttribute('aria-selected', actif ? 'true' : 'false');
        });
        panels.forEach(function (p) {
            var actif = p.id === 'admin-tab-' + cible;
            if (actif) p.style.setProperty('--ueb-from', sens);
            p.classList.toggle('active', actif);
        });
        if (pageTitle) {
            pageTitle.textContent = cible === 'liste' ? 'Dossiers' : "Vue d'ensemble";
        }

        var url = new URL(window.location.href);
        url.searchParams.set('onglet', cible);
        window.history.replaceState({}, '', url);

        // Chart.js ne recalcule pas ses dimensions dans un conteneur masqué :
        // on force un redessin au retour sur la vue d'ensemble.
        if (cible === 'stats' && window.uebCharts) {
            requestAnimationFrame(function () { window.uebCharts.render(); });
        }
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () { activerOnglet(tab.dataset.tab); });
    });

    /* ================================================================
       CHARGEMENT INITIAL
       ================================================================ */
    var ongletInitial = new URL(window.location.href).searchParams.get('onglet');
    if (ongletInitial === 'liste') activerOnglet('liste');

    updateFilterBadge();
    renderChips();
    loadListe();
    loadStats();

}());
