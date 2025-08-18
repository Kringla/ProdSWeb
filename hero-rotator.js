// assets/js/hero-rotator.js
(function () {
  // --- hjelpefunksjoner ----------------------------------------------------
  function basename(u) {
    var s = String(u || "");
    try { s = decodeURIComponent(s); } catch (_) {}
    s = s.split(/[?#]/)[0];
    var parts = s.split("/");
    return parts[parts.length - 1] || s;
  }
  function uniqByFilename(list) {
    var seen = Object.create(null), out = [];
    for (var i=0;i<list.length;i++){
      var k = basename(list[i]).toLowerCase();
      if (!seen[k]) { seen[k]=1; out.push(list[i]); }
    }
    return out;
  }
  function naturalSortByFilename(urls) {
    function parse(name){
      var m = name.match(/^(.*?)(\d+)(\D*)\.(\w+)$/);
      if (m) return { stem:m[1], num:parseInt(m[2],10), raw:name };
      var nums = (name.match(/\d+/g)||[]).map(Number);
      return { stem:name, num: nums.length? nums[0] : Number.MAX_SAFE_INTEGER, raw:name };
    }
    return urls.slice().sort(function(a,b){
      var A=parse(basename(a)), B=parse(basename(b));
      if (A.stem===B.stem && isFinite(A.num)&&isFinite(B.num) && A.num!==B.num) return A.num-B.num;
      if (isFinite(A.num)&&isFinite(B.num) && A.num!==B.num) return A.num-B.num;
      return A.raw.localeCompare(B.raw, undefined, {numeric:true, sensitivity:'base'});
    });
  }

  // --- init én rotator -----------------------------------------------------
  function initOne(el){
    if (!el || el.__rotatorRunning) return;
    el.__rotatorRunning = true;

    var raw = el.getAttribute('data-images') || '[]';
    var urls; try { urls = JSON.parse(raw); } catch(_) { return; }
    if (!Array.isArray(urls) || !urls.length) return;

    urls = uniqByFilename(urls);
    urls = naturalSortByFilename(urls);

    var ms = parseInt(el.getAttribute('data-interval'), 10);
    if (!isFinite(ms) || ms < 1000) ms = 3500;

    var i = 0;
    function toCss(u){ return 'url("' + String(u) + '")'; }

    // Sett første umiddelbart
    el.style.backgroundImage = toCss(urls[i]);

    // Roter i stigende rekkefølge
    setInterval(function(){
      i = (i + 1) % urls.length;
      el.style.backgroundImage = toCss(urls[i]);
    }, ms);

    // Debug i konsollen (frivillig)
    try { console.log('[HeroRotator] ' + urls.map(basename).join(' → ')); } catch(_){}
  }

  function init(selector){
    var els = document.querySelectorAll(selector || '.hero-rotator');
    for (var i=0;i<els.length;i++) initOne(els[i]);
  }

  window.HeroRotator = { init: init };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){ init(); });
  } else {
    init();
  }
})();
