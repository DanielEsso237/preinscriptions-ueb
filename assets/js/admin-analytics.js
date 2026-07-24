/**
 * Graphiques du dashboard admin (Chart.js 4).
 *
 * Deux principes structurent ce fichier :
 *
 * 1. Aucune couleur n'est écrite en dur. Tout est lu au moment du rendu
 *    depuis les tokens CSS (--ueb-chart-*), ce qui fait suivre le thème
 *    clair/sombre sans dupliquer la moindre palette en JS.
 *
 * 2. Le type de graphique suit la nature de la donnée : tendance = courbe,
 *    part d'un tout (≤ 5 parts) = anneau, comparaison de libellés longs =
 *    barres horizontales. Les camemberts au-delà de 5 catégories sont
 *    évités : illisibles et inaccessibles aux daltoniens.
 *
 * Expose window.uebCharts.render(data) et redessine tout seul quand le
 * thème change (événement 'uebThemeChange').
 */
(function () {
    'use strict';

    var instances = {};
    var dernieresDonnees = null;

    var reducedMotion = window.matchMedia &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ================================================================
       LECTURE DES TOKENS DE THÈME
       ================================================================ */
    function token(nom, secours) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(nom);
        return (v && v.trim()) || secours;
    }

    function theme() {
        return {
            series: [
                token('--ueb-chart-1', '#1a4a2e'),
                token('--ueb-chart-2', '#c9a227'),
                token('--ueb-chart-3', '#2f6f8f'),
                token('--ueb-chart-4', '#8a4b2a'),
                token('--ueb-chart-5', '#5c8a3a'),
                token('--ueb-chart-6', '#6b4a86'),
                token('--ueb-chart-7', '#9c6b2e'),
                token('--ueb-chart-8', '#2f7d6b')
            ],
            grid:    token('--ueb-chart-grid', 'rgba(0,0,0,.08)'),
            tick:    token('--ueb-chart-tick', '#6b7a72'),
            text:    token('--ueb-text', '#14201a'),
            surface: token('--ueb-surface', '#ffffff'),
            border:  token('--ueb-border', '#e2e7e3')
        };
    }

    /* ================================================================
       HELPERS DE DONNÉES
       ================================================================ */
    function labels(a) { return (a || []).map(function (x) { return x.label; }); }
    function totaux(a) { return (a || []).map(function (x) { return +x.total; }); }
    function somme(a)  { return (a || []).reduce(function (s, x) { return s + (+x.total || 0); }, 0); }

    function nf(n) {
        return new Intl.NumberFormat('fr-FR').format(n);
    }

    /** Tronque les libellés trop longs sur l'axe (le tooltip garde l'intégral). */
    function court(s, max) {
        s = String(s == null ? '' : s);
        return s.length > max ? s.slice(0, max - 1) + '…' : s;
    }

    /** Date ISO -> libellé court « 12 juil. » pour l'axe temporel. */
    function jourCourt(iso) {
        var d = new Date(iso + 'T00:00:00');
        if (isNaN(d.getTime())) return iso;
        return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
    }

    function jourLong(iso) {
        var d = new Date(iso + 'T00:00:00');
        if (isNaN(d.getTime())) return iso;
        return d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    }

    /* ================================================================
       CYCLE DE VIE DES INSTANCES + ÉTAT VIDE
       ================================================================ */
    function detruire(id) {
        if (instances[id]) {
            instances[id].destroy();
            delete instances[id];
        }
    }

    /**
     * Prépare un canvas : détruit l'ancienne instance, retire un éventuel
     * message d'état vide, et signale si les données permettent un rendu.
     */
    function prepare(id, dataset) {
        var el = document.getElementById(id);
        if (!el) return null;

        detruire(id);

        var body = el.parentNode;
        var ancien = body.querySelector('.admin-chart-empty');
        if (ancien) ancien.remove();

        var vide = !dataset || !dataset.length || somme(dataset) === 0;
        el.style.visibility = vide ? 'hidden' : 'visible';

        if (vide) {
            var p = document.createElement('div');
            p.className = 'admin-chart-empty';
            p.textContent = 'Aucune donnée pour ces filtres';
            body.appendChild(p);
            return null;
        }

        return el;
    }

    /* ================================================================
       OPTIONS COMMUNES
       ================================================================ */
    /**
     * Animation d'entrée, déclinée selon la forme du graphique.
     *
     * 'barres'  : chaque barre pousse après la précédente (30 ms d'écart,
     *             plafonnés à 12 rangs pour que la dernière n'attende pas).
     * 'anneau'  : rotation + ouverture, la lecture part du centre.
     * 'ligne'   : montée simple, sans décalage — une courbe qui se construit
     *             point par point donne une fausse impression de temps réel.
     *
     * Toujours désactivée si l'utilisateur demande moins de mouvement : les
     * données doivent alors être lisibles immédiatement.
     */
    function animation(forme) {
        if (reducedMotion) return false;

        if (forme === 'barres') {
            return {
                duration: 480,
                easing: 'easeOutQuart',
                delay: function (ctx) {
                    // Uniquement à la première peinture : sans ce test, le
                    // moindre survol relancerait la cascade.
                    if (ctx.type !== 'data' || ctx.mode !== 'default') return 0;
                    return Math.min(ctx.dataIndex, 12) * 30;
                }
            };
        }

        if (forme === 'anneau') {
            return {
                duration: 620,
                easing: 'easeOutQuart',
                animateRotate: true,
                animateScale: true
            };
        }

        return { duration: 550, easing: 'easeOutQuart' };
    }

    function tooltip(T, formatter) {
        return {
            backgroundColor: T.text,
            titleColor: T.surface,
            bodyColor: T.surface,
            padding: 10,
            cornerRadius: 8,
            displayColors: false,
            titleFont: { family: 'Inter, sans-serif', size: 12, weight: '600' },
            bodyFont: { family: 'Inter, sans-serif', size: 12 },
            callbacks: formatter || {}
        };
    }

    function grille(T, axe) {
        return {
            grid: {
                color: T.grid,
                drawBorder: false,
                display: axe === 'valeur'
            },
            border: { display: false },
            ticks: {
                color: T.tick,
                font: { family: 'Inter, sans-serif', size: 11 },
                precision: 0
            }
        };
    }

    /* ================================================================
       1. COURBE D'ÉVOLUTION
       ================================================================ */
    function renderEvolution(dataset) {
        var el = prepare('chart-evolution', dataset);
        if (!el) return;

        var T = theme();
        var ctx = el.getContext('2d');

        // Dégradé vertical sous la courbe : donne du corps à la tendance
        // sans masquer la grille.
        var hauteur = el.parentNode.clientHeight || 300;
        var degrade = ctx.createLinearGradient(0, 0, 0, hauteur);
        degrade.addColorStop(0, hexA(T.series[0], .28));
        degrade.addColorStop(1, hexA(T.series[0], 0));

        instances['chart-evolution'] = new Chart(el, {
            type: 'line',
            data: {
                labels: labels(dataset).map(jourCourt),
                datasets: [{
                    label: 'Dossiers',
                    data: totaux(dataset),
                    borderColor: T.series[0],
                    backgroundColor: degrade,
                    borderWidth: 2,
                    fill: true,
                    tension: .35,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: T.series[0],
                    pointHoverBorderColor: T.surface,
                    pointHoverBorderWidth: 2,
                    // Zone de survol généreuse : la courbe reste pointable
                    // même là où elle est plate.
                    pointHitRadius: 18
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: animation(),
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: tooltip(T, {
                        title: function (items) {
                            var i = items[0].dataIndex;
                            return jourLong(dataset[i].label);
                        },
                        label: function (item) {
                            var n = item.parsed.y;
                            return n + (n > 1 ? ' dossiers' : ' dossier');
                        }
                    })
                },
                scales: {
                    x: Object.assign(grille(T, 'categorie'), {
                        ticks: {
                            color: T.tick,
                            font: { family: 'Inter, sans-serif', size: 11 },
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 8
                        }
                    }),
                    y: Object.assign(grille(T, 'valeur'), { beginAtZero: true })
                }
            }
        });
    }

    /** #rrggbb -> rgba(...) ; laisse passer les couleurs déjà en rgb/rgba. */
    function hexA(couleur, alpha) {
        couleur = String(couleur).trim();
        if (couleur.charAt(0) !== '#') return couleur;
        var h = couleur.slice(1);
        if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
        var n = parseInt(h, 16);
        return 'rgba(' + ((n >> 16) & 255) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + alpha + ')';
    }

    /* ================================================================
       2. ANNEAUX (part d'un tout)
       ================================================================ */

    /**
     * Plugin local : inscrit un total et un intitulé au centre de l'anneau.
     * Enregistré par instance (et non globalement) pour ne pas polluer les
     * autres graphiques.
     */
    function centreTexte(valeur, legende, T) {
        return {
            id: 'centreTexte',
            afterDraw: function (chart) {
                var zone = chart.chartArea;
                if (!zone) return;
                var ctx = chart.ctx;
                var cx = (zone.left + zone.right) / 2;
                var cy = (zone.top + zone.bottom) / 2;

                ctx.save();
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';

                ctx.font = '700 22px Sora, sans-serif';
                ctx.fillStyle = T.text;
                ctx.fillText(valeur, cx, cy - 8);

                ctx.font = '500 11px Inter, sans-serif';
                ctx.fillStyle = T.tick;
                ctx.fillText(legende, cx, cy + 12);
                ctx.restore();
            }
        };
    }

    function renderAnneau(id, dataset, options) {
        var el = prepare(id, dataset);
        if (!el) return;

        options = options || {};
        var T = theme();
        var total = somme(dataset);
        // Libellé court pour la légende (sigle de faculté, « Masculin »…),
        // libellé complet conservé pour l'infobulle.
        var lbls = options.mapLabel ? dataset.map(options.mapLabel) : labels(dataset);
        var complets = options.mapComplet ? dataset.map(options.mapComplet) : labels(dataset);
        var couleurs = options.couleurs || T.series;

        instances[id] = new Chart(el, {
            type: 'doughnut',
            data: {
                labels: lbls,
                datasets: [{
                    data: totaux(dataset),
                    backgroundColor: couleurs,
                    borderColor: T.surface,
                    borderWidth: 2,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                animation: animation('anneau'),
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: T.tick,
                            font: { family: 'Inter, sans-serif', size: 11 },
                            boxWidth: 10,
                            boxHeight: 10,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 12,
                            // Le pourcentage est affiché dans la légende :
                            // l'information ne dépend donc jamais de la
                            // seule couleur de la part.
                            generateLabels: function (chart) {
                                var d = chart.data;
                                return d.labels.map(function (label, i) {
                                    var v = d.datasets[0].data[i];
                                    var pct = total ? Math.round((v / total) * 100) : 0;
                                    return {
                                        text: court(label, 22) + ' · ' + pct + '%',
                                        fillStyle: d.datasets[0].backgroundColor[i],
                                        strokeStyle: 'transparent',
                                        pointStyle: 'circle',
                                        hidden: false,
                                        index: i
                                    };
                                });
                            }
                        }
                    },
                    tooltip: tooltip(T, {
                        title: function (items) {
                            return complets[items[0].dataIndex];
                        },
                        label: function (item) {
                            var v = item.parsed;
                            var pct = total ? Math.round((v / total) * 100) : 0;
                            return nf(v) + ' dossiers · ' + pct + '%';
                        }
                    })
                }
            },
            plugins: [centreTexte(
                options.centreValeur || nf(total),
                options.centreLegende || 'dossiers',
                T
            )]
        });
    }

    /* ================================================================
       3. BARRES HORIZONTALES (libellés longs)
       ================================================================ */
    function renderBarresH(id, dataset, options) {
        var el = prepare(id, dataset);
        if (!el) return;

        options = options || {};
        var T = theme();
        var couleur = options.couleur || T.series[0];

        instances[id] = new Chart(el, {
            type: 'bar',
            data: {
                labels: labels(dataset).map(function (l) { return court(l, 28); }),
                datasets: [{
                    label: options.legende || 'Dossiers',
                    data: totaux(dataset),
                    backgroundColor: options.degrade ? dataset.map(function (_, i) {
                        return T.series[i % T.series.length];
                    }) : hexA(couleur, .85),
                    hoverBackgroundColor: couleur,
                    borderRadius: 5,
                    borderSkipped: false,
                    barThickness: 'flex',
                    maxBarThickness: 26
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                animation: animation('barres'),
                plugins: {
                    legend: { display: false },
                    tooltip: tooltip(T, {
                        // Le libellé de l'axe est tronqué : le tooltip
                        // rétablit toujours le nom complet.
                        title: function (items) {
                            return dataset[items[0].dataIndex].label;
                        },
                        label: function (item) {
                            var n = item.parsed.x;
                            return n + (n > 1 ? ' dossiers' : ' dossier');
                        }
                    })
                },
                scales: {
                    x: Object.assign(grille(T, 'valeur'), { beginAtZero: true }),
                    y: Object.assign(grille(T, 'categorie'), {
                        ticks: {
                            color: T.tick,
                            font: { family: 'Inter, sans-serif', size: 11 },
                            autoSkip: false
                        }
                    })
                }
            }
        });
    }

    /* ================================================================
       4. BARRES EMPILÉES HORIZONTALES (croisement faculté x sexe)
       ================================================================ */
    function renderEmpile(id, dataset) {
        var el = prepare('chart-faculte-sexe', (dataset || []).map(function (x) {
            return { label: x.label, total: (+x.hommes || 0) + (+x.femmes || 0) };
        }));
        if (!el) return;

        var T = theme();

        instances[id] = new Chart(el, {
            type: 'bar',
            data: {
                // Sigle si disponible : sur un axe vertical étroit, les
                // intitulés complets seraient tronqués au même préfixe.
                labels: dataset.map(function (x) { return x.code || court(x.label, 22); }),
                datasets: [
                    {
                        label: 'Hommes',
                        data: dataset.map(function (x) { return +x.hommes; }),
                        backgroundColor: T.series[2],
                        borderRadius: { topLeft: 4, bottomLeft: 4 },
                        borderSkipped: false
                    },
                    {
                        label: 'Femmes',
                        data: dataset.map(function (x) { return +x.femmes; }),
                        backgroundColor: T.series[1],
                        borderRadius: { topRight: 4, bottomRight: 4 },
                        borderSkipped: false
                    }
                ]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                animation: animation('barres'),
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: T.tick,
                            font: { family: 'Inter, sans-serif', size: 11 },
                            boxWidth: 10,
                            boxHeight: 10,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 12
                        }
                    },
                    tooltip: tooltip(T, {
                        title: function (items) {
                            return dataset[items[0].dataIndex].label;
                        },
                        label: function (item) {
                            return item.dataset.label + ' : ' + nf(item.parsed.x);
                        }
                    })
                },
                scales: {
                    x: Object.assign(grille(T, 'valeur'), { stacked: true, beginAtZero: true }),
                    y: Object.assign(grille(T, 'categorie'), {
                        stacked: true,
                        ticks: {
                            color: T.tick,
                            font: { family: 'Inter, sans-serif', size: 11 },
                            autoSkip: false
                        }
                    })
                }
            }
        });
    }

    /* ================================================================
       POINT D'ENTRÉE
       ================================================================ */
    function render(data) {
        if (typeof Chart === 'undefined') return;
        if (data) dernieresDonnees = data;
        var A = dernieresDonnees;
        if (!A) return;

        var T = theme();

        renderEvolution(A.evolution);

        renderAnneau('chart-faculte', A.parFaculte, {
            mapLabel: function (x) { return x.code || x.label; },
            mapComplet: function (x) { return x.label; },
            centreLegende: 'dossiers'
        });

        var libelleSexe = function (x) {
            return x.label === 'M' ? 'Masculin' : (x.label === 'F' ? 'Féminin' : 'Non précisé');
        };

        renderAnneau('chart-sexe', A.parSexe, {
            mapLabel: libelleSexe,
            mapComplet: libelleSexe,
            // Bleu / or : les deux teintes restent distinguables en
            // deutéranopie, contrairement à un couple rouge/vert.
            couleurs: [T.series[2], T.series[1], T.series[7]],
            centreLegende: 'candidats'
        });

        renderBarresH('chart-region', A.parRegion, { couleur: T.series[1] });
        renderBarresH('chart-filiere', A.parFiliere, { couleur: T.series[0] });
        renderEmpile('chart-faculte-sexe', A.faculteSexe || []);

        // Total affiché dans l'en-tête de la carte « Évolution ».
        var totalEvo = document.querySelector('[data-total-for="chart-evolution"]');
        if (totalEvo) {
            var t = somme(A.evolution);
            totalEvo.textContent = t ? nf(t) + (t > 1 ? ' dossiers' : ' dossier') : '';
        }
    }

    function detruireTout() {
        Object.keys(instances).forEach(detruire);
    }

    window.uebCharts = { render: render, destroy: detruireTout };

    // Le changement de thème impose un redessin complet : les couleurs sont
    // figées dans les instances Chart.js au moment de leur création.
    document.addEventListener('uebThemeChange', function () { render(); });

    // Compatibilité avec l'ancien contrat d'appel (window.uebAnalytics).
    document.addEventListener('uebAnalyticsReady', function () {
        if (window.uebAnalytics) render(window.uebAnalytics);
    });

}());
