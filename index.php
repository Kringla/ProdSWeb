<?php
require_once __DIR__ . '/includes/bootstrap.php';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/menu.php';

$BASE = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
$loggedIn = !empty($_SESSION['user_id']);
?>

<?php if ($loggedIn): ?>
  <div class="container mt-3">
    <div class="card">
      <strong>Innlogget.</strong> Gå til <a href="<?= $BASE ?>/admin/dashboard.php">Admin</a> (kun for admin), eller bruk søkene under.
    </div>
  </div>
<?php endif; ?>

<section class="hero hero-rotator"
  data-images='[
    "<?= $BASE ?>/assets/img/hero1.jpg",
    "<?= $BASE ?>/assets/img/hero2.jpg",
    "<?= $BASE ?>/assets/img/hero3.jpg",
    "<?= $BASE ?>/assets/img/hero4.jpg",
    "<?= $BASE ?>/assets/img/hero5.jpg"
  ]'
  style="--hero-a: url('<?= $BASE ?>/assets/img/hero1.jpg'); --hero-b: url('<?= $BASE ?>/assets/img/hero2.jpg');">
  <div class="hero-overlay"></div>
  <div class="container hero-inner">
    <h1>Finn fartøy, verft og rederier</h1>
    <h2>Søk fritt uten innlogging. Administrasjon krever innlogging.</h2>
    <div class="cta">
      <a class="btn primary" href="<?= $BASE ?>/user/fartoy_nat.php">Søk fartøy</a>
      <a class="btn" href="<?= $BASE ?>/user/verft_sok.php">Søk verft</a>
      <a class="btn" href="<?= $BASE ?>/user/rederi_sok.php">Søk rederi</a>
      <?php if (!$loggedIn): ?>
        <a class="btn ghost" href="<?= $BASE ?>/login.php">Logg inn (admin)</a>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="container mt-4">
  <div class="card" style="padding:1.5rem 1.5rem 1.25rem;">
    <h2 style="margin:0 0 .25rem 0; font-size:1.6rem;">Velkommen til <span style="color:#2c3e50;">SkipsWeb</span></h2>
    <p class="muted" style="margin:.25rem 0 1rem 0; font-size:1.05rem;">
      “SkipsWeb” gir adgang til en database for norske og utenlandske fartøyer som eksisterer bl.a. i
      <em>Dampskipspostens</em> 125 numre, og i Digitalt Museum. Databasen er ganske fullstendig hva angår hvilke
      fartøyer som er nevnt, men det finnes databaser som er bedre vedrørende fartøyers detaljer og tekniske spesifikasjoner.
    </p>
    <div style="line-height:1.55;">
      <p>Fartøyenes <strong>CV (historikk)</strong> finnes for ca. 70&nbsp;% av fartøyene i databasen.</p>
      <p>For gode, detaljerte beskrivelser av fartøyer vises primært til
        <a href="https://www.sjohistorie.no/no" target="_blank" rel="noopener">Sjøhistorie.no</a>,
        en svært godt utviklet (og mye større) database. Ellers kan en prøve Norsk Skipsfarthistorisk Selskaps
        skipsdatabase på <a href="https://skipshistorie.net/" target="_blank" rel="noopener">skipshistorie.net</a>.
      </p>
      <p>Du kan søke på:</p>
      <ul style="margin:.25rem 0 1rem 1.1rem;">
        <li>Fritekst fartøysnavn, med ev. filter for nasjoner. Gir liste over fartøyer.</li>
        <li>Fritekst rederiers navn, og lister de fartøyer det valgte rederiet har disponert.</li>
        <li>Fritekst verft, og lister over de fartøyer som verftet har bygd.</li>
      </ul>
      <p>Felles for alle listete fartøyene er at tilgjengelige data, historie, spesifikasjoner og linker til andre kilder kan vises.
	  Lykke til med å finne det fartøyet du er på jakt etter.
	  </p>
    </div>
    <div class="muted" style="margin-top:1rem padding:1.5rem 1.5rem 1.25rem;";">
      Kommentarer, spørsmål og ønsker kan du sende til
      <a href="mailto:webman@skipsweb.no">webman@skipsweb.no</a>.
    </div>
  </div>

  <div class="grid cols-2 mt-3">
    <div class="card" style="padding:1.5rem 1.5rem 1.25rem;">
      <h3>Om søkene og dette nettstedet</h3>
      <p class="muted">Søkene forbedres fortløpende basert på mottatte kommentarer.
	  Innholdet utvikles fortløpende.
	  </p>
    </div>
    <div class="card" style="padding:1.5rem 1.5rem 1.25rem;">
      <h3>Innlogging?</h3>
      <p class="muted">Lesing kan gjøres uten innlogging. Redigering krever innlogging.</p>
    </div>
  </div>
</section>

<style>
  /* Enkel hero-stil (kan også ligge i app.css) */
  .hero{ position:relative; min-height: 46vh; display:flex; align-items:center; background: #222; color:#fff; }
  .hero.hero-rotator{ background-image: var(--hero-a); background-size:cover; background-position:center; }
  .hero.hero-rotator.is-b{ background-image: var(--hero-b); }
  .hero-overlay{ position:absolute; inset:0; background:rgba(0,0,0,.35); }
  .hero-inner{ position:relative; z-index:2; text-shadow:0 1px 2px rgba(0,0,0,.6); }
  .hero-inner h1{ margin:0 0 .5rem; font-size:clamp(1.8rem,4vw,3rem); }
  .hero-inner p{ font-size:clamp(1rem,1.6vw,1.25rem); margin:.25rem 0 1rem; }
  .cta{ display:flex; gap:.5rem; flex-wrap:wrap; }
</style>

<script>
/* Fallback-rotator: kjører KUN hvis hero-rotator.js ikke har initialisert */
(function(){
  function parseImages(raw){
    // Prøv JSON først (som i din oppdaterte hero-rotator.js)
    try { return JSON.parse(raw); } catch(e){}
    // Deretter en tolerant parser for array-litteraler
    raw = (raw||'').trim().replace(/^\[/,'').replace(/\]$/,'');
    return raw ? raw.split(',').map(function(s){ return s.trim().replace(/^['"]|['"]$/g,''); }).filter(Boolean) : [];
  }

  function initFallback(){
    var el = document.querySelector('.hero.hero-rotator');
    if(!el) return;
    var imgs = parseImages(el.getAttribute('data-images') || '[]');
    if(imgs.length === 0) return;

    // Sett startverdier (dersom ikke allerede satt via inline style)
    if (!getComputedStyle(el).getPropertyValue('--hero-a')) {
      el.style.setProperty('--hero-a', 'url("'+imgs[0]+'")');
    }
    el.style.setProperty('--hero-b', 'url("'+(imgs[1]||imgs[0])+'")');

    if(imgs.length < 2) return;

    var i = 1, sideB = true, interval = parseInt(el.getAttribute('data-interval')||'6000',10);
    if (!isFinite(interval) || interval < 1000) interval = 6000;

    setInterval(function(){
      i = (i+1) % imgs.length;
      var url = 'url("'+imgs[i]+'")';
      el.style.setProperty(sideB ? '--hero-a' : '--hero-b', url);
      sideB = !sideB;
      el.classList.toggle('is-b', !sideB);
    }, interval);
  }

  document.addEventListener('DOMContentLoaded', function(){
    // Hvis global HeroRotator finnes (fra hero-rotator.js), la den styre – ellers fallback.
    if (window.HeroRotator && typeof window.HeroRotator.init === 'function') {
      window.HeroRotator.init();
    } else {
      initFallback();
    }
  });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
