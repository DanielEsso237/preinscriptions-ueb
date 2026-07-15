(function () {
  'use strict';
  if (typeof window.uebAnalytics === 'undefined') return;

  function labels(a) { return a.map(function (x) { return x.label; }); }
  function data(a) { return a.map(function (x) { return +x.total; }); }

  var el;

  el = document.getElementById('chart-faculte');
  if (el) new Chart(el, { type: 'bar', data: { labels: labels(uebAnalytics.parFaculte), datasets: [{ label: 'Par faculté', data: data(uebAnalytics.parFaculte), backgroundColor: '#2e7d32' }] } });

  el = document.getElementById('chart-filiere');
  if (el) new Chart(el, { type: 'bar', data: { labels: labels(uebAnalytics.parFiliere), datasets: [{ label: 'Par filière', data: data(uebAnalytics.parFiliere), backgroundColor: '#378db7' }] } });

  el = document.getElementById('chart-region');
  if (el) new Chart(el, { type: 'bar', data: { labels: labels(uebAnalytics.parRegion), datasets: [{ label: "Par région d'origine", data: data(uebAnalytics.parRegion), backgroundColor: '#d4a017' }] } });

  el = document.getElementById('chart-sexe');
  if (el) new Chart(el, { type: 'pie', data: { labels: uebAnalytics.parSexe.map(function (x) { return x.label === 'M' ? 'Masculin' : 'Féminin'; }), datasets: [{ data: data(uebAnalytics.parSexe), backgroundColor: ['#378db7', '#6e2a2a'] }] } });

  el = document.getElementById('chart-evolution');
  if (el) new Chart(el, { type: 'line', data: { labels: labels(uebAnalytics.evolution), datasets: [{ label: 'Inscriptions/jour', data: data(uebAnalytics.evolution), borderColor: '#2e7d32', fill: false, tension: .2 }] } });
})();