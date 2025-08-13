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
      <strong>Innlogget.</strong> Gå til <a href="<?= $BASE ?>/admin/dashboard.php">Admin</a> (for admin) eller bruk søkene under.
    </div>
  </div>
<?php endif; ?>

<section class="hero hero-rotator"
         data-images='["<?= $BASE ?>/assets/img/hero1.jpg","<?= $BASE ?>/assets/img/hero2.jpg","<?= $BASE ?>/assets/img/hero3.jpg"]'
         style="--hero-a: url('<?= $BASE ?>/assets/img/hero1.jpg'); --hero-b: url('<?= $BASE ?>/assets/img/hero2.jpg');">
  <div class="hero-overlay"></div>
  <div class="container hero-inner">
    <h1>Finn fartøy, verft og rederier</h1>
    <p>Søk fritt uten innlogging. Administrasjon krever innlogging.</p>
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
        <li>fartøysnavn</li>
        <li>rederiers fartøyer</li>
        <li>verfts bygde fartøyer</li>
      </ul>
      <p>Lykke til med å finne det fartøyet du er på jakt etter.</p>
    </div>
    <div class="muted" style="margin-top:1rem;">
      Kommentarer, spørsmål og ønsker kan du sende til
      <a href="mailto:webman@skipsweb.no">webman@skipsweb.no</a>.
    </div>
  </div>

  <div class="grid cols-2 mt-3">
    <div class="card">
      <h3>Om søkene</h3>
      <p class="muted">S1 er på plass. Verft-/rederi-søk er nye, og vi forbedrer dem fortløpende.</p>
    </div>
    <div class="card">
      <h3>Rent og raskt</h3>
      <p class="muted">Lesing uten innlogging. Redigering bak innlogging.</p>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
