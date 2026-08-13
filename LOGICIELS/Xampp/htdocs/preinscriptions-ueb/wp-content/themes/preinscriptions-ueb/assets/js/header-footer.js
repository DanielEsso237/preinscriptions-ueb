(function () {
  'use strict';
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