/**
 * Page "Gestion des références" (page-references.php) : navigation par
 * table ueb_*, tableau générique, modale d'ajout/modification,
 * suppression, via le registre envoyé par PHP
 * (ueb_admin_ref_get_registry_for_js(), cf. inc/admin-references-functions.php).
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

    /* ================================================================
       THÈME CLAIR / SOMBRE
       Même clé localStorage que le dashboard des dossiers (admin-dashboard.js) :
       un compte qui a accès aux deux pages garde la même préférence.
       Cette page ne charge pas admin-dashboard.js, donc ce bloc est
       nécessairement dupliqué ici (même logique, en plus court).
       ================================================================ */
    var CLE_THEME = 'ueb-admin-theme';

    function themeActif() {
        var force = document.documentElement.getAttribute('data-ueb-theme');
        if (force) return force;
        return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)
            ? 'dark' : 'light';
    }

    function appliquerTheme(mode) {
        document.documentElement.setAttribute('data-ueb-theme', mode);
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
       ================================================================ */
    var cleCourante   = Object.keys(REGISTRY)[0] || '';
    var pageCourante  = 1;
    var rechercheTimeout = null;
    var idEnEdition   = 0; // 0 = création

    /* ================================================================
       NAVIGATION (liste des tables, groupée)
       ================================================================ */
    function renderNav() {
        var nav = $('admin-ref-nav');
        if (!nav) return;

        var groupes = {};
        var ordreGroupes = [];
        Object.keys(REGISTRY).forEach(function (cle) {
            var groupe = REGISTRY[cle].group || 'Autres';
            if (!groupes[groupe]) { groupes[groupe] = []; ordreGroupes.push(groupe); }
            groupes[groupe].push(cle);
        });

        nav.innerHTML = ordreGroupes.map(function (groupe) {
            var boutons = groupes[groupe].map(function (cle) {
                var actif = cle === cleCourante;
                return '<button type="button" class="admin-ref-nav-btn' + (actif ? ' active' : '') + '" data-ref="' + esc(cle) + '">' +
                       esc(REGISTRY[cle].label) + '</button>';
            }).join('');
            return '<div class="admin-ref-nav-group">' +
                   '<span class="admin-ref-nav-heading">' + esc(groupe) + '</span>' + boutons + '</div>';
        }).join('');
    }

    (function attacherNav() {
        var nav = $('admin-ref-nav');
        if (!nav) return;
        nav.addEventListener('click', function (e) {
            var btn = e.target.closest('.admin-ref-nav-btn');
            if (!btn) return;
            if (btn.dataset.ref === cleCourante) return;
            cleCourante = btn.dataset.ref;
            pageCourante = 1;
            var recherche = $('admin-ref-recherche');
            if (recherche) recherche.value = '';
            renderNav();
            chargerListe();
        });
    })();

    /* ================================================================
       TABLEAU
       ================================================================ */
    function libelleColonne(cle, colcfg, row) {
        var val = row[cle];

        if ('select' === colcfg.type) {
            var lib = row[cle + '__libelle'];
            return lib ? esc(lib) : '<span class="admin-ref-empty">—</span>';
        }
        if ('enum' === colcfg.type) {
            var opt = (colcfg.options || []).filter(function (o) { return String(o.id) === String(val); })[0];
            return opt ? esc(opt.libelle) : '<span class="admin-ref-empty">—</span>';
        }
        if (val === null || val === undefined || val === '') {
            return '<span class="admin-ref-empty">—</span>';
        }
        return esc(val);
    }

    function skeletons() {
        var wrap = $('admin-ref-table-wrap');
        if (!wrap) return;
        var lignes = new Array(6).fill('<div class="admin-skeleton admin-skeleton--row"></div>').join('');
        wrap.innerHTML = '<div class="admin-table-wrap" style="padding:1rem">' + lignes + '</div>';
    }

    function etat(icone_, titre, texte, variante) {
        return '<div class="admin-state ' + (variante || '') + '">' +
               '<span class="admin-state-ico">' + icone(icone_) + '</span>' +
               '<h3>' + esc(titre) + '</h3><p>' + esc(texte) + '</p></div>';
    }

    function renderTable(data) {
        var wrap = $('admin-ref-table-wrap');
        var pagEl = $('admin-ref-pagination');
        var cfg = REGISTRY[cleCourante];
        if (!wrap || !cfg) return;

        if (estErreur(data)) {
            wrap.innerHTML = '<div class="admin-table-wrap">' +
                etat('alert', 'Chargement impossible', data.erreur, 'admin-state--error') + '</div>';
            pagEl.innerHTML = '';
            return;
        }

        if (!data.rows.length) {
            wrap.innerHTML = '<div class="admin-table-wrap">' +
                etat('inbox', 'Aucune donnée', 'Aucune ligne dans cette table pour le moment. Utilisez « Ajouter » pour en créer une.') +
                '</div>';
            pagEl.innerHTML = '';
            return;
        }

        var cols = cfg.columns;
        var thead = Object.keys(cols).map(function (cle) {
            return '<th>' + esc(cols[cle].label) + '</th>';
        }).join('') + '<th><span class="admin-sr-only">Actions</span></th>';

        var tbody = data.rows.map(function (row, index) {
            var tds = Object.keys(cols).map(function (cle) {
                return '<td>' + libelleColonne(cle, cols[cle], row) + '</td>';
            }).join('');
            return '<tr style="--i:' + index + '">' + tds +
                '<td class="cell-actions admin-ref-row-actions">' +
                    '<button type="button" class="admin-icon-btn" data-edit="' + row.id + '" aria-label="Modifier">' + icone('edit', 'admin-icon--sm') + '</button>' +
                    '<button type="button" class="admin-icon-btn admin-icon-btn--danger" data-delete="' + row.id + '" aria-label="Supprimer">' + icone('trash', 'admin-icon--sm') + '</button>' +
                '</td></tr>';
        }).join('');

        wrap.innerHTML =
            '<div class="admin-table-wrap"><div class="admin-table-scroll">' +
            '<table class="admin-table"><thead><tr>' + thead + '</tr></thead>' +
            '<tbody>' + tbody + '</tbody></table></div></div>';

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

    function chargerListe() {
        skeletons();
        var recherche = $('admin-ref-recherche');
        return ajax('ueb_admin_ref_list', {
            ref_key: cleCourante,
            recherche: recherche ? recherche.value : '',
            page: pageCourante
        }).then(renderTable);
    }

    document.addEventListener('click', function (e) {
        var pageBtn = e.target.closest('#admin-ref-pagination .admin-page-btn');
        if (pageBtn && !pageBtn.disabled) {
            pageCourante = parseInt(pageBtn.dataset.page, 10);
            chargerListe();
            return;
        }
    });

    var champRecherche = $('admin-ref-recherche');
    if (champRecherche) {
        champRecherche.addEventListener('input', function () {
            if (rechercheTimeout) clearTimeout(rechercheTimeout);
            rechercheTimeout = setTimeout(function () {
                pageCourante = 1;
                chargerListe();
            }, 350);
        });
    }

    /* ================================================================
       MODALE D'AJOUT / MODIFICATION
       ================================================================ */
    var modal = $('admin-ref-modal');
    var dernierDeclencheur = null;

    function ouvrirModal() {
        if (!modal) return;
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        var premier = modal.querySelector('#admin-ref-form-fields input, #admin-ref-form-fields select');
        if (premier) premier.focus();
    }

    function fermerModal() {
        if (!modal || modal.hidden) return;
        var termine = function () {
            modal.classList.remove('is-closing');
            modal.hidden = true;
            document.body.style.overflow = '';
            if (dernierDeclencheur) dernierDeclencheur.focus();
        };
        if (reducedMotion) { termine(); return; }
        modal.classList.add('is-closing');
        setTimeout(termine, 150);
    }

    function champHtml(cle, colcfg, valeur) {
        var id = 'admin-ref-champ-' + cle;
        var requis = colcfg.required ? ' required' : '';
        var label = '<label for="' + id + '">' + esc(colcfg.label) + (colcfg.required ? ' *' : '') + '</label>';

        if ('select' === colcfg.type || 'enum' === colcfg.type) {
            var options = (colcfg.options || []).map(function (o) {
                var selectionne = (valeur !== undefined && valeur !== null && String(valeur) === String(o.id)) ? ' selected' : '';
                return '<option value="' + esc(o.id) + '"' + selectionne + '>' + esc(o.libelle) + '</option>';
            }).join('');
            return '<div class="form-group"><label for="' + id + '">' + esc(colcfg.label) + (colcfg.required ? ' *' : '') + '</label>' +
                   '<select id="' + id + '" name="' + cle + '"' + requis + '>' +
                   '<option value="">— Choisir —</option>' + options + '</select></div>';
        }

        if ('number' === colcfg.type) {
            return '<div class="form-group">' + label +
                   '<input type="number" id="' + id + '" name="' + cle + '" value="' + esc(valeur === null || valeur === undefined ? '' : valeur) + '"' + requis + '></div>';
        }

        return '<div class="form-group">' + label +
               '<input type="text" id="' + id + '" name="' + cle + '" value="' + esc(valeur === null || valeur === undefined ? '' : valeur) + '"' +
               (colcfg.maxlength ? ' maxlength="' + colcfg.maxlength + '"' : '') + requis + '></div>';
    }

    function ouvrirFormulaire(cle, row) {
        var cfg = REGISTRY[cle];
        if (!cfg || !modal) return;

        idEnEdition = row ? row.id : 0;

        $('admin-ref-modal-title').textContent = (row ? 'Modifier — ' : 'Ajouter — ') + cfg.label;
        $('admin-ref-modal-sub').textContent = row ? ('Élément #' + row.id) : '';

        var champs = $('admin-ref-form-fields');
        champs.innerHTML = Object.keys(cfg.columns).map(function (col) {
            return champHtml(col, cfg.columns[col], row ? row[col] : undefined);
        }).join('');

        var erreur = $('admin-ref-form-error');
        erreur.hidden = true;
        erreur.textContent = '';

        ouvrirModal();
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-close-ref-modal]')) { fermerModal(); return; }

        var addBtn = e.target.closest('#admin-ref-add');
        if (addBtn) {
            dernierDeclencheur = addBtn;
            ouvrirFormulaire(cleCourante, null);
            return;
        }

        var editBtn = e.target.closest('[data-edit]');
        if (editBtn) {
            dernierDeclencheur = editBtn;
            ajax('ueb_admin_ref_get', { ref_key: cleCourante, id: editBtn.dataset.edit }).then(function (row) {
                if (estErreur(row)) {
                    window.alert(row.erreur);
                    return;
                }
                ouvrirFormulaire(cleCourante, row);
            });
            return;
        }

        var delBtn = e.target.closest('[data-delete]');
        if (delBtn) {
            var confirmation = window.confirm('Supprimer définitivement cet élément ? Cette action est irréversible.');
            if (!confirmation) return;

            delBtn.disabled = true;
            ajax('ueb_admin_ref_delete', { ref_key: cleCourante, id: delBtn.dataset.delete }).then(function (res) {
                if (estErreur(res)) {
                    window.alert(res.erreur);
                    delBtn.disabled = false;
                    return;
                }
                chargerListe();
            });
        }
    });

    var form = $('admin-ref-form');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var cfg = REGISTRY[cleCourante];
            if (!cfg) return;

            var params = { ref_key: cleCourante, id: idEnEdition };
            Object.keys(cfg.columns).forEach(function (col) {
                var el = $('admin-ref-champ-' + col);
                params['champs[' + col + ']'] = el ? el.value : '';
            });

            var boutonEnvoyer = form.querySelector('button[type="submit"]');
            if (boutonEnvoyer) boutonEnvoyer.disabled = true;

            ajax('ueb_admin_ref_save', params).then(function (res) {
                if (boutonEnvoyer) boutonEnvoyer.disabled = false;

                if (estErreur(res)) {
                    var erreur = $('admin-ref-form-error');
                    erreur.textContent = res.erreur;
                    erreur.hidden = false;
                    return;
                }

                fermerModal();
                chargerListe();
            });
        });
    }

    /* ================================================================
       CHARGEMENT INITIAL
       ================================================================ */
    renderNav();
    chargerListe();

}());
