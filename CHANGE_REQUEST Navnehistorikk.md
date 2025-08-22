1. Mål (Goal)

Hva: Ønsker utvide Navne historikk blokken i fartoydetaljer

Hvorfor: Forbedring


2. Scope (Omfang)

Berørte filer (eksakt sti):

 user/fartoydetaljer.php


Tillatte endringer:

 SQL og layout/markup

 Lov å legge til små CSS-klasser nederst i app.css


3. Rammer og referanser

Prosjektfiler (siste versjon, GitHub): fartoydetaljer.php
Bruk alltid nyeste filer i Kringla/ProdSWeb.

SCHEMA: SkipsWeb_SCHEMA v6.sql

4. Detaljert spesifikasjon

Nåværende oppførsel: Navnehistorikk viser Navn og Tidspunkt

Ønsket oppførsel: Navnehistorikk viser Tispunkt, Navn, Rederi (fra tblFartTid), RegHavn (fra tblFartTid) og Nasjon (fra tblzNasjon)


Data/DB: Query som berøres er:
 $navnehist = [];
    $stmt = $conn->prepare(
        "SELECT t.FartTid_ID, t.YearTid, t.MndTid,
                fn.FartNavn,
                zft.TypeFork
         FROM tblfarttid t
         LEFT JOIN tblfartnavn   fn  ON fn.FartNavn_ID   = t.FartNavn_ID
         LEFT JOIN tblfartspes   fs  ON fs.FartSpes_ID   = t.FartSpes_ID
         LEFT JOIN tblzfarttype  zft ON zft.FartType_ID  = fs.FartType_ID
         WHERE t.FartObj_ID = ?
         ORDER BY COALESCE(t.YearTid,0), COALESCE(t.MndTid,0), t.FartTid_ID"

Forøvrig berøres (minst), kanskje flere.

<h3 style="margin:.25rem 0;">Andre lenker</h3>
            <table class="table" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="text-align:left; padding:.35rem .5rem;">Type / innhold</th>
                        <th style="text-align:left; padding:.35rem .5rem; width:1%;">Åpne</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($fartLinks as $lk): ?>
                    <?php
                        $label = ($lk['LinkType'] !== '' ? $lk['LinkType'] : 'Lenke')
                               . ($lk['LinkInnh'] !== '' ? ': ' . $lk['LinkInnh'] : '');
                    ?>
                    <tr data-open="<?= h($lk['Link']) ?>" style="cursor:pointer;">
                        <td style="padding:.35rem .5rem; border-top:1px solid #ddd;"><?= h($label) ?></td>
                        <td style="padding:.35rem .5rem; border-top:1px solid #ddd;">
                            <a class="btn" href="<?= h($lk['Link']) ?>" target="_blank" rel="noopener noreferrer">Åpne</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
			
			
5. Anker i koden (for trygg innfasing)


Innsettingssted / Erstatning: ???


Leveranseformat (velg én):


 Patch (diff) med før/etter

6. Akseptkriterier (Acceptance Criteria)

 Visuelt: endringen er tydelig i riktig blokk/komponent

 Ingen andre sider påvirkes (spesielt index.php/hero‑rotator)

 Responsiv oppførsel (mobil/desktop) er intakt

 Ingen PHP warnings/notices

 Ingen XSS/sti risiko (bruk h() og basename() der det gjelder)

 Eksplisitte “før/etter”-skjermbilder vedlagt (valgfritt men ønskelig)

7. Testplan (hvordan testes dette)

Lokal (XAMPP):

 Laste siden(e) direkte og verifisere layout

 CTRL+F5 hard refresh (cache)

Browsere:

 Chrome

 
Skjermbredder:


 > 1200px

8. Sikkerhet og kvalitet

HTML/PHP:

 Bruk h() for output escaping

 Bruk basename() på filnavn hvis dynamiske bildekilder

CSS:

 Ikke bruk !important med mindre nødvendig

 Scope endringer (f.eks. .card.centered-card)

JS:

 Ikke endre hero-rotator.js

9. Risiko og rollback


Rollback/plan: Hele filen reverteres

Avhengigheter: Ingen