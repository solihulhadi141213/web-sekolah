(function () {
  'use strict';
  const grid = document.getElementById('teachersGrid');
  const navigation = document.getElementById('teachersPagination');
  const summary = document.getElementById('teachersPageSummary');
  if (!grid || !navigation || !summary) return;
  const cards = Array.from(grid.children);
  const pageSize = 12;
  const pageCount = Math.max(1, Math.ceil(cards.length / pageSize));
  const readPage = function () {
    const value = Number(new URL(location.href).searchParams.get('page'));
    return Number.isInteger(value) ? Math.min(pageCount, Math.max(1, value)) : 1;
  };
  let currentPage = readPage();
  const render = function (moveFocus) {
    const start = (currentPage - 1) * pageSize;
    cards.forEach(function (card, index) { card.hidden = index < start || index >= start + pageSize; });
    summary.textContent = 'Menampilkan ' + (cards.length ? start + 1 : 0) + '–' + Math.min(start + pageSize, cards.length) + ' dari ' + cards.length + ' profil';
    document.querySelector('.teachers-total').textContent = cards.length + ' profil';
    navigation.replaceChildren();
    const addButton = function (label, page, disabled, ariaLabel) {
      const button = document.createElement('button');
      button.type = 'button';
      button.textContent = label;
      button.disabled = disabled;
      button.setAttribute('aria-label', ariaLabel);
      button.setAttribute('aria-controls', 'teachersGrid');
      if (page === currentPage && /^\d+$/.test(label)) button.setAttribute('aria-current', 'page');
      button.addEventListener('click', function () {
        currentPage = page;
        const url = new URL(location.href);
        url.searchParams.set('page', String(page));
        history.pushState(null, '', url);
        render(true);
      });
      navigation.appendChild(button);
    };
    addButton('‹', currentPage - 1, currentPage === 1, 'Halaman sebelumnya');
    for (let page = 1; page <= pageCount; page += 1) {
      if (page !== 1 && page !== pageCount && Math.abs(page - currentPage) > 1) {
        if (page === currentPage - 2 || page === currentPage + 2) {
          const dots = document.createElement('span');
          dots.textContent = '…';
          dots.setAttribute('aria-hidden', 'true');
          navigation.appendChild(dots);
        }
        continue;
      }
      addButton(String(page), page, false, 'Halaman ' + page);
    }
    addButton('›', currentPage + 1, currentPage === pageCount, 'Halaman berikutnya');
    navigation.hidden = false;
    if (moveFocus) {
      grid.setAttribute('tabindex', '-1');
      grid.focus({ preventScroll: true });
      grid.scrollIntoView({ block: 'start', behavior: 'instant' });
    }
  };
  window.addEventListener('popstate', function () { currentPage = readPage(); render(true); });
  render(false);
})();
