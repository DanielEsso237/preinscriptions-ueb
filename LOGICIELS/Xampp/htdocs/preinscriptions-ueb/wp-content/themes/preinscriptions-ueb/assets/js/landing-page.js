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

  /* --- Onglets des campus (bascule automatique) ---
     Le minuteur est la jauge CSS `.tab-prog` de l'onglet actif : sa fin
     d'animation declenche le campus suivant. Avantage : la pause (survol,
     focus, hors ecran, onglet navigateur masque) suspend l'animation ET le
     minuteur d'un seul coup, et `prefers-reduced-motion` (animation:none)
     desactive naturellement le defilement — les onglets restent cliquables. */
  (function () {
    var root = document.querySelector('[data-campus]');
    if (!root) return;
    var tabs = Array.prototype.slice.call(root.querySelectorAll('.tab')),
        panels = Array.prototype.slice.call(root.querySelectorAll('.panel'));
    if (tabs.length < 2 || tabs.length !== panels.length) return;
    var idx = 0, hovered = false, onscreen = false;

    function activate(i, focusTab) {
      idx = (i + tabs.length) % tabs.length;
      tabs.forEach(function (t, n) {
        var on = (n === idx);
        t.classList.toggle('active', on);
        t.setAttribute('aria-selected', on ? 'true' : 'false');
        t.tabIndex = on ? 0 : -1;
      });
      panels.forEach(function (p, n) { p.classList.toggle('show', n === idx); });
      if (focusTab) tabs[idx].focus();
    }

    function sync() {
      root.classList.toggle('is-paused', hovered || !onscreen || document.hidden);
    }

    /* Fin de la jauge => campus suivant. */
    root.addEventListener('animationend', function (e) {
      if (e.animationName === 'tabProg') activate(idx + 1);
    });

    tabs.forEach(function (t, n) {
      t.addEventListener('click', function () { if (n !== idx) activate(n); });
      t.addEventListener('keydown', function (e) {
        var d = (e.key === 'ArrowRight') ? 1 : (e.key === 'ArrowLeft') ? -1 : 0;
        if (!d) return;
        e.preventDefault();
        activate(idx + d, true);
      });
    });

    ['mouseenter', 'focusin'].forEach(function (ev) {
      root.addEventListener(ev, function () { hovered = true; sync(); });
    });
    ['mouseleave', 'focusout'].forEach(function (ev) {
      root.addEventListener(ev, function () { hovered = false; sync(); });
    });
    document.addEventListener('visibilitychange', sync);
    new IntersectionObserver(function (entries) {
      onscreen = entries[0].isIntersecting;
      sync();
    }, { threshold: 0.2 }).observe(root);
    sync();
  })();

  /* --- Mosaique "vie sur le campus" : les vignettes permutent avec la
     grande photo, chacune son tour, en fondu enchaine. Chaque tuile recoit
     un second calque <img> ; on echange les sources puis on croise les
     opacites, ce qui evite tout deplacement d'element (pas de reflow). --- */
  (function () {
    var grid = document.querySelector('[data-vswap]');
    if (!grid || reduce) return;
    var big = grid.querySelector('.vt.big'),
        smalls = Array.prototype.slice.call(grid.querySelectorAll('.vt')).filter(function (t) { return t !== big; });
    if (!big || !smalls.length) return;

    function layers(tile) {
      if (tile.dataset.ready) return tile._layers;
      var img = tile.querySelector('img');
      if (!img) return null;
      var clone = img.cloneNode(false);
      clone.alt = '';
      clone.setAttribute('aria-hidden', 'true');
      clone.classList.add('vt-layer');
      img.classList.add('vt-layer', 'is-on');
      tile.appendChild(clone);
      tile._layers = [img, clone];
      tile._active = 0;
      tile._token = 0;
      tile.dataset.ready = '1';
      return tile._layers;
    }

    /* L'ordre des photos est tenu en memoire (et non relu dans le DOM) : le
       fondu attend le decodage de l'image, donc deux permutations peuvent se
       chevaucher si le reseau traine. Le jeton neutralise alors le fondu
       devenu obsolete, sans jamais perdre ni dupliquer une photo. */
    function show(tile, photo) {
      var L = layers(tile);
      if (!L) return;
      var token = ++tile._token;
      var next = L[1 - tile._active];
      if (next.getAttribute('src') !== photo.src) next.src = photo.src;
      function apply() {
        if (token !== tile._token) return;
        var cur = L[tile._active];
        if (cur === next) return;
        next.setAttribute('alt', photo.alt);
        next.removeAttribute('aria-hidden');
        next.classList.add('is-on');
        cur.classList.remove('is-on');
        cur.setAttribute('alt', '');
        cur.setAttribute('aria-hidden', 'true');
        tile._active = 1 - tile._active;
        tile.classList.add('is-swap');
        setTimeout(function () { tile.classList.remove('is-swap'); }, 900);
      }
      if (next.decode) { next.decode().then(apply).catch(apply); } else { apply(); }
    }

    var tiles = [big].concat(smalls),
        photos = tiles.map(function (t) {
          var i = t.querySelector('img');
          return { src: i.getAttribute('src'), alt: i.getAttribute('alt') || '' };
        }),
        k = 0, timer = null, hovered = false, onscreen = false;

    function step() {
      var j = 1 + (k % smalls.length), swap = photos[0];
      photos[0] = photos[j];
      photos[j] = swap;
      show(tiles[0], photos[0]);
      show(tiles[j], photos[j]);
      k++;
    }

    function sync() {
      var run = onscreen && !hovered && !document.hidden;
      if (run && !timer) timer = setInterval(step, 3400);
      else if (!run && timer) { clearInterval(timer); timer = null; }
    }

    grid.addEventListener('mouseenter', function () { hovered = true; sync(); });
    grid.addEventListener('mouseleave', function () { hovered = false; sync(); });
    document.addEventListener('visibilitychange', sync);
    new IntersectionObserver(function (entries) {
      onscreen = entries[0].isIntersecting;
      sync();
    }, { threshold: 0.25 }).observe(grid);
  })();

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


  /* --- Bouton "retour en haut" --- */
  (function () {
    var btn = document.getElementById('back-to-top');
    if (!btn) return;
    var seuil = 480;

    function toggle() {
      var y = window.scrollY || document.documentElement.scrollTop;
      btn.classList.toggle('show', y > seuil);
    }

    toggle();
    window.addEventListener('scroll', toggle, { passive: true });

    btn.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
    });
  })();

})();