(function () {
  'use strict';

  function labels(a) { return (a || []).map(function (x) { return x.label; }); }
  function data(a) { return (a || []).map(function (x) { return +x.total; }); }
  var palette = ['#2e7d32', '#378db7', '#d4a017', '#6e2a2a', '#61982b', '#1f6f94', '#8a5a00', '#4a4a4a', '#9c6b2e', '#2f6b52'];
  var baseBarOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
  };
  var basePieOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom' } }
  };

  // Registre des instances Chart.js actives, pour pouvoir les détruire
  // avant de redessiner (sinon Chart.js empile les canvas à chaque appel
  // et le rendu se corrompt visuellement).
  var instances = {};

  function destroyIfExists(id) {
    if (instances[id]) {
      instances[id].destroy();
      delete instances[id];
    }
  }

  function renderBar(id, dataset, titre, couleur) {
    destroyIfExists(id);
    var el = document.getElementById(id);
    if (!el || !dataset || !dataset.length) return;
    instances[id] = new Chart(el, {
      type: 'bar',
      data: { labels: labels(dataset), datasets: [{ label: titre, data: data(dataset), backgroundColor: couleur, borderRadius: 4 }] },
      options: Object.assign({}, baseBarOptions, { plugins: { legend: { display: false }, title: { display: true, text: titre, font: { size: 13 } } } })
    });
  }

  function renderPie(id, dataset, titre, mapLabel) {
    destroyIfExists(id);
    var el = document.getElementById(id);
    if (!el || !dataset || !dataset.length) return;
    var lbls = mapLabel ? dataset.map(mapLabel) : labels(dataset);
    instances[id] = new Chart(el, {
      type: 'pie',
      data: { labels: lbls, datasets: [{ data: data(dataset), backgroundColor: palette }] },
      options: Object.assign({}, basePieOptions, { plugins: { legend: { position: 'bottom' }, title: { display: true, text: titre, font: { size: 13 } } } })
    });
  }

  function renderStackedBar(id, dataset, titre) {
    destroyIfExists(id);
    var el = document.getElementById(id);
    if (!el || !dataset || !dataset.length) return;
    instances[id] = new Chart(el, {
      type: 'bar',
      data: {
        labels: dataset.map(function (x) { return x.label; }),
        datasets: [
          { label: 'Hommes', data: dataset.map(function (x) { return +x.hommes; }), backgroundColor: '#1f6f94' },
          { label: 'Femmes', data: dataset.map(function (x) { return +x.femmes; }), backgroundColor: '#9c6b2e' }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' }, title: { display: true, text: titre, font: { size: 13 } } },
        scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  }

  /**
   * Point d'entrée principal : (re)dessine tous les graphiques à partir de
   * window.uebAnalytics. Appelé une première fois au chargement, puis à
   * chaque fois que admin-dashboard.js recharge les stats (changement de
   * filtre), via l'événement 'uebAnalyticsReady'.
   */
  function renderAll() {
    if (typeof window.uebAnalytics === 'undefined') return;
    var A = window.uebAnalytics;

    renderBar('chart-faculte', A.parFaculte, 'Répartition par faculté', '#2e7d32');
    renderBar('chart-filiere', A.parFiliere, 'Répartition par filière (1er choix)', '#378db7');
    renderBar('chart-region', A.parRegion, "Répartition par région d'origine", '#d4a017');
    renderPie('chart-sexe', A.parSexe, 'Répartition par sexe', function (x) {
      return x.label === 'M' ? 'Masculin' : 'Féminin';
    });
    renderStackedBar('chart-faculte-sexe', A.faculteSexe, 'Répartition par faculté et sexe');

    var elEvo = document.getElementById('chart-evolution');
    destroyIfExists('chart-evolution');
    if (elEvo && A.evolution && A.evolution.length) {
      instances['chart-evolution'] = new Chart(elEvo, {
        type: 'line',
        data: {
          labels: labels(A.evolution),
          datasets: [{ label: 'Inscriptions / jour', data: data(A.evolution), borderColor: '#2e7d32', backgroundColor: 'rgba(46,125,50,.08)', fill: true, tension: .25, pointRadius: 4 }]
        },
        options: Object.assign({}, baseBarOptions, { plugins: { legend: { display: false }, title: { display: true, text: 'Évolution des inscriptions', font: { size: 14 } } } })
      });
    }
  }

  document.addEventListener('uebAnalyticsReady', renderAll);
  document.addEventListener('DOMContentLoaded', renderAll);
  if (document.readyState !== 'loading') renderAll();

  // ── Onglets Liste / Statistiques (pur JS, pas de rechargement) ──
  var tabs   = document.querySelectorAll('.admin-tab-btn');
  var panels = document.querySelectorAll('.admin-tab-panel');
  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      var cible = tab.dataset.tab;
      tabs.forEach(function (t) { t.classList.toggle('active', t === tab); });
      panels.forEach(function (p) { p.classList.toggle('active', p.id === 'admin-tab-' + cible); });
      var url = new URL(window.location.href);
      url.searchParams.set('onglet', cible);
      window.history.replaceState({}, '', url);
    });
  });

}());
