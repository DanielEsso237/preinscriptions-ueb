(function () {
  'use strict';
  if (typeof window.uebAnalytics === 'undefined') return;

  function labels(a) { return a.map(function (x) { return x.label; }); }
  function data(a) { return a.map(function (x) { return +x.total; }); }

  var baseOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
  };

  var el;

  el = document.getElementById('chart-faculte');
  if (el) new Chart(el, {
    type: 'bar',
    data: { labels: labels(uebAnalytics.parFaculte), datasets: [{ label: 'Par faculté', data: data(uebAnalytics.parFaculte), backgroundColor: '#2e7d32', borderRadius: 4 }] },
    options: Object.assign({}, baseOptions, { plugins: { legend: { display: false }, title: { display: true, text: 'Répartition par faculté', font: { size: 14 } } } })
  });

  el = document.getElementById('chart-filiere');
  if (el) new Chart(el, {
    type: 'bar',
    data: { labels: labels(uebAnalytics.parFiliere), datasets: [{ label: 'Par filière', data: data(uebAnalytics.parFiliere), backgroundColor: '#378db7', borderRadius: 4 }] },
    options: Object.assign({}, baseOptions, { plugins: { legend: { display: false }, title: { display: true, text: 'Répartition par filière', font: { size: 14 } } } })
  });

  el = document.getElementById('chart-region');
  if (el) new Chart(el, {
    type: 'bar',
    data: { labels: labels(uebAnalytics.parRegion), datasets: [{ label: "Par région d'origine", data: data(uebAnalytics.parRegion), backgroundColor: '#d4a017', borderRadius: 4 }] },
    options: Object.assign({}, baseOptions, { plugins: { legend: { display: false }, title: { display: true, text: "Répartition par région d'origine", font: { size: 14 } } } })
  });

  el = document.getElementById('chart-sexe');
  if (el) new Chart(el, {
    type: 'pie',
    data: {
      labels: uebAnalytics.parSexe.map(function (x) { return x.label === 'M' ? 'Masculin' : 'Féminin'; }),
      datasets: [{ data: data(uebAnalytics.parSexe), backgroundColor: ['#378db7', '#6e2a2a'] }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' }, title: { display: true, text: 'Répartition par sexe', font: { size: 14 } } } }
  });

  el = document.getElementById('chart-evolution');
  if (el) new Chart(el, {
    type: 'line',
    data: { labels: labels(uebAnalytics.evolution), datasets: [{ label: 'Inscriptions / jour', data: data(uebAnalytics.evolution), borderColor: '#2e7d32', backgroundColor: 'rgba(46,125,50,.08)', fill: true, tension: .25, pointRadius: 4 }] },
    options: Object.assign({}, baseOptions, { plugins: { legend: { display: false }, title: { display: true, text: 'Évolution des inscriptions', font: { size: 14 } } } })
  });
})();