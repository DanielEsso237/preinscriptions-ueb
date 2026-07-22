(function () {
    'use strict';

    if (typeof window.uebAdminDashboard === 'undefined') return;
    var CFG = window.uebAdminDashboard;

    /* ================================================================
       APPEL AJAX GÉNÉRIQUE
       ================================================================ */
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
       HELPER : peupler un <select> depuis un tableau {id, libelle}
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

    /* ================================================================
       PEUPLEMENT INITIAL DES FILTRES — depuis CFG.refs, déjà fourni côté
       PHP via wp_localize_script : AUCUN appel réseau nécessaire ici.
       ================================================================ */
    var refs = CFG.refs || {};

    fillSelect(document.getElementById('filter-faculte'), refs.facultes, 'Toutes', true);
    fillSelect(document.getElementById('filter-diplome_admission'), refs.diplomes, 'Tous', true);
    fillSelect(document.getElementById('filter-type_formation'), refs.types_formation, 'Tous', true);
    fillSelect(document.getElementById('filter-niveau_lmd'), refs.niveaux_lmd, 'Tous', true);
    fillSelect(document.getElementById('filter-mention'), refs.mentions, 'Toutes', true);
    fillSelect(document.getElementById('filter-statut_etudiant'), refs.statuts_etudiant, 'Tous', true);
    fillSelect(document.getElementById('filter-sexe'), refs.sexes, 'Tous', true);
    fillSelect(document.getElementById('filter-handicap'), refs.handicaps, 'Tous', true);
    fillSelect(document.getElementById('filter-nationalite'), refs.nationalites, 'Toutes', true);
    fillSelect(document.getElementById('filter-premiere_langue'), refs.langues, 'Toutes', true);
    fillSelect(document.getElementById('filter-situation_matrimoniale'), refs.situations_matrimoniales, 'Toutes', true);
    fillSelect(document.getElementById('filter-statut_socio_professionnel'), refs.statuts_socio, 'Tous', true);
    fillSelect(document.getElementById('filter-region_origine'), refs.regions, 'Toutes', true);
    fillSelect(document.getElementById('filter-sport_prefere'), refs.sports, 'Tous', true);
    fillSelect(document.getElementById('filter-art_pratique'), refs.arts, 'Tous', true);

    // Séries et filières dépendent de faculté (+diplôme pour la série) : pas
    // de liste à l'avance dans refs, désactivés tant que la faculté n'est pas choisie.
    var selFaculte    = document.getElementById('filter-faculte');
    var selDiplome    = document.getElementById('filter-diplome_admission');
    var selSpecialite = document.getElementById('filter-specialite_diplome');
    var selFiliere    = document.getElementById('filter-filiere');
    var selRegion     = document.getElementById('filter-region_origine');
    var selDept       = document.getElementById('filter-departement_origine');
    var selCommune    = document.getElementById('filter-commune_origine');

    function updateSpecialite() {
        var facId = selFaculte.value, dipId = selDiplome.value;
        if (!facId || !dipId) {
            fillSelect(selSpecialite, [], '— Choisir faculté et diplôme —', false);
            return;
        }
        fillSelect(selSpecialite, [], 'Chargement…', false);
        ajax('ueb_admin_get_specialites', { faculte_id: facId, diplome_id: dipId }).then(function (data) {
            fillSelect(selSpecialite, data, 'Toutes', true);
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
       COLLECTE DES FILTRES ACTIFS
       ================================================================ */
    var FILTER_IDS = [
        'faculte', 'diplome_admission', 'specialite_diplome', 'type_formation',
        'filiere', 'niveau_lmd', 'mention', 'statut_etudiant', 'sexe', 'handicap',
        'nationalite', 'premiere_langue', 'situation_matrimoniale',
        'statut_socio_professionnel', 'region_origine', 'departement_origine',
        'commune_origine', 'sport_prefere', 'art_pratique'
    ];

    function collectFilters() {
        var out = {};
        FILTER_IDS.forEach(function (key) {
            var el = document.getElementById('filter-' + key);
            out[key] = el ? el.value : '';
        });
        return out;
    }

    /* ================================================================
       ONGLET LISTE : recherche + pagination
       ================================================================ */
    var currentPage = 1;
    var searchTimeout = null;

    function formatDate(iso) {
        if (!iso) return '';
        var d = new Date(iso.replace(' ', 'T'));
        if (isNaN(d.getTime())) return iso;
        return d.toLocaleDateString('fr-FR') + ' ' + d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    }

    function renderListe(data) {
        var container = document.getElementById('admin-liste-container');
        var countEl   = document.getElementById('admin-results-count');
        var pagEl     = document.getElementById('admin-pagination');
        if (!data) {
            container.innerHTML = '<p class="admin-empty">Erreur lors du chargement.</p>';
            countEl.textContent = '';
            pagEl.innerHTML = '';
            return;
        }

        countEl.textContent = data.total + ' dossier' + (data.total !== 1 ? 's' : '') + ' trouvé' + (data.total !== 1 ? 's' : '');

        if (!data.rows.length) {
            container.innerHTML = '<p class="admin-empty">Aucun dossier ne correspond à ces critères.</p>';
            pagEl.innerHTML = '';
            return;
        }

        var html = '<table class="admin-table"><thead><tr>' +
            '<th>N° Dossier</th><th>Nom</th><th>Prénom</th><th>Sexe</th><th>Faculté</th><th>Filière</th><th>Date</th><th></th>' +
            '</tr></thead><tbody>';

        data.rows.forEach(function (row) {
            html += '<tr>' +
                '<td>' + esc(row.numero_dossier) + '</td>' +
                '<td>' + esc(row.nom) + '</td>' +
                '<td>' + esc(row.prenom) + '</td>' +
                '<td>' + (row.sexe === 'M' ? 'M' : (row.sexe === 'F' ? 'F' : '—')) + '</td>' +
                '<td>' + esc(row.faculte || '—') + '</td>' +
                '<td>' + esc(row.filiere || '—') + '</td>' +
                '<td>' + formatDate(row.date_creation) + '</td>' +
                '<td><button type="button" class="admin-btn-voir" data-numero="' + esc(row.numero_dossier) + '">Voir →</button></td>' +
                '</tr>';
        });

        html += '</tbody></table>';
        container.innerHTML = html;

        // Pagination
        var pag = '';
        if (data.nb_pages > 1) {
            pag += '<button type="button" class="admin-page-btn" data-page="' + (data.page - 1) + '"' + (data.page <= 1 ? ' disabled' : '') + '>← Précédent</button>';
            pag += '<span class="admin-page-info">Page ' + data.page + ' / ' + data.nb_pages + '</span>';
            pag += '<button type="button" class="admin-page-btn" data-page="' + (data.page + 1) + '"' + (data.page >= data.nb_pages ? ' disabled' : '') + '>Suivant →</button>';
        }
        pagEl.innerHTML = pag;

        pagEl.querySelectorAll('.admin-page-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (btn.disabled) return;
                currentPage = parseInt(btn.dataset.page, 10);
                loadListe();
            });
        });
    }

    function esc(str) {
        var div = document.createElement('div');
        div.textContent = str == null ? '' : str;
        return div.innerHTML;
    }

    function loadListe() {
        var container = document.getElementById('admin-liste-container');
        container.innerHTML = '<p class="admin-loading">Chargement…</p>';

        var params = collectFilters();
        params.recherche = document.getElementById('admin-recherche').value || '';
        params.page = currentPage;

        ajax('ueb_admin_get_dossiers', params).then(renderListe);
    }

    var searchInput = document.getElementById('admin-recherche');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            if (searchTimeout) clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function () {
                currentPage = 1;
                loadListe();
            }, 400);
        });
    }

    /* ================================================================
       DÉTAIL D'UN DOSSIER (clic "Voir →")
       ================================================================ */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.admin-btn-voir');
        if (!btn) return;
        ajax('ueb_admin_get_dossier_detail', { numero_dossier: btn.dataset.numero }).then(function (d) {
            if (!d) return;
            var lignes = [
                ['N° Dossier', d.numero_dossier], ['Nom', d.nom], ['Prénom', d.prenom],
                ['Statut', d.statut], ['Faculté', d.faculte], ['Diplôme', d.diplome],
                ['Série', d.serie], ['1er choix filière', d.filiere1], ['2e choix filière', d.filiere2],
                ['Sexe', d.sexe], ['Nationalité', d.nationalite], ['Email', d.email],
                ['Origine', d.origine], ['Téléphones', (d.telephones || []).join(', ')]
            ];
            var msg = lignes.map(function (l) { return l[0] + ' : ' + (l[1] || '—'); }).join('\n');
            alert(msg);
        });
    });

    /* ================================================================
       ONGLET STATISTIQUES
       ================================================================ */
    var statsLoaded = false;

    function loadStats() {
        var params = collectFilters();
        ajax('ueb_admin_get_stats', params).then(function (data) {
            if (!data) return;
            renderKpis(data.chiffres, data.tauxFaculte);
            window.uebAnalytics = {
                parFaculte: data.parFaculte,
                parFiliere: data.parFiliere,
                parRegion: data.parRegion,
                parSexe: data.parSexe,
                faculteSexe: data.faculteSexe,
                evolution: data.evolution
            };
            document.dispatchEvent(new CustomEvent('uebAnalyticsReady'));
        });
    }

    function renderKpis(chiffres, tauxFaculte) {
        var grid = document.getElementById('admin-kpi-grid');
        if (!grid || !chiffres) return;

        var html = '' +
            '<div class="admin-kpi-card"><span class="admin-kpi-value">' + chiffres.total + '</span><span class="admin-kpi-label">Dossiers (filtre actif)</span></div>' +
            '<div class="admin-kpi-card"><span class="admin-kpi-value">' + chiffres.aujourdhui + '</span><span class="admin-kpi-label">Créés aujourd&rsquo;hui</span></div>';

        (tauxFaculte || []).slice(0, 4).forEach(function (t) {
            html += '<div class="admin-kpi-card admin-kpi-card--small"><span class="admin-kpi-value">' + t.pourcentage + '%</span><span class="admin-kpi-label">' + esc(t.label) + '</span></div>';
        });

        grid.innerHTML = html;
    }

    /* ================================================================
       FORMULAIRE DE FILTRES : soumission = recharge liste + stats visibles
       ================================================================ */
    var filterForm = document.getElementById('admin-filter-form');
    filterForm.addEventListener('submit', function (e) {
        e.preventDefault();
        currentPage = 1;
        updateFilterBadge();
        closeDrawer();
        loadListe();
        loadStats();
    });

    var resetBtn = document.getElementById('admin-filter-reset');
    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            filterForm.querySelectorAll('select').forEach(function (s) { s.value = ''; });
            document.getElementById('admin-recherche').value = '';
            fillSelect(selSpecialite, [], '— Choisir faculté et diplôme —', false);
            fillSelect(selFiliere, [], "— Choisir d'abord une faculté —", false);
            fillSelect(selDept, [], "— Choisir d'abord une région —", false);
            fillSelect(selCommune, [], "— Choisir d'abord un département —", false);
            currentPage = 1;
            updateFilterBadge();
            loadListe();
            loadStats();
        });
    }

    /* ================================================================
       TIROIR DE FILTRES : ouverture / fermeture + badge du nombre de
       filtres actifs sur le bouton "Filtres" de la barre du haut.
       ================================================================ */
    var drawer   = document.getElementById('admin-filter-drawer');
    var overlay  = document.getElementById('admin-filter-overlay');
    var toggle   = document.getElementById('admin-filter-toggle');
    var closeBtn = document.getElementById('admin-filter-close');
    var badge    = document.getElementById('admin-filter-count');

    function openDrawer() {
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        overlay.hidden = false;
    }
    function closeDrawer() {
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        overlay.hidden = true;
    }
    if (toggle) toggle.addEventListener('click', openDrawer);
    if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
    if (overlay) overlay.addEventListener('click', closeDrawer);

    function updateFilterBadge() {
        var actifs = FILTER_IDS.filter(function (key) {
            var el = document.getElementById('filter-' + key);
            return el && el.value;
        }).length;
        if (badge) {
            badge.textContent = actifs;
            badge.hidden = actifs === 0;
        }
    }

    /* ================================================================
       TITRE DE LA PAGE : suit l'onglet actif (Vue d'ensemble / Dossiers)
       ================================================================ */
    var pageTitle = document.getElementById('admin-page-title');
    document.querySelectorAll('.admin-sidebar-nav .admin-tab-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (pageTitle) {
                pageTitle.textContent = btn.dataset.tab === 'liste' ? 'Dossiers' : "Vue d'ensemble";
            }
        });
    });

    /* ================================================================
       CHARGEMENT INITIAL
       ================================================================ */
    updateFilterBadge();
    loadListe();
    loadStats();

}());
