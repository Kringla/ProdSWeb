1. Mål (Goal)

Hva: Ønsker utvide Navne historikk blokken i 

Hvorfor: Forbedring

Må være oppfylt innen: [Evt. dato/milepæl]

2. Scope (Omfang)

Berørte filer (eksakt sti):

 user/...

 assets/css/app.css (kryss av hvis lov)

Ikke rør:

 SQL/DB-logikk

 index.php hero-rotator (JS + CSS)

 Autentisering/sesjon

Tillatte endringer:

 Kun layout/markup

 Lov å legge til små CSS-klasser nederst i app.css

 Ikke endre eksisterende klassenavn/global stil

3. Rammer og referanser

Prosjektfiler (siste versjon, GitHub):
Bruk alltid nyeste filer i Kringla/ProdSWeb.

Styrende dokumenter (versjonskrav):

PRD: SkipsWeb_PRD v*.txt

PS: SkipsWeb_PS v*.txt

SCHEMA: SkipsWeb_SCHEMA v*.sql
(Skriv inn faktisk versjon brukt i denne endringen)

PRD versjon: v__

PS versjon: v__

SCHEMA versjon: v__

4. Detaljert spesifikasjon

Nåværende oppførsel: [Beskriv kort dagens atferd]

Ønsket oppførsel: [Beskriv resultatet]

UI/layout‑regler:

 Heading(er) skal være midtstilt

 Ingen cropping av bilder (bruk object-fit: contain)

 Bevar aspektforhold (aspect-ratio der det er relevant)

 Ikke påvirk knappestørrelser eller hero‑rotator

Data/DB: [Ingen/evt. felter/queries som berøres]

5. Anker i koden (for trygg innfasing)

Påkrevd for alle endringer som ikke er “full fil”.

Innsettingssted / Erstatning:

To linjer FØR:

[lim inn 1–2 faktiske linjer før]


MÅL-blokk (åpningslinje, som endres eller som det settes etter):

[lim inn åpningslinjen – f.eks. <!-- Hovedinfo-boks --> eller <h1>...</h1>]


To linjer ETTER:

[lim inn 1–2 faktiske linjer etter]


Leveranseformat (velg én):

 Full erstatningsblokk (HTML/PHP/CSS)

 Full fil (kun hvis eksplisitt godkjent)

 Patch (diff) med før/etter

6. Akseptkriterier (Acceptance Criteria)

 Visuelt: endringen er tydelig i riktig blokk/komponent

 Ingen andre sider påvirkes (spesielt index.php/hero‑rotator)

 Responsiv oppførsel (mobil/desktop) er intakt

 Ingen PHP‑warnings/notices

 Ingen XSS/sti‑risiko (bruk h() og basename() der det gjelder)

 Eksplisitte “før/etter”-skjermbilder vedlagt (valgfritt men ønskelig)

7. Testplan (hvordan testes dette)

Lokal (XAMPP/Laragon):

 Laste siden(e) direkte og verifisere layout

 CTRL+F5 hard refresh (cache)

Staging/Prod (hvis relevant):

 Samme sjekker

Browsere:

 Chrome

 Edge

 Firefox

Skjermbredder:

 < 480px

 768–1024px

 > 1200px

8. Sikkerhet og kvalitet

HTML/PHP:

 Bruk h() for output‑escaping

 Bruk basename() på filnavn hvis dynamiske bildekilder

CSS:

 Ikke bruk !important med mindre nødvendig

 Scope endringer (f.eks. .card.centered-card)

JS:

 Ikke endre hero-rotator.js

9. Risiko og rollback

Risiko: [Lav/Moderat/Høy] – beskriv hvorfor

Rollback‑plan: [Hva reverteres hvis noe går galt]

Avhengigheter: [Lenker til andre pågående CRs eller branches]

10. Godkjenning

Faglig godkjenning: [Navn/dato]

Teknisk godkjenning: [Navn/dato]

Merge gjort av: [Navn/dato]

11. Sjekkliste før merge

 Koden følger scope og anker

 Akseptkriterier innfridd

 Testplan gjennomført (minst lokalt)

 Ingen utilsiktede diffs i andre filer

 Kommentarer/“TODOs” ryddet bort eller begrunnet

12. Etter‑merge oppgaver

 Oppdater PRD/PS (hvis nødvendig)

 Oppdater skjermbilder/dokumentasjon

 Notér endringen i CHANGELOG/Release‑notes

📌 Mini‑eksempel (utfylt)

Mål: Midtstill alt innhold i Hovedinfo‑boks i user/fartoydetaljer.php.
Scope: Kun layout i én blokk; ikke rør SQL/rotator.
Anker:

FØR:

<!-- Hovedinfo-boks -->
<div class="card" style="padding:1rem; margin-bottom:1rem;">


ETTER:

<div class="meta"


Blokk som settes inn/endres (kort):

<!-- Hovedinfo-boks -->
<div class="card centered-card" style="padding:1rem; margin-bottom:1rem; text-align:center;">
  <div style="display:flex; justify-content:center;">
    <h2 style="margin-top:0; font-size:1.8rem; font-weight:600;"><?= h($displayName) ?></h2>
  </div>
  <div class="meta" style="display:flex; gap:1.5rem; flex-wrap:wrap; justify-content:center; align-items:center;">
    <div style="text-align:center;"><strong>Objekt-ID:</strong> <?= (int)$main['FartObj_ID'] ?></div>
    <!-- …resten uendret… -->
  </div>
</div>


Akseptkriterier: Overskrift + .meta visuelt midtstilt (mobil/desktop), ingen andre sider påvirket.
Test: Lokal i Chrome/Edge; hard refresh.
Godkjenning: Signatur/dato