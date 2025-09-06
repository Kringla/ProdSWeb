// assets/js/table-sticky.js
(function () {
  function initShadowHeader(wrapper) {
    // wrapper må være scroll-containeren
    if (!wrapper || !wrapper.classList.contains('table-wrap')) return;

    const table = wrapper.querySelector('table.table');
    if (!table) return;

    // Unngå duplikat
    if (wrapper.querySelector('.table-sticky-header')) return;

    const thead = table.querySelector('thead');
    if (!thead) return;

    // Lag container for skyggeheader
    const sticky = document.createElement('div');
    sticky.className = 'table-sticky-header';

    // Lag header-tabell
    const headerTable = document.createElement('table');
    headerTable.className = 'table';
    const headerThead = document.createElement('thead');

    // Klon første thead-rad (eller alle hvis du har flere rader)
    const rows = Array.from(thead.rows);
    rows.forEach((row) => {
      const cloneRow = document.createElement('tr');
      Array.from(row.cells).forEach((th) => {
        const cloneTh = document.createElement('th');
        cloneTh.textContent = th.textContent;
        // Behold klasser (col--tight, col--low, etc.)
        if (th.className) cloneTh.className = th.className;
        cloneRow.appendChild(cloneTh);
      });
      headerThead.appendChild(cloneRow);
    });

    headerTable.appendChild(headerThead);
    sticky.appendChild(headerTable);
    wrapper.insertBefore(sticky, table);

    // Skjul original thead visuelt
    thead.classList.add('is-visually-hidden');

    // Synk kolonnebredder
    function syncWidths() {
      const dataHeadRow = thead.rows[0];
      const headRow = headerThead.rows[0];
      if (!dataHeadRow || !headRow) return;

      // Mål bredder fra original thead (etter layout)
      const dataCells = Array.from(dataHeadRow.cells);
      const headCells = Array.from(headRow.cells);
      const n = Math.min(dataCells.length, headCells.length);

      // Null still tidligere bredder
      headCells.forEach((c) => (c.style.width = ''));

      // Bruk getBoundingClientRect for presis bredde
      for (let i = 0; i < n; i++) {
        const w = dataCells[i].getBoundingClientRect().width;
        headCells[i].style.width = w + 'px';
      }
    }

    // Synk horisontal scroll
    function syncScroll() {
      headerTable.style.transform = `translateX(${-wrapper.scrollLeft}px)`;
    }

    // Init
    syncWidths();
    syncScroll();

    // Lytt på endringer
    wrapper.addEventListener('scroll', syncScroll, { passive: true });
    window.addEventListener('resize', syncWidths);

    // Hvis innholdet endrer bredde/høyde (f.eks. nytt søkeresultat)
    const ro = new ResizeObserver(syncWidths);
    ro.observe(table);
  }

  function boot() {
    document.querySelectorAll('.table-wrap').forEach(initShadowHeader);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
