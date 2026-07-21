(function () {
  'use strict';
  if (typeof window.uebAnalytics === 'undefined') return;

  var A = window.uebAnalytics;

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

  function renderBar(id, dataset, titre, couleur) {
    var el = document.getElementById(id);
    if (!el || !dataset || !dataset.length) return;
    new Chart(el, {
      type: 'bar',
      data: { labels: labels(dataset), datasets: [{ label: titre, data: data(dataset), backgroundColor: couleur, borderRadius: 4 }] },
      options: Object.assign({}, baseBarOptions, { plugins: { legend: { display: false }, title: { display: true, text: titre, font: { size: 13 } } } })
    });
  }

  function renderPie(id, dataset, titre, mapLabel) {
    var el = document.getElementById(id);
    if (!el || !dataset || !dataset.length) return;
    var lbls = mapLabel ? dataset.map(mapLabel) : labels(dataset);
    new Chart(el, {
      type: 'pie',
      data: { labels: lbls, datasets: [{ data: data(dataset), backgroundColor: palette }] },
      options: Object.assign({}, basePieOptions, { plugins: { legend: { position: 'bottom' }, title: { display: true, text: titre, font: { size: 13 } } } })
    });
  }

  // ── Graphes en barres ──
  renderBar('chart-faculte', A.parFaculte, 'Répartition par faculté', '#2e7d32');
  renderBar('chart-filiere', A.parFiliere, 'Répartition par filière (1er choix)', '#378db7');
  renderBar('chart-diplome', A.parDiplome, "Répartition par diplôme d'admission", '#61982b');
  renderBar('chart-niveau', A.parNiveauLmd, 'Répartition par niveau LMD', '#1f6f94');
  renderBar('chart-mention', A.parMention, 'Répartition par mention', '#8a5a00');
  renderBar('chart-nationalite', A.parNationalite, 'Répartition par nationalité (top 10)', '#4a4a4a');
  renderBar('chart-situation', A.parSituation, 'Répartition par situation matrimoniale', '#9c6b2e');
  renderBar('chart-statut-socio', A.parStatutSocio, 'Répartition par statut socio-professionnel', '#2f6b52');
  renderBar('chart-region', A.parRegion, "Répartition par région d'origine", '#d4a017');
  renderBar('chart-departement', A.parDepartement, "Répartition par département d'origine (top 15)", '#6e2a2a');
  renderBar('chart-sport', A.parSport, 'Répartition par sport préféré (top 10)', '#378db7');
  renderBar('chart-art', A.parArt, 'Répartition par art pratiqué (top 10)', '#d4a017');

  // ── Graphes en camembert ──
  renderPie('chart-type-formation', A.parTypeFormation, 'Répartition par type de formation', function (x) {
    return x.label === 'pro' ? 'Licence Pro' : 'Classique';
  });
  renderPie('chart-statut-etudiant', A.parStatutEtudiant, 'Répartition par statut étudiant');
  renderPie('chart-sexe', A.parSexe, 'Répartition par sexe', function (x) {
    return x.label === 'M' ? 'Masculin' : 'Féminin';
  });
  renderPie('chart-handicap', A.parHandicap, 'Répartition par situation de handicap', function (x) {
    return x.label === 'oui' ? 'Avec handicap' : 'Sans handicap';
  });
  renderPie('chart-langue', A.parLangue, 'Répartition par première langue');

  // ── Évolution (courbe, large) ──
  var elEvo = document.getElementById('chart-evolution');
  if (elEvo && A.evolution && A.evolution.length) {
    new Chart(elEvo, {
      type: 'line',
      data: {
        labels: labels(A.evolution),
        datasets: [{ label: 'Inscriptions / jour', data: data(A.evolution), borderColor: '#2e7d32', backgroundColor: 'rgba(46,125,50,.08)', fill: true, tension: .25, pointRadius: 4 }]
      },
      options: Object.assign({}, baseBarOptions, { plugins: { legend: { display: false }, title: { display: true, text: 'Évolution des inscriptions', font: { size: 14 } } } })
    });
  }

  // ── Onglets Liste / Statistiques (pur JS, pas de rechargement) ──
  var tabs   = document.querySelectorAll('.admin-tab');
  var panels = document.querySelectorAll('.admin-tab-panel');
  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      var cible = tab.dataset.onglet;
      tabs.forEach(function (t) { t.classList.toggle('active', t === tab); });
      panels.forEach(function (p) { p.classList.toggle('active', p.id === 'admin-panel-' + cible); });
      var url = new URL(window.location.href);
      url.searchParams.set('onglet', cible);
      window.history.replaceState({}, '', url);
    });
  });
})();