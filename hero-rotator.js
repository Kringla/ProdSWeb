// assets/js/hero-rotator.js — jevn timing via forhåndslast & decode
(function () {
  function parseImages(raw) {
    if (!raw) return [];
    try { return JSON.parse(raw); } catch (_) {}
    try { return Function("return " + raw)(); } catch (_) {}
    return [];
  }

  function preloadAll(urls) {
    return Promise.all(urls.map(function (u) {
      return new Promise(function (resolve) {
        var img = new Image();
        img.decoding = "async";
        img.onload = function () {
          if (img.decode) {
            img.decode().catch(function(){ /* ignore */ }).finally(resolve);
          } else {
            resolve();
          }
        };
        img.onerror = resolve; // ikke blokker rotasjon hvis ett bilde feiler
        img.src = u;
      });
    }));
  }

  function startRotation(el, urls, intervalMs) {
    // init – sett startverdier
    var i = 1;                      // neste indeks som skal vises
    var useB = true;                // veksle mellom --hero-a og --hero-b
    var a0 = 'url("' + urls[0] + '")';
    var b0 = 'url("' + (urls[1] || urls[0]) + '")';
    el.style.setProperty('--hero-a', a0);
    el.style.setProperty('--hero-b', b0);

    setInterval(function () {
      i = (i + 1) % urls.length;
      var nextUrl = 'url("' + urls[i] + '")';
      // bytt den "skjulte" varianten og toggle klassen for å eksponere den
      el.style.setProperty(useB ? '--hero-b' : '--hero-a', nextUrl);
      el.classList.toggle('is-b', useB);
      useB = !useB;
    }, intervalMs);
  }

  function init(selector) {
    var el = document.querySelector(selector || '.hero-rotator');
    if (!el) return;
    var raw = el.getAttribute('data-images') || '[]';
    var urls = parseImages(raw);
    if (!urls || urls.length < 1) return;

    var ms = parseInt(el.getAttribute('data-interval') || '6000', 10);
    if (!isFinite(ms) || ms < 1000) ms = 6000;

    // Forhåndslast & dekode alle før vi starter – jevn timing
    preloadAll(urls).then(function () {
      startRotation(el, urls, ms);
    });
  }

  window.HeroRotator = { init: init };
  document.addEventListener('DOMContentLoaded', function () { init(); });
})();
