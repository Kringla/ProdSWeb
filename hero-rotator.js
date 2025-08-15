// assets/js/hero-rotator.js
(function(){
  function initOne(el){
    if (!el) return;
    var json = el.getAttribute('data-images') || '[]';
    var imgs; try { imgs = JSON.parse(json); } catch(e){ return; }
    if (!imgs || imgs.length < 2) return;

    var ms = parseInt(el.getAttribute('data-interval')||'6000',10);
    if (!isFinite(ms) || ms < 1000) ms = 6000;

    // Start med --hero-a/--hero-b fra inline style i index.php
    var i = 1;
    var alt = true;
    setInterval(function(){
      var nextUrl = 'url("' + imgs[(i++) % imgs.length] + '")';
      el.style.setProperty(alt ? '--hero-b' : '--hero-a', nextUrl);
      el.classList.toggle('is-alt', alt);
      alt = !alt;
    }, ms);
  }

  window.HeroRotator = {
    init: function(selector){
      var sel = selector || '.hero-rotator';
      var el = document.querySelector(sel);
      if (el) initOne(el);
    }
  };

  document.addEventListener('DOMContentLoaded', function(){
    window.HeroRotator.init();
  });
})();
