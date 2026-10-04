(() => {
  const button = document.querySelector('[data-cvl-search-load-more]');
  const grid = document.querySelector('.cvl-search-products ul.products');

  if (!button || !grid) return;

  const wrapper = button.closest('.cvl-search-load-more-wrap');
  const status = wrapper?.querySelector('[data-cvl-search-load-more-status]');
  const defaultLabel = button.textContent.trim();
  const loadingLabel = button.dataset.loadingLabel || 'A CARREGAR…';
  let loading = false;

  const announce = (message) => {
    if (status) status.textContent = message;
  };

  button.addEventListener('click', async (event) => {
    if (loading || !button.href) return;

    event.preventDefault();
    loading = true;
    button.classList.add('is-loading');
    button.setAttribute('aria-disabled', 'true');
    button.textContent = loadingLabel;
    announce('A carregar mais produtos.');

    try {
      const response = await fetch(button.href, {
        credentials: 'same-origin',
        headers: {
          Accept: 'text/html',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const html = await response.text();
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const nextGrid = doc.querySelector('.cvl-search-products ul.products');

      if (!nextGrid) {
        throw new Error('Lista de produtos não encontrada.');
      }

      const products = Array.from(nextGrid.children).filter((node) =>
        node.matches?.('li.product:not(.product-category)')
      );

      products.forEach((product) => {
        grid.appendChild(document.importNode(product, true));
      });

      const nextButton = doc.querySelector('[data-cvl-search-load-more]');

      if (nextButton?.href) {
        button.href = nextButton.href;
        button.textContent = defaultLabel;
        button.classList.remove('is-loading');
        button.removeAttribute('aria-disabled');
        loading = false;
        announce(`${products.length} produtos adicionados. Existem mais resultados.`);
        return;
      }

      announce(`${products.length} produtos adicionados. Todos os resultados foram carregados.`);
      wrapper?.remove();
    } catch (error) {
      button.textContent = defaultLabel;
      button.classList.remove('is-loading');
      button.removeAttribute('aria-disabled');
      loading = false;
      announce('Não foi possível carregar mais produtos. Pode usar novamente o botão.');
    }
  });
})();
