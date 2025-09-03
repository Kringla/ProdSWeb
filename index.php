<?php
require_once __DIR__ . '/includes/bootstrap.php';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/menu.php';

// ----------------------------------------------------------
// Midlertidig feilsøking
// ----------------------------------------------------------
// Hvis du opplever at "Administrasjon"-knappen eller andre deler av siden bare viser en
// blank side, kan du skru på PHP-feilrapportering midlertidig. Dette gjør at
// eventuelle fatale feil skrives ut på skjermen. Husk å fjerne eller deaktivere
// denne koden når feilen er funnet, da det ikke er lurt å vise interne feil til
// sluttbrukerne i produksjon.
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
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

<?php
$files = glob(__DIR__ . '/assets/img/hero/hero*.{jpg,jpeg,webp,png}', GLOB_BRACE);
natsort($files);
$heroUrls = array_map(fn($p) => $BASE . '/assets/img/hero/' . basename($p), $files);
?>

<section class="hero hero-rotator"
         data-images='<?= json_encode(array_values($heroUrls), JSON_UNESCAPED_SLASHES) ?>'
         data-interval="3500">
  <div class="hero-overlay"></div>
  <div class="container hero-inner">
    <h1>Finn fartøy, verft og rederier</h1>
    <div class="cta">
      <a class="btn" href="<?= $BASE ?>/user/fartoy_navn_sok.php">Søk fartøynavn</a>
      <a class="btn" href="<?= $BASE ?>/user/fartoy_spes_sok.php">Søk spesifikasjoner</a>
      <a class="btn" href="<?= $BASE ?>/user/verft_sok.php">Søk verfts bygg</a>
      <a class="btn" href="<?= $BASE ?>/user/rederi_sok.php">Søk rederiers fartøy</a>
      <?php if (!$loggedIn): ?>
        <!-- Administrasjon-knappen sender brukeren til innlogging med redirect til admin-siden.
             Vi sender et rent relativt sti-argument uten urlencoding. Slashes i stien må bevares
             for at login.php skal kunne tolke destinasjonen korrekt. -->
        <?php 
        /*
         * Destinasjonsstien skal begynne med en skråstrek. Vi peker nå på vår egen innloggingsside
         * (auth_login.php) for å unngå konflikt med andre systemers login-filer. Skråstreker bevares.
         */
        $adminNext = '/admin/fartoy_admin.php';
        ?>
        <a class="btn ghost" href="<?= $BASE ?>/auth_login.php?next=<?= $adminNext ?>">Administrasjon</a>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="container mt-3">
  <div class="card" style="padding:0 1.5rem;line-height:1.55;">
    <h2 style="margin:0 0 .25rem 0; font-size:1.6rem;color: Blue;">Velkommen til <span style="color:Blue;">SkipsWeb</span></h2>
    <h3>Søk fritt uten innlogging. Administrasjon og endringer av innhold krever innlogging.</h3>
    <p class="muted">“SkipsWeb” er en database som gir adgang til data for norske og utenlandske fartøyer som er omtalt i <em>Dampskipspostens</em> 125 numre, 
      og data om fartøyer samlet av <em>Ole Harald Fiske</em> gjennom mange års arbeide for <em>Norsk Maritimt Museum</em>. 
      Noen av de eksisterer i <em>Digitalt Museum</em>. Databasen er utviklet av frivillige entusiaster som bidrar til å registrere bilder av fartøyer ved Norsk Maritimt Museum.
      <hr class="red-line">
      <br><strong>Merk:</strong> Databasen har ingen offisell status ved museet. Riktigheten av dataen er ikke verifisert.</p>
      <hr class="red-line">
      <p class="muted">Du kan søke etter:</p>
      <ul style="margin:.25rem 0 1rem; font-size:1.05rem;text-align: left;">
        <li><span style="color:Blue;">Fartøyer direkte.</span> Med fritekst på hele eller deler av fartøysnavn, med ev. filter for nasjoner. Det gir deg en liste over fartøyer.</li>
        <li style="line-height:1.25;"><span style="color:Blue;">Rederiers fartøy.</span> Med fritekst på hele eller deler av rederiers navn. Det gir deg en liste over de fartøyer det valgte rederiet har disponert.</li>
        <li style="line-height:1.25;"><span style="color:Blue;">Verfts-bygde fartøy.</span> Med fritekst å hele eller deler av verfts navn. Det gir deg en liste over de fartøyer som verftet har bygd.</li>
      </ul>
      <p class="muted">Søket skjelner ikke mellom store og små bokstaver, og fartøysnavn skal brukes uten 'M/S', D/S' o.l. <br> Felles for alle de listete fartøyene er at tilgjengelige data, historie, spesifikasjoner og linker til andre kilder kan vises ved å velge fartøy. Fartøyenes <em>CV (historikk)</em> finnes for ca. 60&nbsp;% av fartøyene i databasen.</p>
    </div> 
      
    <div class= "card" style="padding:0 1.5rem;">
      <p class = "muted">Databasens tilgjengelige data om det enkelte fartøyene i basen varierer. For flere detaljer kan en finne de i databaser som er bedre vedrørende fartøyers detaljer og 
        tekniske spesifikasjoner.For gode, detaljerte beskrivelser av fartøyer vises det til f.eks. 
        <a href="https://www.sjohistorie.no/no" target="_blank" rel="noopener">sjøhistorie.no</a>,
        en svært godt utviklet (og mye større) database. Ellers kan du prøve Norsk Skipsfarthistorisk Selskaps
        skipsdatabase på <a href="https://skipshistorie.net/" target="_blank" rel="noopener">skipshistorie.net</a>, 
        eller Krigsseilerregisterets fartøyer på <a href=https://krigsseilerregisteret.no/skip?q target="_blank" rel="noopener">krigsseilerregisteret.no</a>.
      </p>
      <p class="muted">Bildene som vises i båndet over, er enten private eller hentet fra Digitalt  Museums 'frie' bilder.</p>
      <p class="muted">Søkene forbedres fortløpende basert på mottatte kommentarer. Innholdet utvikles fortløpende.</p>
	    <p class="muted">Lykke til med å finne det fartøyet du er på jakt etter!</p>
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
