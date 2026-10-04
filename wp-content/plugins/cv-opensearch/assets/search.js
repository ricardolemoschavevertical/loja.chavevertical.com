(() => {
  const cfg = window.CVOpenSearch || {};

  const money = (value, currency = 'EUR') => {
    const number = Number(value || 0);
    if (!number) return 'Preço sob consulta';
    try {
      return new Intl.NumberFormat('pt-PT', { style: 'currency', currency }).format(number);
    } catch {
      return number.toFixed(2) + ' €';
    }
  };

  const escapeHtml = (value = '') => String(value).replace(/[&<>"']/g, (ch) => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
  }[ch] || ch));

  const prepareThemeSearch = () => {
    document.querySelectorAll('form.cvl-search').forEach((form) => {
      if (form.hasAttribute('data-cvos-search')) return;

      const input = form.querySelector('input[type="search"][name="s"]');
      if (!input) return;

      form.setAttribute('data-cvos-search', '');
      form.classList.add('cvos-theme-search');
      input.setAttribute('data-cvos-input', '');
      input.setAttribute('aria-autocomplete', 'list');
      input.setAttribute('aria-expanded', 'false');

      let box = form.querySelector('[data-cvos-results]');
      if (!box) {
        box = document.createElement('div');
        box.className = 'cvos-suggestions';
        box.setAttribute('data-cvos-results', '');
        box.hidden = true;
        form.appendChild(box);
      }
    });
  };

  const bind = (root) => {
    if (root.dataset.cvosBound === '1') return;

    const input = root.querySelector('[data-cvos-input]');
    const box = root.querySelector('[data-cvos-results]');
    if (!input || !box || !cfg.endpoint) return;

    root.dataset.cvosBound = '1';
    let timer = 0;
    let controller = null;

    const close = () => {
      box.hidden = true;
      box.innerHTML = '';
      input.setAttribute('aria-expanded', 'false');
    };

    const render = (items, query) => {
      if (!Array.isArray(items) || !items.length) {
        box.innerHTML = '<div class="cvos-empty">Sem resultados</div>';
        box.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        return;
      }

      box.innerHTML = items.map((item) => {
        const image = item.image_url || item.gallery?.[0]?.src || '';
        const title = item.name || '';
        const sku = item.sku || '';
        const url = item.url || item.purchase_url || '#';

        return `
          <a class="cvos-suggestion" href="${escapeHtml(url)}">
            <span class="cvos-thumb">${image ? `<img src="${escapeHtml(image)}" alt="">` : ''}</span>
            <span class="cvos-copy">
              <strong>${escapeHtml(title)}</strong>
              ${sku ? `<small>SKU ${escapeHtml(sku)}</small>` : ''}
            </span>
            <span class="cvos-price">${escapeHtml(money(item.price, item.currency || 'EUR'))}</span>
          </a>`;
      }).join('') + `
        <a class="cvos-see-all" href="${escapeHtml(cfg.searchUrl || '/')}?s=${encodeURIComponent(query)}&post_type=product">
          Ver todos os resultados →
        </a>`;

      box.hidden = false;
      input.setAttribute('aria-expanded', 'true');
    };

    const search = async () => {
      const q = input.value.trim();

      if (q.length < Number(cfg.minChars || 2)) {
        close();
        return;
      }

      if (controller) controller.abort();
      controller = new AbortController();

      try {
        const url = new URL(cfg.endpoint, window.location.origin);
        url.searchParams.set('q', q);
        url.searchParams.set('limit', String(cfg.limit || 10));

        const response = await fetch(url.toString(), {
          signal: controller.signal,
          headers: { Accept: 'application/json' }
        });
        const data = await response.json();

        if (!response.ok) throw new Error(data?.message || 'Erro de pesquisa');
        render(data.items || [], q);
      } catch (error) {
        if (error?.name !== 'AbortError') close();
      }
    };

    input.addEventListener('input', () => {
      window.clearTimeout(timer);
      timer = window.setTimeout(search, 180);
    });

    input.addEventListener('focus', () => {
      if (input.value.trim().length >= Number(cfg.minChars || 2)) search();
    });

    document.addEventListener('click', (event) => {
      if (!root.contains(event.target)) close();
    });

    input.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') close();
    });
  };

  prepareThemeSearch();
  document.querySelectorAll('[data-cvos-search]').forEach(bind);
})();