<?php
// footer.php – LASTES ETTER alt innhold
$BASE = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
?>
<footer class="site-footer">
  <div class="container">
    <div class="muted">© <?= date('Y') ?> SkipsWeb</div>
  </div>
</footer>

<?php
// Last rotator-scriptet (legg filen i /assets/js/hero-rotator.js – se nedenfor)
$rotatorSrc = function_exists('asset')
  ? asset('/assets/js/hero-rotator.js')
  : ($BASE . '/assets/js/hero-rotator.js');
?>
<script src="<?= htmlspecialchars($rotatorSrc, ENT_QUOTES, 'UTF-8') ?>" defer></script>

<script>
// Fallback: hvis hero-rotator.js ikke finnes / ikke definerer HeroRotator, kjør en enkel rotasjon.
(function(){
  function initFallback(){
    var el = document.querySelector('.hero-rotator');
    if (!el) return;
    var json = el.getAttribute('data-images') || '[]';
    var imgs; try { imgs = JSON.parse(json); } catch(e){ return; }
    if (!imgs || imgs.length < 2) return;
    var i = 1, alt = true, ms = parseInt(el.getAttribute('data-interval')||'6000',10);
    function tick(){
      var url = 'url("' + imgs[(i++) % imgs.length] + '")';
      el.style.setProperty(alt ? '--hero-b' : '--hero-a', url);
      el.classList.toggle('is-alt', alt);
      alt = !alt;
    }
    setInterval(tick, isFinite(ms) ? ms : 6000);
  }

  // Hvis hero-rotator.js allerede definerer en init – bruk den. Ellers fallback.
  document.addEventListener('DOMContentLoaded', function(){
    if (window.HeroRotator && typeof window.HeroRotator.init === 'function') {
      window.HeroRotator.init();
    } else {
      initFallback();
    }
  });
})();
</script>

</body>
</html>
