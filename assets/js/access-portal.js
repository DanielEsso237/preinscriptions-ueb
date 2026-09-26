/**
 * Espace de gestion — comportements du portail.
 *
 * AUCUNE décision de sécurité ne repose sur ce fichier : le serveur a déjà
 * filtré la portée et les permissions avant de répondre. Ce qui est fait ici
 * est du confort de lecture — animer, trier, paginer, ouvrir un détail.
 *
 * Timing repris du tableau de bord : entrées de 420 ms, cascade de 45 ms
 * plafonnée à 8 éléments, barres de 650 ms, courbes de 850 ms, compteurs de
 * 650 ms. prefers-reduced-motion rend immédiatement l'état final.
 */
(function () {
    'use strict';

    const cfg = window.uebPortal;
    if (!cfg) return;

    const $ = id => document.getElementById(id);
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    const STAGGER = 45;
    const STAGGER_MAX = 8;

    const nombre = v => Number(v || 0).toLocaleString('fr-FR');
    /* « 24 sept. 2026 » plutôt que « 2026-09-24 » : c'est une date lue par
       des gestionnaires, pas une clé de tri. */
    const dateCourte = v => {
        const d = new Date(String(v || '').replace(' ', 'T'));
        return isNaN(d) ? '—' : d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
    };
    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
    const icone = (nom, cls) => `<svg class="admin-icon${cls ? ' ' + cls : ''}" aria-hidden="true" focusable="false"><use href="#ueb-i-${nom}"/></svg>`;
    const delai = i => `--delay:${Math.min(i, STAGGER_MAX) * STAGGER}ms`;
    const token = nom => getComputedStyle(document.documentElement).getPropertyValue(nom).trim();
    const champs = form => new URLSearchParams(new FormData(form));
    const donneesJSON = id => { const n = $(id); return n ? JSON.parse(n.textContent) : null; };

    let graphiques = [];
    /* Déclarée ici pour que le sélecteur d'établissement, plus haut dans le
       fichier, puisse la rappeler après un changement de portée. */
    let rechargerDossiers = null;

    function detruireGraphiques() {
        graphiques.forEach(g => g.destroy());
        graphiques = [];
    }

    /* ------------------------------------------------------------------ */
    /* Transport                                                           */
    /* ------------------------------------------------------------------ */
    async function api(action, valeurs = {}, nonce = cfg.nonce) {
        const body = new URLSearchParams(valeurs);
        body.set('action', action);
        body.set('nonce', nonce);
        const reponse = await fetch(cfg.ajax, { method: 'POST', credentials: 'same-origin', body });
        let resultat;
        try {
            resultat = await reponse.json();
        } catch (_) {
            throw new Error('La session a expiré ou le service est indisponible. Rechargez la page.');
        }
        if (!reponse.ok || !resultat.success) {
            throw new Error(resultat.data?.message || 'Accès refusé. Rechargez la page et vérifiez vos accès.');
        }
        return resultat.data;
    }

    function message(id, texte, type) {
        const n = $(id);
        if (!n) return;
        n.textContent = '';
        if (texte) {
            n.insertAdjacentHTML('beforeend', icone(type === 'ok' ? 'check' : 'alert', 'admin-icon--sm'));
            n.insertAdjacentHTML('beforeend', `<span>${esc(texte)}</span>`);
        }
        n.classList.toggle('portal-notice--ok', type === 'ok');
        n.classList.toggle('portal-notice--error', type !== 'ok');
        n.hidden = !texte;
    }

    /* Un message qui survit au rechargement déclenché par une écriture. */
    function enregistre(texte) {
        try { sessionStorage.setItem('ueb-portal-notice', texte); } catch (_) {}
        window.location.reload();
    }
    try {
        const memo = sessionStorage.getItem('ueb-portal-notice');
        if (memo) { message('portal-message', memo, 'ok'); sessionStorage.removeItem('ueb-portal-notice'); }
    } catch (_) {}

    function occupe(form, etat) {
        form.setAttribute('aria-busy', String(etat));
        form.querySelectorAll('button[type="submit"]').forEach(b => { b.disabled = etat; });
    }

    /* ------------------------------------------------------------------ */
    /* Thème clair / sombre — même clé que le tableau de bord              */
    /* ------------------------------------------------------------------ */
    const bascule = $('admin-theme-toggle');
    if (bascule) {
        bascule.addEventListener('click', () => {
            const racine = document.documentElement;
            const suivant = racine.getAttribute('data-ueb-theme') === 'dark' ? 'light' : 'dark';
            racine.setAttribute('data-ueb-theme', suivant);
            try { localStorage.setItem('ueb-admin-theme', suivant); } catch (_) {}
            // Chart.js peint dans un canvas : les couleurs du thème ne suivent
            // pas toutes seules, il faut redessiner.
            if (dernieresDonnees) peindreTableauDeBord(dernieresDonnees, false);
        });
    }

    /* Révélation d'un mot de passe. */
    document.querySelectorAll('[data-reveal]').forEach(bouton => {
        bouton.addEventListener('click', () => {
            const champ = bouton.parentElement.querySelector('input');
            const visible = champ.type === 'text';
            champ.type = visible ? 'password' : 'text';
            bouton.setAttribute('aria-label', visible ? 'Afficher le mot de passe' : 'Masquer le mot de passe');
            champ.focus();
        });
    });

    /* ------------------------------------------------------------------ */
    /* Compteurs et courbes                                                */
    /* ------------------------------------------------------------------ */
    function animerCompteurs(racine, animer) {
        racine.querySelectorAll('[data-count]').forEach(n => {
            const cible = Number(n.dataset.count);
            if (!animer || reduced.matches) { n.textContent = nombre(cible); return; }
            const depart = performance.now();
            (function pas(t) {
                const p = Math.min(1, (t - depart) / 650);
                n.textContent = nombre(Math.round(cible * (1 - Math.pow(1 - p, 3))));
                if (p < 1 && n.isConnected) requestAnimationFrame(pas);
            })(depart);
        });
    }

    const compteur = v => `<span data-count="${Number(v)}">${nombre(v)}</span>`;

    /**
     * Courbe compacte. `aire` ajoute un remplissage dégradé sous le tracé :
     * réservé au chiffre dominant, où la surface aide à lire le volume.
     */
    function courbe(valeurs, { largeur = 280, hauteur = 46, aire = false } = {}) {
        if (!valeurs || !valeurs.length) return '';
        const max = Math.max(1, ...valeurs);
        const pas = largeur / Math.max(1, valeurs.length - 1);
        const y = v => (hauteur - 4 - (v / max) * (hauteur - 10)).toFixed(1);
        const points = valeurs.map((v, i) => `${i ? 'L' : 'M'}${(i * pas).toFixed(1)},${y(v)}`).join(' ');
        const id = 'pg' + Math.random().toString(36).slice(2, 8);
        const remplissage = aire
            ? `<defs><linearGradient id="${id}" x1="0" x2="0" y1="0" y2="1">
                   <stop offset="0%" stop-color="currentColor" stop-opacity=".22"/>
                   <stop offset="100%" stop-color="currentColor" stop-opacity="0"/>
               </linearGradient></defs>
               <path d="${points} L${largeur},${hauteur} L0,${hauteur} Z" fill="url(#${id})" stroke="none"/>`
            : '';
        return `<svg class="${aire ? 'portal-headline-spark' : 'portal-spark portal-est-spark'}"
                     viewBox="0 0 ${largeur} ${hauteur}" preserveAspectRatio="none" aria-hidden="true">
                    ${remplissage}
                    <path d="${points}" fill="none" stroke="currentColor" stroke-width="2"
                          stroke-linecap="round" stroke-linejoin="round"
                          vector-effect="non-scaling-stroke" pathLength="1"/>
                </svg>`;
    }

    /** Variation en pourcentage : icône + signe + couleur, jamais la couleur seule. */
    function variation(pourcent, suffixe = 'cette semaine') {
        if (pourcent === null || pourcent === undefined) {
            return `<span class="portal-delta--flat">${icone('minus', 'admin-icon--sm')}Pas de comparaison possible</span>`;
        }
        const v = Number(pourcent);
        const sens = v > 0 ? 'up' : (v < 0 ? 'down' : 'flat');
        const signe = v > 0 ? '+' : '';
        const ic = v > 0 ? 'trend' : (v < 0 ? 'trend-down' : 'minus');
        return `<span class="portal-delta--${sens}">${icone(ic, 'admin-icon--sm')}${signe}${nombre(v)} % ${esc(suffixe)}</span>`;
    }

    function classement(lignes) {
        if (!lignes.length) {
            return `<div class="portal-empty">${icone('inbox')}<strong>Rien à classer</strong><p>Aucune préinscription enregistrée pour le moment.</p></div>`;
        }
        const max = Math.max(1, ...lignes.map(l => Number(l.total)));
        return `<div class="portal-rank">${lignes.map((l, i) => `
            <div class="portal-rank-row">
                <div class="portal-rank-line">
                    <span class="portal-rank-name">
                        <span class="portal-rank-sigle">${esc(l.code || l.label || '—')}</span>
                        ${l.code && l.nom_fr ? `<span class="portal-rank-full">${esc(l.nom_fr)}</span>` : ''}
                    </span>
                    <span class="portal-rank-value">${nombre(l.total)}</span>
                </div>
                <div class="portal-track"><span style="width:${(Number(l.total) / max * 100).toFixed(1)}%;${delai(i)}"></span></div>
            </div>`).join('')}</div>`;
    }

    /* ------------------------------------------------------------------ */
    /* Tableau de bord                                                     */
    /* ------------------------------------------------------------------ */
    const racineTdb = $('portal-dashboard');
    let dernieresDonnees = null;
    let dernierChargement = 0;

    function courbeEvolution(data) {
        const canvas = $('portal-evolution');
        if (!canvas || !window.Chart) return;
        const ctx = canvas.getContext('2d');
        const couleur = token('--ueb-chart-1') || '#1a4a2e';
        const degrade = ctx.createLinearGradient(0, 0, 0, 240);
        degrade.addColorStop(0, couleur + '3d');
        degrade.addColorStop(1, couleur + '00');
        graphiques.push(new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels.map(j => new Date(j + 'T12:00:00').toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })),
                datasets: [{
                    data: data.evolution, borderColor: couleur, backgroundColor: degrade,
                    borderWidth: 2.5, pointRadius: 0, pointHoverRadius: 4, fill: true, tension: .35
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                animation: reduced.matches ? false : { duration: 850, easing: 'easeOutQuart' },
                plugins: { legend: { display: false }, tooltip: { displayColors: false } },
                interaction: { intersect: false, mode: 'index' },
                scales: {
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 6, font: { family: 'Inter', size: 11 }, color: token('--ueb-chart-tick') } },
                    y: { beginAtZero: true, border: { display: false },
                         ticks: { precision: 0, font: { family: 'Inter', size: 11 }, color: token('--ueb-chart-tick') },
                         grid: { color: token('--ueb-chart-grid') } }
                }
            }
        }));
    }

    function beigneStatuts(lignes) {
        const canvas = $('portal-statuts');
        if (!canvas || !window.Chart) return;
        graphiques.push(new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: lignes.map(l => l.label === 'soumis' ? 'Soumis' : 'Brouillons'),
                datasets: [{
                    data: lignes.map(l => Number(l.total)),
                    backgroundColor: lignes.map(l => l.label === 'soumis' ? token('--ueb-chart-1') : token('--ueb-chart-2')),
                    borderWidth: 0, hoverOffset: 5
                }]
            },
            options: {
                maintainAspectRatio: false, cutout: '74%',
                animation: reduced.matches ? false : { duration: 700 },
                plugins: { legend: { display: false }, tooltip: { displayColors: false } }
            }
        }));
    }

    function peindreTableauDeBord(data, animer) {
        detruireGraphiques();
        const globale = racineTdb.dataset.view === 'overview';
        const t = data.totals;
        const varGlobale = t.previous ? Math.round(100 * (t.week - t.previous) / t.previous) : null;

        /* 1. Le chiffre dominant. Un seul nombre porte la page. */
        const satellites = globale
            ? [['Aujourd’hui', t.today], ['Cette semaine', t.week], ['Ce mois-ci', t.month], ['Établissements', data.establishments.length]]
            : [['Aujourd’hui', t.today], ['Cette semaine', t.week], ['Ce mois-ci', t.month]];

        let html = `
        <section class="portal-headline portal-reveal">
            <div>
                <span class="portal-headline-label">Préinscrits${globale ? '' : ' dans cet établissement'}</span>
                <strong class="portal-headline-value">${compteur(t.total)}</strong>
                <span class="portal-headline-delta">${variation(varGlobale)}</span>
            </div>
            <div>
                ${data.evolution ? courbe(data.evolution, { largeur: 420, hauteur: 78, aire: true }) : ''}
                ${data.evolution ? '<span class="portal-headline-spark-caption">30 derniers jours</span>' : ''}
            </div>
            <div class="portal-satellites">
                ${satellites.map(s => `
                    <div class="portal-satellite">
                        <span class="portal-satellite-value">${compteur(s[1])}</span>
                        <span class="portal-satellite-label">${esc(s[0])}</span>
                    </div>`).join('')}
            </div>
        </section>`;

        if (data.unassigned) {
            html += `<div class="portal-notice">${icone('info', 'admin-icon--sm')}
                <span>${nombre(data.unassigned)} dossier(s) sans établissement renseigné sont comptés dans le total.</span></div>`;
        }

        /* 2. Une carte par établissement de la portée. */
        if (globale) {
            html += `<div class="portal-section">
                <div><h2>Chaque établissement</h2><p>Cliquez une carte pour ouvrir son détail.</p></div>
            </div>`;
            if (data.establishments.length) {
                html += `<div class="portal-est-grid">${data.establishments.map((e, i) => {
                    const balise = e.url ? 'a' : 'article';
                    const inactif = Number(e.actif) ? '' : ' portal-est-card--off';
                    const tendance = e.variation === null
                        ? `<span class="portal-delta--flat">${icone('minus', 'admin-icon--sm')}${e.week ? '+' + nombre(e.week) + ' cette semaine' : 'Aucun dépôt'}</span>`
                        : variation(e.variation, 'cette sem.');
                    return `<${balise} ${e.url ? `href="${esc(e.url)}"` : ''} class="portal-est-card${inactif} portal-reveal" style="${delai(i + 1)}">
                        <div class="portal-est-head">
                            <span class="portal-est-id">
                                ${e.logo_url
                                    ? `<span class="portal-est-logo"><img src="${esc(e.logo_url)}" width="38" height="38" alt="" loading="lazy" decoding="async"></span>
                                       <span class="portal-est-code">${esc(e.code)}</span>`
                                    : `<span class="portal-est-sigle">${esc(e.code)}</span>`}
                            </span>
                            ${e.url ? `<span class="portal-est-go">${icone('chevron-right', 'admin-icon--sm')}</span>` : ''}
                        </div>
                        <span class="portal-est-name">${esc(e.nom_fr)}${Number(e.actif) ? '' : '<span class="portal-est-off">Inactif</span>'}</span>
                        <span class="portal-est-value">${compteur(e.total)}</span>
                        <span class="portal-est-delta">${tendance}</span>
                        ${courbe(e.series)}
                    </${balise}>`;
                }).join('')}</div>`;
            } else {
                html += `<div class="portal-panel"><div class="portal-empty">${icone('building')}
                    <strong>Aucun établissement dans votre portée</strong>
                    <p>Votre rôle ne couvre aucun établissement actif. Votre responsable peut l’étendre depuis « Rôles et accès ».</p></div></div>`;
            }
        }

        /* 3. Classement et évolution. */
        html += `<div class="${globale ? 'portal-grid-2' : 'portal-grid-3'}">`;

        html += `<section class="portal-panel portal-reveal" style="${delai(2)}">
            <div class="portal-panel-head">
                <h2>${globale ? 'Classement des établissements' : 'Filières les plus demandées'}</h2>
                <p>${globale ? 'Du plus grand effectif au plus petit.' : 'Selon le premier choix des candidats.'}</p>
            </div>
            ${classement(globale
                ? [...data.establishments].sort((a, b) => b.total - a.total)
                : (data.breakdown.filieres || []))}
        </section>`;

        if (data.evolution) {
            html += `<section class="portal-panel portal-reveal" style="${delai(3)}">
                <div class="portal-panel-head">
                    <h2>Évolution des dépôts</h2>
                    <p>Nombre de dossiers créés par jour, sur 30 jours.</p>
                </div>
                <div class="portal-chart">
                    <canvas id="portal-evolution" role="img" aria-label="Préinscriptions déposées par jour sur les 30 derniers jours. Les chiffres exacts sont dans le tableau ci-dessous."></canvas>
                </div>
                <details class="portal-figures">
                    <summary>Consulter les chiffres jour par jour</summary>
                    <div class="portal-table-wrap"><div class="portal-table-scroll">
                        <table class="portal-table">
                            <thead><tr><th>Date</th><th>Préinscrits</th></tr></thead>
                            <tbody>${data.labels.map((j, i) => `<tr><td>${esc(dateCourte(j))}</td><td class="portal-num">${nombre(data.evolution[i])}</td></tr>`).join('')}</tbody>
                        </table>
                    </div></div>
                </details>
            </section>`;
        }

        if (!globale) {
            const statuts = data.breakdown.statuts || [];
            const totalStatuts = statuts.reduce((s, l) => s + Number(l.total), 0);
            html += `<section class="portal-panel portal-reveal" style="${delai(4)}">
                <div class="portal-panel-head">
                    <h2>Avancement des dossiers</h2>
                    <p>Part des dossiers déjà soumis.</p>
                </div>
                ${totalStatuts ? `<div class="portal-chart portal-chart--small">
                    <canvas id="portal-statuts" role="img" aria-label="Répartition des dossiers par statut"></canvas>
                </div>
                <div class="portal-legend">${statuts.map(l => `
                    <span><i style="background:${l.label === 'soumis' ? token('--ueb-chart-1') : token('--ueb-chart-2')}"></i>
                    ${l.label === 'soumis' ? 'Soumis' : 'Brouillons'} : <strong>${nombre(l.total)}</strong></span>`).join('')}
                </div>` : `<div class="portal-empty">${icone('inbox')}<strong>Aucun dossier</strong><p>Rien n’a encore été déposé pour cet établissement.</p></div>`}
            </section>`;
        }

        html += '</div>';

        if (globale) {
            html += '<p class="portal-hint" style="margin-top:1rem">La variation compare les jours déjà écoulés de cette semaine aux mêmes jours de la semaine précédente.</p>';
        }

        racineTdb.innerHTML = html;
        animerCompteurs(racineTdb, animer);
        courbeEvolution(data);
        if (!globale) beigneStatuts(data.breakdown.statuts || []);
    }

    async function chargerTableauDeBord(animer = true) {
        if (!racineTdb) return;
        racineTdb.setAttribute('aria-busy', 'true');
        try {
            const data = await api('ueb_portal_stats', {
                view: racineTdb.dataset.view,
                establishment: racineTdb.dataset.establishment
            });
            dernieresDonnees = data;
            dernierChargement = Date.now();
            peindreTableauDeBord(data, animer);
        } catch (e) {
            racineTdb.innerHTML = `<div class="portal-notice portal-notice--error" role="alert">
                ${icone('alert', 'admin-icon--sm')}<span>${esc(e.message)}</span>
                <button type="button" class="admin-tbtn" id="portal-retry">${icone('refresh', 'admin-icon--sm')}Réessayer</button></div>`;
            const retry = $('portal-retry');
            if (retry) retry.onclick = () => chargerTableauDeBord(true);
        }
        racineTdb.setAttribute('aria-busy', 'false');
    }

    if (racineTdb) {
        chargerTableauDeBord(true);

        /* Changement d'établissement sans rechargement : l'URL suit, pour que
           le lien reste partageable et le bouton « retour » cohérent. */
        const selecteur = $('portal-establishment');
        if (selecteur) {
            selecteur.addEventListener('change', () => {
                const id = selecteur.value;
                racineTdb.dataset.establishment = id;
                const listeDossiers = $('portal-students');
                if (listeDossiers) {
                    listeDossiers.dataset.establishment = id;
                    const champ = $('portal-list-filters')?.elements.faculte;
                    if (champ) champ.value = id;
                }
                const url = new URL(window.location.href);
                url.searchParams.set('establishment', id);
                history.replaceState(null, '', url);
                const titre = document.querySelector('.admin-topbar h1');
                if (titre) titre.textContent = selecteur.options[selecteur.selectedIndex].text;
                chargerTableauDeBord(true);
                if (rechargerDossiers) rechargerDossiers(true);
            });
        }

        /* Rafraîchissement silencieux : seulement après 2 minutes d'absence,
           et sans rejouer les compteurs — revenir sur l'onglet ne doit pas
           relancer toute l'animation. */
        document.addEventListener('visibilitychange', () => {
            if (document.hidden || Date.now() - dernierChargement < 120000) return;
            chargerTableauDeBord(false);
        });
        window.addEventListener('storage', e => {
            if (e.key === 'ueb-statistics-change') chargerTableauDeBord(false);
        });
    }

    /* ------------------------------------------------------------------ */
    /* Dossiers                                                            */
    /* ------------------------------------------------------------------ */
    const racineDossiers = $('portal-students');

    if (racineDossiers) {
        const filtres = $('portal-list-filters');
        const liste = $('portal-list');
        const pagination = $('portal-pagination');
        let page = 1;
        let generation = 0;

        const colonnes = [
            ['numero_dossier', 'N° dossier'],
            ['nom', 'Étudiant'],
            ['faculte', 'Établissement'],
            ['filiere', 'Filière'],
            ['statut', 'Statut', false],
            ['date_creation', 'Déposé le']
        ];

        function enTetes() {
            const tri = filtres.elements.orderby.value;
            const sens = filtres.elements.order.value;
            return colonnes.map(([cle, libelle, triable = true]) => {
                if (!triable) return `<th>${esc(libelle)}</th>`;
                const actif = tri === cle;
                return `<th ${actif ? `aria-sort="${sens === 'asc' ? 'ascending' : 'descending'}"` : ''}>
                    <button type="button" class="portal-sort" data-sort="${cle}">
                        ${esc(libelle)}${icone(actif ? (sens === 'asc' ? 'up' : 'down') : 'sort', 'admin-icon--sm')}
                    </button>
                </th>`;
            }).join('') + '<th><span class="admin-sr-only">Détail</span></th>';
        }

        rechargerDossiers = async function (reinitialiser) {
            if (racineDossiers.dataset.canList !== '1') return;
            if (reinitialiser) page = 1;
            const courante = ++generation;
            const valeurs = champs(filtres);
            valeurs.set('page', page);

            liste.setAttribute('aria-busy', 'true');
            if (!liste.querySelector('table')) {
                liste.innerHTML = `<div class="portal-table-wrap" style="padding:1rem">
                    ${'<div class="portal-skeleton portal-skeleton--row"></div>'.repeat(6)}
                    <span class="admin-sr-only" role="status">Chargement des dossiers…</span></div>`;
            }

            try {
                const data = await api('ueb_admin_get_dossiers', valeurs, cfg.dataNonce);
                if (courante !== generation) return;

                liste.innerHTML = data.rows.length ? `
                    <div class="portal-table-wrap"><div class="portal-table-scroll">
                        <table class="portal-table">
                            <caption class="admin-sr-only">Dossiers accessibles à votre compte</caption>
                            <thead><tr>${enTetes()}</tr></thead>
                            <tbody>${data.rows.map(r => `
                                <tr class="portal-row-clickable" data-dossier="${esc(r.numero_dossier)}">
                                    <td class="portal-num">${esc(r.numero_dossier)}</td>
                                    <td><strong>${esc([r.nom, r.prenom].filter(Boolean).join(' '))}</strong></td>
                                    <td>${esc(r.faculte || '—')}</td>
                                    <td>${esc(r.filiere || 'Non renseignée')}</td>
                                    <td><span class="portal-status portal-status--${r.statut === 'soumis' ? 'soumis' : 'brouillon'}">${r.statut === 'soumis' ? 'Soumis' : 'Brouillon'}</span></td>
                                    <td class="portal-num">${esc(dateCourte(r.date_creation))}</td>
                                    <td><button type="button" class="portal-row-open" data-dossier="${esc(r.numero_dossier)}"
                                            aria-label="Ouvrir le dossier ${esc(r.numero_dossier)}">${icone('eye', 'admin-icon--sm')}</button></td>
                                </tr>`).join('')}
                            </tbody>
                        </table>
                    </div></div>`
                    : `<div class="portal-table-wrap"><div class="portal-empty">${icone('search')}
                        <strong>Aucun dossier ne correspond</strong>
                        <p>Essayez un autre terme de recherche, ou retirez un filtre.</p></div></div>`;

                const pages = Math.max(1, data.nb_pages);
                pagination.innerHTML = `
                    <span>${nombre(data.total)} dossier(s) · page ${data.page} sur ${pages}</span>
                    <div class="portal-pagination-actions">
                        <button type="button" class="admin-tbtn" id="portal-prev" ${page <= 1 ? 'disabled' : ''}>${icone('arrow-left', 'admin-icon--sm')}Précédent</button>
                        <button type="button" class="admin-tbtn" id="portal-next" ${page >= pages ? 'disabled' : ''}>Suivant${icone('arrow-right', 'admin-icon--sm')}</button>
                    </div>`;
                $('portal-prev').onclick = () => { page--; rechargerDossiers(false); };
                $('portal-next').onclick = () => { page++; rechargerDossiers(false); };
            } catch (e) {
                if (courante !== generation) return;
                liste.innerHTML = `<div class="portal-notice portal-notice--error" role="alert">${icone('alert', 'admin-icon--sm')}<span>${esc(e.message)}</span></div>`;
                pagination.replaceChildren();
            }
            if (courante === generation) liste.setAttribute('aria-busy', 'false');
        };

        /* Tri au clic sur l'en-tête : un deuxième clic inverse le sens. */
        liste?.addEventListener('click', e => {
            const bouton = e.target.closest('[data-sort]');
            if (!bouton) return;
            const cle = bouton.dataset.sort;
            const memeColonne = filtres.elements.orderby.value === cle;
            filtres.elements.orderby.value = cle;
            filtres.elements.order.value = memeColonne && filtres.elements.order.value === 'desc' ? 'asc' : 'desc';
            rechargerDossiers(true);
        });

        /* Recherche au fil de la frappe, temporisée : on n'interroge pas le
           serveur à chaque lettre. */
        let minuterie;
        filtres.addEventListener('input', e => {
            if (e.target.type !== 'search') return;
            clearTimeout(minuterie);
            minuterie = setTimeout(() => rechargerDossiers(true), 350);
        });
        filtres.addEventListener('change', e => {
            if (e.target.type !== 'search') rechargerDossiers(true);
        });
        filtres.addEventListener('submit', e => { e.preventDefault(); rechargerDossiers(true); });

        /* Filtres détaillés (filière, niveau…) : panneau repliable. Ses
           champs sont dans le formulaire : la liste et l'export les suivent
           sans code supplémentaire. Le badge compte les filtres actifs, pour
           qu'un panneau refermé ne cache pas une sélection en cours. */
        const plus = $('portal-more');
        const boutonPlus = $('portal-more-toggle');
        if (plus && boutonPlus) {
            const compteur = $('portal-more-count');
            const effacer = $('portal-more-reset');
            const champsPlus = () => [...plus.querySelectorAll('select, input')];
            const majCompteur = () => {
                const actifs = champsPlus().filter(c => c.value !== '').length;
                compteur.textContent = actifs;
                compteur.hidden = actifs === 0;
                effacer.hidden = actifs === 0;
            };
            boutonPlus.addEventListener('click', () => {
                const ouvrir = plus.hidden;
                plus.hidden = !ouvrir;
                boutonPlus.setAttribute('aria-expanded', String(ouvrir));
                if (ouvrir) champsPlus()[0]?.focus();
            });
            plus.addEventListener('change', majCompteur);
            effacer.addEventListener('click', () => {
                champsPlus().forEach(c => { c.value = ''; });
                majCompteur();
                rechargerDossiers(true);
                boutonPlus.focus();
            });
            majCompteur();
        }

        rechargerDossiers(true);
        window.addEventListener('storage', e => {
            if (e.key === 'ueb-statistics-change') rechargerDossiers(false);
        });

        /* Détail d'un dossier. */
        const dialogueDetail = $('portal-detail-dialog');
        if (dialogueDetail) {
            liste?.addEventListener('click', async e => {
                const bouton = e.target.closest('[data-dossier]');
                if (!bouton) return;
                // Copier un numéro de dossier ou un nom ne doit pas ouvrir la
                // fiche : une sélection de texte en cours n'est jamais un clic.
                const selectionEnCours = window.getSelection && window.getSelection().toString();
                if (selectionEnCours) return;
                const numero = bouton.dataset.dossier;
                $('portal-detail-title').textContent = 'Dossier ' + numero;
                $('portal-detail-sub').textContent = 'Chargement…';
                $('portal-detail-body').innerHTML = `<div class="portal-skeleton portal-skeleton--panel"></div>`;
                dialogueDetail.showModal();
                try {
                    const d = await api('ueb_admin_get_dossier_detail', { numero_dossier: numero }, cfg.dataNonce);
                    $('portal-detail-sub').textContent = [d.faculte, d.filiere1].filter(Boolean).join(' · ');
                    const blocs = [
                        ['Identité', [
                            ['Nom', d.nom], ['Prénom', d.prenom], ['Sexe', d.sexe],
                            ['Nationalité', d.nationalite], ['Origine', d.origine]
                        ]],
                        ['Candidature', [
                            ['Établissement', d.faculte], ['Premier choix', d.filiere1],
                            ['Second choix', d.filiere2], ['Diplôme', d.diplome], ['Série', d.serie]
                        ]],
                        ['Contact', [
                            ['Adresse e-mail', d.email],
                            ['Téléphone', (d.telephones || []).join(', ')]
                        ]]
                    ];
                    $('portal-detail-body').innerHTML = `
                        <div class="portal-detail-grid">${blocs.map(([titre, lignes]) => `
                            <div class="portal-detail-block">
                                <h3>${esc(titre)}</h3>
                                <dl>${lignes.filter(l => l[1]).map(l => `<dt>${esc(l[0])}</dt><dd>${esc(l[1])}</dd>`).join('') || '<dd>Non renseigné</dd>'}</dl>
                            </div>`).join('')}
                        </div>`;
                } catch (err) {
                    $('portal-detail-sub').textContent = '';
                    $('portal-detail-body').innerHTML = `<div class="portal-notice portal-notice--error" role="alert">${icone('alert', 'admin-icon--sm')}<span>${esc(err.message)}</span></div>`;
                }
            });
        }

        /* Export : même menu à formats que le tableau de bord. */
        const boutonExport = $('portal-export');
        if (boutonExport) {
            const menu = $('portal-export-menu');
            const reduit = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            // .admin-export-menu reste à opacity 0 tant qu'il n'a pas la classe
            // is-open (admin-dashboard.css) : retirer [hidden] ne suffit pas, le
            // menu s'ouvrait invisible et le bouton semblait ne rien faire.
            const ouvrir = () => {
                menu.hidden = false;
                requestAnimationFrame(() => menu.classList.add('is-open'));
                boutonExport.setAttribute('aria-expanded', 'true');
                const premier = menu.querySelector('[data-format]');
                if (premier) premier.focus();
            };
            const fermer = () => {
                if (menu.hidden) return;
                menu.classList.remove('is-open');
                boutonExport.setAttribute('aria-expanded', 'false');
                setTimeout(() => { if (!menu.classList.contains('is-open')) menu.hidden = true; }, reduit ? 0 : 140);
            };
            boutonExport.addEventListener('click', () => {
                if (menu.hidden) ouvrir(); else fermer();
            });
            document.addEventListener('click', e => {
                if (!e.target.closest('#portal-export-wrap')) fermer();
            });
            document.addEventListener('keydown', e => {
                if (e.key === 'Escape' && !menu.hidden) { fermer(); boutonExport.focus(); }
            });

            menu.querySelectorAll('[data-format]').forEach(item => {
                item.addEventListener('click', async () => {
                    const format = item.dataset.format;
                    fermer();
                    message('portal-message', 'Préparation de l’export…', 'ok');
                    try {
                        const valeurs = champs(filtres);
                        valeurs.set('action', format === 'csv' ? 'ueb_admin_export_csv' : 'ueb_admin_export');
                        valeurs.set('format', format);
                        valeurs.set('nonce', cfg.dataNonce);
                        const reponse = await fetch(cfg.ajax, { method: 'POST', body: valeurs, credentials: 'same-origin' });
                        if (!reponse.ok || (reponse.headers.get('content-type') || '').includes('json')) {
                            throw new Error('Export refusé ou indisponible. Vérifiez vos permissions et les établissements sélectionnés.');
                        }
                        const blob = await reponse.blob();
                        const url = URL.createObjectURL(blob);
                        const lien = document.createElement('a');
                        lien.href = url;
                        lien.download = 'preinscriptions-ueb.' + ({ excel: 'xlsx', word: 'docx', pdf: 'pdf', csv: 'csv' }[format]);
                        document.body.append(lien);
                        lien.click();
                        lien.remove();
                        setTimeout(() => URL.revokeObjectURL(url), 10000);
                        message('portal-message', 'Votre export est prêt.', 'ok');
                    } catch (err) {
                        message('portal-message', err.message, 'error');
                    }
                });
            });
        }
    }

    /* ------------------------------------------------------------------ */
    /* Dialogues : fermeture commune                                       */
    /* ------------------------------------------------------------------ */
    document.querySelectorAll('[data-close-dialog]').forEach(b => {
        b.addEventListener('click', () => b.closest('dialog').close());
    });

    /* ------------------------------------------------------------------ */
    /* Rôles                                                               */
    /* ------------------------------------------------------------------ */
    const donneesRoles = donneesJSON('portal-role-data');
    if (donneesRoles) {
        const form = $('portal-role-form');
        const dialogue = $('portal-role-dialog');
        let etape = 0;

        const sigles = ids => donneesRoles.establishments
            .filter(e => ids.includes(Number(e.id)))
            .map(e => e.code);

        const libellePortee = role => role.scope === 'all'
            ? 'Tous les établissements'
            : (sigles(role.establishments.map(Number)).join(', ') || 'Aucun établissement');

        /* Liste des rôles. */
        $('portal-role-list').innerHTML = donneesRoles.roles.length
            ? donneesRoles.roles.map((role, i) => `
                <article class="portal-panel portal-role-card portal-reveal" style="${delai(i)}">
                    <div>
                        <h3>${esc(role.name)}</h3>
                        <div class="portal-role-meta">
                            <span class="portal-tag portal-tag--scope">${icone('building', 'admin-icon--sm')}${esc(libellePortee(role))}</span>
                            <span class="portal-tag">${icone('users', 'admin-icon--sm')}${role.users} compte(s)</span>
                            ${role.mine ? '<span class="portal-tag portal-tag--locked">Votre rôle</span>'
                                : (role.locked ? '<span class="portal-tag portal-tag--locked">Protégé</span>' : '')}
                        </div>
                    </div>
                    <ul class="portal-role-perms">
                        ${role.permissions.slice(0, 4).map(c => `<li>${icone('check', 'admin-icon--sm')}${esc(donneesRoles.catalogue[c]?.[1] || c)}</li>`).join('')}
                        ${role.permissions.length > 4 ? `<li>${icone('plus', 'admin-icon--sm')}${role.permissions.length - 4} autre(s) accès</li>` : ''}
                    </ul>
                    <div class="portal-role-actions">
                        ${!role.locked ? `<button type="button" class="admin-tbtn" data-role-edit="${esc(role.key)}">${icone('edit', 'admin-icon--sm')}Modifier</button>` : ''}
                        <button type="button" class="admin-tbtn" data-role-copy="${esc(role.key)}">${icone('copy', 'admin-icon--sm')}Dupliquer</button>
                        ${!role.locked ? `<button type="button" class="admin-tbtn" data-role-delete="${esc(role.key)}">${icone('trash', 'admin-icon--sm')}Supprimer</button>` : ''}
                    </div>
                </article>`).join('')
            : `<div class="portal-panel"><div class="portal-empty">${icone('shield')}
                <strong>Aucun rôle pour l’instant</strong>
                <p>Créez votre premier rôle : donnez-lui un nom, choisissez les établissements qu’il couvre, puis cochez ce qu’il peut faire.</p></div></div>`;

        /* Aperçu : la barre latérale que verra le titulaire du rôle. */
        const menusPossibles = [
            ['ueb_view_overview', 'Vue d’ensemble', 'overview'],
            ['ueb_view_stats', 'Mon établissement', 'building'],
            ['ueb_view_students', 'Dossiers', 'list'],
            ['ueb_manage_roles', 'Rôles et accès', 'shield'],
            ['ueb_manage_users', 'Comptes', 'users'],
            ['ueb_manage_establishments', 'Établissements', 'settings']
        ];

        function apercu() {
            const valeurs = champs(form);
            const portee = valeurs.get('scope');
            const ids = valeurs.getAll('establishments[]').map(Number);
            const caps = valeurs.getAll('permissions[]');

            $('portal-role-establishments').hidden = portee === 'all';

            $('portal-preview-name').textContent = valeurs.get('name')?.trim() || 'Nouveau rôle';

            let entrees = menusPossibles.filter(m => caps.includes(m[0]));
            // Une portée globale entre par la vue d'ensemble, pas par « Mon établissement ».
            if (caps.includes('ueb_view_overview')) entrees = entrees.filter(m => m[0] !== 'ueb_view_stats');

            $('portal-preview-nav').innerHTML = entrees.length
                ? entrees.map((m, i) => `<span style="${delai(i)}">${icone(m[2], 'admin-icon--sm')}${esc(m[1])}</span>`).join('')
                : `<span style="opacity:.6">${icone('lock', 'admin-icon--sm')}Aucun écran</span>`;

            const texte = portee === 'all'
                ? 'Tous les établissements, y compris ceux créés plus tard.'
                : (sigles(ids).join(', ') || 'Aucun établissement sélectionné.');
            $('portal-preview-foot').innerHTML =
                `<strong>Portée :</strong> ${esc(texte)}<br><strong>${caps.length}</strong> permission(s) cochée(s).`;
        }

        function afficherEtape() {
            form.querySelectorAll('[data-step]').forEach(f => { f.hidden = Number(f.dataset.step) !== etape; });
            document.querySelectorAll('.portal-steps li').forEach((li, i) => {
                if (i === etape) li.setAttribute('aria-current', 'step');
                else li.removeAttribute('aria-current');
                li.toggleAttribute('data-done', i < etape);
            });
            $('portal-role-prev').hidden = etape === 0;
            $('portal-role-next').hidden = etape === 2;
            $('portal-role-submit').hidden = etape !== 2;
            const premier = form.querySelector(`[data-step="${etape}"] input:not([type="hidden"]), [data-step="${etape}"] select`);
            if (premier) premier.focus();
            apercu();
        }

        function valider() {
            message('portal-role-error', '');
            if (!form.elements.name.value.trim()) {
                etape = 0; afficherEtape();
                message('portal-role-error', 'Donnez un nom à ce rôle.');
                return false;
            }
            if (etape >= 1) {
                const valeurs = champs(form);
                const total = valeurs.getAll('establishments[]').length;
                const portee = valeurs.get('scope');
                if ((portee === 'single' && total !== 1) || (portee === 'multiple' && total < 2)) {
                    etape = 1; afficherEtape();
                    message('portal-role-error', portee === 'single'
                        ? 'Choisissez exactement un établissement.'
                        : 'Choisissez au moins deux établissements.');
                    return false;
                }
            }
            return true;
        }

        function ouvrirRole(role, dupliquer = false) {
            form.reset();
            etape = 0;
            message('portal-role-error', '');
            form.elements.key.value = role && !dupliquer ? role.key : '';
            form.elements.revision.value = role && !dupliquer ? role.revision : '';
            $('portal-role-heading').textContent = role && !dupliquer ? 'Modifier le rôle' : 'Créer un rôle';
            if (role) {
                form.elements.name.value = role.name + (dupliquer ? ' (copie)' : '');
                form.querySelectorAll('[name="scope"]').forEach(r => { r.checked = r.value === role.scope; });
                form.querySelectorAll('[name="establishments[]"]').forEach(c => {
                    c.checked = role.establishments.map(Number).includes(Number(c.value));
                });
                form.querySelectorAll('[name="permissions[]"]').forEach(c => {
                    c.checked = role.permissions.includes(c.value);
                });
            }
            dialogue.showModal();
            afficherEtape();
        }

        $('portal-new-role').onclick = () => ouvrirRole(null);

        $('portal-role-list').addEventListener('click', e => {
            const bouton = e.target.closest('button');
            if (!bouton) return;
            const cle = bouton.dataset.roleEdit || bouton.dataset.roleCopy || bouton.dataset.roleDelete;
            const role = donneesRoles.roles.find(r => r.key === cle);
            if (!role) return;

            if (bouton.dataset.roleDelete) {
                const suppression = $('portal-delete-form');
                suppression.reset();
                suppression.elements.key.value = cle;
                suppression.elements.revision.value = role.revision;
                $('portal-delete-description').textContent = role.users
                    ? `« ${role.name} » sera supprimé. ${role.users} compte(s) y sont rattaché(s) : choisissez leur nouveau rôle.`
                    : `« ${role.name} » sera supprimé. Aucun compte n’y est rattaché.`;
                $('portal-delete-replacement').hidden = !role.users;
                suppression.elements.replacement.required = Boolean(role.users);
                suppression.elements.replacement.innerHTML = '<option value="">Choisir un rôle</option>'
                    + donneesRoles.roles.filter(r => r.key !== cle && !r.locked)
                        .map(r => `<option value="${esc(r.key)}">${esc(r.name)}</option>`).join('');
                message('portal-delete-error', '');
                $('portal-delete-dialog').showModal();
            } else {
                ouvrirRole(role, Boolean(bouton.dataset.roleCopy));
            }
        });

        form.addEventListener('input', apercu);
        form.addEventListener('change', apercu);
        $('portal-role-next').onclick = () => { if (valider()) { etape++; afficherEtape(); } };
        $('portal-role-prev').onclick = () => { etape--; afficherEtape(); };

        $('portal-role-preset').addEventListener('change', e => {
            const modele = donneesRoles.presets[e.target.value];
            form.querySelectorAll('[name="permissions[]"]').forEach(c => {
                c.checked = Boolean(modele) && modele.permissions.includes(c.value);
            });
            apercu();
        });

        form.addEventListener('submit', async e => {
            e.preventDefault();
            if (etape < 2) { $('portal-role-next').click(); return; }
            if (!valider()) return;
            if (!form.querySelector('[name="permissions[]"]:checked')) {
                message('portal-role-error', 'Cochez au moins une permission.');
                return;
            }
            occupe(form, true);
            const valeurs = champs(form);
            valeurs.set('operation', 'role_save');
            try {
                const r = await api('ueb_access_mutate', valeurs);
                enregistre(r.message);
            } catch (err) {
                message('portal-role-error', err.message);
            } finally {
                occupe(form, false);
            }
        });

        $('portal-delete-form').addEventListener('submit', async e => {
            e.preventDefault();
            const suppression = e.currentTarget;
            occupe(suppression, true);
            const valeurs = champs(suppression);
            valeurs.set('operation', 'role_delete');
            try {
                const r = await api('ueb_access_mutate', valeurs);
                enregistre(r.message);
            } catch (err) {
                message('portal-delete-error', err.message);
            } finally {
                occupe(suppression, false);
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /* Comptes                                                             */
    /* ------------------------------------------------------------------ */
    const formCompte = $('portal-user-form');
    if (formCompte) {
        const champsCreation = $('portal-user-fields');

        document.querySelectorAll('[data-assign-user]').forEach(bouton => {
            bouton.addEventListener('click', () => {
                formCompte.reset();
                formCompte.elements.id.value = bouton.dataset.assignUser;
                $('portal-user-heading').textContent = 'Changer le rôle de ' + bouton.dataset.userName;
                champsCreation.hidden = true;
                champsCreation.querySelectorAll('input').forEach(i => { i.disabled = true; });
                formCompte.elements.role.focus();
                formCompte.scrollIntoView({ behavior: reduced.matches ? 'auto' : 'smooth', block: 'center' });
            });
        });

        formCompte.addEventListener('reset', () => {
            $('portal-user-heading').textContent = 'Créer un compte';
            champsCreation.hidden = false;
            champsCreation.querySelectorAll('input').forEach(i => { i.disabled = false; });
        });

        formCompte.addEventListener('submit', async e => {
            e.preventDefault();
            occupe(formCompte, true);
            const valeurs = champs(formCompte);
            valeurs.set('operation', 'user_save');
            try {
                const r = await api('ueb_access_mutate', valeurs);
                enregistre(r.message);
            } catch (err) {
                message('portal-user-error', err.message);
            } finally {
                occupe(formCompte, false);
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /* Établissements                                                      */
    /* ------------------------------------------------------------------ */
    const formEtab = $('portal-establishment-form');
    if (formEtab) {
        const lignes = donneesJSON('portal-establishment-data') || [];

        function editerEtablissement(bouton) {
            const donnees = lignes.find(l => Number(l.id) === Number(bouton.dataset.editEstablishment));
            if (!donnees) return;
            Object.entries(donnees).forEach(([cle, valeur]) => {
                if (formEtab.elements[cle]) formEtab.elements[cle].value = valeur ?? '';
            });
            $('portal-establishment-heading').textContent = 'Modifier ' + donnees.code;
            formEtab.elements.nom_fr.focus();
            formEtab.scrollIntoView({ behavior: reduced.matches ? 'auto' : 'smooth', block: 'center' });
        }
        document.querySelectorAll('[data-edit-establishment]').forEach(bouton => {
            bouton.addEventListener('click', () => editerEtablissement(bouton));
        });
        // Cliquer n'importe où sur la ligne d'un établissement revient à
        // cliquer sur « Modifier ».
        document.querySelectorAll('tr.portal-row-clickable').forEach(ligneEl => {
            const cible = ligneEl.querySelector('[data-edit-establishment]');
            if (!cible) return;
            ligneEl.addEventListener('click', e => {
                if (e.target.closest('a, button, input, select, textarea, label')) return;
                const selectionEnCours = window.getSelection && window.getSelection().toString();
                if (selectionEnCours) return;
                editerEtablissement(cible);
            });
        });

        formEtab.addEventListener('reset', () => {
            $('portal-establishment-heading').textContent = 'Ajouter un établissement';
        });

        /* Aide à la saisie : l'identifiant d'URL découle du nom tant qu'il
           n'a pas été modifié à la main. */
        let slugTouche = false;
        formEtab.elements.slug.addEventListener('input', () => { slugTouche = true; });
        formEtab.elements.nom_fr.addEventListener('input', e => {
            if (slugTouche || formEtab.elements.id.value) return;
            formEtab.elements.slug.value = e.target.value
                .normalize('NFD').replace(/[̀-ͯ]/g, '')
                .toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        });

        formEtab.addEventListener('submit', async e => {
            e.preventDefault();
            occupe(formEtab, true);
            try {
                const r = await api('ueb_portal_establishment_save', champs(formEtab));
                enregistre(r.message);
            } catch (err) {
                message('portal-establishment-error', err.message);
            } finally {
                occupe(formEtab, false);
            }
        });
    }
}());
