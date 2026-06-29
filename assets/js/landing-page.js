/*
 * Scripts de la landing page (front-page.php) — theme Preinscriptions UEB.
 * Charge uniquement sur la page d'accueil (footer), apres le rendu du DOM.
 * Anime : apparitions au scroll, compteurs, onglets campus, barre de
 * progression, menu hamburger (mobile), diaporama du hero.
 */
(function () {
  'use strict';

  var reduce = window.matchMedia('(prefers-reduced-motion:reduce)').matches;

  /* --- Apparitions au scroll (reveal) --- */
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
    });
  }, { threshold: 0.14 });
  document.querySelectorAll('.reveal').forEach(function (el) { io.observe(el); });

  /* --- Compteurs animes (chiffres cles) --- */
  function count(el) {
    var t = +el.dataset.count, suf = el.dataset.suffix || '';
    if (reduce) { el.textContent = t.toLocaleString('fr') + suf; return; }
    var st = performance.now(), d = 1400;
    (function f(n) {
      var p = Math.min((n - st) / d, 1);
      p = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.floor(p * t).toLocaleString('fr') + suf;
      if (p < 1) requestAnimationFrame(f);
    })(st);
  }
  var cio = new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      if (e.isIntersecting) { count(e.target); cio.unobserve(e.target); }
    });
  }, { threshold: 0.6 });
  document.querySelectorAll('.num[data-count]').forEach(function (el) { cio.observe(el); });

  /* --- Barre de progression des etapes --- */
  var pio = new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      if (e.isIntersecting) {
        var bar = e.target.querySelector('i');
        if (bar) bar.style.width = '100%';
        pio.unobserve(e.target);
      }
    });
  }, { threshold: 0.5 });
  document.querySelectorAll('.progress').forEach(function (el) { pio.observe(el); });

  /* --- Onglets des campus --- */
  var tabs = document.querySelectorAll('.tab'),
      panels = document.querySelectorAll('.panel');
  tabs.forEach(function (t) {
    t.addEventListener('click', function () {
      tabs.forEach(function (x) { x.classList.remove('active'); });
      panels.forEach(function (p) { p.classList.remove('show'); });
      t.classList.add('active');
      var target = panels[+t.dataset.t];
      if (target) target.classList.add('show');
    });
  });

  /* --- Menu hamburger (mobile) --- */
  (function () {
    var burger = document.getElementById('burger'),
        links = document.getElementById('mnav');
    if (!burger || !links) return;
    function setOpen(o) {
      burger.classList.toggle('open', o);
      links.classList.toggle('open', o);
      burger.setAttribute('aria-expanded', o ? 'true' : 'false');
      burger.setAttribute('aria-label', o ? 'Fermer le menu' : 'Ouvrir le menu');
    }
    burger.addEventListener('click', function () { setOpen(!links.classList.contains('open')); });
    links.querySelectorAll('a').forEach(function (a) {
      a.addEventListener('click', function () { setOpen(false); });
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
    window.addEventListener('resize', function () { if (window.innerWidth > 860) setOpen(false); });
  })();

  /* --- Diaporama du hero (fondu enchaine, 3 images) --- */
  document.querySelectorAll('.hero-slider').forEach(function (s) {
    var im = Array.prototype.slice.call(s.children).filter(function (n) { return n.tagName === 'IMG'; });
    if (im.length < 2) return;
    im.forEach(function (x) { x.classList.remove('on'); });
    var i = 0;
    im[0].classList.add('on');
    if (reduce) return;
    setInterval(function () {
      im[i].classList.remove('on');
      i = (i + 1) % im.length;
      im[i].classList.add('on');
    }, 5000);
  });

})();
